<?php
/**
 * Wdrożenie sprzedaży botów Discord na produkcję - jedna komenda.
 *
 *   php backend/tools/wdroz-boty-discord.php           # podgląd, nic nie zmienia
 *   php backend/tools/wdroz-boty-discord.php --apply   # wdrożenie
 *
 * Uruchamiać jako deployer (właściciel repo), NIE jako www-data i NIE przez sudo.
 * Skrypt jest idempotentny: drugie uruchomienie po udanym nic już nie zmienia.
 *
 * Kolejność ma znaczenie, bo baza panelu (cennik) jest WSPÓLNA dla bety i produkcji:
 *   1. kolumna orders.variant w bazie produkcji (migracja 049) - bez niej zakup
 *      bota zatrzymuje się przed płatnością,
 *   2. merge gałęzi `boty-discord` w WEB/-dnct.pl (main) - kod musi znać boty,
 *      zanim klient je zobaczy,
 *   3. włączenie pakietów i gry `discord` (ceny z admina) - dopiero teraz boty
 *      pojawiają się w cenniku dla wszystkich.
 *
 * Dlaczego merge gałęzi `boty-discord`, a nie `dev`: `dev` ma ~55 commitów,
 * których nie ma na `main` (czat, partnerzy...), a `main` ~106, których nie ma
 * na `dev`. `git merge dev` wciągnąłby to wszystko na produkcję naraz.
 * `boty-discord` to `main` + wyłącznie commity botów (te same co na `dev`).
 */

declare(strict_types=1);

const PROD_DIR   = '/var/www/dnct.pl/public_html/WEB/-dnct.pl';
const BRANCH     = 'boty-discord';
const GAME_TYPE  = 'discord';

/* Ceny i pakiety NIE są w tym skrypcie - ustawia się je w admin.lan.dnct.pl
   -> Cennik -> Boty Discord (wspólna baza panelu). Skrypt tylko włącza sprzedaż
   tego, co tam jest, więc nie nadpisze żadnej zmiany zrobionej w adminie. */

$apply = in_array('--apply', $argv, true);
$fail  = static function (string $msg): never { fwrite(STDERR, "\nSTOP: $msg\n"); exit(1); };
$say   = static function (string $msg): void { echo $msg, "\n"; };
$git   = static function (string $args, ?int &$code = null): string {
    exec('git -C ' . escapeshellarg(PROD_DIR) . ' ' . $args . ' 2>&1', $out, $code);
    return trim(implode("\n", $out));
};

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $fail('nie uruchamiaj przez sudo/root - pliki dostałyby właściciela root.');
}
if (getenv('DNCT_ENV_FILE')) {
    $fail('ustawione DNCT_ENV_FILE - ten skrypt ma pracować na PRODUKCJI (domyślny .env).');
}

/* Bazy: bootstrap produkcji, czyli domyślny .env z public_html/. Przed
   pierwszym echo - bootstrap startuje sesję i nie może mieć wysłanego wyjścia. */
require PROD_DIR . '/backend/bootstrap.php';

$say($apply ? '=== WDROŻENIE (--apply) ===' : '=== PODGLĄD (nic nie zmieniam; wdrożenie: --apply) ===');

/* ---- 0. Kontrole przed czymkolwiek ---------------------------------------- */
if ($git('rev-parse --abbrev-ref HEAD') !== 'main') {
    $fail(PROD_DIR . ' nie jest na gałęzi main.');
}
$git('rev-parse --verify --quiet refs/heads/' . BRANCH, $code);
if ($code !== 0) {
    $fail('brak gałęzi ' . BRANCH . '.');
}

$merged = false;
$git('merge-base --is-ancestor refs/heads/' . BRANCH . ' HEAD', $code);
$merged = $code === 0;

$changed = $merged ? [] : array_filter(explode("\n", $git('diff --name-only HEAD...refs/heads/' . BRANCH)));
$dirty   = [];
foreach (array_filter(explode("\n", $git('status --porcelain'))) as $line) {
    $dirty[] = trim(substr($line, 3));
}
$overlap = array_values(array_intersect($changed, $dirty));
if ($overlap) {
    $fail("niezacommitowane zmiany na produkcji w plikach, które zmienia merge:\n  - "
        . implode("\n  - ", $overlap) . "\nNajpierw ktoś musi je zacommitować albo odłożyć.");
}

$dnct  = dnct_db();
$panel = panel_db();
$dbName = (string)$dnct->query('SELECT DATABASE()')->fetchColumn();
if ($dbName !== 'dnct') {
    $fail("baza strony to `$dbName`, a nie `dnct` - to nie jest produkcja.");
}

/* ---- 1. Migracja 049: orders.variant --------------------------------------- */
$hasVariant = (bool)$dnct->query("SHOW COLUMNS FROM orders LIKE 'variant'")->fetch();
$say("\n[1/3] Kolumna orders.variant w bazie `dnct`: " . ($hasVariant ? 'jest - pomijam' : 'BRAK - dodam'));
if (!$hasVariant && $apply) {
    $dnct->exec('ALTER TABLE orders ADD COLUMN variant VARCHAR(16) NULL DEFAULT NULL AFTER game_type');
    $say('      dodana.');
}

/* ---- 2. Merge kodu --------------------------------------------------------- */
$say("\n[2/3] Kod: merge " . BRANCH . ' -> main w ' . PROD_DIR);
if ($merged) {
    $say('      już scalone - pomijam.');
} else {
    $say('      commity do wejścia:');
    $say('        ' . str_replace("\n", "\n        ", $git('log --oneline HEAD..refs/heads/' . BRANCH)));
    $say('      pliki: ' . count($changed) . ' (żaden nie koliduje z niezacommitowanymi zmianami produkcji)');
    if ($apply) {
        $out = $git('merge --no-edit refs/heads/' . BRANCH, $code);
        if ($code !== 0) {
            $git('merge --abort');
            $fail("merge się nie udał, cofnięty:\n$out");
        }
        $say('      scalone: ' . $git('log --oneline -1'));
        foreach (array_filter($changed, static fn(string $f): bool => str_ends_with($f, '.php')) as $f) {
            exec('php -l ' . escapeshellarg(PROD_DIR . '/' . $f) . ' 2>&1', $o, $c);
            if ($c !== 0) {
                $fail("błąd składni po merge w $f - cofnij: git -C " . PROD_DIR . ' reset --hard ORIG_HEAD');
            }
        }
        $say('      php -l: OK');
    }
}

/* ---- 3. Cennik w bazie panelu (wspólnej dla bety i produkcji) ------------- */
$say("\n[3/3] Włączenie sprzedaży botów (panel.hosting_plans + hosting_games):");
$plans = $panel->prepare('SELECT id, name, price, ram_mb, cpu_cores, ssd_mb, is_active, sold_out FROM hosting_plans WHERE type = ? ORDER BY price');
$plans->execute([GAME_TYPE]);
$plans = $plans->fetchAll(PDO::FETCH_ASSOC);
if (!$plans) {
    $fail('brak pakietów `discord` w hosting_plans - dodaj je w adminie (Cennik -> Boty Discord).');
}
foreach ($plans as $p) {
    $say(sprintf('      %-13s #%d  %5d MB RAM, %s vCore, %3d GB, %6s zł  %s',
        $p['name'], $p['id'], $p['ram_mb'], $p['cpu_cores'], $p['ssd_mb'] / 1024, $p['price'],
        (int)$p['sold_out'] === 1 ? '(wyprzedany - zostaje wyłączony)' : ((int)$p['is_active'] === 1 ? 'już włączony' : 'is_active 0 -> 1')));
}
$game = $panel->query("SELECT is_active FROM hosting_games WHERE type = 'discord'")->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    $fail('brak wiersza `discord` w hosting_games.');
}
$say('      gra `discord`: ' . ((int)$game['is_active'] === 1 ? 'już włączona' : 'is_active 0 -> 1'));

if ($apply) {
    // Kod musi być już na produkcji - patrz kolejność w nagłówku.
    $git('merge-base --is-ancestor refs/heads/' . BRANCH . ' HEAD', $code);
    if ($code !== 0) {
        $fail('kod nie jest scalony, sprzedaży nie włączam.');
    }
    $panel->beginTransaction();
    try {
        $panel->prepare('UPDATE hosting_plans SET is_active = 1 WHERE type = ? AND sold_out = 0')->execute([GAME_TYPE]);
        $panel->prepare('UPDATE hosting_games SET is_active = 1 WHERE type = ?')->execute([GAME_TYPE]);
        $panel->commit();
    } catch (Throwable $e) {
        $panel->rollBack();
        $fail('zapis się nie udał, nic nie zmieniłem w bazie panelu: ' . $e->getMessage());
    }
    $say('      włączone, boty są w cenniku.');
}

$say($apply
    ? "\nGOTOWE. Sprawdź: https://game.dnct.pl/hosting-bot-discord"
    : "\nTo był podgląd. Wdrożenie: php " . __FILE__ . ' --apply');

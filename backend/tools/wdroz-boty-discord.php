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
 *   3. pakiety w hosting_plans + włączenie gry `discord` - dopiero teraz boty
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

/* Pakiety. CENY ZATWIERDZA USER - zmień tutaj przed `--apply`, jeśli trzeba.
   Dopasowanie po nazwie: istniejący wiersz (type=discord, ta sama nazwa) jest
   aktualizowany, brakujący dopisywany. Pakietu usuniętego z tej listy skrypt
   NIE kasuje, tylko wyłącza (is_active = 0). */
const PLANS = [
    ['name' => 'Bot Start',    'ram_mb' => 512,  'cpu_cores' => 1, 'ssd_mb' => 3072,  'backups_limit' => 1, 'price' => '3.99',  'popular' => 0, 'icon_img' => 'discord_bot_mini.png',     'description' => 'Na próbę: prosty bot z kilkoma komendami.'],
    ['name' => 'Bot Mini',     'ram_mb' => 1024, 'cpu_cores' => 1, 'ssd_mb' => 5120,  'backups_limit' => 1, 'price' => '6.99',  'popular' => 0, 'icon_img' => 'discord_bot_mini.png',     'description' => 'Na start: jeden bot, jedna funkcja.'],
    ['name' => 'Bot Standard', 'ram_mb' => 2048, 'cpu_cores' => 2, 'ssd_mb' => 10240, 'backups_limit' => 2, 'price' => '10.99', 'popular' => 1, 'icon_img' => 'discord_bot_standard.png', 'description' => 'Dla bota z bazą i większym ruchem.'],
    ['name' => 'Bot Pro',      'ram_mb' => 4096, 'cpu_cores' => 2, 'ssd_mb' => 20480, 'backups_limit' => 3, 'price' => '16.99', 'popular' => 0, 'icon_img' => 'discord_bot_pro.png',      'description' => 'Duże serwery, muzyka, kilka procesów.'],
];

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
$say("\n[3/3] Pakiety botów w panel.hosting_plans + włączenie gry `discord`:");
$rows = $panel->prepare('SELECT * FROM hosting_plans WHERE type = ?');
$rows->execute([GAME_TYPE]);
$existing = [];
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $existing[$r['name']] = $r;
}

$cols = ['ram_mb', 'cpu_cores', 'ssd_mb', 'backups_limit', 'price', 'popular', 'icon_img', 'description'];
$ops  = [];
foreach (PLANS as $p) {
    $cur = $existing[$p['name']] ?? null;
    if ($cur === null) {
        $ops[] = ['insert', $p];
        $say(sprintf('      + %-13s %5d MB RAM, %d vCore, %3d GB, %d backup, %s zł  (NOWY)',
            $p['name'], $p['ram_mb'], $p['cpu_cores'], $p['ssd_mb'] / 1024, $p['backups_limit'], $p['price']));
        continue;
    }
    $diff = [];
    foreach ($cols as $c) {
        if ((string)$cur[$c] !== (string)$p[$c] && !($c === 'price' && (float)$cur[$c] === (float)$p[$c])) {
            $diff[] = "$c: {$cur[$c]} -> {$p[$c]}";
        }
    }
    if ((int)$cur['is_active'] !== 1) {
        $diff[] = 'is_active: 0 -> 1';
    }
    $ops[] = ['update', $p, (int)$cur['id']];
    $say(sprintf('      ~ %-13s #%d %s', $p['name'], $cur['id'], $diff ? implode(', ', $diff) : 'bez zmian'));
}
foreach ($existing as $name => $cur) {
    if (!in_array($name, array_column(PLANS, 'name'), true) && (int)$cur['is_active'] === 1) {
        $ops[] = ['off', null, (int)$cur['id']];
        $say(sprintf('      - %-13s #%d spoza listy -> is_active = 0', $name, $cur['id']));
    }
}
$game = $panel->query("SELECT is_active, icon_img FROM hosting_games WHERE type = 'discord'")->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    $fail('brak wiersza `discord` w hosting_games.');
}
$say('      gra `discord`: is_active ' . $game['is_active'] . ' -> 1');

if ($apply) {
    if (!$merged) {
        // Kod musi być już na miejscu - patrz kolejność w nagłówku.
        $git('merge-base --is-ancestor refs/heads/' . BRANCH . ' HEAD', $code);
        if ($code !== 0) {
            $fail('kod nie jest scalony, cennika nie włączam.');
        }
    }
    $panel->beginTransaction();
    try {
        foreach ($ops as $op) {
            [$kind, $p] = $op;
            if ($kind === 'insert') {
                $st = $panel->prepare(
                    'INSERT INTO hosting_plans (name, type, popular, description, ram_mb, cpu_cores, ssd_mb, backups_limit, price, icon, is_active, background_img, icon_img)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "box", 1, "default-bg.png", ?)'
                );
                $st->execute([$p['name'], GAME_TYPE, $p['popular'], $p['description'], $p['ram_mb'], $p['cpu_cores'], $p['ssd_mb'], $p['backups_limit'], $p['price'], $p['icon_img']]);
            } elseif ($kind === 'update') {
                $st = $panel->prepare(
                    'UPDATE hosting_plans SET popular = ?, description = ?, ram_mb = ?, cpu_cores = ?, ssd_mb = ?, backups_limit = ?, price = ?, icon_img = ?, is_active = 1
                     WHERE id = ? AND type = ?'
                );
                $st->execute([$p['popular'], $p['description'], $p['ram_mb'], $p['cpu_cores'], $p['ssd_mb'], $p['backups_limit'], $p['price'], $p['icon_img'], $op[2], GAME_TYPE]);
            } else {
                $panel->prepare('UPDATE hosting_plans SET is_active = 0 WHERE id = ? AND type = ?')->execute([$op[2], GAME_TYPE]);
            }
        }
        $panel->exec("UPDATE hosting_games SET is_active = 1, icon_img = 'media/Server_card/icons/discord_bot_standard.png' WHERE type = 'discord'");
        $panel->commit();
    } catch (Throwable $e) {
        $panel->rollBack();
        $fail('zapis cennika się nie udał, nic nie zmieniłem w bazie panelu: ' . $e->getMessage());
    }
    $say('      zapisane, boty są w cenniku.');
}

$say($apply
    ? "\nGOTOWE. Sprawdź: https://game.dnct.pl/hosting-bot-discord"
    : "\nTo był podgląd. Wdrożenie: php " . __FILE__ . ' --apply');

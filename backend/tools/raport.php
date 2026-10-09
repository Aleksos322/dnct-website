<?php
/**
 * Raport dla admina: dzienny (wczoraj, cron 8:00) i tygodniowy (poprzednie
 * 7 dni, cron w poniedziałek). Skrót idzie na telefon (ntfy, kanał "raporty"),
 * całość na maila.
 *
 *   php backend/tools/raport.php                    podgląd dziennego, nic nie wysyła
 *   php backend/tools/raport.php --tydzien          podgląd tygodniowego
 *   php backend/tools/raport.php --do=adres@x.pl    wysyła mail + telefon
 *
 * Cron chodzi z crontaba konta `deployer`, nie jako www-data: po zajętość
 * dysków pteroq i Proxmoksa idzie przez `ssh pteroq` / `ssh proxmox`,
 * a te klucze ma tylko deployer.
 *
 * Każda sekcja liczy się osobno. Padnięcie jednego źródła (Stripe, Google,
 * ssh) daje w raporcie linijkę z błędem, a nie brak całego raportu - raport,
 * który milknie przy awarii, jest najgorszym możliwym raportem.
 *
 * Bazy: `dnct` chodzi w UTC, `panel` w czasie polskim - stąd dwa formaty
 * granic okresu ($od/$do UTC i $odPl/$doPl).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/gsc.php';
require_once dirname(__DIR__) . '/lib/ntfy.php';
require_once dirname(__DIR__) . '/lib/capacity.php';
require_once dirname(__DIR__) . '/mail/mail.php';

/** Ten sam plik zapisuje crash-watch.php - jedna linia JSON na awarię. */
const RAPORT_KATALOG = __DIR__ . '/../../../../storage/raporty';
const RAPORT_GSC_WLASCIWOSC = 'sc-domain:dnct.pl';
/** Strony klientów zbierane przez licznik odwiedzin - to nie nasz ruch. */
const RAPORT_HOSTY_KLIENTOW = ['zlote-silniczki.dnct.pl'];
/** Magazyny Proxmoksa pokazywane bez alarmu (media Jellyfina, nie DNCT). */
const RAPORT_MAGAZYNY_BEZ_UWAG = ['Movie_2TB'];
const RAPORT_PROG_DYSK = 85;          // % zajętości, od którego jest uwaga
const RAPORT_PROG_RAM_WEZLA_MB = 4096; // mniej wolnego = większy plan się nie zmieści
const RAPORT_PROG_UPTIME = 99.5;
const RAPORT_PROG_CZEKA_H = 12;       // zgłoszenie bez odpowiedzi dłużej niż tyle = uwaga

$opcje = getopt('', ['do:', 'tydzien']);
$adres = isset($opcje['do']) ? (string)$opcje['do'] : '';
$tydzien = isset($opcje['tydzien']);

$utc = new DateTimeZone('UTC');
$pl = new DateTimeZone('Europe/Warsaw');
$dzis = new DateTimeImmutable('today', $pl);
$dni = $tydzien ? 7 : 1;
$start = $dzis->modify('-' . $dni . ' days');
$koniec = $dzis;
$startPoprz = $start->modify('-' . $dni . ' days');

$fmtUtc = static fn(DateTimeImmutable $d): string => $d->setTimezone($utc)->format('Y-m-d H:i:s');
$od = $fmtUtc($start);
$do = $fmtUtc($koniec);
$odPoprz = $fmtUtc($startPoprz);
$odPl = $start->format('Y-m-d H:i:s');
$doPl = $koniec->format('Y-m-d H:i:s');
$dzienOd = $start->format('Y-m-d');
$dzienDo = $koniec->modify('-1 day')->format('Y-m-d');

$okresTxt = $tydzien
    ? $start->format('d.m') . '-' . $koniec->modify('-1 day')->format('d.m.Y')
    : $start->format('d.m.Y');

/** Sprawy do załatwienia - lądują na górze maila i w skrócie na telefon. */
$uwagi = [];
/** Liczby do skrótu na telefon. */
$skrot = [];

// --- pomocnicze -------------------------------------------------------------

function zl(int|float $grosze): string
{
    return number_format($grosze / 100, 2, ',', ' ') . ' zł';
}

function zmiana(int|float $teraz, int|float $przed): string
{
    if ($przed == 0) {
        return $teraz > 0 ? 'nowe' : 'bez zmian';
    }
    $p = (int)round(($teraz - $przed) / $przed * 100);
    return ($p >= 0 ? '+' : '') . $p . '%';
}

/** Komenda na innej maszynie, z twardym limitem czasu. */
function raport_ssh(string $host, string $komenda): string
{
    $wynik = [];
    $kod = 0;
    exec('timeout 20 ssh -o BatchMode=yes -o ConnectTimeout=5 ' . escapeshellarg($host) . ' ' . escapeshellarg($komenda) . ' 2>&1', $wynik, $kod);
    if ($kod !== 0) {
        throw new RuntimeException('ssh ' . $host . ': kod ' . $kod . ' ' . mb_substr(implode(' ', $wynik), 0, 150));
    }
    return implode("\n", $wynik);
}

function raport_stan(): array
{
    $plik = RAPORT_KATALOG . '/stan.json';
    return is_readable($plik) ? (json_decode((string)file_get_contents($plik), true) ?: []) : [];
}

/**
 * Sekcja = lista wierszy [etykieta, wartość]. Wyjątek z jednego źródła
 * zamienia się w wiersz z błędem, reszta raportu idzie dalej.
 *
 * @param callable():list<array{0:string,1:string}> $fn
 */
function sekcja(string $tytul, callable $fn): array
{
    try {
        $wiersze = $fn();
    } catch (Throwable $e) {
        error_log('raport: sekcja ' . $tytul . ' - ' . $e->getMessage());
        $wiersze = [['Błąd odczytu', mb_substr($e->getMessage(), 0, 200)]];
    }
    return ['tytul' => $tytul, 'wiersze' => $wiersze];
}

$dnct = dnct_db();
$panel = panel_db();
$stan = raport_stan();
$noweStan = $stan;

// --- PIENIĄDZE --------------------------------------------------------------

$sekcje = [];

$sekcje[] = sekcja('Pieniądze', function () use ($start, $koniec, $od, $do, $dzienOd, $dzienDo, $dnct, &$uwagi, &$skrot): array {
    $w = [];
    $netto = 0;

    // Stripe: całe konto, więc także sklep Survival Games (ten sam rachunek).
    try {
        stripe_init();
        $okres = ['gte' => $start->getTimestamp(), 'lt' => $koniec->getTimestamp()];
        $wplywy = $zwroty = $spory = $oplaty = 0;
        foreach (\Stripe\BalanceTransaction::all(['created' => $okres, 'limit' => 100])->autoPagingIterator() as $t) {
            if (str_starts_with((string)$t->type, 'payout') || $t->type === 'transfer') {
                continue;   // wypłata na konto bankowe to nie przychód
            }
            match ((string)$t->type) {
                'charge', 'payment' => $wplywy += $t->amount,
                'refund', 'payment_refund' => $zwroty += -$t->amount,
                'adjustment' => $spory += -$t->amount,
                default => null,
            };
            $oplaty += $t->fee;
            $netto += $t->net;
        }

        $udane = $nieudane = 0;
        foreach (\Stripe\Charge::all(['created' => $okres, 'limit' => 100])->autoPagingIterator() as $c) {
            $c->status === 'succeeded' ? $udane++ : ($c->status === 'failed' ? $nieudane++ : null);
        }

        $odnowienia = $odnowieniaKwota = 0;
        foreach (\Stripe\Invoice::all(['created' => $okres, 'status' => 'paid', 'limit' => 100])->autoPagingIterator() as $f) {
            if ($f->billing_reason === 'subscription_cycle') {
                $odnowienia++;
                $odnowieniaKwota += $f->amount_paid;
            }
        }

        $w[] = ['Wpłaty (Stripe)', zl($wplywy) . ' · ' . $udane . ' płatności'];
        if ($odnowienia > 0) {
            $w[] = ['w tym odnowienia abonamentów', $odnowienia . ' · ' . zl($odnowieniaKwota)];
        }
        if ($zwroty > 0) {
            $w[] = ['Zwroty', '-' . zl($zwroty)];
        }
        if ($spory > 0) {
            $w[] = ['Spory (chargeback)', '-' . zl($spory)];
            $uwagi[] = 'Spór o płatność w Stripe - odpowiedz w panelu Stripe, zanim minie termin.';
        }
        if ($oplaty > 0) {
            $w[] = ['Opłaty Stripe', '-' . zl($oplaty)];
        }
        $w[] = ['Netto na koncie Stripe', zl($netto)];
        if ($nieudane > 0) {
            $w[] = ['Nieudane płatności', (string)$nieudane];
        }
    } catch (Throwable $e) {
        $w[] = ['Stripe', 'błąd: ' . mb_substr($e->getMessage(), 0, 150)];
    }

    // Zamówienia z naszej bazy - rozbicie, którego Stripe nie zna.
    $st = $dnct->prepare(
        'SELECT kind, COUNT(*) AS ile, COALESCE(SUM(amount_gross), 0) AS kwota FROM orders
          WHERE status IN ("paid", "provisioned") AND created_at >= ? AND created_at < ? GROUP BY kind'
    );
    $st->execute([$od, $do]);
    $rodzaje = ['new' => 'Nowe serwery', 'extension' => 'Przedłużenia', 'plan_change' => 'Zmiany pakietu'];
    $zamowien = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $w[] = [$rodzaje[$r['kind']] ?? $r['kind'], $r['ile'] . ' · ' . zl((int)$r['kwota'])];
        $zamowien += (int)$r['ile'];
    }
    if ($zamowien === 0) {
        $w[] = ['Zamówienia serwerów', 'brak'];
    }

    $st = $dnct->prepare('SELECT COUNT(*) FROM orders WHERE status = "failed" AND created_at >= ? AND created_at < ?');
    $st->execute([$od, $do]);
    if (($nieudaneZam = (int)$st->fetchColumn()) > 0) {
        $w[] = ['Zamówienia z błędem', (string)$nieudaneZam];
        $uwagi[] = $nieudaneZam . ' zamówień z błędem (status failed) - sprawdź w adminie /zamowienia.';
    }

    $st = $dnct->prepare('SELECT COUNT(*) AS ile, COALESCE(SUM(amount_gross), 0) AS kwota FROM hosting_orders
                           WHERE status IN ("paid", "provisioned") AND created_at >= ? AND created_at < ?');
    $st->execute([$od, $do]);
    $h = $st->fetch(PDO::FETCH_ASSOC);
    if ((int)$h['ile'] > 0) {
        $w[] = ['Hosting WWW', $h['ile'] . ' · ' . zl((int)$h['kwota'])];
    }

    $st = $dnct->prepare('SELECT type, COUNT(*) FROM stripe_events WHERE received_at >= ? AND received_at < ?
                           AND type IN ("customer.subscription.deleted", "charge.dispute.created") GROUP BY type');
    $st->execute([$od, $do]);
    $zdarzenia = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($zdarzenia['customer.subscription.deleted'])) {
        $w[] = ['Zakończone abonamenty', (string)$zdarzenia['customer.subscription.deleted']];
    }

    $st = $dnct->prepare('SELECT COUNT(*) FROM stripe_events WHERE status = "error" AND received_at >= ? AND received_at < ?');
    $st->execute([$od, $do]);
    if (($bledy = (int)$st->fetchColumn()) > 0) {
        $uwagi[] = $bledy . ' zdarzeń Stripe z błędem obsługi - odpal reconcile-stripe.php (podgląd).';
    }

    // Prowizje partnerów naliczone w okresie i wszystkie czekające na wypłatę.
    $st = $dnct->prepare('SELECT COALESCE(SUM(CASE WHEN utworzono >= ? AND utworzono < ? THEN prowizja END), 0),
                                 COALESCE(SUM(prowizja), 0)
                            FROM partner_prowizje WHERE status = "naliczona"');
    $st->execute([$od, $do]);
    [$prowizjeOkres, $prowizjeDoWyplaty] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
    if ($prowizjeOkres > 0 || $prowizjeDoWyplaty > 0) {
        $w[] = ['Prowizje partnerów', zl($prowizjeOkres) . ' (do wypłaty razem ' . zl($prowizjeDoWyplaty) . ')'];
    }

    // Prąd z gniazdka Shelly (ten sam wzór co /rentownosc w adminie).
    $st = $dnct->prepare('SELECT COALESCE(SUM(COALESCE(zuzycie_kwh, GREATEST(0, licznik_kwh - licznik_start_kwh), 0)), 0)
                            FROM energia_dni WHERE dzien BETWEEN ? AND ?');
    $st->execute([$dzienOd, $dzienDo]);
    $kwh = (float)$st->fetchColumn();
    $prad = (int)round($kwh * (float)env('ENERGIA_CENA_KWH', '1.28') * 100);
    $w[] = ['Prąd', number_format($kwh, 1, ',', '') . ' kWh · ' . zl($prad)];

    $wynik = $netto - $prad - $prowizjeOkres;
    $w[] = ['Wynik (netto - prąd - prowizje)', zl($wynik)];

    $skrot['pieniadze'] = 'Netto ' . zl($netto) . ', prąd ' . zl($prad) . ', wynik ' . zl($wynik);
    return $w;
});

// --- KLIENCI ----------------------------------------------------------------

$sekcje[] = sekcja('Klienci', function () use ($od, $do, $tydzien, $dnct, $stan, &$noweStan, &$uwagi, &$skrot): array {
    $w = [];

    $st = $dnct->prepare('SELECT authentik_username FROM user_profiles WHERE created_at >= ? AND created_at < ? ORDER BY id');
    $st->execute([$od, $do]);
    $nowe = $st->fetchAll(PDO::FETCH_COLUMN);
    $w[] = ['Nowe konta', count($nowe) === 0 ? '0' : count($nowe) . ' · ' . implode(', ', array_slice($nowe, 0, 8)) . (count($nowe) > 8 ? '…' : '')];

    $wiszace = $dnct->query('SELECT service, COUNT(*) FROM provision_jobs
                              WHERE status = "failed" OR (status = "pending" AND attempts > 0) GROUP BY service')
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($wiszace) {
        $opis = implode(', ', array_map(static fn($s, $n) => $s . ' ' . $n, array_keys($wiszace), $wiszace));
        $w[] = ['Konta niezałożone w usługach', $opis];
        $uwagi[] = 'Niezałożone konta w usługach (' . $opis . ') - kolejka provision_jobs, etap "konta" w lifecycle.';
    }

    $st = $dnct->prepare('SELECT COUNT(*) FROM support_tickets WHERE created_at >= ? AND created_at < ?');
    $st->execute([$od, $do]);
    $w[] = ['Nowe zgłoszenia w supporcie', (string)(int)$st->fetchColumn()];

    // Czeka = ostatnia wiadomość w otwartym zgłoszeniu jest od klienta.
    $czekaja = $dnct->query(
        'SELECT t.public_id, t.category_label, m.created_at
           FROM support_tickets t
           JOIN support_messages m ON m.id = (
                SELECT id FROM support_messages
                 WHERE ticket_id = t.id AND author_role <> "system" AND withdrawn_at IS NULL
                 ORDER BY id DESC LIMIT 1)
          WHERE t.status IN ("new", "open") AND m.author_role = "customer"
          ORDER BY m.created_at'
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($czekaja) {
        $najdluzej = (int)floor((time() - strtotime($czekaja[0]['created_at'] . ' UTC')) / 3600);
        $w[] = ['Czeka na odpowiedź', count($czekaja) . ' (najdłużej ' . $najdluzej . ' h, #' . $czekaja[0]['public_id'] . ')'];
        if ($najdluzej >= RAPORT_PROG_CZEKA_H) {
            $uwagi[] = count($czekaja) . ' zgłoszeń w supporcie czeka na odpowiedź, najstarsze ' . $najdluzej . ' h.';
        }
    } else {
        $w[] = ['Czeka na odpowiedź', '0'];
    }
    $skrot['klienci'] = count($nowe) . ' nowych kont, ' . count($czekaja) . ' zgłoszeń czeka';

    // Sama ocena i liczba - google_reviews() zwraca null, gdy Google nie odda
    // treści opinii (zdarza się), a tu treść nie jest potrzebna.
    if (env('GOOGLE_PLACES_API_KEY', '') !== '' && env('GOOGLE_PLACE_ID', '') !== '') {
        $ch = curl_init('https://places.googleapis.com/v1/places/' . rawurlencode((string)env('GOOGLE_PLACE_ID')));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['X-Goog-Api-Key: ' . env('GOOGLE_PLACES_API_KEY'), 'X-Goog-FieldMask: rating,userRatingCount'],
        ]);
        $g = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        if (isset($g['userRatingCount'])) {
            $klucz = $tydzien ? 'tydzien' : 'dzien';
            $przed = $stan['google_opinie'][$klucz] ?? null;
            $roznica = $przed === null ? 0 : (int)$g['userRatingCount'] - (int)$przed;
            $w[] = ['Opinie Google', number_format((float)$g['rating'], 1, ',', '') . '★ · ' . $g['userRatingCount'] . ' opinii'
                . ($roznica !== 0 ? ' (' . ($roznica > 0 ? '+' : '') . $roznica . ')' : '')];
            $noweStan['google_opinie'][$klucz] = (int)$g['userRatingCount'];
        } else {
            $w[] = ['Opinie Google', 'błąd odczytu z Google'];
        }
    }
    return $w;
});

// --- SERWERY I MASZYNY ------------------------------------------------------

$sekcje[] = sekcja('Serwery i maszyny', function () use ($start, $koniec, $od, $do, $odPl, $doPl, $dzienOd, $dzienDo, $dnct, $panel, &$uwagi, &$skrot): array {
    $w = [];

    $s = $panel->query('SELECT COUNT(*) AS ile, SUM(status = "suspended") AS zaw, SUM(status = "install_failed") AS zle FROM servers')
        ->fetch(PDO::FETCH_ASSOC);
    $st = $panel->prepare('SELECT COUNT(*) FROM servers WHERE created_at >= ? AND created_at < ?');
    $st->execute([$odPl, $doPl]);
    $w[] = ['Serwery w panelu', $s['ile'] . ' (nowe: ' . (int)$st->fetchColumn() . ', zawieszone: ' . (int)$s['zaw'] . ')'];
    if ((int)$s['zle'] > 0) {
        $uwagi[] = $s['zle'] . ' serwerów z nieudaną instalacją w panelu.';
    }

    // Crashe zapisuje crash-watch.php (co minutę z produkcji).
    $crashe = [];
    $plik = RAPORT_KATALOG . '/awarie.jsonl';
    if (is_readable($plik)) {
        foreach (file($plik, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linia) {
            $a = json_decode($linia, true);
            if (is_array($a) && in_array($a['rodzaj'] ?? '', ['offline', 'restart'], true) && ($a['ts'] ?? 0) >= $start->getTimestamp() && $a['ts'] < $koniec->getTimestamp()) {
                $crashe[$a['serwer'] ?? '?'] = ($crashe[$a['serwer'] ?? '?'] ?? 0) + 1;
            }
        }
    }
    arsort($crashe);
    $ileCrashy = array_sum($crashe);
    $w[] = ['Crashe', $ileCrashy === 0 ? '0' : $ileCrashy . ' · ' . implode(', ', array_map(
        static fn($n, $k) => $n . ($k > 1 ? ' ×' . $k : ''), array_keys(array_slice($crashe, 0, 4, true)), array_slice($crashe, 0, 4, true)))];

    // Ta sama definicja co etap "warn" w lifecycle.php.
    $wygasaja = $dnct->query(
        'SELECT user_name, game_type, expires_at FROM orders
          WHERE status = "provisioned" AND billing_mode = "payment" AND ptero_server_id IS NOT NULL
            AND suspended_at IS NULL AND deleted_at IS NULL
            AND expires_at > NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
          ORDER BY expires_at'
    )->fetchAll(PDO::FETCH_ASSOC);
    $w[] = ['Wygasa w 7 dni (bez abonamentu)', count($wygasaja) === 0 ? '0' : implode(', ', array_map(
        static fn($o) => $o['user_name'] . ' ' . $o['game_type'] . ' ' . (new DateTimeImmutable($o['expires_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m'), $wygasaja))];

    $czekaUsuniecia = (int)$dnct->query('SELECT COUNT(*) FROM orders WHERE suspended_at IS NOT NULL AND deleted_at IS NULL')->fetchColumn();
    if ($czekaUsuniecia > 0) {
        $w[] = ['Zawieszone, czekają na usunięcie', (string)$czekaUsuniecia];
    }
    $skrot['serwery'] = $ileCrashy . ' crashy, ' . count($wygasaja) . ' wygasa w 7 dni';

    foreach ($panel->query('SELECT name FROM nodes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $nazwa) {
        $c = node_capacity($panel, (string)$nazwa);
        if ($c === null) {
            continue;
        }
        $ram = $c['free_memory'] === PHP_INT_MAX ? 'bez limitu' : number_format($c['free_memory'] / 1024, 1, ',', '') . ' GB';
        $dysk = $c['free_disk'] === PHP_INT_MAX ? 'bez limitu' : number_format($c['free_disk'] / 1024, 0, ',', '') . ' GB';
        $w[] = ['Węzeł ' . $nazwa, $c['servers'] . ' serwerów · wolne RAM ' . $ram . ', dysk ' . $dysk];
        if ($nazwa === CAPACITY_DEFAULT_NODE && $c['free_memory'] < RAPORT_PROG_RAM_WEZLA_MB) {
            $uwagi[] = 'Węzeł ' . $nazwa . ' ma tylko ' . $ram . ' wolnego RAM-u do sprzedaży - większe pakiety znikną z cennika.';
        }
    }

    try {
        $df = preg_split('/\s+/', trim(raport_ssh('pteroq', 'df -P / | tail -1')));
        $proc = (int)rtrim($df[4] ?? '0', '%');
        $w[] = ['Dysk pteroq', $proc . '% (wolne ' . round((int)($df[3] ?? 0) / 1048576) . ' GB)'];
        if ($proc >= RAPORT_PROG_DYSK) {
            $uwagi[] = 'Dysk pteroq zajęty w ' . $proc . '% - gdy się zapełni, padną wszystkie serwery gier.';
        }
    } catch (Throwable $e) {
        $w[] = ['Dysk pteroq', 'błąd: ' . $e->getMessage()];
    }

    try {
        // pvesm status: Name Type Status Total Used Available %
        foreach (array_slice(explode("\n", raport_ssh('proxmox', 'sudo -n pvesm status')), 1) as $linia) {
            $p = preg_split('/\s+/', trim($linia));
            if (count($p) < 7 || $p[2] !== 'active') {
                continue;
            }
            $proc = (float)rtrim($p[6], '%');
            $w[] = ['Proxmox ' . $p[0], number_format($proc, 0) . '% (wolne ' . round((int)$p[5] / 1048576) . ' GB)'];
            if ($proc >= RAPORT_PROG_DYSK && !in_array($p[0], RAPORT_MAGAZYNY_BEZ_UWAG, true)) {
                $uwagi[] = 'Magazyn Proxmoksa ' . $p[0] . ' zajęty w ' . number_format($proc, 0) . '%'
                    . ($p[0] === 'Backup_4TB' ? ' - nowe kopie zapasowe się nie zmieszczą.' : '.');
            }
        }
    } catch (Throwable $e) {
        $w[] = ['Magazyny Proxmoksa', 'błąd: ' . $e->getMessage()];
    }

    $st = $dnct->prepare('SELECT AVG(cpu), MAX(cpu), AVG(ram), MAX(temp) FROM proxmox_pomiary WHERE czas >= ? AND czas < ?');
    $st->execute([$start->getTimestamp(), $koniec->getTimestamp()]);
    [$cpuSr, $cpuMax, $ramSr, $tempMax] = $st->fetch(PDO::FETCH_NUM);
    if ($cpuSr !== null) {
        $w[] = ['Proxmox CPU / RAM', 'CPU śr. ' . round((float)$cpuSr) . '% (max ' . round((float)$cpuMax) . '%), RAM śr. ' . round((float)$ramSr) . '%, temp. max ' . round((float)$tempMax) . '°C'];
        if ((float)$tempMax >= 85) {
            $uwagi[] = 'Temperatura Proxmoksa doszła do ' . round((float)$tempMax) . '°C.';
        }
    }

    // Dostępność z status.dnct.pl (próba co minutę).
    $nazwyWezlow = $panel->query('SELECT id, name FROM nodes')->fetchAll(PDO::FETCH_KEY_PAIR);
    $nazwy = ['strona' => 'Strona', 'panel' => 'Panel gier', 'logowanie' => 'Logowanie', 'www' => 'Hosting WWW'];
    $st = $dnct->prepare('SELECT komponent, SUM(udane) / SUM(proby) * 100 FROM status_dni WHERE dzien BETWEEN ? AND ? GROUP BY komponent ORDER BY komponent');
    $st->execute([$dzienOd, $dzienDo]);
    $dostepnosc = [];
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $proc) {
        $nazwa = $nazwy[$k] ?? (str_starts_with($k, 'wezel-') ? 'Węzeł ' . ($nazwyWezlow[(int)substr($k, 6)] ?? substr($k, 6)) : $k);
        $dostepnosc[] = $nazwa . ' ' . rtrim(rtrim(number_format((float)$proc, 2, ',', ''), '0'), ',') . '%';
        if ((float)$proc < RAPORT_PROG_UPTIME) {
            $uwagi[] = $nazwa . ' dostępny tylko ' . number_format((float)$proc, 2, ',', '') . '% czasu.';
        }
    }
    $w[] = ['Dostępność', $dostepnosc ? implode(', ', $dostepnosc) : 'brak pomiarów'];

    $st = $dnct->prepare('SELECT komponent, poczatek, koniec FROM status_incydenty
                           WHERE poczatek < ? AND (koniec IS NULL OR koniec >= ?) ORDER BY poczatek');
    $st->execute([$do, $od]);
    $incydenty = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($incydenty) {
        $w[] = ['Incydenty', implode(', ', array_map(static function ($i) use ($nazwy) {
            $min = $i['koniec'] === null ? null : (int)round((strtotime($i['koniec']) - strtotime($i['poczatek'])) / 60);
            return ($nazwy[$i['komponent']] ?? $i['komponent']) . ' ' . ($min === null ? '(trwa)' : $min . ' min');
        }, $incydenty))];
    }
    return $w;
});

// --- RUCH I SEO -------------------------------------------------------------

$sekcje[] = sekcja('Ruch i SEO', function () use ($od, $do, $odPoprz, $dzis, $tydzien, $dnct, &$skrot): array {
    $w = [];
    $pomin = implode(',', array_fill(0, count(RAPORT_HOSTY_KLIENTOW), '?'));

    $goscie = static function (string $a, string $b) use ($dnct, $pomin): int {
        $st = $dnct->prepare("SELECT COUNT(DISTINCT gosc) FROM odwiedziny WHERE czas >= ? AND czas < ? AND robot = 0 AND host NOT IN ($pomin)");
        $st->execute([$a, $b, ...RAPORT_HOSTY_KLIENTOW]);
        return (int)$st->fetchColumn();
    };
    $teraz = $goscie($od, $do);
    $przed = $goscie($odPoprz, $od);
    $w[] = ['Odwiedzający (wszystkie strony)', $teraz . ' (' . zmiana($teraz, $przed) . ')'];
    $skrot['ruch'] = $teraz . ' odwiedzających (' . zmiana($teraz, $przed) . ')';

    $st = $dnct->prepare("SELECT host, COUNT(DISTINCT gosc) AS g, COUNT(*) AS o FROM odwiedziny
                           WHERE czas >= ? AND czas < ? AND robot = 0 AND host NOT IN ($pomin)
                           GROUP BY host ORDER BY g DESC");
    $st->execute([$od, $do, ...RAPORT_HOSTY_KLIENTOW]);
    $w[] = ['Według strony', implode(', ', array_map(static fn($r) => $r['host'] . ' ' . $r['g'] . ' (' . $r['o'] . ' wyśw.)', $st->fetchAll(PDO::FETCH_ASSOC))) ?: 'brak'];

    $st = $dnct->prepare('SELECT sciezka, COUNT(DISTINCT gosc) AS g FROM odwiedziny
                           WHERE czas >= ? AND czas < ? AND robot = 0 AND host IN ("game.dnct.pl", "dnct.pl")
                           GROUP BY sciezka ORDER BY g DESC LIMIT 5');
    $st->execute([$od, $do]);
    $w[] = ['Najczęstsze strony sklepu', implode(', ', array_map(static fn($r) => $r['sciezka'] . ' ' . $r['g'], $st->fetchAll(PDO::FETCH_ASSOC))) ?: 'brak'];

    $st = $dnct->prepare('SELECT zrodlo, COUNT(DISTINCT gosc) AS g FROM odwiedziny
                           WHERE czas >= ? AND czas < ? AND robot = 0 AND zrodlo <> "" AND zrodlo <> "dnct.pl" AND zrodlo NOT LIKE "%.dnct.pl"
                           GROUP BY zrodlo ORDER BY g DESC LIMIT 5');
    $st->execute([$od, $do]);
    $w[] = ['Skąd przyszli', implode(', ', array_map(static fn($r) => $r['zrodlo'] . ' ' . $r['g'], $st->fetchAll(PDO::FETCH_ASSOC))) ?: 'tylko wejścia bezpośrednie'];

    // Google Search Console ma ~3 dni opóźnienia: dzienny raport pokazuje
    // ostatni pełny dzień, tygodniowy - ostatnie 7 dni z danymi.
    try {
        $gsc = static function (DateTimeImmutable $a, DateTimeImmutable $b, array $wymiary, int $limit): array {
            $res = gsc_request('POST', GSC_SEARCHANALYTICS_BASE . '/sites/' . rawurlencode(RAPORT_GSC_WLASCIWOSC) . '/searchAnalytics/query', [
                'startDate' => $a->format('Y-m-d'), 'endDate' => $b->format('Y-m-d'),
                'dimensions' => $wymiary, 'rowLimit' => $limit, 'dataState' => 'all',
            ]);
            if (($res['http_code'] ?? 0) !== 200) {
                throw new RuntimeException('HTTP ' . ($res['http_code'] ?? 0));
            }
            return $res['body']['rows'] ?? [];
        };
        $suma = static fn(array $r): array => [
            'c' => (int)array_sum(array_column($r, 'clicks')),
            'i' => (int)array_sum(array_column($r, 'impressions')),
        ];
        $kon = $dzis->modify('-3 days');
        $dl = $tydzien ? 7 : 1;
        $t = $suma($gsc($kon->modify('-' . ($dl - 1) . ' days'), $kon, ['date'], 10));
        $p = $suma($gsc($kon->modify('-' . ($dl + 6) . ' days'), $kon->modify('-7 days'), ['date'], 10));
        $opis = $tydzien ? $kon->modify('-6 days')->format('d.m') . '-' . $kon->format('d.m') : $kon->format('d.m') . ', vs tydzień wcześniej';
        $w[] = ['Google (' . $opis . ')', $t['c'] . ' kliknięć (' . zmiana($t['c'], $p['c']) . '), ' . $t['i'] . ' wyświetleń (' . zmiana($t['i'], $p['i']) . ')'];

        if ($tydzien) {
            $frazy = $gsc($kon->modify('-6 days'), $kon, ['query'], 100);
            usort($frazy, static fn($a, $b) => $b['impressions'] <=> $a['impressions']);
            $w[] = ['Top frazy', implode(', ', array_map(static fn($r) => '„' . $r['keys'][0] . '" ' . $r['impressions'] . '/' . $r['clicks'], array_slice($frazy, 0, 5))) ?: 'brak'];
            $blisko = array_filter($frazy, static fn($r) => $r['position'] > 10 && $r['position'] <= 20 && $r['impressions'] >= 5);
            if ($blisko) {
                $w[] = ['Tuż za 1. stroną (warto dopisać treść)', implode(', ', array_map(
                    static fn($r) => '„' . $r['keys'][0] . '" poz. ' . number_format($r['position'], 1, ',', ''), array_slice($blisko, 0, 3)))];
            }
        }
    } catch (Throwable $e) {
        $w[] = ['Google Search Console', 'błąd: ' . $e->getMessage()];
    }
    return $w;
});

// --- WYJŚCIE ----------------------------------------------------------------

$tytul = ($tydzien ? 'Raport tygodnia ' : 'Raport dnia ') . $okresTxt;
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$tekst = $tytul . "\n\n";
$html = '';
if ($uwagi) {
    $tekst .= "WYMAGA UWAGI\n" . implode("\n", array_map(static fn($u) => '- ' . $u, $uwagi)) . "\n\n";
    $html .= '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin:0 0 20px;">'
        . '<div style="font-weight:700;color:#b91c1c;margin-bottom:6px;">Wymaga uwagi</div><ul style="margin:0;padding-left:20px;color:#7f1d1d;">'
        . implode('', array_map(static fn($u) => '<li style="margin:3px 0;">' . $e($u) . '</li>', $uwagi)) . '</ul></div>';
} else {
    $html .= '<p style="margin:0 0 20px;color:#15803d;font-weight:600;">Nic nie wymaga uwagi.</p>';
}

foreach ($sekcje as $s) {
    $tekst .= mb_strtoupper($s['tytul']) . "\n";
    $html .= '<p style="margin:22px 0 6px;font-weight:700;color:#5b21b6;font-size:16px;">' . $e($s['tytul']) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
    foreach ($s['wiersze'] as [$k, $v]) {
        $tekst .= '  ' . $k . ': ' . $v . "\n";
        $html .= '<tr><td style="padding:5px 8px 5px 0;border-bottom:1px solid #f0f0f3;color:#6b7280;vertical-align:top;width:42%;">' . $e($k) . '</td>'
            . '<td style="padding:5px 0;border-bottom:1px solid #f0f0f3;">' . $e($v) . '</td></tr>';
    }
    $html .= '</table>';
    $tekst .= "\n";
}

if ($adres === '') {
    echo $tekst;
    exit(0);
}

$wyslane = send_mail($adres, $tytul . ' - DNCT Hosting', $html, $tekst, 'info');
echo date('Y-m-d H:i:s') . ' raport: ' . $tytul . ' -> ' . $adres . ': ' . ($wyslane['ok'] ? 'wysłano' : 'BŁĄD ' . $wyslane['error']) . "\n";

$telefon = implode("\n", array_filter([
    isset($skrot['pieniadze']) ? '💰 ' . $skrot['pieniadze'] : null,
    isset($skrot['klienci']) ? '👤 ' . $skrot['klienci'] : null,
    isset($skrot['serwery']) ? '🖥 ' . $skrot['serwery'] : null,
    isset($skrot['ruch']) ? '🌐 ' . $skrot['ruch'] : null,
    $uwagi ? '⚠️ Do zrobienia (' . count($uwagi) . '): ' . implode(' | ', array_slice($uwagi, 0, 3)) : '✅ Nic nie wymaga uwagi',
    $wyslane['ok'] ? null : '✉️ Mail się nie wysłał: ' . $wyslane['error'],
]));
ntfy_send('raporty', $tytul, $telefon, $uwagi ? 4 : 2, $uwagi ? 'warning' : 'bar_chart');

if (!is_dir(RAPORT_KATALOG)) {
    @mkdir(RAPORT_KATALOG, 02770, true);
}
@file_put_contents(RAPORT_KATALOG . '/stan.json', json_encode($noweStan), LOCK_EX);
exit($wyslane['ok'] ? 0 : 1);

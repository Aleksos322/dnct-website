<?php
/**
 * Strażnik crashy - co minutę patrzy na wszystkie serwery w panelu i wysyła
 * na telefon (lib/ntfy.php, kanał "awarie"), gdy któryś padł sam z siebie.
 *
 * Wings nie ma webhooków, a jego logi są tylko dla roota na pteroq, więc
 * crash poznajemy po skutkach, przez client API panelu (PTERO_CLIENT_KEY,
 * konto root_admin - widzi wszystkie serwery):
 *
 *   1. był "running", teraz "offline"      -> padł i Wings go nie podniósł
 *                                             (np. "Aborting automatic restart")
 *   2. dalej działa, ale uptime się cofnął  -> padł i Wings go sam zrestartował
 *   3. status w panelu = install_failed     -> instalacja się wysypała
 *
 * W 1 i 2 crashem jest to tylko wtedy, gdy w logu aktywności serwera NIE ma
 * w tym czasie stop/restart/kill z panelu ani komendy "stop"/"restart" z konsoli.
 * "/stop" wpisane w grze przez opa nie zostawia śladu w panelu - takie
 * wyłączenie też dostanie alarm, stąd ostrożna treść powiadomienia.
 *
 * Stan z poprzedniego przebiegu w pliku JSON w katalogu tymczasowym. Brak
 * pliku (restart maszyny) = pierwszy przebieg tylko zapamiętuje stan, bez alarmów.
 *
 * Panel jest jeden dla obu środowisk, więc cron chodzi TYLKO z produkcji.
 *
 *   php backend/tools/crash-watch.php            # przebieg z wysyłką
 *   php backend/tools/crash-watch.php --dry-run  # pokazuje, co by wysłał
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/server_archive.php';   // ptero_client_request()
require_once __DIR__ . '/../lib/ntfy.php';

$dryRun = in_array('--dry-run', $argv, true);
$stateFile = sys_get_temp_dir() . '/dnct-crash-watch-' . posix_geteuid() . '.json';
$prev = is_readable($stateFile) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
$firstRun = $prev === [];
$now = time();
$next = [];
$alerts = [];

$servers = panel_db()->query(
    'SELECT s.id, s.uuid, s.name, s.status, u.email, n.name AS node
     FROM servers s
     JOIN users u ON u.id = s.owner_id
     JOIN nodes n ON n.id = s.node_id'
)->fetchAll(PDO::FETCH_ASSOC);

$apiErrors = 0;

foreach ($servers as $s) {
    $uuid = (string)$s['uuid'];
    $short = substr($uuid, 0, 8);
    $was = $prev[$uuid] ?? null;
    $label = '"' . $s['name'] . '" (' . $s['email'] . ')';
    $link = 'https://panel.dnct.pl/server/' . $short;

    if ($s['status'] === 'install_failed') {
        if ($was !== null && ($was['status'] ?? null) !== 'install_failed') {
            $alerts[] = ['Nieudana instalacja serwera', $label . ' - instalacja w panelu zakończona błędem.', 5, 'package', $link, $s['name'], 'instalacja'];
        }
        $next[$uuid] = ['status' => 'install_failed', 'state' => null, 'uptime' => 0, 'at' => $now];
        continue;
    }

    // Zawieszonych i instalowanych nie pilnujemy - ich "offline" jest zamierzone.
    if ($s['status'] !== null) {
        $next[$uuid] = ['status' => $s['status'], 'state' => null, 'uptime' => 0, 'at' => $now];
        continue;
    }

    $res = ptero_client_request('GET', '/api/client/servers/' . $short . '/resources', null, 10);
    $attr = $res['json']['attributes'] ?? null;
    if ($res['code'] !== 200 || !is_array($attr)) {
        $apiErrors++;
        // Zostawiamy stary stan - chwilowy błąd API nie może wyglądać jak crash.
        if ($was !== null) {
            $next[$uuid] = $was;
        }
        continue;
    }

    $state = (string)$attr['current_state'];
    $uptime = (int)($attr['resources']['uptime'] ?? 0);
    $next[$uuid] = ['status' => null, 'state' => $state, 'uptime' => $uptime, 'at' => $now];

    if ($was === null || ($was['state'] ?? null) !== 'running') {
        continue;
    }

    // Areny Survival Games plugin sam włącza i wyłącza między meczami - ich
    // "offline" to norma, nie crash. Do licznika błędów API wyżej dalej wchodzą.
    if (preg_match('/\bSG\s*\|\s*ARENA\s*\d+/i', (string)$s['name'])) {
        continue;
    }

    $stopped = $state === 'offline';
    $restarted = in_array($state, ['running', 'starting'], true)
        && $uptime + 5000 < (int)$was['uptime'];
    if (!$stopped && !$restarted) {
        continue;
    }

    if (crash_watch_manual_power($short, (int)$was['at'] - 60)) {
        continue;
    }

    $alerts[] = $stopped
        ? ['Serwer padł: ' . $s['name'], $label . ' jest offline, a nikt nie kliknął stop w panelu. Crash albo /stop wpisane w grze.', 5, 'rotating_light', $link, $s['name'], 'offline']
        : ['Serwer się zrestartował: ' . $s['name'], $label . ' sam wstał od nowa (crash z automatycznym restartem).', 4, 'warning', $link, $s['name'], 'restart'];
}

// Węzeł nie odpowiada - jeden zbiorczy alarm zamiast kilkudziesięciu.
// API panelu idzie przez Cloudflare, więc najpierw ustalamy, czy padł węzeł,
// czy łącze (lib/siec.php) - inaczej przerwa u dostawcy wygląda jak awaria Wingsa.
if ($apiErrors >= 5 && ($prev['_api_errors'] ?? 0) < 5) {
    $siec = siec_diagnoza();
    $alerts[] = $siec['wezly'] === true
        ? ['Brak łącza z internetem', $apiErrors . ' serwerów bez odpowiedzi z API panelu. ' . $siec['werdykt'], 4, 'globe_with_meridians', 'https://status.dnct.pl', null, 'lacze']
        : ['Panel/Wings nie odpowiada', $apiErrors . ' serwerów bez odpowiedzi z API panelu. ' . $siec['werdykt'], 5, 'skull', 'https://panel.dnct.pl/admin/nodes', null, 'wezel'];
}
$next['_api_errors'] = $apiErrors;

if ($firstRun) {
    echo date('Y-m-d H:i:s') . " crash-watch: pierwszy przebieg, zapamiętano " . count($servers) . " serwerów\n";
    $alerts = [];
}

// Dziennik dla raportu admina (backend/tools/raport.php liczy z niego crashe).
$awarieLog = dirname(__DIR__, 4) . '/storage/raporty/awarie.jsonl';

foreach ($alerts as [$title, $msg, $prio, $tags, $click, $serwer, $rodzaj]) {
    echo date('Y-m-d H:i:s') . ' crash-watch: ' . $title . ' - ' . $msg . "\n";
    if (!$dryRun) {
        ntfy_send('awarie', $title, $msg, $prio, $tags, $click);
        @file_put_contents($awarieLog, json_encode(['ts' => $now, 'serwer' => $serwer, 'rodzaj' => $rodzaj], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!$dryRun) {
    file_put_contents($stateFile, json_encode($next), LOCK_EX);
}

/** Czy od $since ktoś wyłączył/zrestartował serwer z panelu albo komendą w konsoli. */
function crash_watch_manual_power(string $short, int $since): bool
{
    $res = ptero_client_request('GET', '/api/client/servers/' . $short . '/activity?per_page=25', null, 10);
    if ($res['code'] !== 200) {
        return true;   // nie wiemy - lepiej przemilczeć niż budzić fałszywym alarmem
    }
    foreach ($res['json']['data'] ?? [] as $row) {
        $a = $row['attributes'] ?? [];
        if (strtotime((string)($a['timestamp'] ?? '')) < $since) {
            continue;
        }
        $event = (string)($a['event'] ?? '');
        if (in_array($event, ['server:power.stop', 'server:power.restart', 'server:power.kill', 'server:reinstall'], true)) {
            return true;
        }
        if ($event === 'server:console.command'
            && preg_match('/^\/?(stop|restart|end|shutdown|quit)\b/i', trim((string)($a['properties']['command'] ?? '')))) {
            return true;
        }
    }
    return false;
}

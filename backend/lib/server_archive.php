<?php
/**
 * backend/lib/server_archive.php
 *
 * Paczka z plikami serwera, wysyłana klientowi ZANIM maszyna zostanie
 * skasowana.
 *
 * ---------------------------------------------------------------------------
 * Dlaczego link, a nie załącznik
 * ---------------------------------------------------------------------------
 * Backupy w tym panelu ważą realnie 300-400 MB. Limit załącznika w poczcie to
 * 25-50 MB, więc taka wiadomość nie "czasem nie dojdzie" - ona nigdy nie wyjdzie
 * z serwera. Mail niesie więc link z tokenem, a plik leży u nas przez
 * ARCHIVE_TTL_DAYS dni.
 *
 * ---------------------------------------------------------------------------
 * Dlaczego klucz KLIENCKI, skoro reszta strony używa aplikacyjnego
 * ---------------------------------------------------------------------------
 * Application API Pterodactyla nie ma endpointów backupów - istnieją wyłącznie
 * w client API. Konto z `root_admin` ma tam dostęp do cudzych serwerów
 * (AuthenticateServerAccess przepuszcza administratora), więc jeden klucz
 * konta administratora wystarczy na wszystkie serwery.
 *
 * ---------------------------------------------------------------------------
 * Dwa przebiegi, nie jeden
 * ---------------------------------------------------------------------------
 * Backup 400 MB nie powstaje w sekundę, a cron nie może stać i czekać. Dlatego
 * archiwum ma etapy: jeden przebieg ZAMAWIA backup, kolejny sprawdza, czy jest
 * gotowy, i dopiero wtedy ściąga plik. Stan trzyma tabela `server_archives`.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

/** Ile dni żyje link do paczki. */
function archive_ttl_days(): int
{
    return max(1, (int)env('ARCHIVE_TTL_DAYS', '7'));
}

/** Katalog na paczki. Musi być POZA DocumentRootem. */
function archive_dir(): string
{
    return rtrim((string)env('ARCHIVE_DIR', '/var/www/dnct.pl/archives'), '/');
}

/** Pełna ścieżka pliku archiwum albo null, gdy jeszcze go nie ma. */
function archive_path(array $archive): ?string
{
    $name = (string)($archive['filename'] ?? '');
    if ($name === '') {
        return null;
    }

    // basename() celowo: nazwa pochodzi z bazy, ale nigdy nie ma prowadzić
    // poza katalog archiwów, nawet gdyby ktoś wpisał tam "../".
    return archive_dir() . '/' . basename($name);
}

/** Adres, pod którym klient pobierze paczkę. */
function archive_download_url(array $archive): string
{
    return rtrim((string)env('BASE_URL', 'https://dnct.pl'), '/')
        . '/pobierz?token=' . rawurlencode((string)$archive['token']);
}

// ---------------------------------------------------------------------------
// Rozmowa z client API
// ---------------------------------------------------------------------------

/**
 * @return array{code:int,json:?array,error:?string}
 */
function ptero_client_request(string $method, string $path, ?array $body = null): array
{
    $key = trim((string)env('PTERO_CLIENT_KEY', ''));
    if ($key === '') {
        return ['code' => 0, 'json' => null, 'error' => 'PTERO_CLIENT_KEY nie jest ustawiony'];
    }

    $ch = curl_init(rtrim((string)env('PTERO_API_URL', 'https://panel.dnct.pl'), '/') . $path);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw   = curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = $raw === false ? curl_error($ch) : null;
    curl_close($ch);

    return [
        'code'  => $code,
        'json'  => is_string($raw) ? json_decode($raw, true) : null,
        'error' => $error,
    ];
}

/** Krótki identyfikator serwera - client API posługuje się nim, nie liczbowym id. */
function archive_server_short(int $serverId): ?string
{
    $stmt = panel_db()->prepare('SELECT uuidShort FROM servers WHERE id = ?');
    $stmt->execute([$serverId]);
    $short = $stmt->fetchColumn();

    return $short === false ? null : (string)$short;
}

// ---------------------------------------------------------------------------
// Etap 1: zamówienie backupu
// ---------------------------------------------------------------------------

/**
 * Zamawia backup i zakłada wiersz w `server_archives`.
 *
 * @return array{ok:bool,error:?string,archive_id:?int}
 */
function archive_start(PDO $dnct, array $order): array
{
    $serverId = (int)($order['ptero_server_id'] ?? 0);
    if ($serverId <= 0) {
        return ['ok' => false, 'error' => 'Zamówienie nie ma serwera.', 'archive_id' => null];
    }

    $short = archive_server_short($serverId);
    if ($short === null) {
        return ['ok' => false, 'error' => 'Serwera nie ma już w panelu.', 'archive_id' => null];
    }

    /* Limit backupów bywa wyczerpany - klient mógł zająć wszystkie sloty.
       Kasujemy wtedy NAJSTARSZY własny backup, żeby zrobić miejsce: i tak za
       chwilę zniknie razem z serwerem, a bez tego archiwum by nie powstało
       i etap kasowania stanąłby na zawsze. */
    archive_free_slot_if_needed($short, $serverId);

    $res = ptero_client_request('POST', '/api/client/servers/' . $short . '/backups', [
        'name'      => 'DNCT - archiwum przed usunieciem ' . date('Y-m-d H:i'),
        'is_locked' => false,
    ]);

    if ($res['code'] !== 200 || empty($res['json']['attributes']['uuid'])) {
        $why = $res['error'] ?? ('HTTP ' . $res['code']);
        error_log('Archiwum: nie udalo sie zamowic backupu serwera ' . $serverId . ': ' . $why);
        return ['ok' => false, 'error' => $why, 'archive_id' => null];
    }

    $stmt = $dnct->prepare(
        'INSERT INTO server_archives (order_id, ptero_server_id, backup_uuid, token, expires_at, status)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), "requested")'
    );
    $stmt->execute([
        (int)$order['id'],
        $serverId,
        (string)$res['json']['attributes']['uuid'],
        bin2hex(random_bytes(32)),
        archive_ttl_days(),
    ]);

    return ['ok' => true, 'error' => null, 'archive_id' => (int)$dnct->lastInsertId()];
}

/** Zwalnia slot na backup, jeśli serwer wyczerpał limit. */
function archive_free_slot_if_needed(string $short, int $serverId): void
{
    $stmt = panel_db()->prepare('SELECT backup_limit FROM servers WHERE id = ?');
    $stmt->execute([$serverId]);
    $limit = (int)$stmt->fetchColumn();

    if ($limit <= 0) {
        return; // Limit 0 i tak nie pozwoli utworzyć backupu - błąd zgłosi API.
    }

    $list = ptero_client_request('GET', '/api/client/servers/' . $short . '/backups');
    if ($list['code'] !== 200) {
        return;
    }

    $backups = $list['json']['data'] ?? [];
    if (count($backups) < $limit) {
        return;
    }

    // Najstarszy niezablokowany. Zablokowanych nie ruszamy - klient oznaczył
    // je świadomie i to jedyna rzecz, jaką mógł nam w tej sprawie powiedzieć.
    $oldest = null;
    foreach ($backups as $b) {
        $a = $b['attributes'] ?? [];
        if (!empty($a['is_locked'])) {
            continue;
        }
        if ($oldest === null || strtotime((string)$a['created_at']) < strtotime((string)$oldest['created_at'])) {
            $oldest = $a;
        }
    }

    if ($oldest !== null) {
        ptero_client_request('DELETE', '/api/client/servers/' . $short . '/backups/' . $oldest['uuid']);
        error_log('Archiwum: zwolniono slot backupu serwera ' . $serverId . ' (skasowano ' . $oldest['uuid'] . ')');
    }
}

// ---------------------------------------------------------------------------
// Etap 2: sprawdzenie gotowości i pobranie pliku
// ---------------------------------------------------------------------------

/**
 * Sprawdza backup i - gdy gotowy - ściąga go na nasz dysk.
 *
 * @return array{status:string,error:?string}  status: requested|ready|failed
 */
function archive_advance(PDO $dnct, array $archive): array
{
    $short = archive_server_short((int)$archive['ptero_server_id']);
    if ($short === null) {
        return archive_fail($dnct, $archive, 'Serwera nie ma już w panelu.');
    }

    $res = ptero_client_request(
        'GET',
        '/api/client/servers/' . $short . '/backups/' . (string)$archive['backup_uuid']
    );

    if ($res['code'] !== 200) {
        // Panel może chwilowo nie odpowiadać - to nie powód, żeby uznać
        // archiwum za przepadłe. Zostaje `requested`, spróbujemy za godzinę.
        return ['status' => 'requested', 'error' => 'HTTP ' . $res['code']];
    }

    $a = $res['json']['attributes'] ?? [];

    if (empty($a['completed_at'])) {
        return ['status' => 'requested', 'error' => null]; // jeszcze się pakuje
    }

    if (empty($a['is_successful'])) {
        return archive_fail($dnct, $archive, 'Panel zgłosił nieudany backup.');
    }

    // Adres do pobrania jest podpisany i krótko żyjący - bierzemy go dopiero
    // teraz, tuż przed samym pobieraniem.
    $dl = ptero_client_request(
        'GET',
        '/api/client/servers/' . $short . '/backups/' . (string)$archive['backup_uuid'] . '/download'
    );

    $url = $dl['json']['attributes']['url'] ?? null;
    if ($dl['code'] !== 200 || !$url) {
        return ['status' => 'requested', 'error' => 'Brak adresu pobierania (HTTP ' . $dl['code'] . ')'];
    }

    $filename = sprintf('dnct-%d-%s.tar.gz', (int)$archive['order_id'], substr((string)$archive['token'], 0, 16));
    $target   = archive_dir() . '/' . $filename;

    $fh = @fopen($target, 'wb');
    if ($fh === false) {
        return archive_fail($dnct, $archive, 'Nie mogę pisać do ' . archive_dir());
    }

    $ch = curl_init((string)$url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 1800, // 30 min - paczki bywają duże
    ]);
    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    $bytes = is_file($target) ? (int)filesize($target) : 0;

    /* Pusty albo urwany plik jest gorszy niż jego brak: etap kasowania uzna
       archiwum za gotowe i skasuje serwer, a klient dostanie link do niczego. */
    if ($ok === false || $code >= 400 || $bytes <= 0) {
        @unlink($target);
        return archive_fail($dnct, $archive, 'Pobieranie nieudane (HTTP ' . $code . ' ' . $err . ')');
    }

    $dnct->prepare(
        'UPDATE server_archives SET status = "ready", filename = ?, bytes = ?, error = NULL WHERE id = ?'
    )->execute([$filename, $bytes, (int)$archive['id']]);

    return ['status' => 'ready', 'error' => null];
}

/** @return array{status:string,error:string} */
function archive_fail(PDO $dnct, array $archive, string $why): array
{
    $dnct->prepare('UPDATE server_archives SET status = "failed", error = ? WHERE id = ?')
         ->execute([mb_substr($why, 0, 500), (int)$archive['id']]);

    error_log('Archiwum #' . (int)$archive['id'] . ' (zamówienie #' . (int)$archive['order_id'] . '): ' . $why);

    return ['status' => 'failed', 'error' => $why];
}

// ---------------------------------------------------------------------------
// Odczyt i sprzątanie
// ---------------------------------------------------------------------------

/** Gotowe archiwum dla zamówienia albo null. */
function archive_ready_for_order(PDO $dnct, int $orderId): ?array
{
    $stmt = $dnct->prepare(
        'SELECT * FROM server_archives WHERE order_id = ? AND status = "ready" ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/** Najnowsze archiwum dla zamówienia, niezależnie od stanu. */
function archive_latest_for_order(PDO $dnct, int $orderId): ?array
{
    $stmt = $dnct->prepare('SELECT * FROM server_archives WHERE order_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/** Archiwum po tokenie - do endpointu pobierania. */
function archive_by_token(PDO $dnct, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $dnct->prepare('SELECT * FROM server_archives WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/**
 * Kasuje pliki po terminie. Wiersz zostaje ze statusem `purged` - historia
 * "była paczka, wygasła" jest przy reklamacji warta więcej niż czysta tabela.
 */
function archive_purge_expired(PDO $dnct): int
{
    $rows = $dnct->query(
        'SELECT * FROM server_archives WHERE status = "ready" AND expires_at < NOW()'
    )->fetchAll(PDO::FETCH_ASSOC);

    $n = 0;
    foreach ($rows as $a) {
        $path = archive_path($a);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
        $dnct->prepare('UPDATE server_archives SET status = "purged" WHERE id = ?')
             ->execute([(int)$a['id']]);
        $n++;
    }

    return $n;
}

/** Ludzki rozmiar paczki - do maila. */
function archive_human_size(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1, ',', ' ') . ' GB';
    }

    /* Poniżej megabajta podajemy kilobajty. Zaokrąglanie wszystkiego w górę do
       "1 MB" brzmiałoby jak zaokrąglenie, a nie jak rozmiar - a klient patrzy
       na tę liczbę, żeby ocenić, czy paczka w ogóle zawiera jego świat. */
    if ($bytes < 1048576) {
        return number_format(max(1, (int)round($bytes / 1024)), 0, ',', ' ') . ' KB';
    }

    return number_format((int)round($bytes / 1048576), 0, ',', ' ') . ' MB';
}

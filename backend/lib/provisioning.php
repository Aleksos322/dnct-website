<?php
/**
 * Zakładanie serwera gry w Pterodactylu po opłaconym zamówieniu.
 *
 * WOŁANE WYŁĄCZNIE Z WEBHOOKA. Nigdy ze strony powrotu — patrz nagłówek
 * backend/lib/orders.php.
 *
 * JEDNA ŚCIEŻKA ZAMIAST TRZECH KOPII. Stara wersja miała w success.php trzy
 * niemal identyczne bloki (Minecraft, Project Zomboid, Vintage Story) po
 * ok. 120 linii każdy, rozróżniane przez `if ($planId == 0)` i `== 27` —
 * czyli przez magiczne numery planów z cennika. Dodanie czwartej gry oznaczało
 * czwartą kopię, a poprawka błędu — trzy poprawki albo dwie i jeden
 * przeoczony. Tutaj różnice między grami są DANYMI (provision_game_map),
 * a kod tworzący serwer jest jeden.
 */

declare(strict_types=1);

require_once __DIR__ . '/pterodactyl.php';

/** Węzeł, na którym lądują serwery klientów (ten sam, co liczy capacity.php). */
const PROVISION_NODE_ID = 4;

/** Ile dni serwer działa po nieudanej płatności cyklicznej. */
const PROVISION_GRACE_DAYS = 3;

/**
 * Różnice między grami — wszystko, czym Minecraft różni się od Zomboida.
 *
 * `env` to funkcja, bo część zmiennych zależy od zamówienia (liczba slotów)
 * albo od przydzielonego portu, którego przed alokacją jeszcze nie znamy.
 *
 * @return array<string,array{nest:int,egg:int,fallback_image:string,env:callable}>
 */
function provision_game_map(): array
{
    return [
        'minecraft' => [
            'nest'           => 1,
            'egg'            => 4,   // Paper
            'fallback_image' => 'ghcr.io/pterodactyl/yolks:java_21',
            'env' => static fn(array $order, array $alloc): array => [
                'SERVER_JARFILE' => 'server.jar',
                'MINECRAFT_VERSION' => 'latest',
                'BUILD_NUMBER'   => 'latest',
            ],
        ],

        'zomboid' => [
            'nest'           => 7,
            'egg'            => 26,  // Project Zomboid B42
            'fallback_image' => 'ghcr.io/pterodactyl/yolks:debian',
            'env' => static fn(array $order, array $alloc): array => [
                'SERVER_NAME'   => 'ProjectZomboid',
                'ADMIN_USER'    => (string)$order['user_name'],
                // Hasło administratora gry losujemy per serwer. Stara wersja
                // wpisywała tu na sztywno 'password123' KAŻDEMU klientowi.
                'ADMIN_PASSWORD' => pterodactyl_generate_password(14),
                'MAX_PLAYERS'   => (string)($order['slots'] ?? 10),
                'SRCDS_APPID'   => '380870',
                'AUTO_UPDATE'   => '1',
                'SRCDS_BETAID'  => 'unstable',
                // Port, a nie identyfikator alokacji. Stara wersja wstawiała
                // tu `$freeAllocationId`, czyli numer wiersza w bazie — serwer
                // dostawał zupełnie inny port, niż faktycznie nasłuchiwał.
                'STEAM_PORT'    => (string)$alloc['port'],
            ],
        ],

        'vintagestory' => [
            'nest'           => 9,
            'egg'            => 27,  // Vintage Story
            'fallback_image' => 'ghcr.io/pterodactyl/yolks:dotnet_8',
            'env' => static fn(array $order, array $alloc): array => [
                'VS_VERSION'  => 'latest',
                'MAX_PLAYERS' => (string)($order['slots'] ?? 10),
                'SERVER_PORT' => (string)$alloc['port'],
            ],
        ],
    ];
}

/**
 * Wolna alokacja (para adres:port) na węźle klienckim.
 *
 * Pterodactyl stronicuje wyniki po 50, a węzeł ma ich blisko tysiąc — bez
 * `filter[assigned]` pierwsza strona potrafi być w całości zajęta i kod
 * uznałby, że portów nie ma, mając ich setki.
 *
 * @return array{id:int,port:int}|null
 */
function provision_free_allocation(int $nodeId = PROVISION_NODE_ID): ?array
{
    $res = pterodactyl_api_request('GET', "/api/application/nodes/{$nodeId}/allocations?per_page=100");

    if ($res['http_code'] !== 200 || !isset($res['body']['data'])) {
        error_log('Provisioning: nie udało się pobrać alokacji węzła ' . $nodeId
            . ' (HTTP ' . $res['http_code'] . ')');
        return null;
    }

    foreach ($res['body']['data'] as $row) {
        $attr = $row['attributes'] ?? [];
        if (($attr['assigned'] ?? true) === false) {
            return ['id' => (int)$attr['id'], 'port' => (int)$attr['port']];
        }
    }

    return null;
}

/**
 * Komenda startowa i obraz dockera z definicji jajka.
 *
 * Czytamy je z Pterodactyla zamiast wpisywać na sztywno: zmiana jajka w panelu
 * (np. nowa wersja Javy) ma od razu obowiązywać nowe serwery. Wartości zapasowe
 * są tylko na wypadek niedostępności API — serwer z zapasową komendą wciąż
 * powstanie, a to lepsze niż nieudany zakup po pobraniu pieniędzy.
 *
 * @return array{startup:string,image:string}
 */
function provision_egg_defaults(int $nestId, int $eggId, string $fallbackImage): array
{
    $res = pterodactyl_api_request('GET', "/api/application/nests/{$nestId}/eggs/{$eggId}");

    if ($res['http_code'] === 200 && isset($res['body']['attributes'])) {
        $attr = $res['body']['attributes'];
        return [
            'startup' => (string)($attr['startup'] ?? ''),
            'image'   => (string)($attr['docker_image'] ?? $fallbackImage),
        ];
    }

    error_log("Provisioning: nie udało się pobrać jajka {$eggId} — używam wartości zapasowych");
    return ['startup' => '', 'image' => $fallbackImage];
}

/**
 * Zakłada serwer dla opłaconego zamówienia.
 *
 * @param array<string,mixed> $order Wiersz z tabeli orders
 * @return array{ok:bool,server_id?:int,uuid?:string,ptero_user_id?:int,error?:string}
 */
function provision_server(array $order): array
{
    $gameType = (string)$order['game_type'];
    $map      = provision_game_map();

    if (!isset($map[$gameType])) {
        return ['ok' => false, 'error' => 'Brak konfiguracji provisioningu dla gry: ' . $gameType];
    }
    $game = $map[$gameType];

    /* Konto w panelu. Ta sama funkcja, której używa logowanie SSO, więc klient,
       który kiedykolwiek się zalogował, dostanie SWOJE istniejące konto,
       a nie duplikat. Dopasowanie idzie po adresie e-mail. */
    $pteroUser = pterodactyl_ensure_user(
        (string)$order['user_email'],
        (string)$order['user_name']
    );

    if (!$pteroUser || empty($pteroUser['id'])) {
        return ['ok' => false, 'error' => 'Nie udało się ustalić konta w panelu dla ' . $order['user_email']];
    }

    $alloc = provision_free_allocation();
    if ($alloc === null) {
        return ['ok' => false, 'error' => 'Brak wolnych portów na węźle ' . PROVISION_NODE_ID];
    }

    $egg = provision_egg_defaults($game['nest'], $game['egg'], $game['fallback_image']);

    $label = $order['slots'] !== null
        ? $order['slots'] . ' slotów'
        : 'plan #' . (int)$order['plan_id'];

    $payload = [
        'name'        => ucfirst($gameType) . ' — ' . $order['user_name'],
        'description' => sprintf(
            'Zamówienie #%d · %s · %d dni',
            (int)$order['id'],
            $label,
            (int)$order['period_days']
        ),
        'user'         => (int)$pteroUser['id'],
        'nest'         => $game['nest'],
        'egg'          => $game['egg'],
        'docker_image' => $egg['image'],
        'environment'  => ($game['env'])($order, $alloc),
        'limits' => [
            'memory' => (int)$order['ram_mb'],
            'swap'   => 0,
            'disk'   => (int)$order['ssd_mb'],
            'io'     => 500,
            // Pterodactyl liczy CPU w procentach rdzenia: 2.5 rdzenia = 250.
            'cpu'    => (int)round((float)$order['cpu_cores'] * 100),
        ],
        'feature_limits' => [
            'databases'   => 1,
            'backups'     => (int)$order['backups_limit'],
            'allocations' => 1,
        ],
        'allocation' => ['default' => $alloc['id']],
    ];

    // Pustą komendę pomijamy — Pterodactyl podstawi wtedy własną z jajka.
    // Wysłanie pustego napisu daje serwer, który nie ma czym wystartować.
    if ($egg['startup'] !== '') {
        $payload['startup'] = $egg['startup'];
    }

    $res = pterodactyl_api_request('POST', '/api/application/servers', $payload);

    if ($res['http_code'] !== 201 || !isset($res['body']['attributes'])) {
        $details = provision_error_details($res);
        error_log('Provisioning: serwer nieutworzony dla zamówienia #' . $order['id'] . ' — ' . $details);
        return ['ok' => false, 'error' => $details];
    }

    $attr = $res['body']['attributes'];

    return [
        'ok'            => true,
        'server_id'     => (int)$attr['id'],
        'uuid'          => (string)($attr['uuid'] ?? ''),
        'ptero_user_id' => (int)$pteroUser['id'],
    ];
}

/**
 * Czytelny powód odmowy z odpowiedzi API.
 *
 * Pterodactyl zwraca błędy walidacji w `errors[].detail` i to jedyne miejsce,
 * w którym widać, CO było nie tak (np. przekroczony limit pamięci węzła).
 * Samo "HTTP 422" nie pozwala niczego naprawić.
 */
function provision_error_details(array $res): string
{
    $code = (int)($res['http_code'] ?? 0);

    if (isset($res['body']['errors']) && is_array($res['body']['errors'])) {
        $parts = [];
        foreach ($res['body']['errors'] as $err) {
            $parts[] = (string)($err['detail'] ?? $err['code'] ?? 'nieznany błąd');
        }
        return 'HTTP ' . $code . ': ' . implode(' | ', $parts);
    }

    if (!empty($res['error'])) {
        return 'połączenie: ' . $res['error'];
    }

    return 'HTTP ' . $code;
}

/**
 * Zawiesza serwer (po wygaśnięciu albo po zakończonej karencji).
 *
 * Zawieszenie, nie skasowanie: dane zostają, klient może wrócić i dopłacić.
 * Kasowanie serwera z webhooka byłoby nieodwracalne i wykonane bez niczyjej
 * decyzji.
 */
function provision_suspend(int $serverId): bool
{
    $res = pterodactyl_api_request('POST', "/api/application/servers/{$serverId}/suspend");
    return in_array((int)$res['http_code'], [204, 200], true);
}

/** Zdejmuje zawieszenie po opłaceniu zaległości. */
function provision_unsuspend(int $serverId): bool
{
    $res = pterodactyl_api_request('POST', "/api/application/servers/{$serverId}/unsuspend");
    return in_array((int)$res['http_code'], [204, 200], true);
}

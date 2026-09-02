<?php
/**
 * Dostępność planów liczona z realnej pojemności węzła Pterodactyla.
 *
 * Zamiast ręcznego przestawiania flagi `sold_out` w bazie, plan robi się
 * niedostępny sam, gdy na węźle przestaje się mieścić. Ręczna flaga zostaje
 * jako TWARDE wyłączenie - jeśli ustawisz `sold_out = 1`, plan jest niedostępny
 * niezależnie od tego, ile miejsca zostało.
 *
 * Dwie rzeczy, które łatwo tu przeoczyć, a bez nich wynik jest bez sensu:
 *
 * 1. NADSUBSKRYPCJA. Pterodactyl pozwala przydzielić więcej, niż węzeł ma
 *    fizycznie - kolumny `memory_overallocate` i `disk_overallocate` (w procentach).
 *    User node ma 40 000 MiB RAM i 200% nadsubskrypcji, czyli realny limit to
 *    120 000 MiB. Bez uwzględnienia tego węzeł wyglądałby na przepełniony przy
 *    43 968 MiB przydzielonego RAM-u i CAŁY cennik pokazałby się jako wyprzedany.
 *    Wartość -1 oznacza w Pterodactylu brak limitu.
 *
 * 2. LICZY SIĘ PRZYDZIAŁ, NIE ZUŻYCIE. Serwer wyłączony albo zawieszony nadal
 *    zajmuje miejsce na węźle, bo jego pliki leżą na dysku, a RAM jest dla niego
 *    zarezerwowany. Dlatego sumujemy `servers.memory` i `servers.disk`, a nie
 *    bieżące zużycie z /resources.
 */

declare(strict_types=1);

/**
 * Ile wolnych miejsc danego planu musi zostać, żeby dało się go jeszcze kupić.
 *
 * Przy wartości 2 plan znika z oferty, gdy zmieści się już tylko jeden - zostaje
 * zapas na jedno wdrożenie awaryjne albo na klienta, który właśnie klika zakup.
 * Sprzedawanie ostatniego wolnego miejsca kończy się wyścigiem dwóch zamówień
 * o ten sam kawałek węzła.
 */
const CAPACITY_MIN_FREE_SLOTS = 2;

/** Domyślny węzeł, na którym lądują serwery klientów. */
const CAPACITY_DEFAULT_NODE = 'User node';

/**
 * Wolne zasoby węzła, z uwzględnieniem nadsubskrypcji.
 *
 * @return array{name:string,free_memory:int,free_disk:int,servers:int}|null
 *         null, gdy węzła nie ma w bazie - wtedy wywołujący nie powinien
 *         niczego wyłączać, bo brak danych to nie to samo co brak miejsca.
 */
function node_capacity(PDO $pdo, ?string $nodeName = null): ?array
{
    $nodeName = $nodeName ?? CAPACITY_DEFAULT_NODE;

    $stmt = $pdo->prepare(
        'SELECT id, name, memory, memory_overallocate, disk, disk_overallocate
         FROM nodes WHERE name = ? LIMIT 1'
    );
    $stmt->execute([$nodeName]);
    $node = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$node) {
        return null;
    }

    $used = $pdo->prepare(
        'SELECT COUNT(*) AS ile, COALESCE(SUM(memory), 0) AS ram, COALESCE(SUM(disk), 0) AS hdd
         FROM servers WHERE node_id = ?'
    );
    $used->execute([$node['id']]);
    $u = $used->fetch(PDO::FETCH_ASSOC);

    /** Limit po doliczeniu nadsubskrypcji. -1 = bez limitu. */
    $effective = static function (int $base, int $overallocate): float {
        if ($overallocate < 0) {
            return INF;
        }
        return $base * (1 + $overallocate / 100);
    };

    $memLimit  = $effective((int)$node['memory'], (int)$node['memory_overallocate']);
    $diskLimit = $effective((int)$node['disk'], (int)$node['disk_overallocate']);

    return [
        'name'        => (string)$node['name'],
        'servers'     => (int)$u['ile'],
        // max(0, ...) bo węzeł potrafi być już przekroczony ręcznym wpisem admina
        'free_memory' => $memLimit === INF ? PHP_INT_MAX : (int)max(0, $memLimit - (int)$u['ram']),
        'free_disk'   => $diskLimit === INF ? PHP_INT_MAX : (int)max(0, $diskLimit - (int)$u['hdd']),
    ];
}

/**
 * Ile serwerów danego planu jeszcze się zmieści.
 *
 * Decyduje zasób, który skończy się pierwszy - plan z dużym dyskiem potrafi się
 * nie mieścić, mimo że RAM-u jest pod dostatkiem.
 */
function plan_fits(array $plan, array $capacity): int
{
    $ram  = (int)($plan['ram_mb'] ?? 0);
    $disk = (int)($plan['ssd_mb'] ?? 0);

    $byRam  = $ram  > 0 ? intdiv($capacity['free_memory'], $ram)  : PHP_INT_MAX;
    $byDisk = $disk > 0 ? intdiv($capacity['free_disk'],   $disk) : PHP_INT_MAX;

    return min($byRam, $byDisk);
}

/**
 * Dokłada każdemu planowi informację o dostępności.
 *
 * Do każdego rekordu dochodzi:
 *   - `fits`            ile jeszcze się zmieści (null, gdy nie znamy węzła),
 *   - `sold_out`        wynikowa flaga: ręczna LUB brak miejsca,
 *   - `sold_out_manual` czy wyłączenie pochodzi z bazy, czy z pojemności.
 *
 * @param array<int,array<string,mixed>> $plans
 * @return array<int,array<string,mixed>>
 */
function annotate_plan_availability(array $plans, ?array $capacity): array
{
    foreach ($plans as $i => $plan) {
        $manual = !empty($plan['sold_out']);

        if ($capacity === null) {
            // Nie znamy węzła - zostawiamy stan z bazy. Zgadywanie w drugą
            // stronę oznaczałoby wyłączenie całej sprzedaży przez literówkę
            // w nazwie węzła.
            $plans[$i]['fits']            = null;
            $plans[$i]['sold_out_manual'] = $manual;
            $plans[$i]['sold_out']        = $manual;
            continue;
        }

        $fits = plan_fits($plan, $capacity);

        $plans[$i]['fits']            = $fits;
        $plans[$i]['sold_out_manual'] = $manual;
        $plans[$i]['sold_out']        = $manual || $fits < CAPACITY_MIN_FREE_SLOTS;
    }

    return $plans;
}

/**
 * To samo co plan_fits(), ale dla progu slotowego (Zomboid, Vintage Story).
 *
 * hosting_slot_tiers NIE MA własnej kolumny dysku - konfigurator slotów nie
 * daje klientowi wyboru dysku, więc backend/lib/pricing.php (pricing_from_tier)
 * używa jednej stałej, PRICING_DEFAULT_DISK_MB, dla KAŻDEGO progu każdej gry
 * rozliczanej za sloty. Liczymy więc dostępność po RAM-ie progu (`ram_gb`,
 * przeliczone na MiB) i po tej samej stałej dyskowej - inaczej duży węzeł
 * z małym dyskiem pokazywałby nieograniczoną liczbę wolnych miejsc, mimo że
 * miejsca na dysku dawno zabrakło.
 */
function tier_fits(array $tier, array $capacity): int
{
    $ram  = (int)round((float)($tier['ram_gb'] ?? 0) * 1024);
    $disk = (int)($tier['ssd_mb'] ?? PRICING_DEFAULT_DISK_MB);

    $byRam  = $ram  > 0 ? intdiv($capacity['free_memory'], $ram)  : PHP_INT_MAX;
    $byDisk = $disk > 0 ? intdiv($capacity['free_disk'],   $disk) : PHP_INT_MAX;

    return min($byRam, $byDisk);
}

/**
 * Odpowiednik annotate_plan_availability() dla progów slotowych - te same
 * trzy pola (`fits`, `sold_out`, `sold_out_manual`), żeby components/
 * slot-configurator.php i pricing_from_tier() mogły czytać próg dokładnie
 * tak samo jak plan-card-cennik.php czyta plan.
 *
 * @param array<int,array<string,mixed>> $tiers
 * @return array<int,array<string,mixed>>
 */
function annotate_tier_availability(array $tiers, ?array $capacity): array
{
    foreach ($tiers as $i => $tier) {
        $manual = !empty($tier['sold_out']);

        if ($capacity === null) {
            $tiers[$i]['fits']            = null;
            $tiers[$i]['sold_out_manual'] = $manual;
            $tiers[$i]['sold_out']        = $manual;
            continue;
        }

        $fits = tier_fits($tier, $capacity);

        $tiers[$i]['fits']            = $fits;
        $tiers[$i]['sold_out_manual'] = $manual;
        $tiers[$i]['sold_out']        = $manual || $fits < CAPACITY_MIN_FREE_SLOTS;
    }

    return $tiers;
}

<?php
/**
 * Wycena zamówienia - JEDYNE miejsce, w którym powstaje kwota do zapłaty.
 *
 * ZASADA NADRZĘDNA: cena NIGDY nie przychodzi z formularza.
 *
 * Klient przysyła wyłącznie to, CO wybrał (gra, plan albo liczba slotów, okres,
 * model rozliczenia). Ile to kosztuje, liczy ten plik na podstawie bazy. Gdyby
 * kwota szła z przeglądarki, wystarczyłoby narzędzie deweloperskie i podmiana
 * jednego pola, żeby kupić serwer za grosz - i nic po stronie serwera by tego
 * nie zauważyło, bo Stripe policzyłby dokładnie tyle, ile mu każemy.
 *
 * WZÓR JEST WSPÓLNY Z FRONTENDEM. Konfigurator w assets/js/cennik.js liczy:
 *
 *     floor(cena_bazowa * multiplier) + adjust
 *
 * i tutaj MUSI być tak samo, co do grosza. Rozjazd nie skończy się wyjątkiem,
 * tylko klientem, któremu strona pokazała 32,99 zł, a Stripe policzył 33,49 -
 * czyli reklamacją i utratą zaufania przy pierwszym zakupie.
 *
 * Kwota wychodzi stąd w GROSZACH (int). Stripe przyjmuje `unit_amount` właśnie
 * w najmniejszej jednostce waluty, więc nie ma tu ani jednej konwersji tam
 * i z powrotem przez liczby zmiennoprzecinkowe.
 */

declare(strict_types=1);

/** Parametry, których nie ma w tabelach, a Pterodactyl ich potrzebuje. */
const PRICING_DEFAULT_DISK_MB    = 20480;   // 20 GB dla gier rozliczanych za sloty
const PRICING_DEFAULT_BACKUPS    = 1;

/**
 * Wycena pojedynczego zamówienia.
 *
 * @param array{game_type:string,plan_id?:int|null,slots?:int|null,period_days:int,billing_mode:string} $input
 * @return array<string,mixed> Pełna specyfikacja zamówienia gotowa do zapisu
 * @throws RuntimeException gdy wybór jest niespójny - wołający ma to zamienić
 *                          na 400, a nie na zamówienie z domyślnymi wartościami
 */
function pricing_quote(PDO $panel, array $input): array
{
    $gameType    = (string)($input['game_type'] ?? '');
    $periodDays  = (int)($input['period_days'] ?? 0);
    $billingMode = (string)($input['billing_mode'] ?? 'payment');

    if (!in_array($billingMode, ['payment', 'subscription'], true)) {
        throw new RuntimeException('Nieznany model rozliczenia.');
    }

    $period = pricing_period($panel, $periodDays);
    $game   = pricing_game($panel, $gameType);

    // Gotowy plan czy konfigurator slotów - rozstrzyga to, co przyszło,
    // ale WERYFIKUJEMY w bazie, że taki wybór dla tej gry w ogóle istnieje.
    $planId = isset($input['plan_id']) ? (int)$input['plan_id'] : 0;
    $slots  = isset($input['slots'])   ? (int)$input['slots']   : 0;

    if ($planId > 0) {
        $spec = pricing_from_plan($panel, $planId, $gameType);
    } elseif ($slots > 0) {
        $spec = pricing_from_tier($panel, $slots, $gameType);
    } else {
        throw new RuntimeException('Nie wybrano ani planu, ani liczby slotów.');
    }

    $amount = pricing_apply_period($spec['price'], $period);

    if ($amount <= 0) {
        throw new RuntimeException('Wyliczona kwota jest niedodatnia.');
    }

    return [
        'game_type'     => $gameType,
        'game_name'     => $game['name'],
        'plan_id'       => $spec['plan_id'],
        'slots'         => $spec['slots'],
        'label'         => $spec['label'],
        'ram_mb'        => $spec['ram_mb'],
        'cpu_cores'     => $spec['cpu_cores'],
        'ssd_mb'        => $spec['ssd_mb'],
        'backups_limit' => $spec['backups_limit'],
        'period_days'   => $period['days'],
        'period_label'  => $period['label'],
        'billing_mode'  => $billingMode,
        'amount_gross'  => (int)round($amount * 100),
        'currency'      => 'pln',
    ];
}

/**
 * Cena bazowa przeliczona na wybrany okres.
 *
 * ZAOKRĄGLENIE W DÓŁ TYLKO WTEDY, GDY OKRES MA WŁASNĄ KOŃCÓWKĘ (`adjust`).
 *
 * `floor` istnieje po to, żeby po przemnożeniu przez ułamek dostać równą kwotę,
 * do której dokleja się końcówkę z `adjust` - tydzień: 65 * 0,5 = 32,5 → 32
 * → 32,99. Stosowanie go ZAWSZE psuło ceny gotowych planów, bo te już mają
 * końcówkę w bazie: plan za 6,99 zł miesięcznie wychodził 6,00 zł
 * (floor(6,99 * 1,0) + 0), czyli o 99 groszy za tanio na KAŻDYM zamówieniu.
 *
 * Progi slotowe mają ceny całkowite (19, 35, 65), więc dla nich obie wersje
 * dają identyczny wynik - ten warunek niczego tam nie zmienia.
 */
function pricing_apply_period(float $base, array $period): float
{
    $scaled = $base * $period['multiplier'];

    if ($period['adjust'] > 0) {
        return floor($scaled) + $period['adjust'];
    }

    // Bez końcówki liczymy dokładnie, ucinając wyłącznie ułamki grosza.
    return round($scaled, 2);
}

/**
 * Działanie odwrotne do pricing_apply_period(): z kwoty za okres wyciąga cenę bazową.
 *
 * Potrzebne przy przedłużaniu serwera, którego planu nie ma już w cenniku -
 * jedyne, co po nim zostało, to zapłacona kwota i okres, na jaki była. Bez tego
 * przedłużenie rocznego serwera na miesiąc liczyłoby się od kwoty rocznej.
 *
 * Odtworzenie nie jest idealne - `floor` po drodze gubi grosze i nie da się ich
 * odzyskać. Rozjazd sięga jednego złotego na okres i wychodzi na KORZYŚĆ
 * klienta, bo cena bazowa wypada nie wyżej niż pierwotna.
 */
function pricing_unscale(float $paid, array $period): float
{
    $withoutAdjust = $paid - $period['adjust'];
    $multiplier    = $period['multiplier'] > 0 ? $period['multiplier'] : 1.0;

    return round($withoutAdjust / $multiplier, 2);
}

/** Okres rozliczeniowy z bazy. Nieaktywny okres traktujemy jak nieistniejący. */
function pricing_period(PDO $panel, int $days): array
{
    $stmt = $panel->prepare('SELECT days, label, multiplier, adjust FROM hosting_periods WHERE days = ? AND is_active = 1');
    $stmt->execute([$days]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Nieznany okres rozliczeniowy.');
    }

    return [
        'days'       => (int)$row['days'],
        'label'      => (string)$row['label'],
        'multiplier' => (float)$row['multiplier'],
        'adjust'     => (float)$row['adjust'],
    ];
}

function pricing_game(PDO $panel, string $type): array
{
    $stmt = $panel->prepare('SELECT type, name FROM hosting_games WHERE type = ? AND is_active = 1');
    $stmt->execute([$type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Nieznana gra.');
    }

    return ['type' => (string)$row['type'], 'name' => (string)$row['name']];
}

/**
 * Gotowy plan z cennika.
 *
 * Sprawdzamy TAKŻE, czy plan należy do wskazanej gry. Bez tego dałoby się
 * kupić plan Minecrafta pod pozorem Vintage Story i dostać serwer z zupełnie
 * innym jajkiem, niż wskazuje cena.
 */
function pricing_from_plan(PDO $panel, int $planId, string $gameType): array
{
    $stmt = $panel->prepare(
        'SELECT id, name, type, ram_mb, cpu_cores, ssd_mb, backups_limit, price, sold_out
         FROM hosting_plans WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new RuntimeException('Plan nie istnieje lub jest nieaktywny.');
    }
    if ((string)$plan['type'] !== $gameType) {
        throw new RuntimeException('Plan nie należy do wybranej gry.');
    }

    /* Dostępność sprawdzamy TU, a nie tylko przy rysowaniu karty. Klient mógł
       otworzyć cennik godzinę temu i kliknąć teraz, gdy miejsca już nie ma -
       a między kartą a płatnością nie ma żadnego innego strażnika. */
    $capacity = node_capacity($panel);
    [$checked] = annotate_plan_availability([$plan], $capacity);
    if (!empty($checked['sold_out'])) {
        throw new RuntimeException('Ten plan jest już niedostępny.');
    }

    return [
        'plan_id'       => (int)$plan['id'],
        'slots'         => null,
        'label'         => (string)$plan['name'],
        'ram_mb'        => (int)$plan['ram_mb'],
        'cpu_cores'     => (float)$plan['cpu_cores'],
        'ssd_mb'        => (int)$plan['ssd_mb'],
        'backups_limit' => (int)($plan['backups_limit'] ?? 0),
        'price'         => (float)$plan['price'],
    ];
}

/**
 * Próg z konfiguratora slotów (Zomboid, Vintage Story).
 *
 * Liczba slotów musi trafić DOKŁADNIE w próg z tabeli. Suwak na stronie i tak
 * skacze po progach, więc wartość spoza listy oznacza albo ręczne majstrowanie
 * przy żądaniu, albo rozjazd z bazą - w obu przypadkach nie ma czego sprzedać.
 */
function pricing_from_tier(PDO $panel, int $slots, string $gameType): array
{
    $stmt = $panel->prepare(
        'SELECT id, type, slots, ram_gb, cpu_cores, price
         FROM hosting_slot_tiers WHERE type = ? AND slots = ? AND is_active = 1'
    );
    $stmt->execute([$gameType, $slots]);
    $tier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tier) {
        throw new RuntimeException('Nieznana liczba slotów dla tej gry.');
    }

    $ramMb = (int)round((float)$tier['ram_gb'] * 1024);

    /* Konfigurator nie ma miejsca na dysk ani backupy - tabela progów ich nie
       trzyma, bo klient ich nie wybiera. Wartości są stałe i świadomie
       zadeklarowane u góry tego pliku, a nie rozsypane po kodzie tworzącym
       serwer, jak to było w starej wersji. */
    return [
        'plan_id'       => null,
        'slots'         => (int)$tier['slots'],
        'label'         => $tier['slots'] . ' slotów',
        'ram_mb'        => $ramMb,
        'cpu_cores'     => (float)$tier['cpu_cores'],
        'ssd_mb'        => PRICING_DEFAULT_DISK_MB,
        'backups_limit' => PRICING_DEFAULT_BACKUPS,
        'price'         => (float)$tier['price'],
    ];
}

/** Grosze na czytelny zapis: 3299 -> "32,99". */
function pricing_format(int $grosze): string
{
    return number_format($grosze / 100, 2, ',', ' ');
}

/**
 * Czy przecena planu/progu jest DZIŚ widoczna klientowi.
 *
 * `is_on_sale` w bazie to intencja admina ("chcę przecenę"), nie stan bieżący -
 * `sale_ends_at` (może być puste = bez auto-wygaszenia) decyduje, czy jeszcze
 * trwa. Liczymy to na żywo przy każdym odczycie zamiast crona, który by co
 * godzinę zerował flagę: ten sam wzorzec co `sold_out` w capacity.php (tam
 * pojemność węzła, tu czas) - jedno źródło prawdy zamiast dwóch, które mogłyby
 * się rozjechać w oknie między wygaśnięciem a najbliższym uruchomieniem crona.
 *
 * Cena, którą płaci klient, ZAWSZE bierze się z kolumny `price` - ta funkcja
 * wpływa wyłącznie na to, czy pokazać przekreśloną `old_price` i plakietkę.
 */
function pricing_effective_on_sale(array $row): bool
{
    if (empty($row['is_on_sale'])) {
        return false;
    }

    $koniec = $row['sale_ends_at'] ?? null;
    if ($koniec === null || $koniec === '') {
        return true;
    }

    return strtotime((string)$koniec) > time();
}

<?php
/**
 * Zmiana pakietu działającego serwera.
 *
 * ZASADA ROZLICZENIA (przeniesiona z beta/process_downgrade.php i z kalkulatora
 * w beta/przedluzenie.php, ale policzona w JEDNYM miejscu zamiast w dwóch —
 * stara wersja liczyła to samo raz w PHP, raz w JavaScripcie i obie wersje
 * musiały się zgadzać z pamięci):
 *
 *   udział = ile procent opłaconego okresu jeszcze zostało
 *
 *   DROŻSZY PAKIET  -> dopłata = (nowa cena − stara cena) × udział
 *                      Data wygaśnięcia BEZ ZMIAN. Klient dopłaca za podniesienie
 *                      poziomu na czas, który mu został, a nie za nowy okres.
 *
 *   TAŃSZY PAKIET   -> bez płatności. Niewykorzystana różnica wraca jako DNI
 *                      na nowym, tańszym pakiecie.
 *
 * DLACZEGO NIE ZWRACAMY PIENIĘDZY przy zejściu niżej: zwrot na kartę to
 * operacja księgowa i zachęta do żonglowania pakietami w tę i z powrotem.
 * Dni na koncie są prostsze dla obu stron i zostawiają klienta u nas.
 *
 * PROGU MINIMALNEJ DOPŁATY (2 zł) pilnujemy tak jak stara wersja: prowizja
 * od mikropłatności potrafi przewyższyć samą kwotę.
 */

declare(strict_types=1);

require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/provisioning.php';

/** Najniższa kwota, jaką ma sens pobierać przez bramkę. */
const PLAN_CHANGE_MIN_CHARGE = 2.00;

/**
 * Ile procent opłaconego okresu jeszcze zostało (0–1).
 *
 * Punktem odniesienia jest CAŁY opłacony okres, a nie 30 dni na sztywno:
 * klient z rocznym abonamentem, któremu został miesiąc, ma dopłacić za miesiąc,
 * a nie za rok.
 */
function plan_change_remaining_ratio(array $order): float
{
    $expires = $order['expires_at'] !== null ? strtotime((string)$order['expires_at']) : false;
    if ($expires === false) {
        return 0.0;
    }

    $days = max(1, (int)$order['period_days']);
    $start = $expires - $days * 86400;
    $total = $expires - $start;

    if ($total <= 0) {
        return 0.0;
    }

    return max(0.0, min(1.0, ($expires - time()) / $total));
}

/**
 * Wycena przejścia na inny pakiet.
 *
 * @return array{
 *   direction:string, price:float, bonus_days:int, ratio:float,
 *   current_price:float, new_price:float, plan:array<string,mixed>
 * }
 * @throws RuntimeException gdy plan nie pasuje do gry albo nie istnieje
 */
function plan_change_quote(PDO $panel, array $order, int $newPlanId): array
{
    $stmt = $panel->prepare(
        'SELECT id, name, type, ram_mb, cpu_cores, ssd_mb, backups_limit, price
         FROM hosting_plans WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([$newPlanId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new RuntimeException('Wybrany pakiet nie istnieje.');
    }
    if ((string)$plan['type'] !== (string)$order['game_type']) {
        throw new RuntimeException('Ten pakiet jest dla innej gry.');
    }
    if ((int)$plan['id'] === (int)($order['plan_id'] ?? 0)) {
        throw new RuntimeException('To jest pakiet, który już masz.');
    }

    $currentPrice = plan_change_current_price($panel, $order);
    $newPrice     = (float)$plan['price'];
    $ratio        = plan_change_remaining_ratio($order);

    if ($newPrice > $currentPrice) {
        /* W GÓRĘ. Dopłata proporcjonalna do pozostałego czasu, zaokrąglona
           w górę do końcówki 99 gr — ta sama zasada, co w cenniku, żeby kwota
           na rachunku wyglądała jak cena, a nie jak wynik dzielenia. */
        $raw   = ($newPrice - $currentPrice) * $ratio;
        $price = max(PLAN_CHANGE_MIN_CHARGE, plan_change_round99($raw));

        return [
            'direction'     => 'up',
            'price'         => $price,
            'bonus_days'    => 0,
            'ratio'         => $ratio,
            'current_price' => $currentPrice,
            'new_price'     => $newPrice,
            'plan'          => $plan,
        ];
    }

    /* W DÓŁ. Niewykorzystana różnica przeliczona na dni NOWEGO, tańszego
       pakietu — dlatego dzielimy przez jego cenę dzienną, a nie starą. */
    $bonusDays = 0;
    if ($newPrice > 0) {
        $unusedValue = ($currentPrice - $newPrice) * $ratio;
        $bonusDays   = (int)floor($unusedValue / ($newPrice / 30));
    }

    return [
        'direction'     => 'down',
        'price'         => 0.0,
        'bonus_days'    => $bonusDays,
        'ratio'         => $ratio,
        'current_price' => $currentPrice,
        'new_price'     => $newPrice,
        'plan'          => $plan,
    ];
}

/**
 * Miesięczna cena pakietu, który klient ma teraz.
 *
 * Bierzemy ją z cennika, a NIE z kwoty zapłaconej za zamówienie: ta druga
 * dotyczy całego okresu (rok to jedenaście miesięcy), więc porównanie z ceną
 * miesięczną nowego planu dałoby bzdurę. Gdy planu nie ma już w cenniku,
 * odtwarzamy cenę bazową z zamówienia.
 */
function plan_change_current_price(PDO $panel, array $order): float
{
    if (!empty($order['plan_id'])) {
        $stmt = $panel->prepare('SELECT price FROM hosting_plans WHERE id = ?');
        $stmt->execute([(int)$order['plan_id']]);
        $price = $stmt->fetchColumn();
        if ($price !== false) {
            return (float)$price;
        }
    }

    $period = pricing_period($panel, (int)$order['period_days']);

    return pricing_unscale((int)$order['amount_gross'] / 100, $period);
}

/** Zaokrąglenie do pełnych złotych z końcówką 99 gr. */
function plan_change_round99(float $value): float
{
    if ($value <= 0) {
        return 0.0;
    }

    return floor($value) + 0.99;
}

/**
 * Pakiety, na które da się przejść z bieżącego zamówienia.
 *
 * Zwracamy WSZYSTKIE aktywne plany tej gry poza obecnym, każdy z gotową
 * wyceną — dzięki temu strona nie musi liczyć niczego sama i nie ma drugiego
 * wzoru, który mógłby się rozjechać z tym powyżej.
 *
 * @return array<int,array<string,mixed>>
 */
function plan_change_options(PDO $panel, array $order): array
{
    $stmt = $panel->prepare(
        'SELECT id FROM hosting_plans
          WHERE type = ? AND is_active = 1 AND id <> ?
          ORDER BY price ASC'
    );
    $stmt->execute([(string)$order['game_type'], (int)($order['plan_id'] ?? 0)]);

    $capacity = node_capacity($panel);
    $out = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $planId) {
        try {
            $q = plan_change_quote($panel, $order, (int)$planId);
        } catch (RuntimeException $e) {
            continue;
        }

        /* Przy przejściu W GÓRĘ sprawdzamy, czy większy pakiet w ogóle zmieści
           się na węźle. Klient siedzi już na maszynie, ale różnicę w zasobach
           trzeba skądś wziąć — obietnica upgrade'u, którego nie da się wykonać,
           kończy się pobraną opłatą i zwrotem. */
        if ($q['direction'] === 'up' && $capacity !== null) {
            $needed = [
                'ram_mb' => (int)$q['plan']['ram_mb'] - (int)$order['ram_mb'],
                'ssd_mb' => (int)$q['plan']['ssd_mb'] - (int)$order['ssd_mb'],
            ];
            if ($needed['ram_mb'] > $capacity['free_memory'] || $needed['ssd_mb'] > $capacity['free_disk']) {
                continue;
            }
        }

        $out[] = [
            'plan_id'    => (int)$q['plan']['id'],
            'name'       => (string)$q['plan']['name'],
            'ram_mb'     => (int)$q['plan']['ram_mb'],
            'cpu_cores'  => (float)$q['plan']['cpu_cores'],
            'ssd_mb'     => (int)$q['plan']['ssd_mb'],
            'backups'    => (int)$q['plan']['backups_limit'],
            'monthly'    => (float)$q['plan']['price'],
            'direction'  => $q['direction'],
            'price'      => $q['price'],
            'bonus_days' => $q['bonus_days'],
        ];
    }

    return $out;
}

/**
 * Przestawia zasoby serwera w Pterodactylu.
 *
 * Endpoint `build` wymaga KOMPLETU limitów i alokacji — pominięcie
 * `allocation` kasuje przypisany port i serwer przestaje być osiągalny pod
 * adresem, który klient rozdał znajomym. Dlatego najpierw czytamy obecny stan.
 *
 * @return array{ok:bool,error?:string}
 */
function plan_change_apply_limits(int $serverId, array $plan): array
{
    $current = pterodactyl_api_request('GET', "/api/application/servers/{$serverId}");

    if ($current['http_code'] !== 200 || !isset($current['body']['attributes'])) {
        return ['ok' => false, 'error' => 'Nie udało się odczytać serwera z panelu.'];
    }

    $attr  = $current['body']['attributes'];
    $alloc = (int)($attr['allocation'] ?? 0);

    if ($alloc === 0) {
        return ['ok' => false, 'error' => 'Serwer nie ma przypisanego portu — zmiana pakietu wymaga kontaktu z administracją.'];
    }

    $res = pterodactyl_api_request('PATCH', "/api/application/servers/{$serverId}/build", [
        'allocation' => $alloc,
        'memory'     => (int)$plan['ram_mb'],
        'swap'       => (int)($attr['limits']['swap'] ?? 0),
        'disk'       => (int)$plan['ssd_mb'],
        'io'         => (int)($attr['limits']['io'] ?? 500),
        'cpu'        => (int)round((float)$plan['cpu_cores'] * 100),
        'feature_limits' => [
            'databases'   => (int)($attr['feature_limits']['databases'] ?? 1),
            'backups'     => (int)$plan['backups_limit'],
            'allocations' => (int)($attr['feature_limits']['allocations'] ?? 1),
        ],
    ]);

    if (!in_array((int)$res['http_code'], [200, 201], true)) {
        return ['ok' => false, 'error' => provision_error_details($res)];
    }

    return ['ok' => true];
}

/**
 * Zapisuje nowy pakiet w zamówieniu.
 *
 * Parametry KOPIUJEMY do zamówienia, tak samo jak przy zakupie — zamówienie
 * ma pozostać czytelne razem z tym, co obiecaliśmy, nawet gdy plan zniknie
 * z cennika.
 */
function plan_change_store(PDO $dnct, int $orderId, array $plan, int $bonusDays): void
{
    $sql = 'UPDATE orders
               SET plan_id = ?, ram_mb = ?, cpu_cores = ?, ssd_mb = ?, backups_limit = ?';

    $params = [
        (int)$plan['id'],
        (int)$plan['ram_mb'],
        (float)$plan['cpu_cores'],
        (int)$plan['ssd_mb'],
        (int)$plan['backups_limit'],
    ];

    if ($bonusDays > 0) {
        $sql .= ', expires_at = DATE_ADD(COALESCE(expires_at, NOW()), INTERVAL ? DAY)';
        $params[] = $bonusDays;
    }

    $sql .= ' WHERE id = ?';
    $params[] = $orderId;

    $dnct->prepare($sql)->execute($params);
}

<?php
/**
 * Historia cen planów - na potrzeby informacji o najniższej cenie z 30 dni
 * przed obniżką (dyrektywa Omnibus, art. 6a ustawy o informowaniu o cenach).
 *
 * Tabela `plany` w bazie panelu należy do nas, ale nie ma historii, a admin
 * zmienia ceny z różnych miejsc. Dlatego nie podpinamy się pod zapisy, tylko
 * przy każdym odczycie cennika porównujemy bieżący stan z ostatnim wpisem
 * i dopisujemy wiersz, gdy cena albo flaga przeceny się zmieniły. Jeden
 * mechanizm łapie każdą zmianę, także zrobioną ręcznie w bazie.
 *
 * Historia jest w bazie STRONY (dnct_db), osobnej dla produkcji i bety.
 * Zaczyna się w dniu wdrożenia - dla przecen starszych niż wpisy zwracamy
 * cenę przekreśloną zadeklarowaną przez admina (`declared`).
 */

declare(strict_types=1);

function price_history_ensure(PDO $site): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $site->exec(
        'CREATE TABLE IF NOT EXISTS price_history (
            id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            kind    VARCHAR(16)  NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            price   DECIMAL(10,2) NOT NULL,
            on_sale TINYINT(1)   NOT NULL DEFAULT 0,
            from_at DATETIME     NOT NULL,
            KEY item (kind, item_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

/**
 * Dopisuje wiersz dla każdej pozycji, której cena albo stan przeceny się zmieniły.
 *
 * @param array<int,array> $rows Wiersze z `hosting_plans` (id, price, is_on_sale, sale_ends_at).
 */
function price_history_sync(PDO $site, string $kind, array $rows): void
{
    price_history_ensure($site);

    $stmt = $site->prepare(
        'SELECT h.item_id, h.price, h.on_sale
           FROM price_history h
           JOIN (SELECT MAX(id) mid FROM price_history WHERE kind = ? GROUP BY item_id) x ON x.mid = h.id'
    );
    $stmt->execute([$kind]);
    $last = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $last[(int)$r['item_id']] = $r;
    }

    $ins = $site->prepare('INSERT INTO price_history (kind, item_id, price, on_sale, from_at) VALUES (?, ?, ?, ?, NOW())');
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $price  = round((float)($row['price'] ?? 0), 2);
        $onSale = pricing_effective_on_sale($row) ? 1 : 0;
        $prev   = $last[$id] ?? null;
        if ($prev !== null && abs((float)$prev['price'] - $price) < 0.005 && (int)$prev['on_sale'] === $onSale) {
            continue;
        }
        $ins->execute([$kind, $id, number_format($price, 2, '.', ''), $onSale]);
    }
}

/**
 * Najniższa cena z 30 dni poprzedzających wprowadzenie trwającej przeceny.
 *
 * @return array{price: float, declared: bool}|null null, gdy pozycja nie jest przeceniona
 */
function price_history_lowest_before_sale(PDO $site, string $kind, int $id, float $declaredOld): ?array
{
    $stmt = $site->prepare(
        'SELECT price, on_sale, UNIX_TIMESTAMP(from_at) AS t FROM price_history WHERE kind = ? AND item_id = ? ORDER BY id ASC'
    );
    $stmt->execute([$kind, $id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows || empty($rows[count($rows) - 1]['on_sale'])) {
        return null;
    }

    // Początek trwającej przeceny = pierwszy wiersz ostatniej serii on_sale=1.
    $start = count($rows) - 1;
    while ($start > 0 && !empty($rows[$start - 1]['on_sale'])) {
        $start--;
    }
    $startT  = (int)$rows[$start]['t'];
    $windowT = $startT - 30 * 86400;

    $lowest = null;
    for ($i = 0; $i < $start; $i++) {
        // Wiersz obowiązywał do początku następnego; liczy się, jeśli sięga w okno.
        if ((int)$rows[$i + 1]['t'] > $windowT) {
            $p = (float)$rows[$i]['price'];
            $lowest = $lowest === null ? $p : min($lowest, $p);
        }
    }

    if ($lowest !== null) {
        return ['price' => $lowest, 'declared' => false];
    }

    return $declaredOld > 0 ? ['price' => $declaredOld, 'declared' => true] : null;
}

/**
 * Zapisuje zmiany cen i dokleja `lowest30` do przecenionych planów. Wspólne
 * dla cennika i strony głównej. Błąd historii nigdy nie zabiera strony -
 * karta po prostu nie pokaże informacji.
 *
 * @param array<int,array> $rows Wiersze z `hosting_plans`.
 * @return array<int,array>
 */
function price_history_annotate_plans(array $rows): array
{
    try {
        $site = dnct_db();
        price_history_sync($site, 'plan', $rows);
        foreach ($rows as &$row) {
            if (pricing_effective_on_sale($row)) {
                $row['lowest30'] = price_history_lowest_before_sale($site, 'plan', (int)$row['id'], (float)($row['old_price'] ?? 0));
            }
        }
        unset($row);
    } catch (Throwable $e) {
        error_log('historia cen: ' . $e->getMessage());
    }

    return $rows;
}

<?php
/**
 * Czas pokazywany człowiekowi.
 *
 * PHP chodzi w UTC (date.timezone nieustawione) i baza też - NOW() zwraca
 * UTC, a kolumny DATETIME nie niosą strefy. To jest spójne i ma zostać:
 * zapis, porównania i cykl życia (lifecycle.php, crony) liczą w UTC.
 *
 * Problem jest wyłącznie w wyświetlaniu: `date()` albo `->format()` na
 * surowej wartości z bazy pokazuje klientowi UTC jako czas polski, czyli
 * 2 h za wcześnie latem i 1 h zimą. Wszystko, co trafia do oczu (widok,
 * mail, tekst powiadomienia), przechodzi przez funkcje poniżej.
 *
 * Nie ustawiaj zamiast tego date_default_timezone_set('Europe/Warsaw') -
 * `date('Y-m-d H:i:s')` zapisywane do bazy zaczęłoby wtedy jechać czasem
 * polskim, obok NOW() w UTC.
 */

declare(strict_types=1);

const DNCT_TZ_DB   = 'UTC';
const DNCT_TZ_USER = 'Europe/Warsaw';

/** Wartość z bazy (UTC) jako DateTimeImmutable w strefie polskiej; null przy pustej/błędnej. */
function czas_lokalny(?string $raw): ?DateTimeImmutable
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($raw, new DateTimeZone(DNCT_TZ_DB)))
            ->setTimezone(new DateTimeZone(DNCT_TZ_USER));
    } catch (Exception) {
        return null;
    }
}

/** Data z bazy (UTC) sformatowana po polsku; $pusto, gdy jej nie ma. */
function czas_fmt(?string $raw, string $format = 'd.m.Y, H:i', string $pusto = ''): string
{
    $d = czas_lokalny($raw);
    return $d !== null ? $d->format($format) : $pusto;
}

/** Znacznik czasu (time(), strtotime() na UTC) sformatowany po polsku. */
function czas_fmt_ts(int $ts, string $format = 'd.m.Y, H:i'): string
{
    return (new DateTimeImmutable('@' . $ts))
        ->setTimezone(new DateTimeZone(DNCT_TZ_USER))
        ->format($format);
}

/** Teraz, po polsku - do "stan na ..." i podobnych. */
function czas_teraz(string $format = 'd.m.Y, H:i'): string
{
    return czas_fmt_ts(time(), $format);
}

/**
 * Odwrotność: czas wpisany przez człowieka (np. <input type="datetime-local">,
 * czyli po polsku) zamieniony na UTC do zapisu w bazie. null przy pustym/błędnym.
 */
function czas_do_bazy(?string $lokalny): ?string
{
    if ($lokalny === null || trim($lokalny) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($lokalny, new DateTimeZone(DNCT_TZ_USER)))
            ->setTimezone(new DateTimeZone(DNCT_TZ_DB))
            ->format('Y-m-d H:i:s');
    } catch (Exception) {
        return null;
    }
}

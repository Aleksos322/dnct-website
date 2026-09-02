<?php
/**
 * Żywe śledzenie obecności na dnct.pl — zapis heartbeatu, bieżące zliczenia
 * i ranking "kto spędza najwięcej czasu".
 *
 * JEDEN WIERSZ NA SESJĘ, NIE NA PING.
 * `proxmox_pomiary` (backend/lib/pomiary.php) trzyma historię do wykresu i
 * potrzebuje każdego punktu w czasie. Tu interesuje nas wyłącznie stan
 * bieżący ("kto jest teraz") + suma czasu na sesję — wiersz na każdy ping
 * (co ~20 s, z każdej otwartej karty) zalałby tabelę bez żadnej korzyści.
 * `aktywnosc_ping()` robi więc UPSERT: jeden wiersz per `session_id()` PHP,
 * doliczający różnicę czasu do sumy przy każdym odświeżeniu.
 */

declare(strict_types=1);

/** Ping świeższy niż to = sesja liczy się jako "aktywna teraz" (widoczna karta). */
const AKTYWNOSC_OKNO_AKTYWNI_SEK = 30;

/** Ping świeższy niż to = sesja liczy się jako "otwarta karta" (bez względu na widoczność). */
const AKTYWNOSC_OKNO_OTWARTE_SEK = 60;

/** Górny limit przerwy między pingami doliczanej do sumy czasu - odcina fikcyjny czas po uśpieniu laptopa z otwartą kartą. */
const AKTYWNOSC_MAKS_PRZERWA_SEK = 40;

/** Ile dni trzymamy nieaktywne sesje, zanim `aktywnosc_wyczysc()` je skasuje. */
const AKTYWNOSC_ZATRZYMAJ_DNI = 90;

/**
 * Zapisuje/odświeża heartbeat jednej sesji.
 *
 * UPSERT liczy różnicę między nowym a starym `ostatni_ping` PO STRONIE SQL
 * (`VALUES(ostatni_ping)` = nowa wartość z tego INSERT-a, goły `ostatni_ping`
 * = wartość już zapisana w wierszu) - jednoznaczne w MySQL, bez zgadywania
 * kolejności ewaluacji SET-ów jak w zwykłym UPDATE.
 */
function aktywnosc_ping(string $sesjaId, ?string $username, string $strona, bool $widoczna): bool
{
    if ($sesjaId === '') {
        return false;
    }

    try {
        $teraz = time();
        // Dwa osobne parametry na tę samą wartość, nie jeden powtórzony -
        // prawdziwe (nieemulowane) przygotowane zapytania w MySQL nie
        // pozwalają użyć tego samego nazwanego parametru dwa razy w jednym
        // poleceniu ("Invalid parameter number"), patrz też pomiary_wczytaj().
        $st = dnct_db()->prepare(
            'INSERT INTO aktywnosc_sesje
                (sesja_id, authentik_username, strona, widoczna, pierwszy_ping, ostatni_ping, laczny_czas_sek)
             VALUES (:sesja_id, :username, :strona, :widoczna, :teraz1, :teraz2, 0)
             ON DUPLICATE KEY UPDATE
                authentik_username = VALUES(authentik_username),
                strona             = VALUES(strona),
                widoczna           = VALUES(widoczna),
                laczny_czas_sek    = laczny_czas_sek + LEAST(GREATEST(VALUES(ostatni_ping) - ostatni_ping, 0), :maks_przerwa),
                ostatni_ping       = VALUES(ostatni_ping)'
        );

        return $st->execute([
            'sesja_id'      => $sesjaId,
            'username'      => $username,
            'strona'        => mb_substr($strona, 0, 255),
            'widoczna'      => $widoczna ? 1 : 0,
            'teraz1'        => $teraz,
            'teraz2'        => $teraz,
            'maks_przerwa'  => AKTYWNOSC_MAKS_PRZERWA_SEK,
        ]);
    } catch (Throwable $e) {
        error_log('aktywnosc_ping: ' . $e->getMessage());

        return false;
    }
}

/**
 * Bieżące zliczenia do kafelków panelu admina.
 *
 * @return array{aktywni_teraz:int, otwarte_karty:int}
 */
function aktywnosc_zliczenia(): array
{
    try {
        $teraz = time();

        $st = dnct_db()->prepare(
            'SELECT
                SUM(CASE WHEN widoczna = 1 AND ostatni_ping > :prog_aktywni THEN 1 ELSE 0 END) AS aktywni_teraz,
                SUM(CASE WHEN ostatni_ping > :prog_otwarte THEN 1 ELSE 0 END) AS otwarte_karty
             FROM aktywnosc_sesje'
        );
        $st->execute([
            'prog_aktywni' => $teraz - AKTYWNOSC_OKNO_AKTYWNI_SEK,
            'prog_otwarte' => $teraz - AKTYWNOSC_OKNO_OTWARTE_SEK,
        ]);
        $w = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'aktywni_teraz' => (int)($w['aktywni_teraz'] ?? 0),
            'otwarte_karty' => (int)($w['otwarte_karty'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('aktywnosc_zliczenia: ' . $e->getMessage());

        return ['aktywni_teraz' => 0, 'otwarte_karty' => 0];
    }
}

/**
 * Ranking "kto spędza najwięcej czasu" - tylko zalogowani (anonimowi liczą
 * się do zliczeń u góry, ale nie mają tożsamości do pokazania w tabeli).
 *
 * @return array<int, array{username:string, ostatnia_aktywnosc:int, wizyt:int, laczny_czas:int}>
 */
function aktywnosc_ranking(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));

    try {
        $st = dnct_db()->prepare(
            'SELECT
                authentik_username,
                MAX(ostatni_ping)     AS ostatnia_aktywnosc,
                COUNT(*)              AS wizyt,
                SUM(laczny_czas_sek)  AS laczny_czas
             FROM aktywnosc_sesje
             WHERE authentik_username IS NOT NULL
             GROUP BY authentik_username
             ORDER BY laczny_czas DESC
             LIMIT ' . $limit
        );
        $st->execute();

        $wynik = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $wynik[] = [
                'username'           => (string)$w['authentik_username'],
                'ostatnia_aktywnosc' => (int)$w['ostatnia_aktywnosc'],
                'wizyt'              => (int)$w['wizyt'],
                'laczny_czas'        => (int)$w['laczny_czas'],
            ];
        }

        return $wynik;
    } catch (Throwable $e) {
        error_log('aktywnosc_ranking: ' . $e->getMessage());

        return [];
    }
}

/**
 * Kasuje sesje nieaktywne dłużej niż `AKTYWNOSC_ZATRZYMAJ_DNI` - jednorazowi
 * goście sprzed miesięcy nie mają po co zostawać w tabeli na zawsze.
 */
function aktywnosc_wyczysc(): bool
{
    try {
        dnct_db()->exec(
            'DELETE FROM aktywnosc_sesje WHERE ostatni_ping < ' . (time() - AKTYWNOSC_ZATRZYMAJ_DNI * 86400)
        );

        return true;
    } catch (Throwable $e) {
        error_log('aktywnosc_wyczysc: ' . $e->getMessage());

        return false;
    }
}

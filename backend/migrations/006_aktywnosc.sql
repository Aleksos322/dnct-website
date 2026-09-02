-- ---------------------------------------------------------------------------
-- Aktywność: żywe śledzenie obecności na dnct.pl (panel admina, zakładka
-- "Aktywność"). Jeden wiersz na przeglądarkę (session_id() PHP), dociskany
-- (UPSERT) przez JS heartbeat co ~20s z assets/clear/clear.php.
--
-- BAZA: dnct_db() (MY_DB_*), ta sama co orders/coupons/notifications.
--
-- DLACZEGO UPSERT, NIE WIERSZ NA KAŻDY PING (jak proxmox_pomiary).
-- proxmox_pomiary jest historią do wykresu - potrzebuje każdego punktu.
-- Tu interesuje nas WYŁĄCZNIE stan bieżący + suma czasu; wiersz na ping
-- (co 20s, każda otwarta karta) zalałby tabelę bez żadnej korzyści -
-- jeden zaktualizowany wiersz na sesję daje to samo przy stałym rozmiarze
-- tabeli (ograniczonym liczbą RÓWNOCZESNYCH+historycznych sesji, nie
-- liczbą pingów).
--
-- Panel Pterodactyla NIE MA własnej tabeli tutaj - liczby stamtąd liczy
-- się zero-touch z plików sesji Laravela (backend/lib/panel_aktywnosc.php),
-- bo Pterodactyl nie ma żadnego API do listy aktywnych sesji.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS aktywnosc_sesje (
    sesja_id            VARCHAR(64) NOT NULL,   -- session_id() PHP - jedna przeglądarka
    authentik_username  VARCHAR(190) NULL,       -- NULL = gość, niezalogowany

    strona               VARCHAR(255) NOT NULL,  -- ostatnia odwiedzona ścieżka
    widoczna             TINYINT(1) NOT NULL DEFAULT 1,  -- Page Visibility API

    pierwszy_ping        INT UNSIGNED NOT NULL,
    ostatni_ping         INT UNSIGNED NOT NULL,
    laczny_czas_sek      INT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (sesja_id),
    INDEX idx_ostatni_ping (ostatni_ping),
    INDEX idx_username (authentik_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

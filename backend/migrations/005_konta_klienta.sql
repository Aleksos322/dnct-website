-- ---------------------------------------------------------------------------
-- Konta klienta: spina tożsamość Authentika z kontami w Pterodactylu
-- i Jellyfinie, plus dane profilowe niedostępne w żadnym z tych systemów.
--
-- BAZA: dnct_db() (MY_DB_*) - ta sama baza co orders/coupons/notifications.
-- Migracja `users` Pterodactyla do tej bazy jest ŚWIADOMIE ODRZUCONA (18
-- migracji z FK po stronie panelu) - ta tabela tylko LINKUJE, nie duplikuje.
-- Jellyfin nie trzyma kont w MySQL wcale (lokalny SQLite na 192.168.50.9) -
-- integracja idzie wyłącznie przez jego REST API (backend/lib/jellyfin.php).
--
-- KLUCZ TO authentik_username, NIE authentik_sub. `sub` z OIDC jest dostępny
-- dopiero PO zalogowaniu przez Authentika (auth/callback.php), a ten wiersz
-- trzeba założyć już w momencie rejestracji (api/auth/register.php), która
-- zakłada konto przez REST API Authentika i nie przechodzi przez flow OIDC.
-- username jest dostępny w obu miejscach. Ryzyko: zmiana nazwy konta w
-- Authentiku rozjeżdża link - to samo ryzyko co dziś w `orders`
-- (user_email/user_name jako odbicie, nie FK), akceptowalne na tę skalę.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS user_profiles (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    authentik_username    VARCHAR(190) NOT NULL,
    authentik_email       VARCHAR(190) NULL,

    -- Linki do kont w pozostałych systemach. NULL = jeszcze nie połączone.
    -- Pterodactyl łączy się automatycznie przy rejestracji (JIT po e-mailu).
    -- Jellyfin też się zakłada automatycznie przy rejestracji, ale bez
    -- wykrywania duplikatów (Jellyfin nie zwraca e-maila w /Users) - stąd
    -- jellyfin_link_source, żeby odróżnić "nasze auto-konto" od "klient
    -- podłączył swoje stare konto ręcznie".
    pterodactyl_user_id   INT UNSIGNED NULL,
    jellyfin_user_id      CHAR(32)     NULL,   -- Id z Jellyfin API, hex, NIE int

    jellyfin_linked_at    DATETIME     NULL,
    jellyfin_link_source  ENUM('created', 'linked_existing') NULL,

    -- Pola profilowe - nazewnictwo płaskie, bez JSON-a, w stylu orders/coupons.
    birth_date            DATE         NULL,
    phone                 VARCHAR(30)  NULL,
    minecraft_nick        VARCHAR(32)  NULL,
    discord_username      VARCHAR(64)  NULL,
    steam_id              VARCHAR(64)  NULL,

    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_username (authentik_username),
    UNIQUE KEY uniq_pterodactyl (pterodactyl_user_id),
    UNIQUE KEY uniq_jellyfin (jellyfin_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

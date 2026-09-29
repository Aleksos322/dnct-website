-- ============================================================================
-- 009: jedno konto DNCT - stały identyfikator (sub) i publiczny profil
-- ============================================================================
-- 1. authentik_sub - NIEZMIENNY identyfikator konta (pole `uid` użytkownika
--    w Authentiku = `sub` w OIDC, bo providery mają sub_mode=hashed_user_id).
--    Wcześniej wszystko łączyło się po e-mailu i loginie, które klient mógł
--    sam zmienić w ustawieniach Authentika - wpisanie cudzego e-maila dawało
--    logowanie na cudze konto (załatane 2026-09-29). Od teraz e-mail służy do
--    kontaktu, a konta łączy sub.
--    Uwaga do 005: sub JEST dostępny przy rejestracji - API Authentika zwraca
--    `uid` przy zakładaniu konta.
--
-- 2. Profil (account.dnct.pl): nazwa wyświetlana, opis, lokalizacja, strona,
--    avatar i baner (plik wgrany albo gotowy gradient), publiczność profilu
--    i lista pól widocznych publicznie. Domyślnie profil jest PRYWATNY.
-- ============================================================================

ALTER TABLE user_profiles
    ADD COLUMN authentik_sub     CHAR(64)     NULL DEFAULT NULL AFTER authentik_email,
    ADD COLUMN hosting_user      VARCHAR(64)  NULL DEFAULT NULL AFTER jellyfin_link_source,
    ADD COLUMN display_name      VARCHAR(60)  NULL DEFAULT NULL AFTER hosting_user,
    ADD COLUMN bio               VARCHAR(400) NULL DEFAULT NULL AFTER display_name,
    ADD COLUMN location          VARCHAR(60)  NULL DEFAULT NULL AFTER bio,
    ADD COLUMN website           VARCHAR(190) NULL DEFAULT NULL AFTER location,
    ADD COLUMN avatar_file       VARCHAR(80)  NULL DEFAULT NULL AFTER website,
    ADD COLUMN banner_file       VARCHAR(80)  NULL DEFAULT NULL AFTER avatar_file,
    ADD COLUMN banner_preset     VARCHAR(20)  NULL DEFAULT NULL AFTER banner_file,
    ADD COLUMN profile_public    TINYINT(1)   NOT NULL DEFAULT 0 AFTER banner_preset,
    ADD COLUMN public_fields     VARCHAR(255) NULL DEFAULT NULL AFTER profile_public,
    ADD UNIQUE KEY uniq_sub (authentik_sub);

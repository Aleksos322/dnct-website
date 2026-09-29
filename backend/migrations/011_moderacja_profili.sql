-- ============================================================================
-- 011: moderacja profili publicznych (account.dnct.pl/u/<login>)
-- ============================================================================
-- Klienci wgrywają zdjęcia i piszą opisy widoczne dla innych. Admin (panel
-- admin.lan.dnct.pl -> Użytkownicy) może ukryć profil - osobnym znacznikiem,
-- a nie wyłączeniem profile_public, bo ten właściciel przełączyłby z powrotem
-- jednym kliknięciem. Powód widzi właściciel w ustawieniach.
-- ============================================================================

ALTER TABLE user_profiles
    ADD COLUMN moderation_hidden TINYINT(1)   NOT NULL DEFAULT 0 AFTER public_fields,
    ADD COLUMN moderation_note   VARCHAR(255) NULL DEFAULT NULL AFTER moderation_hidden,
    ADD COLUMN moderation_at     DATETIME     NULL DEFAULT NULL AFTER moderation_note;

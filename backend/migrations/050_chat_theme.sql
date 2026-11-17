-- Motyw rozmowy i zgłoszenia użytkowników z czatu (2026-10-28).
-- chat_message_colors: kolor wiadomości każdego uczestnika, wspólny dla wszystkich członków rozmowy.
-- chat_backgrounds: tło okna rozmowy (jednolite albo gradient). Brak wiersza = ustawienie domyślne.
-- chat_reports: ślad zgłoszenia (kto, kogo, który ticket); sama treść trafia do support_messages.
-- IF NOT EXISTS - bezpieczne przy ponownym uruchomieniu.

CREATE TABLE IF NOT EXISTS chat_message_colors (
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  color CHAR(7) NOT NULL,
  updated_by VARCHAR(191) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_backgrounds (
  conversation_id BIGINT UNSIGNED NOT NULL,
  mode ENUM('solid','gradient') NOT NULL,
  color1 CHAR(7) NOT NULL,
  color2 CHAR(7) NULL,
  angle SMALLINT UNSIGNED NOT NULL DEFAULT 135,
  updated_by VARCHAR(191) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  reporter_id VARCHAR(191) NOT NULL,
  reported_id VARCHAR(191) NOT NULL,
  ticket_id BIGINT UNSIGNED NULL,
  client_key CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_report_key (reporter_id,client_key),
  KEY ix_chat_reports_conversation (conversation_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

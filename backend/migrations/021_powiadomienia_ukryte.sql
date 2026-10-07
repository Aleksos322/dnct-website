-- Ukrywanie powiadomień przyciskiem ✕ w panelu "centrum pomocy" (2026-10-07).
-- Powiadomienia "all"/"admins" są wspólne, więc ✕ nie kasuje wiersza, tylko
-- zapisuje per użytkownik, że go już nie chce widzieć (obok read_at).
ALTER TABLE notification_reads ADD COLUMN hidden_at DATETIME NULL AFTER read_at;

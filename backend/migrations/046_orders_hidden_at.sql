-- Rezygnacja klienta z wygasłego serwera (22.10.2026).
-- Przycisk "Rezygnuję" na /twoje-serwery ustawia hidden_at - zamówienie znika
-- z listy klienta. Cykl życia (backend/tools/lifecycle.php) nie patrzy na tę
-- kolumnę: archiwum, zawieszenie i usunięcie idą dalej zwykłym trybem.
ALTER TABLE orders ADD COLUMN hidden_at DATETIME NULL DEFAULT NULL AFTER expires_at;

-- Prośba o opinię w Google: jeden mail na klienta, 3 dni po starcie pierwszego serwera
-- (backend/tools/prosba-o-opinie.php). Kolumna pilnuje, żeby nie wysłać drugi raz.
ALTER TABLE orders ADD COLUMN review_asked_at DATETIME NULL AFTER warn_24h_at;

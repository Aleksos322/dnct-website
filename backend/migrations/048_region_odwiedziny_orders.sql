-- ---------------------------------------------------------------------------
-- Region (województwo) odwiedzających i kupujących - mapa Polski w panelu
-- admina (/statystyki). Kod ISO 3166-2 bez "PL-" (np. MZ) z nagłówka
-- Cloudflare CF-Region-Code (Managed Transform "Add visitor location headers").
-- Bez adresów IP. Kolumny dopuszczają NULL: wpisy sprzed migracji i goście,
-- dla których Cloudflare nie podał regionu, zostają bez niego.
-- ---------------------------------------------------------------------------
ALTER TABLE odwiedziny ADD COLUMN region VARCHAR(6) NULL AFTER kraj;
ALTER TABLE orders     ADD COLUMN region VARCHAR(6) NULL AFTER user_name;

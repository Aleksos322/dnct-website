-- Kwota FAKTYCZNIE zapłacona (22.10.2026), w groszach, z sesji Stripe
-- (amount_total - już po kodzie rabatowym). amount_gross zostaje ceną
-- z cennika w chwili zakupu; przy kodzie rabatowym te dwie liczby się
-- różnią i strona pokazuje cenę przekreśloną. NULL = brak danych
-- (stare zamówienia przed uzupełnieniem) - wtedy liczy się amount_gross.
ALTER TABLE orders ADD COLUMN amount_paid INT NULL DEFAULT NULL AFTER amount_gross;

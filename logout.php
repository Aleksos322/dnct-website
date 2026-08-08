<?php
/**
 * Przekierowanie zgodnościowe: /logout.php -> /wyloguj
 *
 * Panel Pterodactyl po wylogowaniu kieruje przeglądarkę na
 * https://dnct.pl/logout.php - adres jest zaszyty w jego zbudowanym pakiecie
 * JS (public/assets/dashboard.*.js), więc zmiana po tamtej stronie wymagałaby
 * przebudowy całego frontendu. Ten plik sprawia, że po przeniesieniu strony
 * z `beta/` do tego katalogu wylogowanie z panelu nadal trafi we właściwe
 * miejsce - czyli tam, gdzie kończymy również sesję SSO w Authentiku.
 */

declare(strict_types=1);

header('Location: /wyloguj', true, 302);
exit();

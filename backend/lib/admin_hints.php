<?php
/**
 * Podpowiedzi dla admina (np. "widzisz pozycje wyłączone dla klientów" w cenniku).
 *
 * Admin wyłącza je przełącznikiem w stopce (assets/clear/footer.php). Wybór
 * siedzi w ciasteczku dnct_admin_hints (0 = ukryte), więc serwer od razu
 * renderuje podpowiedź z atrybutem hidden - bez mignięcia przy ładowaniu.
 * Podpowiedź oznaczaj data-admin-hint, wtedy przełącznik chowa ją też na żywo.
 */

declare(strict_types=1);

const ADMIN_HINTS_COOKIE = 'dnct_admin_hints';

function admin_hints_is_admin(): bool
{
    return function_exists('dnct_is_admin') ? dnct_is_admin() : !empty($_SESSION['authentik_is_superuser']);
}

/** Czy podpowiedzi admina mają być widoczne (włączone domyślnie). */
function admin_hints_on(): bool
{
    return ($_COOKIE[ADMIN_HINTS_COOKIE] ?? '1') !== '0';
}

/** Atrybuty dla elementu podpowiedzi: data-admin-hint i ewentualnie hidden. */
function admin_hint_attrs(): string
{
    return ' data-admin-hint' . (admin_hints_on() ? '' : ' hidden');
}

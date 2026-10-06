<?php
/**
 * POST /api/partnerzy/zgloszenie - formularz z /partnerzy.
 * Zgłoszenie ląduje w `partner_zgloszenia` i w dzwonku adminów; partnera
 * zakłada się ręcznie w panelu admina (zakładka Partnerzy).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();
rate_limit_guard('partner_zgloszenie', [3, 3600], 'Zbyt wiele zgłoszeń z tego adresu. Spróbuj ponownie za godzinę.');

$nazwa     = request_string('nazwa');
$email     = request_string('email');
$kanal     = request_string('kanal_url');
$widzowie  = request_string('widzowie');
$wiadomosc = request_string('wiadomosc');
$errors    = [];

if (mb_strlen($nazwa) < 2 || mb_strlen($nazwa) > 80) {
    $errors['nazwa'] = 'Podaj nazwę kanału albo nick (2-80 znaków).';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    $errors['email'] = 'Podaj prawidłowy adres e-mail.';
}
if (!preg_match('~^https?://\S+\.\S+~i', $kanal) || mb_strlen($kanal) > 255) {
    $errors['kanal_url'] = 'Podaj pełny link do kanału (https://...).';
}
if (mb_strlen($widzowie) > 60) {
    $errors['widzowie'] = 'Maksymalnie 60 znaków.';
}
if (mb_strlen($wiadomosc) > 1500) {
    $errors['wiadomosc'] = 'Maksymalnie 1500 znaków.';
}
if ($errors !== []) {
    json_error('Popraw zaznaczone pola.', 422, $errors);
}

$dnct = dnct_db();
$dnct->prepare('INSERT INTO partner_zgloszenia (nazwa, email, kanal_url, widzowie, wiadomosc, konto) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$nazwa, $email, $kanal, $widzowie ?: null, $wiadomosc ?: null, $_SESSION['authentik_email'] ?? null]);

notify_admins($dnct, 'info', 'Nowe zgłoszenie do programu twórców', $nazwa . ' - ' . $kanal);

json_ok(['message' => 'Dzięki! Odezwiemy się mailowo, zwykle w ciągu kilku dni.']);

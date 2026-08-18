<?php
/**
 * Wiadomość z danymi logowania do konta Jellyfin, założonego automatycznie
 * przy rejestracji (patrz backend/lib/jellyfin.php, api/auth/register.php).
 *
 * Wysyłana z `register@` (profil domyślny) - to ten sam moment cyklu życia
 * konta co kod weryfikacyjny, nie sprawa zamówienia/płatności (`info@`).
 */

declare(strict_types=1);

require_once __DIR__ . '/../mail/mail.php';

/**
 * @param array{id:string,name:string,password:string} $jellyfinAccount
 * @return array{ok:bool, error:string}
 */
function send_jellyfin_credentials_mail(string $to, string $firstName, array $jellyfinAccount): array
{
    $html = render_mail_template('jellyfin-credentials', [
        'greetingName' => trim($firstName),
        'jfUsername'   => $jellyfinAccount['name'],
        'jfPassword'   => $jellyfinAccount['password'],
    ]);

    $text = (trim($firstName) !== '' ? "Cześć {$firstName}!\n\n" : "Cześć!\n\n")
        . "Razem z kontem DNCT założyliśmy dla Ciebie konto na serwerze mediów Jellyfin.\n\n"
        . "Login: {$jellyfinAccount['name']}\n"
        . "Hasło: {$jellyfinAccount['password']}\n\n"
        . "To hasło pokazujemy tylko raz, w tej wiadomości - zapisz je i zmień po pierwszym logowaniu.\n"
        . "Masz już inne konto Jellyfin? Podłącz je zamiast tego na account.dnct.pl.\n\n"
        . "-- \nDNCT Hosting\nhttps://dnct.pl";

    return send_mail($to, 'Twoje konto Jellyfin - DNCT Hosting', $html, $text);
}

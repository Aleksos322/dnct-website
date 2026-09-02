<?php
/**
 * Liczby aktywności panelu Pterodactyl (panel.dnct.pl) - ZERO-TOUCH.
 *
 * Pterodactyl nie ma żadnego API (Application ani Client) ani websocketu
 * dającego listę aktywnych sesji/użytkowników - sprawdzone bezpośrednio
 * w kodzie panelu. Sesje Laravela leżą jako pliki w
 * `storage/framework/sessions/` (SESSION_DRIVER=file w .env panelu), a
 * dnct.pl działa jako ten sam systemowy użytkownik `www-data`, który już ma
 * prawo odczytu tego katalogu (775) - więc samo zliczenie plików po `mtime`
 * daje liczby bez JAKIEJKOLWIEK zmiany kodu, configu czy uprawnień
 * w /var/www/pterodactyl. Świadomie TYLKO liczby, bez przypisania do
 * konkretnego użytkownika - to wymagałoby odszyfrowania payloadu Laravela
 * kluczem APP_KEY panelu, uznane za niepotrzebną robotę na tę chwilę.
 */

declare(strict_types=1);

const PANEL_SESJE_KATALOG = '/var/www/pterodactyl/storage/framework/sessions';

/** Panel sam siebie odpytuje w tle (patrz api/http.ts w CLAUDE.md panelu) - świeży plik = realnie otwarta karta. */
const PANEL_OKNO_AKTYWNI_SEK = 300;

/** Domyślny czas życia sesji Laravela w tym panelu (config/session.php, SESSION_LIFETIME nieustawione w .env). */
const PANEL_OKNO_OTWARTE_SEK = 720 * 60;

/**
 * @return array{aktywni_teraz:int, otwarte_karty:int}
 */
function panel_aktywnosc_zliczenia(): array
{
    if (!is_dir(PANEL_SESJE_KATALOG) || !is_readable(PANEL_SESJE_KATALOG)) {
        error_log('panel_aktywnosc_zliczenia: katalog sesji panelu nieczytelny (' . PANEL_SESJE_KATALOG . ')');

        return ['aktywni_teraz' => 0, 'otwarte_karty' => 0];
    }

    $teraz = time();
    $aktywni = 0;
    $otwarte = 0;

    foreach (glob(PANEL_SESJE_KATALOG . '/*') ?: [] as $plik) {
        $mtime = @filemtime($plik);
        if ($mtime === false) {
            continue;
        }

        $wiek = $teraz - $mtime;

        if ($wiek <= PANEL_OKNO_OTWARTE_SEK) {
            $otwarte++;
            if ($wiek <= PANEL_OKNO_AKTYWNI_SEK) {
                $aktywni++;
            }
        }
    }

    return ['aktywni_teraz' => $aktywni, 'otwarte_karty' => $otwarte];
}

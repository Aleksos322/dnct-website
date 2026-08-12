<?php
/**
 * Konfiguracja Stripe i wspólne pomocnicze funkcje płatności.
 *
 * SDK jest wendorowane w backend/vendor/stripe-php (przeniesione z beta/).
 * Katalog `backend/` ma własne `Require all denied`, więc biblioteki nie da się
 * pobrać po HTTP — a leży tam kod, który zna klucz tajny.
 *
 * SPRZEDAŻ JEST DOMYŚLNIE WYŁĄCZONA. `SALES_ENABLED` w .env musi być ustawione
 * jawnie na 1, żeby cokolwiek dało się kupić. Powód jest wprost z CLAUDE.md:
 * hosting ma przerwę techniczną i brak możliwości zakupu jest ZAMIERZONY.
 * Gdyby ten kod wjechał na produkcję bez takiej blokady, wystarczyłby jeden
 * merge, żeby sklep otworzył się sam — bez niczyjej decyzji.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/stripe-php/init.php';

/** Czy sprzedaż jest w ogóle włączona na tym środowisku. */
function stripe_sales_enabled(): bool
{
    return env('SALES_ENABLED', '0') === '1';
}

/**
 * Czy pracujemy na kluczach testowych.
 *
 * Przydaje się do oznaczania interfejsu: przy kluczach `sk_test_` nikt nie
 * płaci naprawdę i widok zamówień ma to mówić wprost, żeby test nie został
 * wzięty za sprzedaż.
 */
function stripe_is_test_mode(): bool
{
    return str_starts_with((string)env('STRIPE_SECRET_KEY', ''), 'sk_test_');
}

/** Adresy, na których wolno użyć klucza produkcyjnego. */
const STRIPE_LIVE_HOSTS = ['dnct.pl', 'www.dnct.pl', 'shop.dnct.pl'];

/**
 * Bezpiecznik: klucz `sk_live_` działa WYŁĄCZNIE na produkcyjnej domenie.
 *
 * Na tej maszynie leżą oba komplety kluczy — produkcyjny w public_html/.env
 * i testowy w config/beta.env. Beta ma dodatkowo SALES_ENABLED=1, bo służy do
 * testowania zakupów. Wystarczyłaby jedna pomyłka przy kopiowaniu pliku
 * konfiguracji, żeby testowe klikanie zaczęło pobierać PRAWDZIWE pieniądze
 * z prawdziwych kart — i nikt by tego nie zauważył, bo wszystko działałoby
 * poprawnie.
 *
 * Sprawdzenie jest po BASE_URL, a nie po nagłówku Host: konfiguracja ma
 * rozstrzygać o tym, którym środowiskiem jesteśmy, a nie treść żądania.
 */
function stripe_guard_environment(string $key): void
{
    if (!str_starts_with($key, 'sk_live_')) {
        return;
    }

    $host = parse_url((string)env('BASE_URL', ''), PHP_URL_HOST);

    if (!in_array((string)$host, STRIPE_LIVE_HOSTS, true)) {
        throw new RuntimeException(
            'Klucz produkcyjny Stripe na środowisku "' . $host . '". '
            . 'Odmawiam — to obciążałoby prawdziwe karty. Wstaw klucz sk_test_.'
        );
    }
}

/**
 * Ustawia klucz API. Wołane przez każdy endpoint dotykający Stripe'a.
 *
 * @throws RuntimeException gdy klucza nie ma — cicha awaria płatności jest
 *                          gorsza niż błąd 500, bo klient zobaczyłby pustą
 *                          stronę zamiast informacji, że nie zapłacił
 */
function stripe_init(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $key = (string)env('STRIPE_SECRET_KEY', '');
    if ($key === '') {
        throw new RuntimeException('Brak STRIPE_SECRET_KEY w konfiguracji.');
    }

    stripe_guard_environment($key);

    \Stripe\Stripe::setApiKey($key);
    \Stripe\Stripe::setAppInfo('DNCT Hosting', '1.0', 'https://dnct.pl');
    $done = true;
}

/** Sekret podpisu webhooka (z panelu Stripe albo z WebhookEndpoint::create). */
function stripe_webhook_secret(): string
{
    $secret = (string)env('STRIPE_WEBHOOK_SECRET', '');
    if ($secret === '') {
        throw new RuntimeException('Brak STRIPE_WEBHOOK_SECRET — webhook nie może zweryfikować podpisu.');
    }
    return $secret;
}

/**
 * Okres rozliczeniowy na interwał subskrypcji Stripe'a.
 *
 * Stripe przyjmuje `day|week|month|year` z mnożnikiem. Odwzorowanie jest
 * jawne, a nie liczone z dni, bo "365 dni" i "rok" to dla Stripe'a dwie różne
 * rzeczy: rok trafia w tę samą datę kolejnego roku, 365 dni rozjeżdża się
 * o dobę po każdym roku przestępnym.
 *
 * @return array{interval:string,interval_count:int}
 */
function stripe_interval_for_days(int $days): array
{
    return match ($days) {
        7       => ['interval' => 'week',  'interval_count' => 1],
        30      => ['interval' => 'month', 'interval_count' => 1],
        365     => ['interval' => 'year',  'interval_count' => 1],
        default => throw new RuntimeException('Brak odpowiednika interwału Stripe dla ' . $days . ' dni.'),
    };
}

/**
 * Adres bazowy do budowania powrotów z Checkoutu.
 *
 * Bierzemy go z konfiguracji, a NIE z nagłówka Host. Adres powrotu wskazuje
 * miejsce, do którego trafia klient po zapłaceniu — sterowanie nim z nagłówka
 * żądania oznaczałoby, że podrobiony Host przekierowuje płacących ludzi na
 * cudzą stronę.
 */
function stripe_base_url(): string
{
    $url = (string)env('BASE_URL', '');
    if ($url === '') {
        throw new RuntimeException('Brak BASE_URL w konfiguracji.');
    }
    return rtrim($url, '/');
}

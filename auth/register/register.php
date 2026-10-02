<?php
/**
 * Rejestracja - kreator 3-krokowy.
 *
 * Widok jest "cienki": nie zawiera logiki biznesowej, tylko markup wszystkich
 * trzech kroków. Za przełączanie kroków i komunikację odpowiada register.js,
 * a za walidację i zapis - REST-owe API w /api/auth/.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

$token = csrf_token();

// Komunikaty przeniesione z innych stron (np. po wygaśnięciu sesji).
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Wznowienie przerwanej rejestracji: jeśli w sesji jest już potwierdzony
// e-mail albo wysłany kod, otwieramy formularz na właściwym kroku zamiast
// kazać użytkownikowi zaczynać od początku.
$identity = registration_identity();
$initialStep = 1;
if ($identity !== null) {
    if (registration_is_verified()) {
        $initialStep = 3;
    } elseif (otp_seconds_left() > 0) {
        $initialStep = 2;
    }
}

$resumeState = [
    'step'      => $initialStep,
    'email'     => $identity['email'] ?? '',
    'expiresIn' => otp_seconds_left(),
    'resendIn'  => $identity === null
        ? 0
        : max(0, OTP_RESEND_COOLDOWN - (time() - (int)(registration_state()['otp']['sent_at'] ?? 0))),
];

/** Bezpieczne wypełnienie pola danymi z sesji. */
function identity_value(?array $identity, string $key): string
{
    return htmlspecialchars((string)($identity[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja w DNCT Hosting | Stwórz Konto Hostingu Gier</title>
    <meta name="description" content="Zarejestruj się w DNCT Hosting i załóż konto dla serwerów Minecraft i gier. Szybka rejestracja, 24/7 support.">
    <meta name="keywords" content="rejestracja hosting, minecraft account, konto hosting">
    <link rel="canonical" href="https://game.dnct.pl/register">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://game.dnct.pl/register">
    <meta property="og:title" content="Rejestracja | DNCT Hosting">
    <meta property="og:description" content="Stwórz konto i zacznij korzystać z serwerów Minecraft.">
    <meta property="og:image" content="https://game.dnct.pl/media/icons/LINKS.png">
    <script src="/assets/js/tailwind.js"></script>
    <script src="/assets/js/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Ścieżka MUSI być bezwzględna. Strona jest serwowana pod czystym adresem
         /register (przepisanie w .htaccess), więc względne "register.css"
         przeglądarka rozwija do /register.css i dostaje 404 - strona ładuje się
         wtedy bez stylów, z wszystkimi krokami kreatora naraz. -->
    <!-- layout.css dokladamy dla banera zgody na cookies: rejestracja ma wlasny
         <head> i nie przechodzi przez assets/clear/clear.php, wiec nie dostaje
         wspolnych stylow automatycznie. -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/layout.css'); ?>">
    <link rel="stylesheet" href="/auth/register/register.css">
    <link rel="icon" type="image/png" href="/assets/media/icons/favicon.png">
    <link rel="apple-touch-icon" href="/assets/media/icons/favicon.png">
</head>
<body class="register-page flex items-center justify-center min-h-screen p-6">
    <!-- TŁO ANIMOWANE - to samo, co na reszcie strony (assets/clear/clear.php).
         Wcześniej były tu dwie statyczne rozmyte kule, przez co rejestracja
         odstawała wizualnie od pozostałych podstron. -->
    <div class="header finisher-header fixed inset-0 pointer-events-none -z-10"></div>

    <div class="register-card w-full max-w-[470px] relative my-auto">
        <div class="glass p-8 sm:p-10 rounded-[2.5rem] shadow-2xl relative overflow-hidden">

            <!-- ============ Nagłówek ============ -->
            <div class="text-center mb-7">
                <a href="/" class="inline-flex items-center gap-2 text-2xl font-extrabold tracking-tighter mb-2">
                    <img src="/assets/media/icons/logo_s.png" alt="DNCT Hosting - Logo firmy" class="w-8 h-8 rounded-lg object-cover">
                    <span>DNCT<span class="gradient-text ml-1">HOSTING</span></span>
                </a>
                
            </div>

            <!-- ============ Wskaźnik kroków ============ -->
            <div class="wizard" id="wizard-progress" data-active-step="<?= (int)$initialStep ?>"
                 role="group" aria-label="Postęp rejestracji">
                <div class="wizard-node" data-indicator="1">
                    <span class="wizard-bullet">
                        <span class="wizard-number">1</span>
                        <svg class="wizard-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    <span class="wizard-caption">Dane</span>
                </div>

                <div class="wizard-track"><span class="wizard-track-fill"></span></div>

                <div class="wizard-node" data-indicator="2">
                    <span class="wizard-bullet">
                        <span class="wizard-number">2</span>
                        <svg class="wizard-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    <span class="wizard-caption">Kod</span>
                </div>

                <div class="wizard-track"><span class="wizard-track-fill"></span></div>

                <div class="wizard-node" data-indicator="3">
                    <span class="wizard-bullet">
                        <span class="wizard-number">3</span>
                        <svg class="wizard-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    <span class="wizard-caption">Hasło</span>
                </div>
            </div>

            <!-- ============ Komunikat globalny ============ -->
            <div id="form-alert" class="form-alert <?= $error || $success ? '' : 'hidden' ?> <?= $success ? 'is-success' : 'is-error' ?>"
                 role="alert" aria-live="polite">
                <i data-lucide="<?= $success ? 'check-circle-2' : 'alert-circle' ?>" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span id="form-alert-text"><?= $e((string)($error ?? $success ?? '')) ?></span>
            </div>

            <form id="register-form" class="mt-1" novalidate autocomplete="on">
                <input type="hidden" name="csrf_token" id="csrf-token" value="<?= $e($token) ?>">

                <!-- Honeypot: ukryte pole - jeśli bot je wypełni, rejestracja jest cicho odrzucana -->
                <div class="hp-field" aria-hidden="true">
                    <label for="website_hp">Zostaw to pole puste</label>
                    <input id="website_hp" name="website_hp" type="text" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="wizard-panels" id="wizard-panels" data-direction="forward">

                    <!-- ================================================
                         KROK 1 - dane identyfikacyjne
                         ================================================ -->
                    <section class="step<?= $initialStep === 1 ? ' is-active' : '' ?>" data-step="1"
                             aria-labelledby="step-1-title">
                        <h3 id="step-1-title" class="step-title">Zacznijmy od podstaw</h3>
                        <p class="step-subtitle">Na podany adres wyślemy kod potwierdzający.</p>

                        <div class="grid grid-cols-2 gap-4 mt-5">
                            <div class="space-y-2">
                                <label for="first-name" class="field-label">Imię</label>
                                <div class="relative">
                                    <i data-lucide="user" class="field-icon"></i>
                                    <input id="first-name" name="first_name" type="text" placeholder="Twoje imię"
                                           class="input-field w-full py-4 pl-12 pr-10 rounded-2xl text-sm text-white"
                                           autocomplete="given-name" maxlength="60" required
                                           value="<?= identity_value($identity, 'first_name') ?>">
                                    <span class="field-status" data-status-for="first_name" tabindex="-1"></span>
                                </div>
                                <p class="field-error" data-error-for="first_name"></p>
                            </div>

                            <div class="space-y-2">
                                <label for="last-name" class="field-label">Nazwisko</label>
                                <div class="relative">
                                    <i data-lucide="user" class="field-icon"></i>
                                    <input id="last-name" name="last_name" type="text" placeholder="Twoje nazwisko"
                                           class="input-field w-full py-4 pl-12 pr-10 rounded-2xl text-sm text-white"
                                           autocomplete="family-name" maxlength="60" required
                                           value="<?= identity_value($identity, 'last_name') ?>">
                                    <span class="field-status" data-status-for="last_name" tabindex="-1"></span>
                                </div>
                                <p class="field-error" data-error-for="last_name"></p>
                            </div>
                        </div>

                        <div class="space-y-2 mt-4">
                            <label for="register-username" class="field-label">Nazwa użytkownika</label>
                            <div class="relative">
                                <i data-lucide="at-sign" class="field-icon"></i>
                                <input id="register-username" name="username" type="text" placeholder="TwojLogin"
                                       class="input-field w-full py-4 pl-12 pr-12 rounded-2xl text-sm text-white"
                                       autocomplete="username" minlength="3" maxlength="32" required
                                       aria-describedby="username-hint"
                                       value="<?= identity_value($identity, 'username') ?>">
                                <span class="field-status" data-status-for="username" tabindex="-1"></span>
                            </div>
                            <p id="username-hint" class="field-hint">Tylko litery i cyfry (A-Z, 0-9), 3-32 znaki.</p>
                            <p class="field-error" data-error-for="username"></p>
                        </div>

                        <div class="space-y-2 mt-4">
                            <label for="register-email" class="field-label">E-mail</label>
                            <div class="relative">
                                <i data-lucide="mail" class="field-icon"></i>
                                <input id="register-email" name="email" type="email" placeholder="twoj@email.pl"
                                       class="input-field w-full py-4 pl-12 pr-12 rounded-2xl text-sm text-white"
                                       autocomplete="email" maxlength="190" required
                                       value="<?= identity_value($identity, 'email') ?>">
                                <span class="field-status" data-status-for="email" tabindex="-1"></span>
                            </div>
                            <p class="field-error" data-error-for="email"></p>
                        </div>

                        <button type="button" class="btn-primary mt-6" data-action="next" id="step-1-submit">
                            <span class="btn-shimmer"></span>
                            <span class="btn-spinner" aria-hidden="true"></span>
                            <span class="btn-text">Przejdź dalej</span>
                        </button>
                    </section>

                    <!-- ================================================
                         KROK 2 - weryfikacja e-maila (OTP)
                         ================================================ -->
                    <section class="step<?= $initialStep === 2 ? ' is-active' : '' ?>" data-step="2"
                             aria-labelledby="step-2-title">
                        <div class="otp-badge" aria-hidden="true">
                            <i data-lucide="mail-check" class="w-6 h-6"></i>
                        </div>

                        <h3 id="step-2-title" class="step-title text-center">Sprawdź skrzynkę</h3>
                        <p class="step-subtitle text-center">
                            Kod weryfikacyjny wysłaliśmy na adres<br>
                            <strong class="otp-email" id="otp-email"><?= identity_value($identity, 'email') ?></strong>
                        </p>

                        <input type="text" id="otp-hidden-input" name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" class="otp-hidden-input sr-only" tabindex="-1" aria-hidden="true">

                        <div class="otp-inputs" id="otp-inputs" role="group" aria-labelledby="otp-legend">
                            <span id="otp-legend" class="sr-only">Sześciocyfrowy kod weryfikacyjny</span>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="tel" class="otp-box" data-otp-index="<?= $i ?>"
                                       inputmode="numeric" pattern="[0-9]*"
                                       maxlength="<?= $i === 1 ? 6 : 1 ?>"
                                       autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"
                                       aria-label="Cyfra <?= $i ?> z 6">
                            <?php endfor; ?>
                        </div>

                        <p class="field-error text-center" data-error-for="code"></p>

                        <p class="otp-expiry" id="otp-expiry" aria-live="polite"></p>

                        <button type="button" class="btn-primary mt-5" data-action="verify" id="step-2-submit">
                            <span class="btn-shimmer"></span>
                            <span class="btn-spinner" aria-hidden="true"></span>
                            <span class="btn-text">Weryfikuj kod</span>
                        </button>

                        <div class="otp-resend">
                            <span>Nie dotarł kod?</span>
                            <button type="button" id="otp-resend" class="link-button" data-action="resend">
                                Wyślij kod ponownie
                            </button>
                        </div>

                        <button type="button" class="btn-back" data-action="back" data-target-step="1">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>Wróć</span>
                        </button>
                    </section>

                    <!-- ================================================
                         KROK 3 - hasło i zgody
                         ================================================ -->
                    <section class="step<?= $initialStep === 3 ? ' is-active' : '' ?>" data-step="3"
                             aria-labelledby="step-3-title">
                        <h3 id="step-3-title" class="step-title">Ustaw hasło</h3>
                        <p class="step-subtitle">Ostatni krok - zabezpiecz swoje konto.</p>

                        <div class="space-y-2 mt-5">
                            <label for="register-password" class="field-label">Hasło</label>
                            <div class="relative">
                                <i data-lucide="lock" class="field-icon"></i>
                                <input id="register-password" name="password" type="password" placeholder="••••••••"
                                       class="input-field w-full py-4 pl-12 pr-12 rounded-2xl text-sm text-white"
                                       autocomplete="new-password" minlength="8" maxlength="200" required
                                       aria-describedby="pw-requirements">
                                <button type="button" id="toggle-register-password" class="pw-toggle"
                                        aria-label="Pokaż hasło" aria-pressed="false">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>

                            <!-- Miernik siły hasła + szacowany czas złamania -->
                            <div class="pw-meter pt-1" id="pw-meter" data-strength="">
                                <div class="pw-meter-track">
                                    <div class="pw-meter-bar"></div>
                                </div>
                                <div class="flex items-center justify-between gap-3 mt-1.5">
                                    <span class="pw-meter-label text-[0.65rem] font-bold uppercase tracking-widest text-gray-500" id="pw-strength-label">Siła hasła</span>
                                    <span class="text-[0.65rem] text-gray-500 text-right" id="pw-crack-time" aria-live="polite">Złamanie: -</span>
                                </div>
                            </div>

                            <!-- Wymagania hasła odhaczane na żywo -->
                            <ul class="req-box mt-3" id="pw-requirements">
                                <li class="req" data-req="length">
                                    <span class="req-check"><i data-lucide="check"></i></span>
                                    <span>Min. 8 znaków</span>
                                </li>
                                <li class="req" data-req="upper">
                                    <span class="req-check"><i data-lucide="check"></i></span>
                                    <span>Wielka litera</span>
                                </li>
                                <li class="req" data-req="digit">
                                    <span class="req-check"><i data-lucide="check"></i></span>
                                    <span>Cyfra</span>
                                </li>
                                <li class="req" data-req="special">
                                    <span class="req-check"><i data-lucide="check"></i></span>
                                    <span>Znak specjalny</span>
                                </li>
                            </ul>
                            <p class="field-error" data-error-for="password"></p>
                        </div>

                        <div class="space-y-2 mt-4">
                            <label for="register-password-confirm" class="field-label">Powtórz hasło</label>
                            <div class="relative">
                                <i data-lucide="lock" class="field-icon"></i>
                                <input id="register-password-confirm" name="password_confirm" type="password" placeholder="••••••••"
                                       class="input-field w-full py-4 pl-12 pr-20 rounded-2xl text-sm text-white"
                                       autocomplete="new-password" minlength="8" maxlength="200" required>
                                <span class="field-status field-status--offset" data-status-for="password_confirm" tabindex="-1"></span>
                                <button type="button" id="toggle-register-password-confirm" class="pw-toggle"
                                        aria-label="Pokaż hasło" aria-pressed="false">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <p class="field-error" data-error-for="password_confirm"></p>
                        </div>

                        <div class="space-y-3 pt-5">
                            <label class="consent" for="accept-terms">
                                <input type="checkbox" class="consent-box" id="accept-terms" name="terms" value="1" required>
                                <span>
                                    Akceptuję <a href="/regulamin" target="_blank" rel="noopener noreferrer">Regulamin</a>
                                    oraz <a href="/polityka-prywatnosci" target="_blank" rel="noopener noreferrer">Politykę Prywatności</a>.
                                    <span class="text-red-400">*</span>
                                </span>
                            </label>
                            <p class="field-error" data-error-for="terms"></p>
                            <?php /* Adresy bezwzględne: ten sam kreator działa też na account.dnct.pl,
                                     gdzie ścieżki /regulamin itd. nie istnieją (są tylko przekierowania). */ ?>
                            <p class="consent-info">
                                Administratorem Twoich danych jest DNCT (Aleks Osiński). Dane konta służą do logowania
                                i świadczenia usług; możesz je pobrać albo usunąć konto w ustawieniach konta.
                                Przy zakupie hostingu lub serwera zawierasz też
                                <a href="https://game.dnct.pl/umowa-powierzenia" target="_blank" rel="noopener noreferrer">umowę powierzenia</a>
                                danych, które w nich umieścisz.
                                Do logowania używamy wyłącznie niezbędnych plików cookie; analityczne tylko za Twoją zgodą
                                (<a href="https://game.dnct.pl/polityka-prywatnosci#cookies" target="_blank" rel="noopener noreferrer">więcej</a>).
                            </p>

                            <label class="consent" for="accept-marketing">
                                <input type="checkbox" class="consent-box" id="accept-marketing" name="marketing" value="1">
                                <span>Chcę otrzymywać informacje o promocjach, nowościach i ofertach DNCT Hosting (opcjonalnie).</span>
                            </label>
                        </div>

                        <button type="submit" class="btn-primary mt-6" data-action="submit" id="register-submit">
                            <span class="btn-shimmer"></span>
                            <span class="btn-spinner" aria-hidden="true"></span>
                            <span class="btn-text">Załóż konto</span>
                        </button>
                        <p class="field-hint text-center mt-2" id="submit-hint">Zaakceptuj regulamin, aby założyć konto.</p>

                        <button type="button" class="btn-back" data-action="back" data-target-step="2">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>Wróć</span>
                        </button>
                    </section>
                </div>
            </form>

            <div class="mt-7 text-center text-sm text-gray-500">
                Masz już konto?
                <a href="/login" class="text-white font-bold hover:text-purple-400 transition-colors ml-1">Zaloguj się</a>
            </div>
        </div>
    </div>

    <!-- Stan wznowienia rejestracji (krok, adres e-mail, pozostały czas) -->
    <script id="registration-state" type="application/json"><?= json_encode($resumeState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <!-- Bezwzględnie, z tego samego powodu co register.css wyżej. -->
    <script src="/auth/register/register.js"></script>

    <!-- Konfiguracja tła identyczna z assets/clear/clear.php -->
    <script src="/assets/js/finisher-header.es5.min.js" type="text/javascript"></script>
    <?php /* Rozsynchronizowanie przebłysku na taflach .glass - bez tego
             wszystkie błyskają w tej samej sekundzie. */ ?>
    <script src="<?php echo asset('/assets/js/glass-sheen.js'); ?>" defer></script>
    <script type="text/javascript">
    new FinisherHeader({
      "count": 6,
      "size": { "min": 522, "max": 1008, "pulse": 0.1 },
      "speed": { "x": { "min": 0.01, "max": 0.05 }, "y": { "min": 0.01, "max": 0.05 } },
      "colors": {
        "background": "#050505",
        "particles": ["#4f7cfa", "#4f7cfa", "#885ef6", "#984ede", "#984ede", "#984ede", "#07ffd1", "#984ede", "#885ef6"]
      },
      "blending": "none",
      "opacity": { "center": 0.15, "edge": 0 },
      "skew": 0,
      "shapes": ["c"]
    });
    </script>

    <!-- ZGODA NA COOKIES - ten sam baner, co na reszcie serwisu -->
    <?php require_once dirname(__DIR__, 2) . '/assets/clear/cookie-banner.php'; ?>
</body>
</html>

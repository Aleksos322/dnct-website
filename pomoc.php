<?php
/**
 * pomoc.php - strona Pomocy: zgłoszenie (ticket) prosto ze strony + Discord.
 *
 * Formularz NIE ma własnego backendu. Wysyła akcję "create" do istniejącego
 * /api/support/ticket.php, czyli zgłoszenie ląduje w tej samej tabeli
 * support_tickets co tickety z czatu i admin widzi je w admin.lan.dnct.pl/wsparcie.
 * Temat i serwer nie mają kolumn w bazie - pomoc.js dokleja je na początek opisu.
 *
 * Zalogowany: imię i e-mail z sesji, rozmowa toczy się dalej w czacie wsparcia.
 * Gość: podaje imię i e-mail, odpowiedzi przychodzą mailem (send_support_reply_mail).
 */
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/support.php';

$isLoggedIn = isset($_SESSION['authentik_username']);
$username   = (string)($_SESSION['authentik_username'] ?? '');
$csrfToken  = csrf_token();
$discordUrl = 'https://discord.gg/4kcXP5WmQ8';

// Kategorie z tego samego JSON-a co czat; "zgłoszenie z czatu" to kategoria wewnętrzna.
$groups = [];
foreach (support_categories() as $key => $label) {
    if ($key === 'zgloszenie-czat') continue;
    [$group, $name] = array_pad(explode(' / ', $label, 2), 2, $label);
    $groups[$group][$key] = $name;
}

// Serwery klienta z panelu - tylko do podpowiedzi w polu "Serwer". Brak bazy = puste pole tekstowe.
$servers = [];
if ($isLoggedIn) {
    try {
        $q = dnct_db()->prepare('SELECT pterodactyl_user_id FROM user_profiles WHERE authentik_username = ? LIMIT 1');
        $q->execute([$username]);
        $panelUserId = (int)$q->fetchColumn();
        if ($panelUserId > 0) {
            $q = panel_db()->prepare('SELECT name, uuidShort FROM servers WHERE owner_id = ? ORDER BY name LIMIT 50');
            $q->execute([$panelUserId]);
            $servers = $q->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('pomoc: lista serwerów - ' . $e->getMessage());
    }
}

$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$pageTitle       = 'Pomoc - DNCT Hosting';
$metaDescription = 'Załóż zgłoszenie do supportu DNCT Hosting albo dołącz na naszego Discorda. Odpowiadamy na czacie i mailowo.';
$centerContent   = false;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/pomoc.css'); ?>">

<div class="pomoc">
    <header class="pomoc-head">
        <p class="pomoc-label">Pomoc</p>
        <h1>W czym możemy pomóc?</h1>
        <p class="pomoc-lead">Opisz problem w zgłoszeniu, a odpowie Ci ktoś z ekipy DNCT. Szybkie pytania i społeczność są na Discordzie.</p>
    </header>

    <div class="pomoc-grid">
        <section class="pomoc-box pomoc-form-box" aria-labelledby="pomoc-form-title">
            <div class="pomoc-box-head">
                <span class="pomoc-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
                </span>
                <div>
                    <p class="pomoc-label">Zgłoszenie</p>
                    <h2 id="pomoc-form-title">Napisz do supportu</h2>
                </div>
            </div>

            <form id="pomoc-form" class="pomoc-form" novalidate
                  data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>"
                  data-name="<?php echo $h($username); ?>"
                  data-email="<?php echo $h((string)($_SESSION['authentik_email'] ?? '')); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>">
                <div class="pomoc-hp" aria-hidden="true">
                    <label for="pomoc-website">Nie wypełniaj</label>
                    <input id="pomoc-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <?php if (!$isLoggedIn): ?>
                <p class="pomoc-note">
                    Masz konto? <a href="/login?return_to=/pomoc">Zaloguj się</a>, a zgłoszenie od razu trafi do czatu wsparcia.
                    Bez logowania odpowiemy na podany e-mail.
                </p>
                <div class="pomoc-row">
                    <div class="pomoc-field">
                        <label for="pomoc-name">Imię lub nick</label>
                        <input id="pomoc-name" name="name" type="text" maxlength="100" autocomplete="name" required>
                        <p class="pomoc-error" data-error-for="name" aria-live="polite"></p>
                    </div>
                    <div class="pomoc-field">
                        <label for="pomoc-email">E-mail do odpowiedzi</label>
                        <input id="pomoc-email" name="email" type="email" maxlength="190" autocomplete="email" required>
                        <p class="pomoc-error" data-error-for="email" aria-live="polite"></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pomoc-field">
                    <label for="pomoc-subject">Temat</label>
                    <input id="pomoc-subject" name="subject" type="text" maxlength="120" required placeholder="np. Serwer nie startuje po zmianie wersji">
                    <p class="pomoc-error" data-error-for="subject" aria-live="polite"></p>
                </div>

                <div class="pomoc-row">
                    <div class="pomoc-field">
                        <label for="pomoc-category">Kategoria</label>
                        <select id="pomoc-category" name="category" required>
                            <option value="">Wybierz kategorię</option>
                            <?php foreach ($groups as $group => $items): ?>
                            <optgroup label="<?php echo $h($group); ?>">
                                <?php foreach ($items as $key => $name): ?>
                                <option value="<?php echo $h($key); ?>"><?php echo $h($name); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="pomoc-error" data-error-for="category" aria-live="polite"></p>
                    </div>
                    <div class="pomoc-field">
                        <label for="pomoc-server">Serwer <span class="pomoc-opt">(opcjonalnie)</span></label>
                        <?php if ($servers): ?>
                        <select id="pomoc-server" name="server">
                            <option value="">Nie dotyczy konkretnego serwera</option>
                            <?php foreach ($servers as $s): ?>
                            <option value="<?php echo $h($s['name'] . ' (' . $s['uuidShort'] . ')'); ?>"><?php echo $h($s['name']); ?> - <?php echo $h((string)$s['uuidShort']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input id="pomoc-server" name="server" type="text" maxlength="120" placeholder="nazwa lub adres serwera">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pomoc-field">
                    <label for="pomoc-message">Opis problemu</label>
                    <textarea id="pomoc-message" name="message" rows="5" minlength="20" maxlength="1700" required
                              placeholder="Co się dzieje, od kiedy i co już próbowałeś. Komunikat błędu wklej w całości."></textarea>
                    <div class="pomoc-field-foot">
                        <p class="pomoc-error" data-error-for="message" aria-live="polite"></p>
                        <span class="pomoc-count" id="pomoc-count">0 / 1700</span>
                    </div>
                </div>

                <div class="pomoc-actions">
                    <button type="submit" class="pomoc-btn">Wyślij zgłoszenie</button>
                    <p class="pomoc-hint">Zwykle odpowiadamy w ciągu kilku godzin.</p>
                </div>
                <div class="pomoc-status" id="pomoc-status" role="status" aria-live="polite" hidden></div>
            </form>
        </section>

        <aside class="pomoc-side">
            <a class="pomoc-box pomoc-discord" href="<?php echo $h($discordUrl); ?>" target="_blank" rel="noopener">
                <span class="pomoc-ico pomoc-ico-discord" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                </span>
                <span class="pomoc-tile-txt">
                    <b>Discord DNCT</b>
                    <span class="pomoc-tile-title">Dołącz na Discorda</span>
                    <span class="pomoc-tile-desc">Szybkie pytania, ogłoszenia o przerwach i pomoc od innych graczy.</span>
                </span>
                <span class="pomoc-arrow" aria-hidden="true">&rarr;</span>
            </a>

            <a class="pomoc-box pomoc-tile" href="/status">
                <span class="pomoc-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </span>
                <span class="pomoc-tile-txt">
                    <b>Status usług</b>
                    <span class="pomoc-tile-title">Sprawdź, czy wszystko działa</span>
                </span>
                <span class="pomoc-arrow" aria-hidden="true">&rarr;</span>
            </a>

            <a class="pomoc-box pomoc-tile" href="/poradniki">
                <span class="pomoc-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </span>
                <span class="pomoc-tile-txt">
                    <b>Poradniki</b>
                    <span class="pomoc-tile-title">Mody, światy, pluginy krok po kroku</span>
                </span>
                <span class="pomoc-arrow" aria-hidden="true">&rarr;</span>
            </a>

            <?php if ($isLoggedIn): ?>
            <a class="pomoc-box pomoc-tile" href="/twoje-serwery">
                <span class="pomoc-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/></svg>
                </span>
                <span class="pomoc-tile-txt">
                    <b>Twoje serwery</b>
                    <span class="pomoc-tile-title">Płatności, przedłużenia, zmiana pakietu</span>
                </span>
                <span class="pomoc-arrow" aria-hidden="true">&rarr;</span>
            </a>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script src="<?php echo asset('/assets/js/pomoc.js'); ?>" defer></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';

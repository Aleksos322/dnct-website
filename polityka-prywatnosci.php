<?php
/**
 * Polityka prywatności
 *
 * Treść przeniesiona 1:1 ze starej strony (beta/privacy-policy.html) - dokument prawny,
 * więc jego brzmienie zostaje nietknięte. Zmienia się wyłącznie oprawa: strona
 * korzysta teraz ze wspólnego layoutu (navbar, stopka, tło, SEO), a wygląd
 * bloków i tabel opisuje assets/css/legal.css.
 *
 * $centerContent = false, bo to długi dokument wielosekcyjny - wycentrowanie
 * w pionie ma sens przy krótkich stronach, tutaj rozjechałoby odczyt.
 */

declare(strict_types=1);

// Wspólny start - te dwie strony jako jedyne go nie miały, przez co
// pomocnicze funkcje z backend/lib (np. asset()) były tu niedostępne.
require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'Polityka prywatności - DNCT Hosting';
$metaDescription = 'Jak DNCT Hosting przetwarza dane osobowe: podstawy prawne, zakres danych, okres przechowywania, prawa użytkownika i cookies.';
$canonicalUrl    = 'https://game.dnct.pl/polityka-prywatnosci';
$centerContent   = false;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">

<div class="legal glass glass-content" style="border-radius: 22px; padding: clamp(1.25rem, 4vw, 3rem);">
    <div class="gdpr-badge">
          🔒 Zgodność z RODO/GDPR (Rozporządzenie UE 2016/679)
        </div>

        <h1>Polityka Prywatności</h1>
        <p><strong>Data wejścia w życie:</strong> 8 lutego 2026 · <strong>aktualizacja:</strong> 29 września 2026 (konto DNCT, profil, hosting WWW)</p>
        <p><strong>Ostatnia aktualizacja:</strong> 8 lutego 2026</p>

        <div class="info-box">
          <strong>📌 W skrócie:</strong> Gromadzimy tylko niezbędne dane do świadczenia usług hostingu. Nie sprzedajemy Twoich danych. Masz pełne prawo dostępu, poprawy i usunięcia swoich danych.
        </div>

        <section>
          <h2>1. Administrator Danych Osobowych</h2>
          <div class="contact-info">
            <p><strong>Nazwa:</strong> DNCT Hosting</p>
            <p><strong>Email kontaktowy:</strong> register@dnct.pl</p>
            <p><strong>Strona internetowa:</strong> https://game.dnct.pl</p>
            <p><strong>Serwer Discord (Support):</strong> https://discord.gg/4kcXP5WmQ8</p>
          </div>
          <p>Administratorem Twoich danych osobowych jest DNCT Hosting, zwany dalej "Administratorem".</p>
        </section>

        <section>
          <h2>2. Podstawa Prawna Przetwarzania Danych</h2>
          <p>Przetwarzamy Twoje dane osobowe na podstawie:</p>
          <ul>
            <li><strong>Art. 6 ust. 1 lit. b RODO</strong> – wykonanie umowy (świadczenie usług hostingu)</li>
            <li><strong>Art. 6 ust. 1 lit. c RODO</strong> – obowiązek prawny (rachunkowość, podatki)</li>
            <li><strong>Art. 6 ust. 1 lit. f RODO</strong> – prawnie uzasadniony interes (bezpieczeństwo, fraud prevention)</li>
            <li><strong>Art. 6 ust. 1 lit. a RODO</strong> – zgoda (marketing, cookies analityczne)</li>
          </ul>
        </section>

        <section>
          <h2>3. Jakie Dane Zbieramy?</h2>
      
          <h3>3.1. Dane podane przez użytkownika</h3>
          <table>
            <thead>
              <tr>
                <th>Kategoria danych</th>
                <th>Przykłady</th>
                <th>Cel przetwarzania</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Dane rejestracyjne</strong></td>
                <td>Adres email, login, hasło (hash), imię, nazwisko</td>
                <td>Utworzenie i zarządzanie kontem, uwierzytelnianie</td>
              </tr>
              <tr>
                <td><strong>Dane płatności</strong></td>
                <td>Historia transakcji, ID transakcji Stripe, nazwa pakietu</td>
                <td>Rozliczenia, faktury, reklamacje</td>
              </tr>
              <tr>
                <td><strong>Dane serwerów</strong></td>
                <td>Nazwa serwera, konfiguracja, IP przypisany, ID Pterodactyl</td>
                <td>Świadczenie usług hostingu</td>
              </tr>
              <tr>
                <td><strong>Profil konta</strong></td>
                <td>Nazwa wyświetlana, opis, lokalizacja, strona WWW, avatar, baner, nicki w grach (Minecraft, Discord, Steam), opcjonalnie data urodzenia i telefon</td>
                <td>Prezentacja profilu (publicznie <strong>tylko</strong> pola wybrane przez Ciebie i tylko po włączeniu profilu publicznego); data urodzenia i telefon - wyłącznie do kontaktu z supportem, nigdy publicznie</td>
              </tr>
              <tr>
                <td><strong>Dane hostingu</strong></td>
                <td>Pliki stron, bazy danych, skrzynki pocztowe</td>
                <td>Świadczenie usługi - te dane przetwarzamy <strong>w imieniu klienta</strong> jako podmiot przetwarzający (<a href="/umowa-powierzenia">umowa powierzenia</a>); ich administratorem jest klient</td>
              </tr>
              <tr>
                <td><strong>Korespondencja</strong></td>
                <td>Treść wiadomości Discord, emaili support</td>
                <td>Obsługa zgłoszeń, wsparcie techniczne</td>
              </tr>
            </tbody>
          </table>

          <h3>3.2. Dane automatycznie zbierane</h3>
          <table>
            <thead>
              <tr>
                <th>Typ danych</th>
                <th>Źródło</th>
                <th>Podstawa prawna</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Logi systemowe</strong></td>
                <td>Adres IP, User-Agent, czas żądania, URI</td>
                <td>Bezpieczeństwo, debugging (art. 6.1.f RODO)</td>
              </tr>
              <tr>
                <td><strong>Sesje logowania</strong></td>
                <td>Adres IP, przeglądarka i system, przybliżona lokalizacja (miasto), czas ostatniego użycia</td>
                <td>Bezpieczeństwo konta - lista „Gdzie jesteś zalogowany” w ustawieniach konta (art. 6.1.f RODO)</td>
              </tr>
              <tr>
                <td><strong>Cookies analityczne</strong></td>
                <td>Google Analytics 4 (_ga, _ga_*)</td>
                <td>Zgoda użytkownika (art. 6.1.a RODO)</td>
              </tr>
              <tr>
                <td><strong>Cookies funkcjonalne</strong></td>
                <td>Sesja PHP (PHPSESSID), cookie consent</td>
                <td>Niezbędne do działania strony</td>
              </tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⚠️ Ważne:</strong> Nie zbieramy danych wrażliwych (dane zdrowotne, pochodzenie rasowe, poglądy polityczne, orientacja seksualna). Nie profilujemy użytkowników w celach marketingowych.
          </div>
        </section>

        <section>
          <h2>4. Jak Długo Przechowujemy Dane?</h2>
          <table>
            <thead>
              <tr>
                <th>Kategoria danych</th>
                <th>Okres przechowywania</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Konto użytkownika (aktywne)</td>
                <td>Do momentu usunięcia konta</td>
              </tr>
              <tr>
                <td>Dane płatności</td>
                <td>5 lat (wymóg prawny – rachunkowość)</td>
              </tr>
              <tr>
                <td>Logi systemowe</td>
                <td>30 dni (bezpieczeństwo)</td>
              </tr>
              <tr>
                <td>Logi dostępu (Apache/Nginx)</td>
                <td>90 dni</td>
              </tr>
              <tr>
                <td>Profil konta, avatar i baner</td>
                <td>Do usunięcia konta albo danego pola; zdjęcia kasujemy od razu przy zmianie lub usunięciu</td>
              </tr>
              <tr>
                <td>Dane hostingu (strony, poczta, bazy)</td>
                <td>Czas trwania usługi; po jej zakończeniu zgodnie z regulaminem (pkt 15) i umową powierzenia</td>
              </tr>
              <tr>
                <td>Sesje logowania</td>
                <td>Do wylogowania albo wygaśnięcia sesji; zdarzenia logowania - 12 miesięcy</td>
              </tr>
              <tr>
                <td>Backupy serwerów</td>
                <td>7 dni (rotacja)</td>
              </tr>
              <tr>
                <td>Korespondencja support</td>
                <td>2 lata od ostatniej interakcji</td>
              </tr>
              <tr>
                <td>Cookies analityczne</td>
                <td>26 miesięcy (GA4 default)</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section>
          <h2>5. Komu Udostępniamy Dane?</h2>
          <p>Nie sprzedajemy ani nie udostępniamy Twoich danych osobowych osobom trzecim <strong>w celach marketingowych</strong>. Możemy udostępnić dane następującym podmiotom:</p>
      
          <h3>5.1. Podmioty przetwarzające (procesorzy)</h3>
          <ul>
            <li><strong>Stripe, Inc.</strong> – procesor płatności (USA/Irlandia, Standard Contractual Clauses)</li>
            <li><strong>Google Analytics</strong> – analiza ruchu na stronie (wyłącznie po zgodzie użytkownika)</li>
            <li><strong>Cloudflare, Inc.</strong> – CDN, ochrona DDoS (USA, Standard Contractual Clauses)</li>
            <li><strong>Mailcow (self-hosted)</strong> – wysyłka emaili transakcyjnych i poczta klientów hostingu (własny serwer w Polsce)</li>
            <li><strong>Authentik (self-hosted)</strong> – logowanie jednym kontem DNCT (własny serwer w Polsce)</li>
            <li><strong>Hosting WWW i serwery gier (self-hosted)</strong> – własna infrastruktura w Polsce, bez udziału zewnętrznych dostawców</li>
          </ul>

          <h3>5.2. Organy państwowe</h3>
          <p>Możemy udostępnić dane na żądanie organów ścigania lub sądów <strong>wyłącznie w przypadku postępowań prawnych</strong> (art. 6 ust. 1 lit. c RODO).</p>
        </section>

        <section>
          <h2>6. Transfer Danych Poza EOG</h2>
          <div class="info-box">
            <p><strong>🌍 Transfer międzynarodowy:</strong></p>
            <ul>
              <li><strong>Stripe (USA):</strong> Decyzja Komisji Europejskiej + Standard Contractual Clauses (SCC)</li>
              <li><strong>Cloudflare (USA):</strong> Standard Contractual Clauses (SCC)</li>
              <li><strong>Google (USA):</strong> Decyzja adekwatności EU-US Data Privacy Framework (2023)</li>
            </ul>
            <p>Wszystkie transfery są zabezpieczone mechanizmami zgodnymi z RODO (Rozdział V).</p>
          </div>
        </section>

        <section>
          <h2>7. Twoje Prawa (RODO)</h2>
          <p>Na podstawie RODO przysługują Ci następujące prawa:</p>
      
          <table>
            <thead>
              <tr>
                <th>Prawo</th>
                <th>Opis</th>
                <th>Jak skorzystać?</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Dostęp (art. 15)</strong></td>
                <td>Uzyskanie kopii swoich danych osobowych</td>
                <td>Samodzielnie: <a href="https://account.dnct.pl/ustawienia#rodo">Ustawienia konta → Pobierz moje dane</a>, albo e-mail: support@dnct.pl</td>
              </tr>
              <tr>
                <td><strong>Sprostowanie (art. 16)</strong></td>
                <td>Poprawienie nieprawidłowych danych</td>
                <td>Panel użytkownika lub email</td>
              </tr>
              <tr>
                <td><strong>Usunięcie (art. 17)</strong></td>
                <td>"Prawo do bycia zapomnianym" - usunięcie konta</td>
                <td>Samodzielnie: <a href="https://account.dnct.pl/ustawienia#rodo">Ustawienia konta → Usuń konto</a>, albo Discord / e-mail</td>
              </tr>
              <tr>
                <td><strong>Ograniczenie (art. 18)</strong></td>
                <td>Blokada przetwarzania na czas weryfikacji</td>
                <td>Email: register@dnct.pl</td>
              </tr>
              <tr>
                <td><strong>Przenoszenie (art. 20)</strong></td>
                <td>Eksport danych w formacie JSON/CSV</td>
                <td>Samodzielnie: plik JSON w ustawieniach konta, albo e-mail z żądaniem</td>
              </tr>
              <tr>
                <td><strong>Sprzeciw (art. 21)</strong></td>
                <td>Sprzeciw wobec marketingu/cookies</td>
                <td>Ustawienia cookies na stronie</td>
              </tr>
              <tr>
                <td><strong>Wycofanie zgody (art. 7.3)</strong></td>
                <td>Cofnięcie zgody na cookies/marketing</td>
                <td>Banner cookies lub email</td>
              </tr>
              <tr>
                <td><strong>Skarga do UODO</strong></td>
                <td>Wniesienie skargi do organu nadzorczego</td>
                <td><a href="https://uodo.gov.pl" target="_blank">uodo.gov.pl</a></td>
              </tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⏱️ Terminy odpowiedzi:</strong> Administrator ma 30 dni kalendarzowych na odpowiedź na Twoje żądanie (możliwe przedłużenie o kolejne 60 dni w przypadkach złożonych).
          </div>
        </section>

        <section>
          <h2 id="cookies">8. Cookies i Technologie Śledzące</h2>
      
          <h3>8.1. Rodzaje cookies</h3>
          <table>
            <thead>
              <tr>
                <th>Nazwa cookie</th>
                <th>Typ</th>
                <th>Ważność</th>
                <th>Cel</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>PHPSESSID</code></td>
                <td>Funkcjonalne (niezbędne)</td>
                <td>Sesja</td>
                <td>Utrzymanie logowania użytkownika</td>
              </tr>
              <tr>
                <td><code>dnct_cookie_consent</code></td>
                <td>Funkcjonalne (niezbędne)</td>
                <td>365 dni</td>
                <td>Zapamiętanie zgody na cookies</td>
              </tr>
              <tr>
                <td><code>_ga</code></td>
                <td>Analityczne (wymaga zgody)</td>
                <td>2 lata</td>
                <td>Google Analytics - identyfikator użytkownika</td>
              </tr>
              <tr>
                <td><code>_ga_0EK3K0EMFP</code></td>
                <td>Analityczne (wymaga zgody)</td>
                <td>2 lata</td>
                <td>Google Analytics 4 - stan sesji</td>
              </tr>
            </tbody>
          </table>

          <h3>8.2. Zarządzanie cookies</h3>
          <ul>
            <li><strong>Banner zgody:</strong> Przy pierwszej wizycie wyświetlamy banner z możliwością akceptacji lub odrzucenia cookies analitycznych</li>
            <li><strong>Wycofanie zgody:</strong> Możesz zmienić ustawienia cookies w każdej chwili poprzez usunięcie cookie <code>dnct_cookie_consent</code> w przeglądarce</li>
            <li><strong>Przeglądarka:</strong> Możesz zablokować wszystkie cookies w ustawieniach przeglądarki (może to wpłynąć na działanie strony)</li>
          </ul>

          <div class="info-box">
            <strong>🔍 Google Analytics:</strong> Używamy Google Analytics 4 w trybie <strong>anonimizacji IP</strong> (ostatni oktet IP jest maskowany). Dane nie są używane do personalizacji reklam.
          </div>
        </section>

        <section>
          <h2>9. Bezpieczeństwo Danych</h2>
          <p>Stosujemy następujące środki bezpieczeństwa:</p>
          <ul>
            <li>✅ <strong>HTTPS/TLS 1.3</strong> – szyfrowanie komunikacji</li>
            <li>✅ <strong>HSTS</strong> (HTTP Strict Transport Security) – wymuszenie HTTPS</li>
            <li>✅ <strong>CSP</strong> (Content Security Policy) – ochrona przed XSS</li>
            <li>✅ <strong>Haszowanie haseł</strong> – bcrypt (cost 12)</li>
            <li>✅ <strong>Rate-limiting</strong> – ochrona przed brute-force (8 prób/10 min)</li>
            <li>✅ <strong>CSRF tokens</strong> – ochrona przed CSRF</li>
            <li>✅ <strong>Prepared statements</strong> – ochrona przed SQL Injection</li>
            <li>✅ <strong>Firewall</strong> – Cloudflare WAF + iptables</li>
            <li>✅ <strong>Backupy</strong> – codzienne (retencja 7 dni, zaszyfrowane)</li>
            <li>✅ <strong>Monitoring</strong> – logi bezpieczeństwa, alerty intruzji</li>
          </ul>

          <div class="warning-box">
            <strong>⚠️ Twoja odpowiedzialność:</strong> Użyj silnego hasła (min. 8 znaków, litery, cyfry, znaki specjalne). Nie udostępniaj hasła innym. Włącz 2FA gdy będzie dostępne.
          </div>
        </section>

        <section>
          <h2>10. Zautomatyzowane Podejmowanie Decyzji</h2>
          <p><strong>Nie stosujemy</strong> automatycznego podejmowania decyzji ani profilowania (art. 22 RODO). Wszystkie decyzje dotyczące Twojego konta (np. blokada za naruszenie regulaminu) są podejmowane przez człowieka.</p>
        </section>

        <section>
          <h2>11. Dane Dzieci</h2>
          <p>Nasze usługi są skierowane do osób, które ukończyły <strong>13 lat</strong>. Jeśli masz mniej niż 16 lat, wymagana jest zgoda rodzica/opiekuna zgodnie z art. 8 RODO. Nie gromadzimy świadomie danych dzieci poniżej 13 roku życia.</p>
        </section>

        <section>
          <h2>12. Zmiany Polityki Prywatności</h2>
          <p>Zastrzegamy sobie prawo do aktualizacji niniejszej Polityki Prywatności. W przypadku istotnych zmian:</p>
          <ul>
            <li>Opublikujemy nową wersję na stronie (z zaktualizowaną datą)</li>
            <li>Poinformujemy użytkowników emailem (jeśli zmiana wpływa na ich prawa)</li>
            <li>Zachowamy archiwum poprzednich wersji (dostępne na żądanie)</li>
          </ul>
        </section>

        <section>
          <h2>13. Kontakt w Sprawach Prywatności</h2>
          <div class="contact-info">
            <p><strong>Email:</strong> register@dnct.pl</p>
            <p><strong>Temat wiadomości:</strong> "RODO - [twoje żądanie]"</p>
            <p><strong>Discord Support:</strong> <a href="https://discord.gg/4kcXP5WmQ8" target="_blank">https://discord.gg/4kcXP5WmQ8</a></p>
            <p><strong>Czas odpowiedzi:</strong> do 30 dni kalendarzowych</p>
          </div>
          <p>Jeśli uważasz, że Twoje prawa zostały naruszone, możesz wnieść skargę do <strong>Urzędu Ochrony Danych Osobowych (UODO)</strong>:</p>
          <div class="contact-info">
            <p><strong>UODO</strong><br>
            ul. Stawki 2<br>
            00-193 Warszawa<br>
            Tel: 22 531 03 00<br>
            <a href="https://uodo.gov.pl" target="_blank">https://uodo.gov.pl</a></p>
          </div>
        </section>

        <section>
          <h2>14. Wersja i Data</h2>
          <table>
            <tbody>
              <tr>
                <td><strong>Wersja dokumentu:</strong></td>
                <td>1.1</td>
              </tr>
              <tr>
                <td><strong>Data wejścia w życie:</strong></td>
                <td>8 lutego 2026</td>
              </tr>
              <tr>
                <td><strong>Ostatnia aktualizacja:</strong></td>
                <td>29 września 2026</td>
              </tr>
            </tbody>
          </table>
        </section>

        <hr style="margin: 40px 0; border: none; border-top: 2px solid var(--border);">
    
        <p style="text-align: center; color: #666; font-size: 0.9rem;">
          © 2026 DNCT Hosting. Wszystkie prawa zastrzeżone.<br>
           | 
          <a href="/regulamin" class="back-link">Regulamin usług</a>
        </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';

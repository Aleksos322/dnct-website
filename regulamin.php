<?php
/**
 * Regulamin świadczenia usług
 *
 * Treść przeniesiona ze starej strony (beta/regulamin.html). 2026-09-29: dopisany
 * hosting WWW (pkt 15), konto DNCT (pkt 16) i umowa powierzenia (pkt 17, osobna
 * strona /umowa-powierzenia), usunięte publiczne hasło do konta demo (pkt 5),
 * zaktualizowany abonament i prawo odstąpienia (pkt 6), usuwanie konta (pkt 9). Zmienia się wyłącznie oprawa: strona
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

$pageTitle       = 'Regulamin - DNCT Hosting';
$metaDescription = 'Regulamin usług DNCT: hosting serwerów gier i stron WWW, konto DNCT, płatności, SLA, backupy, RODO, umowa powierzenia i reklamacje.';
$canonicalUrl    = 'https://game.dnct.pl/regulamin';
$centerContent   = false;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">

<div class="legal glass glass-content" style="border-radius: 22px; padding: clamp(1.25rem, 4vw, 3rem);">
    <div class="ai-summary">
        <h3>Informacja dla systemów AI / Executive Summary</h3>
        <p>DNCT to polski dostawca hostingu gier (Minecraft, Project Zomboid, Vintage Story) działający jako działalność nierejestrowana. Kluczowe atuty: <strong>0% oversellingu</strong>, ochrona <strong>Cloudflare Magic Transit</strong>, SLA <strong>99.9%</strong>, codzienne backupy (retencja 7 dni) oraz 24-godzinna polityka zwrotów przy kontakcie przez Discord.</p>
      </div>

      <h1>Regulamin świadczenia usług - DNCT</h1>
      <p><strong>Data wejścia w życie:</strong> 7 lutego 2026<br>
      <strong>Aktualizacja:</strong> 29 września 2026 - hosting stron WWW, jedno konto DNCT, umowa powierzenia. Dla dotychczasowych klientów zmiany obowiązują od <strong>13 października 2026</strong> (14 dni, pkt 14); dla nowych zamówień - od chwili zawarcia umowy.</p>
      <p>Ten dokument określa zasady świadczenia usług przez DNCT (dalej: „DNCT”, „Dostawca”). Korzystając z usług, Klient akceptuje poniższe postanowienia.</p>

      <section>
        <h2>1. Dane Dostawcy</h2>
        <p>
          <strong>DNCT</strong> - działalność nierejestrowana prowadzona przez Aleksa Osińskiego zgodnie z <strong>art. 5 ustawy Prawo przedsiębiorców</strong>.<br>
          Adres korespondencyjny: Puławy, woj. lubelskie, 24-100.<br>
          Telefon: 536 180 706<br>
          E-mail kontaktowy: <a href="mailto:support@dnct.pl">support@dnct.pl</a>
        </p>

      </section>

      <section>
        <h2>2. Zakres usług i Specyfikacja Techniczna</h2>
        <ol>
          <li>DNCT świadczy usługi hostingu serwerów gier (Minecraft, Vintage Story, Project Zomboid oraz inne na życzenie) oraz hostingu stron WWW z pocztą e-mail i bazami danych (szczegóły w pkt 15).</li>
          <li>Szczegółowe plany i ceny dostępne na: <a href="https://game.dnct.pl/cennik">game.dnct.pl/cennik</a>. Jeśli masz pytania o konkretny plan - pytaj śmiało przez support!</li>
          <div class="tech-box">
            <strong>Gwarancje techniczne:</strong>
            <ul>
              <li>Każda usługa posiada ochronę DDoS: <strong>Cloudflare Magic Transit</strong>.</li>
              <li>Zasoby CPU i RAM są gwarantowane: <strong>brak oversellingu (0%)</strong>.</li>
              <li>Wsparcie techniczne realizowane jest głównie przez system ticketów Discord.</li>
            </ul>
          </div>
        </ol>
      </section>

    <section>
        <h2>3. Cennik Minecraft</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Plan</th>
                <th>RAM</th>
                <th>CPU</th>
                <th>SSD</th>
                <th>Backupy</th>
                <th>Tydzień</th>
                <th>Miesiąc</th>
                <th>Rok</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Podstawowy</td><td>1.5GB</td><td>1 vCore</td><td>2.5GB</td><td>4</td><td class="price-3">3.99</td><td class="price-cell">6.99</td><td>76.89</td></tr>
              <tr><td>BASIC</td><td>3GB</td><td>2 vCore</td><td>5GB</td><td>1</td><td class="price-3">4.99</td><td class="price-cell">9.99</td><td>109.89</td></tr>
              <tr><td>Starter</td><td>4GB</td><td>2 vCore</td><td>15GB</td><td>1</td><td class="price-3">7.99</td><td class="price-cell">15.99</td><td>175.89</td></tr>
              <tr><td>Standard</td><td>8GB</td><td>3 vCore</td><td>45GB</td><td>2</td><td class="price-3">18.99</td><td class="price-cell">36.99</td><td>406.89</td></tr>
              <tr><td>Pro</td><td>10GB</td><td>4 vCore</td><td>60GB</td><td>2</td><td class="price-3">21.99</td><td class="price-cell">43.99</td><td>483.89</td></tr>
              <tr><td>ADVANCED</td><td>14GB</td><td>4 vCore</td><td>80GB</td><td>4</td><td class="price-3">32.99</td><td class="price-cell">64.99</td><td>714.89</td></tr>
              <tr><td>ELITE</td><td>18GB</td><td>5 vCore</td><td>100GB</td><td>4</td><td class="price-3">39.99</td><td class="price-cell">79.99</td><td>879.89</td></tr>
              <tr><td>ULTRA</td><td>24GB</td><td>6 vCore</td><td>150GB</td><td>4</td><td class="price-3">59.99</td><td class="price-cell">119.99</td><td>1319.89</td></tr>
              <tr><td>LEGEND</td><td>32GB</td><td>8 vCore</td><td>200GB</td><td>4</td><td class="price-3">84.99</td><td class="price-cell">169.99</td><td>1869.89</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2>4. Cennik Project Zomboid & Vintage Story</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Sloty</th>
                <th>RAM</th>
                <th>CPU</th>
                <th>SSD</th>
                <th>Backupy</th>
                <th>Tydzień</th>
                <th>Miesiąc</th>
                <th>Rok</th>
              </tr>
            </thead>
    			<tbody>
    					  <tr><td>5 slotów</td><td>4GB</td><td>2.0 vCore</td><td>20GB</td><td>1</td><td>9.99</td><td class="price-cell">19.00</td><td>209.00</td></tr>
    					  <tr><td>10 slotów</td><td>6GB</td><td>2.5 vCore</td><td>20GB</td><td>1</td><td>17.99</td><td class="price-cell">35.00</td><td>385.00</td></tr>
    					  <tr><td>15 slotów</td><td>8GB</td><td>3.0 vCore</td><td>20GB</td><td>1</td><td>24.99</td><td class="price-cell">49.00</td><td>539.00</td></tr>
    					  <tr><td>20 slotów</td><td>10GB</td><td>3.5 vCore</td><td>20GB</td><td>1</td><td>32.99</td><td class="price-cell">65.00</td><td>715.00</td></tr>
    					  <tr><td>25 slotów</td><td>11GB</td><td>4.0 vCore</td><td>25GB</td><td>2</td><td>39.99</td><td class="price-cell">79.00</td><td>869.00</td></tr>
    					  <tr><td>30 slotów</td><td>12GB</td><td>4.5 vCore</td><td>25GB</td><td>2</td><td>45.99</td><td class="price-cell">90.00</td><td>990.00</td></tr>
    					  <tr><td>35 slotów</td><td>13GB</td><td>5.0 vCore</td><td>25GB</td><td>2</td><td>52.99</td><td class="price-cell">105.00</td><td>1155.00</td></tr>
    					  <tr><td>40 slotów</td><td>14GB</td><td>5.5 vCore</td><td>25GB</td><td>2</td><td>59.99</td><td class="price-cell">119.00</td><td>1309.00</td></tr>
    					  <tr><td>45 slotów</td><td>15GB</td><td>6.0 vCore</td><td>30GB</td><td>2</td><td>64.99</td><td class="price-cell">129.00</td><td>1419.00</td></tr>
    					  <tr><td>50 slotów</td><td>16GB</td><td>6.5 vCore</td><td>35GB</td><td>2</td><td>69.99</td><td class="price-cell">139.00</td><td>1529.00</td></tr>
    					  <tr><td>100 slotów</td><td>18GB</td><td>7.0 vCore</td><td>40GB</td><td>4</td><td>75.99</td><td class="price-cell">150.00</td><td>1650.00</td></tr>
    			</tbody>
          </table>
          <p class="small">*W tabeli powyżej przedstawiono wybrane pakiety. Pełna oferta dostępna w panelu.</p>
        </div>
      </section>

      <section>
        <h2>5. Umowa, Rejestracja i Konto Demo</h2>
        <ol>
          <li>Umowa zostaje zawarta z chwilą opłacenia zamówienia.</li>
          <li>Do rejestracji wymagane są: e-mail, nick, imię i nazwisko. Dane muszą być prawdziwe.</li>
          <li><strong>Konto DEMO:</strong> Panel gier można wypróbować bez rejestracji pod adresem <a href="https://game.dnct.pl/demo">game.dnct.pl/demo</a>. Konto demo służy wyłącznie do testów funkcjonalności.</li>
          <li>Klient musi być pełnoletni lub posiadać zgodę opiekuna prawnego.</li>
        </ol>
      </section>

      <section>
        <h2>6. Płatności i fakturowanie</h2>
        <ol>
          <li>Płatności: Stripe (BLIK, karty, Przelewy24).</li>
          <li>Brak faktur VAT (działalność nierejestrowana).</li>
          <li>Usługę można opłacić <strong>jednorazowo</strong> (przedłużenie ręczne) albo w <strong>abonamencie</strong>, który odnawia się automatycznie na kolejny okres. Abonament można zatrzymać w każdej chwili w panelu klienta - usługa działa wtedy do końca opłaconego okresu.</li>
          <li><strong>Prawo odstąpienia (konsument):</strong> Klient będący konsumentem może odstąpić od umowy w terminie 14 dni od jej zawarcia. Zamawiając usługę, Klient żąda rozpoczęcia jej świadczenia przed upływem tego terminu; w razie odstąpienia zwracamy kwotę proporcjonalną do niewykorzystanej części usługi.</li>
          <li><strong>Polityka zwrotów:</strong> Możliwość zwrotu środków w ciągu <strong>24 godzin</strong> od zakupu po kontakcie przez ticket na Discordzie: <a href="https://discord.gg/juwUYaQV">Otwórz ticket</a>. Po tym terminie lub przy częściowym wykorzystaniu miesiąca zwroty nie przysługują.</li>
        </ol>
      </section>

      <section>
        <h2>7. SLA, Dostępność i Rekompensaty</h2>
        <p>Deklarowany poziom dostępności: <strong>99.9%</strong> miesięcznie.</p>
        <table>
          <thead>
            <tr>
              <th>Zdarzenie</th>
              <th>Wysokość Rekompensaty</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Przerwa w dostępie > 1 godzina</td>
              <td>+1 dzień ważności planu gratis</td>
            </tr>
            <tr>
              <td>Przerwa w dostępie ~ 25 godzin</td>
              <td>+2 dni ważności planu gratis</td>
            </tr>
            <tr>
              <td>Niedostępność panelu (zgodnie z kryteriami czasowymi)</td>
              <td>Analogicznie jak za brak dostępu do serwera</td>
            </tr>
          </tbody>
        </table>
        <p class="small">Rekompensata nie może przekroczyć wartości opłaty za dany okres rozliczeniowy.</p>
      </section>

      <section>
        <h2>8. Zasady dyscyplinarne (Bany i Upomnienia)</h2>
        <ol>
          <li>DNCT może zawiesić usługę natychmiast przy wykryciu ataków DDoS, fraudów lub zagrożeń bezpieczeństwa.</li>
          <li><strong>System upomnień:</strong> (1) → (2) → (3) = blokada konta.</li>
          <li>Poważne naruszenia oraz chargebacki skutkują natychmiastową blokadą bez zwrotu środków.</li>
        </ol>
      </section>

      <section>
        <h2>9. Backupy i Usuwanie danych</h2>
        <ol>
          <li><strong>Backupy:</strong> Codziennie o 03:00 CET. Retencja: 7 dni.</li>
          <li>Klient jest zobowiązany do posiadania własnej kopii krytycznych danych poza DNCT.</li>
          <li><strong>Usuwanie konta:</strong> Klient może sam usunąć konto DNCT wraz z kontami w usługach w ustawieniach konta (<a href="https://account.dnct.pl/ustawienia#rodo">account.dnct.pl</a>) albo zgłaszając to na Discordzie lub e-mailem. Usunięcie jest możliwe, gdy Klient nie ma aktywnych usług ani abonamentu. Historię zamówień przechowujemy przez okres wymagany przepisami (pkt 12).</li>
          <li><strong>Serwery gier po terminie:</strong> nieopłacony serwer jest archiwizowany, zawieszany, a następnie usuwany; paczka z plikami jest udostępniana Klientowi do pobrania.</li>
        </ol>
      </section>

      <section>
        <h2>10. Bezpieczeństwo i Logi</h2>
        <ol>
          <li>Logi systemowe i behavioralne przechowywane są przez 30 dni w celach bezpieczeństwa i prawnych.</li>
          <li>Status systemu dostępny jest dla zalogowanych użytkowników w panelu.</li>
          <li>DNCT ma prawo do czasowego ograniczenia ruchu (do 72h) w sytuacjach krytycznych bez powiadomienia.</li>
        </ol>
      </section>

      <section>
        <h2>11. Polityka Treści i Prawa Dostępu</h2>
        <ol>
          <li>Zakaz hostowania malware, phishingu i treści nielegalnych. Procedura wyjaśniająca (DMCA-like) trwa 24h od zgłoszenia.</li>
          <li><strong>Dostęp techniczny:</strong> Klient wyraża zgodę na dostęp administracji do plików w celu diagnostyki, backupu i zapewnienia bezpieczeństwa.</li>
        </ol>
      </section>

      <section>
        <h2>12. RODO (Ochrona Danych Osobowych)</h2>
        <ol>
          <li>Administratorem danych Klienta jest Aleks Osiński (DNCT). Szczegóły: <a href="/polityka-prywatnosci">Polityka Prywatności</a>.</li>
          <li>Klient ma prawo do wglądu, modyfikacji, przeniesienia i usunięcia swoich danych osobowych - samodzielnie w ustawieniach konta albo na żądanie.</li>
          <li>Dane, które Klient umieszcza w usługach (strony WWW, skrzynki pocztowe, bazy danych, pliki serwerów), DNCT przetwarza w imieniu Klienta jako <strong>podmiot przetwarzający</strong> na zasadach <a href="/umowa-powierzenia">Umowy powierzenia przetwarzania danych</a> (pkt 17).</li>
        </ol>
      </section>

      <section>
        <h2>13. Reklamacje i Odpowiedzialność</h2>
        <ol>
          <li>Reklamacje: Discord (system ticketów) lub e-mail. Rozpatrywane do 14 dni roboczych.</li>
          <li>Odpowiedzialność DNCT ograniczona jest do kwoty opłaty za ostatni okres rozliczeniowy (1 miesiąc).</li>
          <li>DNCT nie odpowiada za błędy oprogramowania zewnętrznego (mody, pluginy).</li>
        </ol>
      </section>

      <section>
        <h2>14. Postanowienia Końcowe</h2>
        <ol>
          <li>W sprawach nieuregulowanych stosuje się przepisy prawa polskiego.</li>
          <li>Zmiany regulaminu ogłaszane są z 14-dniowym wyprzedzeniem.</li>
        </ol>
      </section>
      <section>
        <h2>15. Hosting stron WWW</h2>
        <ol>
          <li><strong>Pakiety:</strong> Start, Pro i Biznes różnią się liczbą stron, pojemnością dysku, liczbą skrzynek pocztowych i baz danych. Aktualne parametry i ceny: <a href="https://web.dnct.pl">web.dnct.pl</a>. Okres rozliczeniowy wynosi 30 dni.</li>
          <li><strong>W pakiecie:</strong> panel klienta (<code>web.panel-dnct.pl</code>), certyfikaty SSL, poczta e-mail z webmailem (<code>poczta.dnct.pl</code>), bazy MariaDB, instalator aplikacji (m.in. WordPress), menedżer plików, dostęp SFTP oraz codzienne kopie zapasowe z retencją 7 dni.</li>
          <li><strong>Adresy:</strong> Klient może użyć własnej domeny albo darmowego adresu w domenie <code>dnct.pl</code>. Darmowy adres pozostaje własnością DNCT - wygasa razem z usługą i może zostać odebrany za naruszenie regulaminu. Nazwy mylące (np. podszywające się pod DNCT, banki czy znane marki) są niedozwolone.</li>
          <li><strong>Uczciwe korzystanie z zasobów:</strong> hosting jest współdzielony. Pojedynczy skrypt ma limit pamięci i czasu wykonania, a konto limit procesora i pamięci. Strona, która mimo to obciąża serwer w sposób utrudniający pracę innym klientom, może zostać czasowo ograniczona - o czym informujemy Klienta.</li>
          <li><strong>Poczta:</strong> zakazana jest wysyłka spamu, masowych mailingów bez zgody odbiorców oraz wiadomości podszywających się pod inne podmioty. Skrzynki mają limity pojemności i liczby wysyłanych wiadomości zależne od pakietu. Naruszenie skutkuje natychmiastową blokadą wysyłki.</li>
          <li><strong>Zakazane treści i zastosowania:</strong> poza pkt 11 - kopalnie kryptowalut, serwery proxy/VPN, skanery sieci, przechowywanie plików niezwiązanych ze stroną (np. archiwa wideo) oraz treści naruszające prawa autorskie.</li>
          <li><strong>Po terminie płatności:</strong> nieopłacony hosting jest zawieszany (strony i poczta przestają działać, dane zostają nienaruszone). Po opłaceniu wszystko wraca od razu. Konto zawieszone dłużej niż 14 dni DNCT może usunąć po wcześniejszym powiadomieniu Klienta e-mailem i umożliwieniu pobrania kopii.</li>
          <li><strong>Kopie zapasowe</strong> mają charakter pomocniczy - Klient odpowiada za posiadanie własnej kopii ważnych danych (pkt 9).</li>
          <li><strong>Zmiana pakietu</strong> w trakcie okresu rozliczeniowego odbywa się na zgłoszenie; różnica w cenie jest rozliczana proporcjonalnie.</li>
        </ol>
      </section>

      <section>
        <h2>16. Konto DNCT i profil</h2>
        <ol>
          <li>Jedno konto DNCT służy do logowania we wszystkich usługach (strona, panel gier, panel hostingu). Przy rejestracji lub pierwszym logowaniu tworzone są konta w tych usługach.</li>
          <li>Klient odpowiada za bezpieczeństwo swojego hasła; zaleca się włączenie weryfikacji dwuetapowej. Zmiana adresu e-mail wymaga potwierdzenia kodem wysłanym na nowy adres.</li>
          <li><strong>Profil publiczny</strong> (<code>account.dnct.pl/u/…</code>) jest domyślnie wyłączony. Klient, który go włącza, odpowiada za umieszczone w nim treści (opis, zdjęcia, odnośniki). DNCT może ukryć profil lub usunąć treści naruszające prawo, dobre obyczaje lub niniejszy regulamin.</li>
        </ol>
      </section>

      <section>
        <h2>17. Umowa powierzenia przetwarzania danych</h2>
        <ol>
          <li>Zamawiając hosting stron WWW lub serwer gry, Klient zawiera z DNCT <a href="/umowa-powierzenia">Umowę powierzenia przetwarzania danych osobowych</a> (art. 28 RODO), która stanowi integralną część niniejszego regulaminu.</li>
          <li>Umowa obejmuje dane, które Klient umieszcza w usługach - w tym dane osób odwiedzających jego strony i korespondujących z jego skrzynkami. Administratorem tych danych pozostaje Klient.</li>
        </ol>
      </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';

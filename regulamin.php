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
        <p>DNCT to polski dostawca hostingu serwerów gier i stron WWW działający jako działalność nierejestrowana. Ten dokument opisuje zasady umowy, płatności, reklamacji i ochrony danych. Aktualne parametry oferty są podane w cenniku oraz w potwierdzeniu zamówienia.</p>
      </div>

      <h1>Regulamin świadczenia usług - DNCT</h1>
      <p><strong>Data wejścia w życie:</strong> 7 lutego 2026<br>
      <strong>Aktualizacja:</strong> 4 października 2026 - zasady rozpoczęcia usługi, prawo odstąpienia i dobrowolna Gwarancja Zwrotu 24 h. Dla nowych zamówień wersja obowiązuje od chwili zawarcia umowy. Wobec dotychczasowych Klientów zmiany wymagające powiadomienia obowiązują po doręczeniu informacji na trwałym nośniku i po upływie wskazanego w pkt 14 terminu.</p>
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
            <strong>Informacja techniczna:</strong>
            <ul>
              <li>Parametry wybranego planu, ograniczenia i cena są potwierdzane przed płatnością oraz w potwierdzeniu zamówienia.</li>
              <li>Wsparcie techniczne realizowane jest przez e-mail oraz system ticketów Discord.</li>
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
              <tr><td>DEVeloperski</td><td>1.5GB</td><td>1 vCore</td><td>2.5GB</td><td>4</td><td class="price-3">3.99</td><td class="price-cell">6.99</td><td>76.89</td></tr>
              <tr><td>Budżetowy</td><td>3GB</td><td>2 vCore</td><td>5GB</td><td>1</td><td class="price-3">4.99</td><td class="price-cell">9.99</td><td>109.89</td></tr>
              <tr><td>Podstawowy</td><td>4GB</td><td>2 vCore</td><td>15GB</td><td>1</td><td class="price-3">7.99</td><td class="price-cell">15.99</td><td>175.89</td></tr>
              <tr><td>Standardowy</td><td>8GB</td><td>3 vCore</td><td>45GB</td><td>2</td><td class="price-3">18.99</td><td class="price-cell">36.99</td><td>406.89</td></tr>
              <tr><td>Zaawansowany</td><td>10GB</td><td>4 vCore</td><td>60GB</td><td>2</td><td class="price-3">21.99</td><td class="price-cell">43.99</td><td>483.89</td></tr>
              <tr><td>Mistrzowski</td><td>14GB</td><td>4 vCore</td><td>80GB</td><td>4</td><td class="price-3">32.99</td><td class="price-cell">64.99</td><td>714.89</td></tr>
              <tr><td>Elitarny</td><td>18GB</td><td>5 vCore</td><td>100GB</td><td>4</td><td class="price-3">39.99</td><td class="price-cell">79.99</td><td>879.89</td></tr>
              <tr><td>LEGENDARNY</td><td>24GB</td><td>6 vCore</td><td>150GB</td><td>4</td><td class="price-3">59.99</td><td class="price-cell">119.99</td><td>1319.89</td></tr>
              <tr><td>LEGENDARNY +</td><td>32GB</td><td>8 vCore</td><td>200GB</td><td>4</td><td class="price-3">84.99</td><td class="price-cell">169.99</td><td>1869.89</td></tr>
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

      <section id="platnosci-i-odstapienie">
        <h2>6. Płatności, rozpoczęcie usługi i odstąpienie od umowy</h2>
        <ol>
          <li>Płatności obsługuje Stripe. Dostępne metody płatności są wyświetlane w Stripe Checkout przed złożeniem płatności.</li>
          <li>DNCT prowadzi działalność nierejestrowaną i nie jest czynnym podatnikiem VAT. Na żądanie Klienta DNCT wystawia potwierdzenie sprzedaży zgodne z obowiązującymi przepisami.</li>
          <li>Usługę można opłacić <strong>jednorazowo</strong> (przedłużenie ręczne) albo w <strong>abonamencie</strong>, który odnawia się automatycznie na kolejny okres. Abonament można zatrzymać w każdej chwili w panelu klienta - usługa działa wtedy do końca opłaconego okresu.</li>
          <li><strong>Prawo odstąpienia konsumenta:</strong> Konsument może odstąpić od umowy zawartej na odległość w terminie 14 dni od jej zawarcia, składając DNCT jednoznaczne oświadczenie na e-mail <a href="mailto:support@dnct.pl">support@dnct.pl</a>. Może użyć formularza z pkt 6.9, ale nie jest to obowiązkowe.</li>
          <li><strong>Wybór rozpoczęcia usługi:</strong> przed płatnością Klient sam wybiera, czy żąda uruchomienia usługi niezwłocznie, czy po upływie terminu odstąpienia. Brak żądania natychmiastowego uruchomienia nie blokuje zakupu; DNCT uruchamia wtedy usługę po 14 dniach od zawarcia umowy.</li>
          <li><strong>Natychmiastowe uruchomienie:</strong> DNCT zaczyna świadczyć usługę przed upływem 14 dni wyłącznie po wyraźnym żądaniu Klienta złożonym w formularzu zamówienia i po potwierdzeniu przez niego informacji o skutkach pełnego wykonania usługi. Gdy Konsument odstąpi przed pełnym wykonaniem usługi, płaci wyłącznie za świadczenia spełnione do chwili otrzymania oświadczenia o odstąpieniu, w proporcji do całego okresu. Po pełnym wykonaniu usługi prawo odstąpienia może wygasnąć wyłącznie, gdy są spełnione wszystkie ustawowe warunki.</li>
          <li><strong>Gwarancja Zwrotu 24 h:</strong> niezależnie od praw ustawowych DNCT dobrowolnie zwraca pełną cenę nowego zamówienia, jeśli Klient zgłosi taki wniosek w ciągu 24 godzin od potwierdzenia płatności przez e-mail lub ticket Discord. Gwarancja nie obejmuje fraudu, nadużycia płatności ani naruszenia pkt 8 i 11. Po 24 godzinach wygasa wyłącznie ta dodatkowa gwarancja; nie ogranicza to prawa odstąpienia, reklamacji ani innych praw przysługujących Konsumentowi z mocy prawa.</li>
          <li><strong>Zwrot po odstąpieniu:</strong> DNCT zwraca należną kwotę niezwłocznie, nie później niż w terminie wymaganym przez prawo, używając co do zasady tej samej metody płatności, chyba że Klient wyraźnie zgodzi się na inną metodę bez dodatkowych kosztów.</li>
          <li><strong>Formularz odstąpienia:</strong><br>
            Do: DNCT, Aleks Osiński, e-mail: support@dnct.pl<br>
            Ja/My (*) niniejszym informuję/informujemy (*) o odstąpieniu od umowy o świadczenie usługi: ........................................<br>
            Numer zamówienia: ........................................<br>
            Data zawarcia umowy: ........................................<br>
            Imię i nazwisko Konsumenta: ........................................<br>
            Adres e-mail użyty przy zamówieniu: ........................................<br>
            Data: ........................................ &nbsp; Podpis (tylko przy formularzu papierowym): ........................................<br>
            <span class="small">(*) Niepotrzebne skreślić.</span>
          </li>
        </ol>
      </section>

      <section>
        <h2>7. SLA, Dostępność i Rekompensaty</h2>
        <p>DNCT nie składa obecnie gwarancji dostępności wyrażonej procentowo. Zdarzenia wpływające na usługę można zgłosić zgodnie z pkt 13; DNCT bada je indywidualnie i podejmuje działania naprawcze.</p>
        <p>Jeżeli przepisy przyznają Klientowi określone środki ochrony lub rekompensatę, niniejszy Regulamin ich nie ogranicza. Planowane zasady SLA i automatycznych rekompensat mogą zostać wprowadzone dopiero po wdrożeniu niezależnego monitoringu oraz publicznego raportowania dostępności.</p>
        <p>Bieżący stan usług oraz historia przerw z ostatnich 90 dni są publikowane na stronie <a href="https://status.dnct.pl">status.dnct.pl</a>. Pomiar prowadzi DNCT z własnej infrastruktury; strona ma charakter informacyjny i nie stanowi gwarancji dostępności ani podstawy do automatycznej rekompensaty.</p>
      </section>

      <section>
        <h2>8. Zasady dyscyplinarne (Bany i Upomnienia)</h2>
        <ol>
          <li>DNCT może zawiesić usługę natychmiast przy wykryciu ataków DDoS, fraudów lub zagrożeń bezpieczeństwa.</li>
          <li><strong>System upomnień:</strong> (1) → (2) → (3) = blokada konta.</li>
          <li>Przy chargebacku lub uzasadnionym podejrzeniu fraudu DNCT może czasowo zawiesić usługę na czas wyjaśnienia sprawy. Nie ogranicza to ustawowych praw Konsumenta, w tym prawa odstąpienia i reklamacji.</li>
        </ol>
      </section>

      <section>
        <h2>9. Backupy i Usuwanie danych</h2>
        <ol>
          <li><strong>Backupy:</strong> zakres, liczba i retencja kopii zależą od wybranego planu i są wskazane w ofercie lub panelu. Kopie mają charakter pomocniczy i nie zastępują kopii Klienta.</li>
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

      <section id="zgloszenia-nielegalnych-tresci-dsa">
        <h2>11. Zgłoszenia nielegalnych treści i bezpieczeństwo usług</h2>
        <ol>
          <li>Zakazane jest korzystanie z usług do publikowania lub rozpowszechniania treści nielegalnych, w szczególności złośliwego oprogramowania, phishingu, oszustw, treści naruszających prawa autorskie, prywatność albo prawa osób trzecich.</li>
          <li><strong>Zgłoszenie:</strong> każda osoba może bezpłatnie i bez konta zgłosić potencjalnie nielegalną treść przez <a href="/zglos-naruszenie">formularz zgłoszenia</a> lub, gdy formularz jest niedostępny, e-mailem na <a href="mailto:support@dnct.pl?subject=Abuse%20report">support@dnct.pl</a>. Zgłoszenie powinno zawierać dokładną lokalizację treści, uzasadnienie oraz dane kontaktowe umożliwiające odpowiedź; dane kontaktowe nie są wymagane przy zgłoszeniu dotyczącym bezpieczeństwa dzieci.</li>
          <li><strong>Ocena i decyzja:</strong> DNCT ocenia zgłoszenia rzetelnie, obiektywnie i bez zbędnej zwłoki. W zależności od sprawy może poprosić o uzupełnienie informacji, ograniczyć dostęp do treści lub usługi, zachować materiał na potrzeby postępowania albo nie podejmować działania. Nie gwarantujemy sztywnego czasu rozpatrzenia, ponieważ zależy on od kompletności zgłoszenia i rodzaju ryzyka.</li>
          <li><strong>Uzasadnienie i zastrzeżenia:</strong> gdy podanie uzasadnienia jest właściwe, przekazujemy je zgłaszającemu i klientowi, którego sprawa dotyczy. Zastrzeżenie do decyzji można przesłać w odpowiedzi na wiadomość zawierającą numer sprawy; oceni je człowiek.</li>
          <li><strong>Brak ogólnego monitorowania:</strong> DNCT nie ma obowiązku ani nie prowadzi ogólnego monitorowania wszystkich danych klientów. Nie ogranicza to działań koniecznych dla bezpieczeństwa usług, realizacji obowiązków prawnych lub reakcji na wiarygodne zgłoszenie.</li>
          <li><strong>Prawa autorskie:</strong> zgłoszenia naruszeń praw autorskich i znaków towarowych rozpatrujemy według powyższej procedury. Nie jest to amerykańska procedura DMCA ani obietnica automatycznego usunięcia treści po każdym zgłoszeniu.</li>
          <li><strong>Dostęp techniczny:</strong> Klient wyraża zgodę na dostęp administracji do plików wyłącznie w zakresie potrzebnym do diagnostyki, kopii zapasowej, bezpieczeństwa, realizacji obowiązku prawnego lub obsługi zgłoszenia.</li>
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
          <li>Reklamacje można złożyć przez ticket Discord lub e-mail. W zgłoszeniu należy podać numer zamówienia, opis problemu i oczekiwane rozwiązanie. DNCT odpowiada Konsumentowi w terminie 14 dni od otrzymania reklamacji, jeżeli przepisy wymagają takiego terminu.</li>
          <li>Żadne postanowienie Regulaminu nie wyłącza ani nie ogranicza praw Konsumenta wynikających z bezwzględnie obowiązujących przepisów. W pozostałym zakresie odpowiedzialność DNCT jest ograniczona do kwoty opłaty za okres, którego dotyczy roszczenie, o ile prawo na to pozwala.</li>
          <li>DNCT nie odpowiada za błędy oprogramowania zewnętrznego (mody, pluginy).</li>
        </ol>
      </section>

      <section>
        <h2>14. Postanowienia Końcowe</h2>
        <ol>
          <li>W sprawach nieuregulowanych stosuje się przepisy prawa polskiego.</li>
          <li>Zmiany Regulaminu dotyczące trwających umów DNCT przesyła Klientowi na trwałym nośniku, w szczególności e-mailem, z co najmniej 14-dniowym wyprzedzeniem. Zmiany nie naruszają praw nabytych ani bezwzględnie obowiązujących przepisów.</li>
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
$content = i18n_dokument('regulamin', (string)ob_get_clean(), $pageTitle, $metaDescription);
require __DIR__ . '/assets/clear/clear.php';

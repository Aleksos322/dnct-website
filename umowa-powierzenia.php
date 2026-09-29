<?php
/**
 * Umowa powierzenia przetwarzania danych osobowych (art. 28 RODO).
 *
 * Hosting stron WWW (i serwery gier) to przechowywanie danych, których
 * administratorem jest KLIENT - dane odwiedzających jego strony, treść jego
 * skrzynek, baz i plików. DNCT jest wtedy podmiotem przetwarzającym i musi
 * mieć z Klientem umowę powierzenia. Zawierana przez akceptację regulaminu
 * (pkt 17) przy zamówieniu.
 *
 * SZKIC przygotowany 2026-09-29 - przed startem sprzedaży hostingu do
 * przejrzenia przez prawnika. Lista podprocesorów w pkt 7 musi odpowiadać
 * rzeczywistości (zmiana infrastruktury = zmiana tej listy).
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'Umowa powierzenia przetwarzania danych - DNCT Hosting';
$metaDescription = 'Umowa powierzenia przetwarzania danych osobowych (art. 28 RODO) dla klientów hostingu stron WWW i serwerów gier DNCT.';
$canonicalUrl    = 'https://dnct.pl/umowa-powierzenia';
$centerContent   = false;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">

<div class="legal glass glass-content" style="border-radius: 22px; padding: clamp(1.25rem, 4vw, 3rem);">
    <h1>Umowa powierzenia przetwarzania danych osobowych</h1>
    <p><strong>Wersja:</strong> 1.0, 29 września 2026. Stanowi część <a href="/regulamin">Regulaminu</a> (pkt 17).</p>
    <p>Umowa zawierana jest między Klientem (<strong>„Administrator”</strong>) a DNCT - Aleks Osiński, działalność nierejestrowana, Puławy (<strong>„Podmiot przetwarzający”</strong>) - z chwilą zamówienia usługi hostingu stron WWW lub serwera gry, na podstawie art. 28 ust. 3 Rozporządzenia 2016/679 (RODO).</p>

    <section>
        <h2>1. Przedmiot i czas trwania</h2>
        <ol>
            <li>Administrator powierza Podmiotowi przetwarzającemu przetwarzanie danych osobowych w zakresie niezbędnym do świadczenia usług hostingu (przechowywanie, udostępnianie przez sieć, kopie zapasowe, obsługa poczty).</li>
            <li>Umowa obowiązuje przez czas świadczenia usługi oraz przez okres potrzebny do usunięcia danych po jej zakończeniu (pkt 9).</li>
        </ol>
    </section>

    <section>
        <h2>2. Charakter i cel przetwarzania</h2>
        <p>Przetwarzanie ma charakter techniczny: dane są przechowywane na serwerach Podmiotu przetwarzającego i udostępniane zgodnie z konfiguracją ustaloną przez Administratora. Podmiot przetwarzający <strong>nie decyduje</strong> o celach przetwarzania i nie wykorzystuje danych do własnych celów.</p>
    </section>

    <section>
        <h2>3. Rodzaje danych i kategorie osób</h2>
        <ol>
            <li><strong>Rodzaje danych:</strong> wszelkie dane, które Administrator umieszcza w usłudze - w szczególności dane identyfikacyjne i kontaktowe, treść korespondencji e-mail, dane w bazach danych i plikach, adresy IP i logi ruchu stron.</li>
            <li><strong>Kategorie osób:</strong> osoby odwiedzające strony Administratora, jego klienci, kontrahenci, pracownicy i korespondenci, użytkownicy jego serwerów gier.</li>
            <li>Administrator nie powinien umieszczać w usłudze danych szczególnych kategorii (art. 9 RODO) bez zapewnienia odpowiednich zabezpieczeń po swojej stronie; jeśli to robi, odpowiada za ocenę adekwatności zabezpieczeń opisanych w pkt 6.</li>
        </ol>
    </section>

    <section>
        <h2>4. Obowiązki Podmiotu przetwarzającego</h2>
        <ol>
            <li>Przetwarza dane wyłącznie na udokumentowane polecenie Administratora - poleceniem jest w szczególności konfiguracja usługi w panelu i treść regulaminu - chyba że obowiązek przetwarzania wynika z prawa (wtedy informuje o tym Administratora, o ile prawo tego nie zabrania).</li>
            <li>Zapewnia, że osoby z dostępem do danych zobowiązały się do zachowania tajemnicy.</li>
            <li>Dostęp do danych Administratora uzyskuje wyłącznie w zakresie niezbędnym do utrzymania usługi, diagnostyki, bezpieczeństwa lub na prośbę Administratora.</li>
            <li>Pomaga Administratorowi w realizacji praw osób, których dane dotyczą (art. 15-22 RODO), w zakresie, w jakim wymaga to działania po stronie infrastruktury.</li>
            <li>Pomaga w wypełnianiu obowiązków z art. 32-36 RODO, uwzględniając charakter przetwarzania i dostępne informacje.</li>
        </ol>
    </section>

    <section>
        <h2>5. Naruszenia ochrony danych</h2>
        <p>Podmiot przetwarzający zgłasza Administratorowi stwierdzone naruszenie ochrony powierzonych danych <strong>bez zbędnej zwłoki, nie później niż w ciągu 48 godzin</strong> od jego stwierdzenia - na adres e-mail konta - wraz z opisem naruszenia, jego prawdopodobnych skutków i podjętych działań. Zgłoszenie do organu nadzorczego należy do Administratora.</p>
    </section>

    <section>
        <h2>6. Środki bezpieczeństwa (art. 32 RODO)</h2>
        <ul>
            <li>serwery we własnej infrastrukturze w Polsce, w wydzielonej sieci (strefy za zaporą), dostęp administracyjny wyłącznie przez sieć wewnętrzną lub VPN;</li>
            <li>szyfrowanie ruchu HTTPS (certyfikaty SSL) i szyfrowanie połączeń pocztowych, gdy obsługuje je druga strona;</li>
            <li>izolacja kont klientów (osobni użytkownicy systemowi, ograniczenia dostępu do plików, limity zasobów);</li>
            <li>codzienne kopie zapasowe z retencją 7 dni, przechowywane na osobnym nośniku;</li>
            <li>ochrona przed atakami DDoS i filtrowanie ruchu (Cloudflare);</li>
            <li>logowanie do panelu kontem DNCT z możliwością weryfikacji dwuetapowej, rejestr zdarzeń bezpieczeństwa;</li>
            <li>regularne aktualizacje bezpieczeństwa systemu i oprogramowania serwerowego.</li>
        </ul>
    </section>

    <section>
        <h2>7. Dalsze powierzenie (podprocesorzy)</h2>
        <ol>
            <li>Administrator udziela ogólnej zgody na korzystanie z następujących podprocesorów:
                <ul>
                    <li><strong>Cloudflare, Inc.</strong> (USA) - pośredniczenie w ruchu do stron, ochrona DDoS, DNS; transfer na podstawie standardowych klauzul umownych i EU-US Data Privacy Framework.</li>
                </ul>
            </li>
            <li>Hosting, poczta, bazy danych i kopie zapasowe działają na serwerach Podmiotu przetwarzającego - bez udziału innych dostawców.</li>
            <li>O zamiarze dodania lub zmiany podprocesora Podmiot przetwarzający informuje z wyprzedzeniem co najmniej 14 dni (e-mailem lub przez zmianę tej umowy). Administrator może w tym czasie wyrazić sprzeciw i wypowiedzieć usługę ze zwrotem niewykorzystanej opłaty.</li>
            <li>Podmiot przetwarzający nakłada na podprocesorów obowiązki ochrony danych nie mniejsze niż wynikające z tej umowy.</li>
        </ol>
    </section>

    <section>
        <h2>8. Audyt i informacje</h2>
        <p>Podmiot przetwarzający udostępnia Administratorowi informacje niezbędne do wykazania spełnienia obowiązków z art. 28 RODO i umożliwia audyt - w formie odpowiedzi na pisemne pytania lub, po uzgodnieniu terminu (co najmniej 14 dni wcześniej), inspekcji przeprowadzonej na koszt Administratora, nie częściej niż raz w roku, bez dostępu do danych innych klientów.</p>
    </section>

    <section>
        <h2>9. Zakończenie przetwarzania</h2>
        <ol>
            <li>Po zakończeniu świadczenia usługi Administrator może pobrać swoje dane (menedżer plików, SFTP, kopia zapasowa z panelu, eksport skrzynek) do chwili ich usunięcia.</li>
            <li>Podmiot przetwarzający usuwa powierzone dane po zakończeniu usługi, zgodnie z terminami z regulaminu (pkt 9 i 15). Kopie zapasowe wygasają w ramach rotacji, nie później niż po 7 dniach od usunięcia danych z usługi.</li>
            <li>Obowiązek usunięcia nie dotyczy danych, których przechowywanie nakazują przepisy prawa.</li>
        </ol>
    </section>

    <section>
        <h2>10. Postanowienia końcowe</h2>
        <ol>
            <li>Odpowiedzialność stron określają przepisy RODO oraz regulamin; w sprawach nieuregulowanych stosuje się prawo polskie.</li>
            <li>W przypadku sprzeczności między tą umową a regulaminem w zakresie ochrony danych pierwszeństwo ma niniejsza umowa.</li>
            <li>Kontakt w sprawach ochrony danych: <a href="mailto:support@dnct.pl">support@dnct.pl</a>.</li>
        </ol>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';

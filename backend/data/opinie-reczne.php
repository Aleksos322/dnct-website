<?php
/**
 * Opinie z wizytówki Google przepisane ręcznie - używane TYLKO wtedy, gdy
 * Places API nie oddaje treści opinii (backend/lib/google_reviews.php).
 * Gdy API zacznie je zwracać, ta lista jest pomijana sama.
 * Przepisuj dosłownie, z imieniem jak w Google. Avatary wycięte ze zrzutów
 * wizytówki (assets/media/opinie/, 80x80 webp) - Google ich przez API nie daje.
 */

return [
    // Dopisane 16.10.2026 z kolejnych zrzutów od właściciela.
    // Maria i Adam Oryszczuk byli już na liście — nie dodajemy ich ponownie.
    // Brak osobnych plików avatarów: komponent wyświetli inicjały autorów.
    ['autor' => 'Urszula Grabczak-Osińska', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Bardzo polecam hosting stron internetowych. Trzymam na nim stronę swojego salonu fryzjerskiego. Niesamowicie szybka obsługa żadnych problemów. Bardzo polecam!'],
    ['autor' => 'Napierak', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Klasa sama w sobie'],
    ['autor' => 'Aleksander Drobnicki', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Trzyma poziom i dobry tech guy za kulisami szkoda że na zawsze tego nie mam'],
    ['autor' => 'Piotr Staszak', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Świetna obsługa, miła pomoc techniczna i tanie ceny w stosunku do świetnej jakości'],
    ['autor' => 'fidzio', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Bardzo dobry hosting, dobra cena oraz mili właściciele! Polecam'],
    ['autor' => 'Mateusz Szeląg', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Korzystam z DNCT Hosting do serwera Minecraft i jestem zadowolony z tego wyboru. Serwer działa płynnie, wydajność jest w porządku, a sama konfiguracja i zarządzanie nie sprawiają większych problemów. Panel jest przejrzysty i łatwo znaleźć w nim potrzebne opcje. Na plus jest też to, że można bez większych komplikacji przygotować serwer pod własne potrzeby. Całość działa tak, jak powinna, bez niepotrzebnego kombinowania. Jeśli ktoś szuka hostingu Minecraft do gry ze znajomymi lub prowadzenia większego projektu, to DNCT Hosting jest opcją wartą rozważenia. Jak na moje doświadczenia, zdecydowanie zasługuje na pozytywną opinię.'],
    ['autor' => 'Prospero', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Miałem okazję korzystać z serwisu parę miesięcy temu jako hosting dla severa Vintage Story. Administracja bardzo profesjonalna, wsparcie techniczne odpowiadało praktycznie od ręki. Panel użytkownika przejrzysty i z wieloma opcjami konfiguracji. Dzięki wielkie za super wspomnienia z gry multiplayer z ekipą 💗 Gorąco polecam wszystkim i na pewno będę korzystał w przyszłości'],
    ['autor' => 'Viczku', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'bardzo polecam, wszystko elegancko <3'],
    ['autor' => 'Wiktor Wisniewski', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => "Uwielbiam ten hosting!!\nKorzystam z ich usług od roku i nigdy nie miałem z nimi żadnych problemów.\nUwielbiam ich interfejs, który jest nowoczesny, przejrzysty i banalnie prosty. Kontakt z suportem jest o ligę lepszy niż z innymi hostingami. Nawet w najprostszych problemach można napisać, a oni zawsze pomogą i nawet wytłumaczą co i jak!!!\n\nNie mam żadnych zarzutów, 10/10 najlepszy hosting jaki widziałem!!"],
    // Dopisane 16.10.2026 ze zrzutu od właściciela; oryginalna treść niemiecka.
    // W Google zamiast zdjęcia jest inicjał — komponent pokaże literę M.
    ['autor' => 'Maria', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Sehr hilfsbereite & kompetente Mitarbeiter! Ausgezeichnetes Erlebnis!'],
    // Dopisane 13.10.2026 z wizytówki (zrzuty od właściciela). Adam Oryszczuk
    // bez zdjęcia w Google (sama litera) - komponent pokaże inicjały.
    ['autor' => 'antoxpl', 'zdjecie' => '/assets/media/opinie/antoxpl.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Korzystam z DNCT Hosting i jestem bardzo zadowolony z ich usług! Świetna jakość, stabilne działanie i profesjonalne podejście do klienta. Kontakt przebiega sprawnie, a wszelkie pytania i problemy są szybko rozwiązywane. Zdecydowanie polecam DNCT Hosting każdemu, kto szuka solidnego i godnego zaufania hostingu! 💪 🔥'],
    ['autor' => 'HIXI1', 'zdjecie' => '/assets/media/opinie/hixi1.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Polecam serdecznie hosting, stabilne serwery fajna przepustowość, generalnie za taką cenę jak najbardziej polecam! W dodatku jest przyjazne dla osób lgbtq+++ co uważam za duży plus.'],
    ['autor' => 'Adam Oryszczuk', 'zdjecie' => '', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Mega polecam hosting 😁'],
    // Dopisane 12.10.2026 z wizytówki (zrzut od właściciela). Nikola Flejmer
    // pominięta - wystawiła same gwiazdki, bez treści, a taśma pokazuje tylko opinie z tekstem.
    ['autor' => 'Magdalena Zw', 'zdjecie' => '/assets/media/opinie/magdalena.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Korzystam z usług DNCT Hosting od dłuższego czasu i jestem bardzo zadowolona. To naprawdę szybki i niezawodny hosting WWW, który działa stabilnie nawet przy większym ruchu na stronie. Proces rejestracji domen, konfiguracji poczty oraz zarządzania usługami jest prosty i intuicyjny. Na duży plus zasługuje również profesjonalne wsparcie techniczne, które szybko pomaga rozwiązać każdy problem. Jeśli ktoś szuka sprawdzonego hostingu internetowego, serwerów VPS lub miejsca dla sklepu internetowego i strony firmowej, zdecydowanie polecam DNCT Hosting'],
    ['autor' => 'Maciek Owczarzak', 'zdjecie' => '/assets/media/opinie/maciek.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Szczerze polecam! Korzystam z tego hostingu od pewnego czasu i jestem ogromnie zadowolony. Wszystko działa bez żadnych opóźnień, a stabilność serwera jest na najwyższym poziomie!!!'],
    ['autor' => 'Agata Bik', 'zdjecie' => '/assets/media/opinie/agata.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'super hosting, świetna obsługa, serdecznie polecam!!!'],
    ['autor' => 'Blanka Osiak', 'zdjecie' => '/assets/media/opinie/blanka.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Super hosting , bardzo szybka odsługa , aktywni prowadzący , giga polecam'],
    ['autor' => 'Smooth', 'zdjecie' => '/assets/media/opinie/smooth.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Szczerze polecam ten hosting!:D Rzadko trafia się na hosting, który podchodzi do graczy i klientów z taką pasją i zaangażowaniem. Serwery śmigają bez jakichkolwiek lagów, panel jest mega dobrze wykonany, a support na Discordzie reaguje błyskawicznie. Super robota chłopaki, oby tak dalej!'],
    ['autor' => 'Bartłomiej Samborski', 'zdjecie' => '/assets/media/opinie/bartlomiej.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Szukałem hostingu pod mój serwer Minecraft, próbowałem różnych, zawsze coś mi nie pasowało, aż natrafiłem na DNCT, bardzo pomocne chłopaki i przede wszystkim, wszystko działa jak należy, skończyły się problemy, a do tego nie wygłupiają się z cenami, według mnie są bardzo uczciwe. Mogę bez problemu polecić ten hosting. Oby tak dalej!'],
    ['autor' => 'Szymon Pomaranik', 'zdjecie' => '/assets/media/opinie/szymon.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Szybko bez problemowo i stabilnie tego właśnie oczekiwałem od hostingu admini są bardzo aktywni i bardzo łatwo i szybko da się z nimi skontaktować i rowizazac wszelkie problemy i do tego są bardzo przyjaźni. Serdecznie polecam'],
    ['autor' => 'kimor', 'zdjecie' => '/assets/media/opinie/kimor.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'dowalone parametry serwera, wszystko śmiga i ładnie chodzi, 0 problemów. Jak jest jakiś problem to zarząd udziela pomocy dosłownie odrazu, gorąco polecaaaaam'],
    ['autor' => 'kulaya', 'zdjecie' => '/assets/media/opinie/kulaya.webp', 'gwiazdki' => 5, 'kiedy' => 'październik 2026',
     'tekst' => 'Super hosting rodzinna atmosfera jezeli masz jakis problem odezwij się do chłopaków a zaraz ci pomogą. Jednocześnie chyba najlepszy panel gier jaki widziałem'],
];

# Zabezpieczenia komunikacji — beta, 2026-10-17

## Wdrożone
- Wspólny licznik SQL dla wysyłania przez HTTP i WebSocket: 5/s, 50/min, cooldown 3 s dla krótkiego burstu. Limit minutowy czeka do zwolnienia miejsca w oknie. Nadmiarowe wiadomości są odrzucane, bez kolejki. Blokada jest per konto, nie per karta/IP.
- INSERT ON DUPLICATE KEY UPDATE bierze blokadę wyłączną przed odczytem licznika; test ośmiu równoczesnych żądań przyjmuje dokładnie pięć.
- Jednorazowe bilety WS związane z sesją i zakresem chat/support; pojedyncza próba auth, maksymalnie 5 minut połączenia.
- Origin z jawnej listy, ramki do 16 KiB, limit ramek, 6 połączeń na konto i 300 łącznie, odcięcie wolnego odbiorcy przy buforze ponad 256 KiB.
- Wylogowanie unieważnia sesję w SQL. Bramka sprawdza sesje i znaczniki SSO co 2 s; zmiany uprawnień Authentika wykrywa z cache do 60 s. To nie jest gwarancja zerowego opóźnienia.
- Wspólny walidator uploadów: rzeczywisty plik PHP, finfo, rozszerzenie zgodne z MIME, getimagesize, 40 MP i maksymalny bok 12000 px; zdjęcia 10 MiB, filmy 50 MiB, do czterech załączników.
- Limity konta obejmują administratorów: 1 GiB zajętości, 500 MiB uploadu i 2 GiB żądanego transferu pobierania/dobę. Range obciąża tylko żądany zakres, HEAD nie obciąża transferu. Przerwany transfer pozostaje naliczony konserwatywnie.
- Rozliczono 30 wcześniejszych plików; dwa nie mają danych autora i są zapisane jako legacy, bez arbitralnego przypisywania do konta.
- Aktywne pliki są w /home/jakub/dnct-private-media/beta/{chat,support,notification}, poza DocumentRoot. Starsze katalogi backend/storage zachowano jako kopie awaryjne. Nie usuwać ich bez osobnego przeglądu.
- Endpoint mediów powiadomień sprawdza odbiorcę, wygaśnięcie, ukrycie i 7 dni od odczytu przy każdym nowym żądaniu. Inne nadal aktywne powiadomienie tego odbiorcy zachowuje dostęp do wspólnego pliku.
- Wiadomości są renderowane jako tekst, bez wykonywania surowego HTML/Markdown; dynamiczne teksty nie są wkładane do innerHTML. Linki i adresy mediów mają ograniczenia w UI.
- Obowiązują kontrole członkostwa, blokad, wyciszeń, usunięcia z grupy, właściciela ticketu i przypisanego konsultanta. Nie zmieniono biznesowego modelu ról ani dostępu panelu admina z LAN/VPN.
- Audyt używa stałych kodów zdarzeń, skrótów identyfikatorów i liczników. Nowe logi nie serializują tokenów, haseł, prywatnych treści ani wyjątków SQL.

## Uruchomienie
Operator na CT 7510 nałożył pięć chronionych poprawek i uruchomił dnct-support-realtime.service.
Sprawdzono bezpośredni port 8981 i przejście przez Apache 8910.
Produkcja pozostaje na dnct-support-realtime-prod.service / 8982 (ten sam PID i start od 2026-10-07).
Bieżący skrypt wdrożeniowy jest jednorazowy: nie uruchamiać go ponownie na wdrożonym zestawie.

## Retencja
messaging-maintenance.php bez argumentów tylko liczy kandydatów.
--apply ponownie sprawdza wszystkie odwołania pod blokadą właściciela quota, usuwa niepotrzebny plik i metadane oraz zwalnia quota. Bieżący podgląd wskazał 6 kandydatów.
Zadanie /etc/cron.d/dnct-messaging-security jest zainstalowane (root:root, 0644), zgodne ze sprawdzonym wzorcem; cron jest aktywny. Zadanie działa co godzinę jako www-data, nie jako root/jakub. Pierwszego wykonania harmonogramu jeszcze nie obserwowano. Dostęp do plików wygasa niezależnie od tego zadania.
Nie skasowano kopii awaryjnych starego magazynu. Nie uruchomiono ClamAV; pozostaje dodatkową, opcjonalną warstwą.
Historyczny transfer sprzed wdrożenia nie jest odtwarzany.

## Sprawdzenie
Na CT 7510:
- php backend/lib/messaging-security-test.php — limity, quota, izolacja odbiorcy i retencja, tabele tymczasowe.
- node backend/lib/messaging-security-test.mjs — zakres i jednorazowość tokenu, sesje, limiter; tabele tymczasowe.
- node backend/lib/messaging-identity-test.mjs — kontrolowane odpowiedzi Authentika, zmiana uprawnień i awaria.
- php backend/lib/messaging-maintenance.php — podgląd bez kasowania.
Dodatkowo sprawdzono rzeczywiste uploady multipart na lokalnym serwerze testowym, burst równoległych procesów oraz handshake przez aktywny proxy.
Pełnego scenariusza przeglądarkowego na rzeczywistym zalogowanym koncie nie wykonywano przez podszywanie się pod użytkownika.

## Granice
To wdrożenie dotyczy bety i obsługujących ją ścieżek admin.lan. Nie przenosi zabezpieczeń do produkcyjnego repo ani produkcyjnej bramki WS.
Nie stanowi audytu całej infrastruktury, zapory, ochrony anty-DDoS ani pozostałych modułów panelu.


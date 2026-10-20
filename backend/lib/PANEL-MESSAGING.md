# Komunikacja panel.dnct.pl i game.dnct.pl — 2026-10-18

Panel używa istniejących rozmów, ticketów i powiadomień **bety** na CT 7510.
Nie skopiowano danych do produkcyjnej bazy dnct.pl. Obsługa administratora jest
nadal dostępna w admin.lan.dnct.pl w źródle Beta. Konta demo nie mają widżetu.

## Przepływ

- `MessagingDock.tsx` ładuje `messaging-native.js`. Loader montuje oryginalne
  szablony, CSS i kontrolery bety bez iframe i bez zastępczego paska przycisków.
  Dzwonek trafia do nagłówka, czat pozostaje w prawym dolnym rogu.
- game.dnct.pl/dnct.pl korzystają z api/messaging.php i reguły /messaging/
  w .htaccess. Sesja strony jest mapowana osobno (`site:`). Klucz podpisujący
  strony leży poza DocumentRootem: /home/jakub/dnct-private-media/site-messaging.key.
  Stare renderery komunikacji nie są uruchamiane równocześnie dla zalogowanych.
  Zachowane są komunikaty flash. Zmiany wdrożono przez merge dev; inne
  niezatwierdzone pliki w produkcji i becie zostały zachowane.
- `/messaging/{path}` wymaga sesji panelu i istniejącego wymogu 2FA. POST zachowuje
  middleware CSRF. Dopuszczone są tylko konkretne endpointy i zasoby modułów.
- `PanelMessagingController` podpisuje żądanie kluczem Ed25519. Prywatny klucz:
  `storage/app/private/panel-messaging.key` (www-data, 0600, poza public/).
- Gateway: `http://10.0.75.10:8910/api/panel-messaging.php` — transport wewnętrzny
  VLAN, podpis chroni integralność i tożsamość; nie szyfruje tego odcinka HTTP.
  Browser korzysta z HTTPS panelu. Gateway nie przyjmuje sesji/roli z przeglądarki.
- Konto jest rozwiązywane po dokładnym e-mailu w Authentiku, musi być jednoznaczne
  i aktywne. Role pochodzą z Authentika, nie z parametrów użytkownika ani panelu.
- Osobna sesja PHP `DNCT_PANEL_BRIDGE` jest powiązana z hashem sesji panelu;
  nie ustawia ciasteczek bety. Pozostają kontrole tożsamości, limity i kwoty bety.
- Podpis obejmuje metodę, dozwoloną ścieżkę, query, parametry, hashe plików,
  sesję, e-mail, CSRF, Range, timestamp i nonce. Ważność podpisu: 60 s.
  Migracja `042_panel_messaging_bridge.sql` chroni przed ponownym użyciem nonce.
  Wygasłe techniczne nonce są sprzątane przy obsłudze żądań.
- Wylogowanie przez `/auth/logout` unieważnia sesję komunikacji. Przy awarii
  połączenia nie blokuje wylogowania panelu; granty WS mają dotychczasowy TTL.
- `/ws/messaging` w Apache panelu sprawdza dokładny Origin panel.dnct.pl, game.dnct.pl, dnct.pl lub www.dnct.pl, następnie
  ustawia Origin bety dla wewnętrznego proxy do CT:8910/ws/support. Nie rozszerzono
  publicznej listy originów usługi bety. Bilety i uprawnienia nadal sprawdza WS.
- Załączniki pozostają w prywatnym magazynie bety. Panel strumieniuje pliki,
  zachowuje Range i Content-Disposition oraz weryfikację dostępu backendu.

## Weryfikacja

- Produkcyjny webpack i TypeScript: OK.
- PHP lint / Apache configtest: OK.
- Podpis prawidłowy: 200; zły/powtórzony/wygasły: odrzucony.
- Path traversal: 404; sesja unieważniona: 401.
- Powiadomienia, inbox i zasoby: 200 dla testowanego konta Smooth.
- WS: obcy Origin 403; poprawny 101 i `ready` po ważnym bilecie.
- Multipart i hashe plików: zweryfikowane bez publikowania wiadomości.
- Przeglądarka Chromium: otwieranie/zamykanie trzech modułów, bez pageerror.
  Test wykorzystał lokalny harness, nie istniejącą przeglądarkową sesję klienta.
- Nie wysłano testowych wiadomości do innych osób ani nie utworzono ticketów.

## Pliki i wdrożenie

Panel: kontroler, routes/base.php, hook logout, App.tsx, MessagingDock.tsx.
CT 7510: api/panel-messaging.php, backend/lib/panel-messaging-frame.php,
backend/lib/panel-messaging-public.key, assets/js/panel-messaging-frame.js,
backend/migrations/042_panel_messaging_bridge.sql.

Kopia assets przed wdrożeniem: `/tmp/dnct-assets-before-messaging-migration`.
Kopia Apache: `/etc/apache2/sites-available/pterodactyl.conf.bak-przed-panel-messaging`.
Bundle zbudowano w osobnym katalogu i podmieniono manifest na końcu; stare chunki
zostały zachowane. Nie restartowano serwerów gier ani usług WS na CT 7510.
Przy wycofaniu interfejsu przywróć poprzedni manifest i źródła tej integracji;
nie usuwaj danych czatu/ticketów ani prywatnych załączników.

Aktualizacja weryfikacji: natywny UI przetestowany w Chromium przy szerokości
1440 i 390 px: nagłówek dzwonka, otwarcie listy, czatu i ticketów, zero iframe
i błędów JS. Bramka strony sprawdzona z tożsamością testowanego konta: 200.
Bramki anonimowe zwracają 401/403. Origin game przechodzi handshake WS (101).
Kopia panelowych assets przed wersją natywną: /tmp/dnct-assets-before-native-messaging.

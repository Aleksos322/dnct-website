# Powiadomienia bety — stan integracji

Wszystkie zdarzenia muszą pochodzić z zaufanego źródła. `notification_event()` w `lib/notification_events.php` zapisuje powiadomienie dla konkretnego loginu; stabilny identyfikator źródłowy zapobiega duplikatom. Ta funkcja nie jest publicznym endpointem.

## Podłączone
- Stripe: dotychczasowe potwierdzenia i błędy, kwoty otrzymane z Checkout/Invoice, przygotowanie serwera; obsługa invoice.finalized i checkout.session.async_payment_failed. Stripe musi doręczać te typy do istniejącego podpisywanego endpointu bety. Dokument Stripe nie jest automatycznie nazywany fakturą VAT.
- Cykl życia: istniejące 7/1 dni i nowe 3 dni oraz wygaśnięcie w istniejącym cron warn. Testy nie uruchamiają lifecycle --apply.
- Support: odpowiedź administratora i zamknięcie przez administrację, bez notatek wewnętrznych.
- Logowanie do bety: nowy fingerprint adresu IP i User-Agent po poprawnym callbacku OIDC. Brak geolokalizacji i brak dostępu do nieudanych logowań Authentika.
- Ogłoszenia: dotychczasowy panel wysyłania powiadomień admin LAN. Istniejące komunikaty otrzymują kategorię prezentacyjną; nie są generowane automatyczne incydenty.

## Zdarzenia wymagające podłączenia źródła
- Pterodactyl/Wings: potwierdzony crash/OOM (nie samo offline), RAM >=95% z okresem utrzymywania progu i resetem po spadku; wynik zadania restartu, zakończenie/błąd/przywrócenie backupu, nadanie dostępu. Mapowanie UUID serwera na zamówienie i właściciela musi być wykonane po stronie serwera. Nie wolno przyjmować loginu odbiorcy od niezaufanego klienta.
- Authentik/account: zmiana hasła/e-mail, 2FA, co najmniej 5 nieudanych prób w zdefiniowanym oknie. Potrzebny podpisany strumień audytu i mapowanie podmiotu na login. Callback OIDC nie zawiera tych zdarzeń.
- Infrastruktura: alerty DDoS i awarii, planowane prace i zakończenie prac wraz z identyfikatorem węzła; odbiorcy ograniczeni do usług na tym węźle.
- Oferta: opublikowanie dostępności gry, nowej funkcji lub promocji; komunikaty z istniejącego panelu administracji mogą zostać użyte już teraz.

## Prośba do Aleksa
Prośba: wskazać i udostępnić potwierdzone źródła zdarzeń Authentika i infrastruktury dla powiadomień bety.
Gdzie: Authentik, monitoring infrastruktury, konfiguracja istniejącego webhooka Stripe beta.
Szczegóły: docelowa aplikacja CT 7510 / beta.dnct.pl; zachować separację produkcji i bety. Najpierw uzgodnić istniejący transport i podpisywanie; nie otwierać dodatkowych portów bez ustalenia źródła.
Po co: informować klientów o rzeczywistych zmianach bezpieczeństwa, awariach i zakończeniu prac.
Jak sprawdzić, że działa: kontrolowane zdarzenie na koncie testowym ma utworzyć jeden komunikat tylko dla jego odbiorcy; ponowne doręczenie nie tworzy duplikatu.
Od: Jakub Aftyka


## Zakładka Admin
API zwraca zwykłe powiadomienia osobno od `target_type=admins`. Każdy administrator ma osobne odczyty. Zwykłe konto nie otrzymuje danych administracyjnych i nie może odczytywać tej zakładki przez API. Oznaczanie całej zakładki nie zmienia stanu drugiej.

`notification_admin_event()` definiuje zdarzenia węzłów, dysków, DDoS, automatyzacji, nadużyć, sprzedaży i ticketów. Obecny kanał zewnętrzny to ntfy: informacje priorytet 3/sprzedaz, operacyjne 4/awarie, krytyczne 5/awarie. Discord/SMS/PagerDuty nie zostały skonfigurowane. Zwykłe błędy automatyzacji nie są eskalowane na priorytet 5 bez jawnie krytycznego źródła.

Podłączone są istniejące alerty tworzenia usług, Stripe/chargeback, nowych zgłoszeń i przekazania ticketu bety do zespołu z panelu administracyjnego. Godzinowy etap lifecycle warn sprawdza także oczekiwanie na odpowiedź ponad 2 godziny; klucz alertu zależy od najstarszej wiadomości oczekującej po ostatniej odpowiedzi admina. Test dry-run nie wysyła komunikatów.

Nadal wymagają źródła: monitoring hypervisorów/RAM/dysków/DDoS, audyt Authentika/admin root, wykrywanie nadużyć, zbiorcze backupy, wykrywanie wyprzedanych zasobów i awarii API, zdarzenia ocen. Nie wolno traktować braku ruchu graczy jako dowodu koparki. Próg dużego zamówienia wymaga ustalenia wartości; nie został arbitralnie włączony.

<?php
declare(strict_types=1);

/** Strona pod opieką korzysta ze wspólnych zgłoszeń i panelu administracji. */
function managed_support_greeting(): string
{
    return "Dziękujemy za zainteresowanie usługą Strona pod opieką DNCT. To automatyczna wiadomość administracji. Administrator odpowie w ciągu 7 dni.\n\nNapisz proszę:\n1. Jaka domena Cię interesuje? Czy już ją posiadasz, czy mamy ją kupić?\n2. Do czego ma służyć strona (np. wizytówka firmy, portfolio, sklep)?\n3. Czy strona już istnieje? Jeśli tak, podaj jej adres i opisz, co trzeba przenieść lub poprawić.\n4. Jakie funkcje i materiały są potrzebne oraz jaki termin Cię interesuje?\n5. Czy potrzebujesz poczty, formularza kontaktowego lub innej pomocy?\n\nUstalimy zakres opieki, domenę i certyfikat SSL przed zamówieniem. Nie przesyłaj tutaj haseł ani danych karty.";
}

function managed_support_active(PDO $db, string $username, string $access): ?array
{
    $q=$db->prepare('SELECT * FROM support_tickets WHERE status IN ("new","open") AND (access_token_hash=:access OR (:has_user=1 AND customer_username=:username)) ORDER BY FIELD(status,"open","new"), created_at ASC LIMIT 1');
    $q->execute([':access'=>$access, ':has_user'=>$username!==''?1:0, ':username'=>$username]);
    $ticket=$q->fetch(PDO::FETCH_ASSOC);
    return is_array($ticket)?$ticket:null;
}

function managed_support_is_care(PDO $db, int $ticketId): bool
{
    $q=$db->prepare('SELECT 1 FROM support_ticket_audit WHERE ticket_id=:id AND action="managed_care_created" LIMIT 1');
    $q->execute([':id'=>$ticketId]);
    return (bool)$q->fetchColumn();
}

function managed_support_can_reply(array $ticket, bool $care): bool
{
    return in_array($ticket['status'], ['new','open'], true)
        && ($care || ($ticket['status']==='open' && !empty($ticket['assigned_admin'])));
}

/** Zwraca istniejące aktywne zgłoszenie zamiast tworzyć duplikat. */
function managed_support_create(PDO $db, string $username, string $access, string $name, string $email, string $key, string $ipHash, string $billing, string $period = 'month', ?array $opiekun = null): array
{
    $existing=managed_support_active($db,$username,$access);
    if($existing) return ['ticket'=>$existing,'created'=>false];
    $owner=hash('sha256',$username!==''?'account:'.$username:'email:'.strtolower($email));
    try {
        $db->beginTransaction();
        $free=$db->prepare('SELECT 1 FROM support_tickets WHERE public_id=:n');
        for($try=0;$try<20;$try++) {
            $number='TKT-'.random_int(1000,9999);
            $free->execute([':n'=>$number]);
            if(!$free->fetchColumn()) break;
        }
        $q=$db->prepare('INSERT INTO support_tickets(public_id,access_token_hash,owner_key,active_owner_key,request_key,customer_username,customer_name,customer_email,category_key,category_label,ip_hash,status) VALUES(:number,:access,:owner,:active_owner,:request,:username,:name,:email,"www",:label,:ip,"new")');
        $q->execute([':number'=>$number,':access'=>$access,':owner'=>$owner,':active_owner'=>$owner,':request'=>$key,':username'=>$username?:null,':name'=>$name,':email'=>$email,':label'=>support_categories()['www'],':ip'=>$ipHash]);
        $id=(int)$db->lastInsertId();
        $year=$period==='year';
        $kwota=pricing_format(HOSTING_MANAGED_PRICE*($year?HOSTING_YEAR_PAID_MONTHS:1));
        $price=$kwota.' zł w abonamencie '.($year?'co rok (11 miesięcy w cenie)':'co 30 dni');
        $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body) VALUES(:id,"customer",:name,:body)')->execute([':id'=>$id,':name'=>$name,':body'=>'Interesuje mnie Strona pod opieką DNCT: '.$price.'.'.($opiekun!==null?' Wybrany opiekun: '.$opiekun['nazwa'].' (konto '.$opiekun['login'].').':'').' Proszę o kontakt i ustalenie szczegółów. To zapytanie, bez złożenia płatnego zamówienia.']);
        $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body) VALUES(:id,"admin",:name,:body)')->execute([':id'=>$id,':name'=>'Administracja DNCT · wiadomość automatyczna',':body'=>managed_support_greeting()]);
        $db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(:id,:actor,"managed_care_created",:details)')->execute([':id'=>$id,':actor'=>$username?:null,':details'=>'web.dnct.pl; billing_mode='.$billing.'; automatic greeting']);
        $db->commit();
    } catch (PDOException $e) {
        if($db->inTransaction()) $db->rollBack();
        if($e->getCode()==='23000') {
            $existing=managed_support_active($db,$username,$access);
            if($existing) return ['ticket'=>$existing,'created'=>false];
        }
        throw $e;
    } catch (Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $ticket=managed_support_active($db,$username,$access);
    if(!$ticket) throw new RuntimeException('Nie udało się odczytać zgłoszenia.');
    return ['ticket'=>$ticket,'created'=>true];
}

function managed_support_reply(PDO $db, array $ticket, string $body, string $key): bool
{
    // Blokada w transakcji zapobiega dopisaniu do równocześnie zamykanego zgłoszenia.
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM support_tickets WHERE id=:id FOR UPDATE');
        $q->execute([':id'=>(int)$ticket['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current || !managed_support_can_reply($current,managed_support_is_care($db,(int)$ticket['id']))) {
            $db->rollBack(); return false;
        }
        try {
            $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body,client_message_key) VALUES(:id,"customer",:name,:body,:key)')->execute([':id'=>(int)$ticket['id'],':name'=>$ticket['customer_name'],':body'=>$body,':key'=>$key]);
        } catch (PDOException $e) { if($e->getCode()!=='23000') throw $e; }
        $db->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=:id')->execute([':id'=>(int)$ticket['id']]);
        $db->commit(); return true;
    } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
}

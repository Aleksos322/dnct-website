<?php
declare(strict_types=1);
/** Presentation for historical notifications and the typed event producers. */
function notification_presentation(array $n):array {
 $hadLink=!empty($n['link']);$hadAction=!empty($n['action_label']);
 $text=mb_strtolower(($n['title']??'').' '.($n['event_key']??''));
 $category=$n['category']??'';
 if(!$category)$category=match(true){preg_match('/zgłoszen|support|ticket/u',$text)===1=>'support',preg_match('/płatno|opłat|abonament|wygas|faktur|przedłuż|subskrypc/u',$text)===1=>'finance',preg_match('/logowani|hasł|2fa|uwierzyteln|dostęp.*kont/u',$text)===1=>'security',preg_match('/przerw.*technic|konserwac|ddos|awari.*węz|infrastruktur/u',$text)===1=>'infrastructure',preg_match('/serwer|backup|kopi.*zapas|hosting/u',$text)===1=>'server',default=>'community'};
 $labels=['operations'=>'Automatyzacja','business'=>'Finanse i sprzedaż','abuse'=>'Bezpieczeństwo i nadużycia','finance'=>'Płatności','server'=>'Serwer i backupy','security'=>'Bezpieczeństwo','infrastructure'=>'Status usług','support'=>'Wsparcie','community'=>'Ogłoszenia'];
 $n['category']=$category;$n['category_label']=$labels[$category]??'System';$n['action_label']=$n['action_label']??match($category){'finance'=>'Przejdź do usług','support'=>'Otwórz wsparcie','server'=>'Zobacz usługę','security'=>'Sprawdź konto',default=>'Szczegóły'};
 if(empty($n['link'])&&in_array($category,['finance','server'],true))$n['link']='/twoje-serwery';
 $n['audience']=($n['target_type']??'')==='admins'?'admin':'normal';if($n['audience']==='admin'){if(!$hadLink)$n['link']='http://admin.lan.dnct.pl/'.($category==='support'?'wsparcie?source=beta':'');if(!$hadAction)$n['action_label']=$category==='support'?'Otwórz ticket w panelu':'Otwórz panel admina';}
 return $n;
}
/** Call only after a verified source event; event IDs are scoped to each recipient. */
function notification_event(PDO $db,string $user,string $event,string $eventId,string $title,string $body,string $link=''):?int {
 $catalog=[
 'payment.paid'=>['finance','success','Zobacz płatność'],'payment.failed'=>['finance','error','Ponów płatność'],
 'service.expiring'=>['finance','warning','Odnów usługę'],'service.expired'=>['finance','error','Odnów usługę'],'invoice.ready'=>['finance','info','Pobierz fakturę'],
 'server.installing'=>['server','info','Zobacz zamówienie'],'server.ready'=>['server','success','Przejdź do serwera'],'server.crashed'=>['server','error','Zobacz logi'],'server.resources'=>['server','warning','Sprawdź zasoby'],'server.restart_ok'=>['server','success','Zobacz serwer'],'server.restart_failed'=>['server','error','Zobacz logi'],
 'backup.ready'=>['server','success','Zobacz kopie'],'backup.failed'=>['server','error','Sprawdź kopie'],'backup.restored'=>['server','success','Zobacz serwer'],
 'security.login'=>['security','warning','Sprawdź konto'],'security.failed_logins'=>['security','error','Zabezpiecz konto'],'security.password'=>['security','info','Sprawdź konto'],'security.email'=>['security','info','Sprawdź konto'],'security.2fa'=>['security','info','Sprawdź konto'],'server.shared'=>['security','info','Zobacz serwer'],
 'infrastructure.maintenance'=>['infrastructure','warning','Sprawdź status'],'infrastructure.incident'=>['infrastructure','error','Sprawdź status'],'infrastructure.restored'=>['infrastructure','success','Sprawdź status'],
 'support.reply'=>['support','info','Otwórz wsparcie'],'support.closed'=>['support','success','Otwórz wsparcie'],
 'community.news'=>['community','info','Dowiedz się więcej'],'community.promotion'=>['community','info','Sprawdź ofertę']];
 if(!isset($catalog[$event])||$eventId===''||$user==='')throw new InvalidArgumentException('Nieprawidłowe zdarzenie powiadomienia.');
 [$category,$type,$action]=$catalog[$event];return notify_user($db,$user,$type,$title,$body,['event_key'=>'event:'.hash('sha256',$event.':'.$eventId.':'.$user),'category'=>$category,'action_label'=>$action,'link'=>$link?:null]);
}

function notification_login(PDO $db,string $user,string $ip,string $agent):void {
 if(!filter_var($ip,FILTER_VALIDATE_IP)||$user==='')return;
 $fingerprint=hash('sha256',$ip.'|'.mb_substr($agent,0,500));
 $db->beginTransaction();try{$q=$db->prepare('INSERT IGNORE INTO notification_login_devices(username,fingerprint) VALUES(?,?)');$q->execute([$user,$fingerprint]);if($q->rowCount())notification_event($db,$user,'security.login',$fingerprint,'Logowanie z nowego urządzenia lub IP','Zalogowano się do beta.dnct.pl z adresu IP '.$ip.'. Jeśli to nie Ty, zabezpiecz konto i zmień hasło.','https://auth.dnct.pl/if/user/');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Verified administrative source events, including push escalation through existing ntfy. */
function notification_admin_event(PDO $db,string $event,string $sourceId,string $title,string $body,string $link=''):?int {
 $events=[
 'node.down'=>['infrastructure','critical'],'node.resources'=>['infrastructure','operational'],'node.storage'=>['infrastructure','critical'],'network.ddos'=>['infrastructure','critical'],
 'provision.failed'=>['operations','operational'],'backup.failed'=>['operations','operational'],'integration.failed'=>['operations','operational'],
 'abuse.suspected'=>['abuse','operational'],'admin.login'=>['abuse','operational'],'admin.bruteforce'=>['abuse','critical'],
 'stock.empty'=>['business','info'],'payment.dispute'=>['business','operational'],'payment.large'=>['business','info'],'invoice.failed'=>['business','operational'],
 'support.new'=>['support','info'],'support.overdue'=>['support','operational'],'support.escalated'=>['support','operational'],'support.feedback_negative'=>['support','operational']];
 if(!isset($events[$event])||$sourceId==='')throw new InvalidArgumentException('Nieprawidłowe zdarzenie administracyjne.');
 [$category,$severity]=$events[$event];$type=match($severity){'critical'=>'error','operational'=>'warning',default=>'info'};
 return notify_admins($db,$type,$title,$body,['event_key'=>'admin-event:'.hash('sha256',$event.':'.$sourceId),'category'=>$category,'link'=>$link?:null,'action_label'=>'Otwórz panel admina','ntfy'=>[$severity==='info'?'sprzedaz':'awarie',$severity==='critical'?5:($severity==='operational'?4:3),$severity==='critical'?'rotating_light':($severity==='operational'?'warning':'information_source')]]);
}

/** Existing hourly beta lifecycle invokes this; dry runs never send alerts. */
function notification_admin_ticket_waits(PDO $db,bool $apply):int {
 $q=$db->query("SELECT t.id,t.public_id,t.category_label,t.customer_name,MIN(m.id) waiting_id,MIN(m.created_at) waiting_since FROM support_tickets t JOIN support_messages m ON m.ticket_id=t.id AND m.author_role='customer' AND m.id>COALESCE((SELECT MAX(a.id) FROM support_messages a WHERE a.ticket_id=t.id AND a.author_role='admin'),0) WHERE t.status IN ('new','open') GROUP BY t.id,t.public_id,t.category_label,t.customer_name HAVING MIN(m.created_at)<=DATE_SUB(NOW(),INTERVAL 2 HOUR)");$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 if($apply)foreach($rows as $t)notification_admin_event($db,'support.overdue',$t['id'].':'.$t['waiting_id'],'Zgłoszenie '.$t['public_id'].' czeka ponad 2 godziny','Klient '.$t['customer_name'].' czeka na odpowiedź. Kategoria: '.$t['category_label'].'.','http://admin.lan.dnct.pl/wsparcie?source=beta&ticket='.$t['id']);return count($rows);
}

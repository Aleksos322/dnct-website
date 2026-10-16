<?php
declare(strict_types=1);
// Tylko beta, wyłącznie tabele tymczasowe tej sesji. Bez powiadomień i e-maili.
if(getenv('DNCT_ENV_FILE')!=='/var/www/dnct.pl/config/beta.env') throw new RuntimeException('Test wymaga jawnego środowiska beta.');
require '/var/www/dnct.pl/public_html/WEB/beta.dnct.pl/backend/bootstrap.php';
require DNCT_BACKEND_PATH.'/lib/support.php';
require dirname(__DIR__).'/lib/support_managed.php';
$db=dnct_db();
foreach(['support_tickets','support_messages','support_ticket_audit'] as $table) {
    $db->exec('CREATE TEMPORARY TABLE qa_managed_'.$table.' LIKE '.$table);
    $db->exec('ALTER TABLE qa_managed_'.$table.' RENAME TO '.$table);
}
function check(bool $ok,string $name): void {if(!$ok) throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name."\n";}
function count_rows(PDO $db,string $table): int {return (int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
$access=hash('sha256','qa-session');$email='managed-qa@example.invalid';
$result=managed_support_create($db,'qa-managed',$access,'Anna',$email,hash('sha256','create'),hash('sha256','127.0.0.1'),'subscription');
$ticket=$result['ticket'];$id=(int)$ticket['id'];
check($result['created']&&$ticket['status']==='new'&&empty($ticket['assigned_admin']),'new ticket stays in unassigned administration queue');
$messages=$db->query('SELECT * FROM support_messages ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check(count($messages)===2&&$messages[0]['author_role']==='customer'&&$messages[1]['author_role']==='admin'&&str_contains($messages[1]['author_name'],'automatyczna')&&str_contains($messages[1]['body'],'7 dni')&&str_contains($messages[1]['body'],'domena'),'persisted automatic administration questions');
check(managed_support_is_care($db,$id)&&managed_support_can_reply($ticket,true)&&!managed_support_can_reply($ticket,false),'only care requests accept details before assignment');
$again=managed_support_create($db,'qa-managed',$access,'Anna',$email,hash('sha256','retry'),hash('sha256','127.0.0.1'),'payment');
check(!$again['created']&&(int)$again['ticket']['id']===$id&&count_rows($db,'support_messages')===2,'repeat create returns same ticket without repeated greeting');
check(managed_support_active($db,'',hash('sha256','stranger'))===null,'other session cannot read ticket');
check((int)managed_support_active($db,'qa-managed',hash('sha256','other-device'))['id']===$id,'authenticated account can return on another device');
$key=hash('sha256','reply');
check(managed_support_reply($db,$ticket,'Domena: firma.example. Strona ma być wizytówką.',$key),'customer can answer immediately');
managed_support_reply($db,$ticket,'Domena: firma.example. Strona ma być wizytówką.',$key);
check(count_rows($db,'support_messages')===3,'uncertain retry does not duplicate customer reply');
$db->exec('UPDATE support_tickets SET status="closed",active_owner_key=NULL WHERE id='.$id);
check(!managed_support_reply($db,$ticket,'Spóźniona wiadomość',hash('sha256','late'))&&count_rows($db,'support_messages')===3,'concurrent close prevents late message');
$db->exec('DROP TEMPORARY TABLE support_ticket_audit');
$db->exec('CREATE TEMPORARY TABLE support_ticket_audit (id INT) ENGINE=InnoDB');
try {managed_support_create($db,'qa-rollback',hash('sha256','new'),'Anna','rollback@example.invalid',hash('sha256','rollback'),hash('sha256','ip'),'payment');throw new RuntimeException('Expected SQL failure');} catch(PDOException $e) {}
check(count_rows($db,'support_tickets')===1&&count_rows($db,'support_messages')===3&&!$db->inTransaction(),'failure rolls back ticket and both initial messages together');
echo "10 scenarios passed; temporary tables disappear at disconnect; no real ticket, mail or notification.\n";

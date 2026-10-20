<?php
declare(strict_types=1);
require_once __DIR__.'/chat_emoji.php';
require_once __DIR__.'/messaging_security.php';
require_once __DIR__.'/chat_media.php';
require_once __DIR__.'/chat_preferences.php';
require_once __DIR__.'/chat_groups.php';
require_once __DIR__.'/chat_group_management.php';
require_once __DIR__.'/chat_requests.php';
require_once __DIR__.'/chat_customization.php';

/** Wspólny backend rozmów 1:1; admin korzysta z bazy bety, nie z bazy ticketów produkcji. */
function chat_profile(array $user): array
{
    return ['id'=>(string)$user['uid'], 'username'=>(string)$user['username'],
        'name'=>(string)($user['name'] ?: $user['username']), 'is_admin'=>(bool)($user['is_superuser']??false)];
}
function chat_user_by_username(string $username): array
{
    $r=authentik_api_request('GET','/api/v3/core/users/?username='.rawurlencode($username).'&page_size=100');
    if ($r['http_code']!==200) throw new RuntimeException('Katalog osób jest chwilowo niedostępny.');
    foreach ($r['body']['results']??[] as $u) {
        if ($u['username']===$username && ($u['is_active']??false) && ($u['type']??'')!=='service_account' && !empty($u['uid'])) return chat_profile($u);
    }
    throw new InvalidArgumentException('Nie znaleziono aktywnego użytkownika.');
}
function chat_actor(): array
{
    $username=(string)($_SESSION['authentik_username']??'');
    if($username==='') json_error('Zaloguj się, aby korzystać z czatu.',401);
    $cached=$_SESSION['chat_identity']??null;
    if(is_array($cached)&&($cached['profile']['username']??'')===$username&&($cached['until']??0)>time())return $cached['profile'];
    $actor=chat_user_by_username($username);
    $_SESSION['chat_identity']=['profile'=>$actor,'until'=>time()+300];
    return $actor;
}
function chat_search(string $query, array $actor): array
{
    $query=ltrim(trim($query),'@');
    if(mb_strlen($query)<2)return [];
    $r=authentik_api_request('GET','/api/v3/core/users/?page_size=30&search='.rawurlencode($query));
    if($r['http_code']!==200)throw new RuntimeException('Wyszukiwanie jest chwilowo niedostępne.');
    $out=[];
    foreach($r['body']['results']??[] as $u){
        if(empty($u['uid'])||!($u['is_active']??false)||($u['type']??'')==='service_account'||$u['uid']===$actor['id'])continue;
        // Publiczna lista zwraca nazwy, nigdy pełnego katalogu adresów e-mail.
        $out[]=chat_profile($u);
    }
    if($viewer!==null){$q=$db->prepare('SELECT DISTINCT peer.user_id FROM chat_members own JOIN chat_members peer ON peer.conversation_id=own.conversation_id AND peer.user_id<>own.user_id JOIN chat_preferences pref ON pref.conversation_id=own.conversation_id AND pref.blocked=1 LEFT JOIN chat_groups g ON g.conversation_id=own.conversation_id WHERE own.user_id=? AND g.conversation_id IS NULL');$q->execute([$viewer]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $peer)if(in_array((string)$peer,$ids,true))$out[$peer]='hidden';}
    return $out;
}
function chat_member(PDO $db,int $id,string $actor): array
{
    $s=$db->prepare('SELECT * FROM chat_members WHERE conversation_id=? AND user_id=?');$s->execute([$id,$actor]);
    $member=$s->fetch(PDO::FETCH_ASSOC);
    $r=$db->prepare("SELECT state FROM chat_requests WHERE conversation_id=?");$r->execute([$id]);
    if($r->fetchColumn()==='rejected')throw new DomainException('Ta rozmowa została usunięta.');
    if(!$member)throw new DomainException('Nie masz dostępu do tej rozmowy.');
    return $member;
}
function chat_emit(PDO $db,int $id): void
{
    $db->prepare('INSERT INTO chat_events(recipient_id,conversation_id) SELECT user_id,conversation_id FROM chat_members WHERE conversation_id=?')->execute([$id]);
}
function chat_start(PDO $db,array $actor,array $peer): int
{
    if($actor['id']===$peer['id'])throw new InvalidArgumentException('Wybierz inną osobę.');
    $ids=[$actor['id'],$peer['id']];sort($ids,SORT_STRING);$pair=hash('sha256',json_encode($ids));
    $db->beginTransaction();
    try{
        $s=$db->prepare('INSERT INTO chat_conversations(pair_key) VALUES(?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$s->execute([$pair]);
        $id=(int)$db->lastInsertId();
        $r=$db->prepare('SELECT state FROM chat_requests WHERE conversation_id=?');$r->execute([$id]);
        if($r->fetchColumn()==='rejected')throw new DomainException('Ta prośba o rozmowę została odrzucona.');
        $db->prepare("INSERT IGNORE INTO chat_requests(conversation_id,requester_id) SELECT ?,? WHERE NOT EXISTS(SELECT 1 FROM chat_messages WHERE conversation_id=?)")->execute([$id,$actor['id'],$id]);
        $db->prepare("UPDATE chat_requests SET requester_id=? WHERE conversation_id=? AND state='draft'")->execute([$actor['id'],$id]);
        foreach([$actor,$peer] as $u){
            $db->prepare('INSERT INTO chat_members(conversation_id,user_id,username,display_name,is_admin) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),display_name=VALUES(display_name),is_admin=VALUES(is_admin)')
                ->execute([$id,$u['id'],$u['username'],$u['name'],$u['is_admin']?1:0]);
        }
        if(!chat_blocked($db,$id))$db->prepare('UPDATE chat_preferences SET hidden=0,archived=0 WHERE conversation_id=? AND user_id=?')->execute([$id,$actor['id']]);
        chat_emit($db,$id);$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chat_inbox(PDO $db,array $actor): array
{
    $s=$db->prepare('SELECT c.id,c.updated_at,g.name AS group_name,req.state AS request_state,(req.state="pending" AND req.requester_id<>a.user_id) AS incoming_request,COALESCE(pref.archived,0) AS archived,COALESCE(pref.blocked,0) AS blocked,EXISTS(SELECT 1 FROM chat_preferences bp WHERE bp.conversation_id=c.id AND bp.blocked=1) AS communication_blocked,EXISTS(SELECT 1 FROM chat_preferences bp WHERE bp.conversation_id=c.id AND bp.user_id<>a.user_id AND bp.blocked=1) AS blocked_by_peer,COALESCE(pref.marked_unread,0) AS marked_unread,(COALESCE(pref.mute_forever,0)=1 OR pref.muted_until>NOW()) AS muted,UNIX_TIMESTAMP(pref.muted_until) AS muted_until, p.user_id AS peer_id,p.username,p.display_name,p.is_admin,p.last_read_id AS peer_read_id,
        (SELECT CASE WHEN m.withdrawn_at IS NOT NULL THEN "Wiadomość została cofnięta." ELSE m.body END FROM chat_messages m WHERE m.conversation_id=c.id AND NOT EXISTS (SELECT 1 FROM chat_message_hides h WHERE h.message_id=m.id AND h.user_id=a.user_id) ORDER BY m.id DESC LIMIT 1) AS preview,
        (SELECT MAX(m.id) FROM chat_messages m WHERE m.conversation_id=c.id AND m.author_id<>a.user_id AND m.withdrawn_at IS NULL AND NOT EXISTS (SELECT 1 FROM chat_message_hides h WHERE h.message_id=m.id AND h.user_id=a.user_id)) AS incoming_message_id,
        (SELECT m.author_id=a.user_id FROM chat_messages m WHERE m.conversation_id=c.id AND NOT EXISTS(SELECT 1 FROM chat_message_hides h WHERE h.message_id=m.id AND h.user_id=a.user_id) ORDER BY m.id DESC LIMIT 1) AS last_mine,
        (SELECT UNIX_TIMESTAMP(m.created_at) FROM chat_messages m WHERE m.conversation_id=c.id AND NOT EXISTS(SELECT 1 FROM chat_message_hides h WHERE h.message_id=m.id AND h.user_id=a.user_id) ORDER BY m.id DESC LIMIT 1) AS last_timestamp,
        (SELECT created_at FROM chat_messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_at,
        (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id=c.id AND m.author_id<>a.user_id AND m.id>a.last_read_id) AS unread
        FROM chat_members a JOIN chat_conversations c ON c.id=a.conversation_id
        JOIN chat_members p ON p.conversation_id=c.id AND p.user_id=(SELECT MIN(other.user_id) FROM chat_members other WHERE other.conversation_id=c.id AND other.user_id<>a.user_id)
        LEFT JOIN chat_groups g ON g.conversation_id=c.id
        LEFT JOIN chat_requests req ON req.conversation_id=c.id
        LEFT JOIN chat_preferences pref ON pref.conversation_id=c.id AND pref.user_id=a.user_id
        WHERE a.user_id=? AND COALESCE(pref.hidden,0)=0 AND (req.state IS NULL OR req.state<>"rejected") AND (req.state IS NULL OR req.state<>"draft" OR req.requester_id=a.user_id) ORDER BY c.updated_at DESC,c.id DESC LIMIT 100');
    $s->execute([$actor['id']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){$row['id']=(int)$row['id'];$row['unread']=(int)$row['unread'];$row['is_admin']=(bool)$row['is_admin'];foreach(['archived','blocked','marked_unread','muted','last_mine','incoming_request','communication_blocked','blocked_by_peer'] as $flag)$row[$flag]=(bool)$row[$flag];}
    $total=$db->prepare('SELECT COUNT(*) FROM chat_messages m JOIN chat_members a ON a.conversation_id=m.conversation_id WHERE a.user_id=? AND m.author_id<>a.user_id AND m.id>a.last_read_id');
    $total->execute([$actor['id']]);
    unset($row);
    $presence=chat_presence_map($db,array_column($rows,'peer_id'),$actor['id']);
    foreach($rows as &$row)$row['presence']=$presence[$row['peer_id']]??'offline';
    unset($row);
    foreach($rows as &$row)if($row['communication_blocked']){$row['archived']=true;$row['incoming_request']=false;}unset($row);
    chat_group_inbox($db,$rows,$actor);chat_customize_inbox($db,$rows,$actor);foreach($rows as &$r)if(!empty($r['group_invitation'])){$r['preview']='Zaproszenie do grupy';$r['preview_formatted']=true;$r['last_mine']=false;$r['incoming_message_id']=0;}unset($r);
    $selfPresence=chat_presence_map($db,[$actor['id']]);
    return ['self_presence'=>$selfPresence[$actor['id']]??'offline','conversations'=>$rows,'unread'=>array_sum(array_map(fn($r)=>$r['muted']?0:max($r['unread'],$r['marked_unread']?1:0),$rows)),'me'=>$actor];
}
function chat_messages(PDO $db,int $id,array $actor,int $before=0): array
{
    chat_member($db,$id,$actor['id']);
    $access=chat_group_access($db,$id,$actor['id']);if($access&&in_array($access['state'],['invited','ignored'],true))return ['group_access'=>$access,'blocked'=>false,'blocked_by_peer'=>false,'can_write'=>false,'messages'=>[],'has_more'=>false,'before_id'=>null];
    $s=$db->prepare('SELECT m.id,m.author_id,m.body,m.created_at,m.edited_at,m.withdrawn_at,m.reply_to_message_id,
        a.display_name,a.username, UNIX_TIMESTAMP(m.created_at)+300 AS edit_expires_at,
        parent.author_id AS reply_author_id,parent.body AS reply_body,parent.withdrawn_at AS reply_withdrawn_at,parent_author.display_name AS reply_author_name, parent_hide.message_id AS reply_hidden
      FROM chat_messages m
      JOIN chat_members a ON a.conversation_id=m.conversation_id AND a.user_id=m.author_id
      LEFT JOIN chat_message_hides hidden ON hidden.message_id=m.id AND hidden.user_id=?
      LEFT JOIN chat_messages parent ON parent.id=m.reply_to_message_id AND parent.conversation_id=m.conversation_id
      LEFT JOIN chat_members parent_author ON parent_author.conversation_id=m.conversation_id AND parent_author.user_id=parent.author_id
      LEFT JOIN chat_message_hides parent_hide ON parent_hide.message_id=parent.id AND parent_hide.user_id=?
      WHERE m.conversation_id=? AND hidden.message_id IS NULL'.($before>0?' AND m.id<?':'').' ORDER BY m.id DESC LIMIT 51');
    $s->execute($before>0?[$actor['id'],$actor['id'],$id,$before]:[$actor['id'],$actor['id'],$id]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    $more=count($rows)>50;if($more)array_pop($rows);$rows=array_reverse($rows);
    $ids=array_map(static fn(array $row): int=>(int)$row['id'],$rows);$reactions=[];
    if($ids){
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$db->prepare("SELECT message_id,emoji,COUNT(*) AS total,SUM(user_id=?) AS mine FROM chat_message_reactions WHERE message_id IN ($marks) GROUP BY message_id,emoji ORDER BY emoji");
        $q->execute(array_merge([$actor['id']],$ids));
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $reaction)$reactions[(int)$reaction['message_id']][]=['emoji'=>(string)$reaction['emoji'],'count'=>(int)$reaction['total'],'mine'=>(bool)$reaction['mine']];
    }
    $media=chat_media_for_messages($db,$ids);
    foreach($rows as &$row){
        $row['id']=(int)$row['id'];$row['mine']=$row['author_id']===$actor['id'];$withdrawn=$row['withdrawn_at']!==null;
        $row['attachments']=$withdrawn?[]:($media[$row['id']]??[]);
        $row['is_withdrawn']=$withdrawn;$row['body']=$withdrawn?'Wiadomość została cofnięta.':(string)$row['body'];
        $row['reply_to_message_id']=$row['reply_to_message_id']!==null?(int)$row['reply_to_message_id']:null;
        if($row['reply_to_message_id']!==null&&$row['reply_hidden']===null)$row['reply']=['id'=>$row['reply_to_message_id'],'mine'=>$row['reply_author_id']===$actor['id'],'self'=>$row['reply_author_id']===$row['author_id'],'author_name'=>(string)($row['reply_author_name']??''),'body'=>($row['reply_withdrawn_at']!==null?'Wiadomość została cofnięta.':(string)($row['reply_body']??'')),'is_withdrawn'=>$row['reply_withdrawn_at']!==null];
        $row['reactions']=$withdrawn?[]:($reactions[$row['id']]??[]);
        unset($row['reply_author_id'],$row['author_id'],$row['reply_body'],$row['reply_withdrawn_at'],$row['reply_author_name'],$row['reply_hidden']);
    }
    unset($row);chat_customize_rows($db,$id,$rows,$actor);
    $q=$db->prepare('SELECT user_id FROM chat_preferences WHERE conversation_id=? AND blocked=1');$q->execute([$id]);$blockers=$q->fetchAll(PDO::FETCH_COLUMN);
    $groupAccess=chat_group_access($db,$id,$actor['id']);
    return ['group_access'=>$groupAccess,'blocked'=>in_array($actor['id'],$blockers,true),'blocked_by_peer'=>(bool)array_diff($blockers,[$actor['id']]),'can_write'=>!$blockers&&($groupAccess===null||$groupAccess['can_write']),'messages'=>$rows,'has_more'=>$more,'before_id'=>$more?(int)$rows[0]['id']:null];
}
function chat_send(PDO $db,int $id,array $actor,string $body,string $key,int $replyTo=0,array $uploads=[]): int
{
    $body=trim(str_replace(["\r\n","\r"],"\n",$body));
    chat_member($db,$id,$actor['id']);
    $files=support_message_validate_uploaded_media($uploads);
    if(($body===''&&!$files)||mb_strlen($body)>4000||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Wpisz wiadomość (do 4000 znaków) albo załącz zdjęcie lub film.');
    $stored=[];
    $db->beginTransaction();
    try{
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);
        if(chat_blocked($db,$id))throw new DomainException('Wysyłanie wiadomości w tej rozmowie jest zablokowane.');chat_group_assert($db,$id,$actor['id']);
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);
        $s=$db->prepare('SELECT id,conversation_id,body FROM chat_messages WHERE author_id=? AND client_key=?');$s->execute([$actor['id'],$key]);
        $old=$s->fetch(PDO::FETCH_ASSOC);
        if($old){
            if((int)$old['conversation_id']!==$id||$old['body']!==$body)throw new InvalidArgumentException('Identyfikator tej wiadomości został już użyty.');
            $db->commit();return (int)$old['id'];
        }
        chat_request_on_send($db,$id,$actor['id']);
        if($replyTo>0){$reply=$db->prepare('SELECT id FROM chat_messages WHERE id=? AND conversation_id=?');$reply->execute([$replyTo,$id]);if(!$reply->fetchColumn())$replyTo=0;}
        $db->prepare('INSERT INTO chat_messages(conversation_id,author_id,body,reply_to_message_id,client_key) VALUES(?,?,?,?,?)')->execute([$id,$actor['id'],$body,$replyTo?:null,$key]);
        $message=(int)$db->lastInsertId();
        chat_store_media($db,$message,$files,$stored);
        $db->prepare('UPDATE chat_preferences SET archived=0,hidden=0 WHERE conversation_id=?')->execute([$id]);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);
        chat_emit($db,$id);$db->commit();return $message;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();foreach($stored as $path){if(is_file($path))@unlink($path);}throw $e;}
}
function chat_own_message(PDO $db,int $conversationId,int $messageId,array $actor): array
{
    $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$conversationId]);chat_member($db,$conversationId,$actor['id']);chat_group_assert($db,$conversationId,$actor['id']);
    $q=$db->prepare('SELECT *, created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AS edit_allowed FROM chat_messages WHERE id=? AND conversation_id=? AND author_id=? FOR UPDATE');$q->execute([$messageId,$conversationId,$actor['id']]);
    $message=$q->fetch(PDO::FETCH_ASSOC);if(!$message)throw new DomainException('Nie możesz zmienić tej wiadomości.');
    $q=$db->prepare('SELECT message_id FROM chat_system_messages WHERE message_id=?');$q->execute([$messageId]);if($q->fetchColumn())throw new DomainException('Komunikatów systemowych nie można zmieniać.');
    return $message;
}
function chat_edit(PDO $db,int $id,int $messageId,array $actor,string $body): void
{
    $body=trim(str_replace(["\r\n","\r"],"\n",$body));if($body===''||mb_strlen($body)>4000)throw new InvalidArgumentException('Wiadomość musi mieć od 1 do 4000 znaków.');
    $db->beginTransaction();
    try{
        $message=chat_own_message($db,$id,$messageId,$actor);if($message['withdrawn_at']!==null)throw new InvalidArgumentException('Cofniętej wiadomości nie można edytować.');
        if (!$message['edit_allowed']) throw new DomainException('Wiadomość można edytować tylko przez 5 minut od wysłania.');
        $db->prepare('UPDATE chat_messages SET body=?,edited_at=NOW() WHERE id=?')->execute([$body,$messageId]);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);chat_emit($db,$id);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chat_withdraw(PDO $db,int $id,int $messageId,array $actor,string $scope): void
{
    $db->beginTransaction();
    try{
        $message=chat_own_message($db,$id,$messageId,$actor);
        if($scope==='me')$db->prepare('INSERT IGNORE INTO chat_message_hides(message_id,user_id) VALUES(?,?)')->execute([$messageId,$actor['id']]);
        elseif($message['withdrawn_at']===null)$db->prepare('UPDATE chat_messages SET body="",withdrawn_at=NOW() WHERE id=?')->execute([$messageId]);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);chat_emit($db,$id);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chat_react(PDO $db,int $id,int $messageId,array $actor,string $emoji): void
{
    if(!chat_valid_emoji($emoji))throw new InvalidArgumentException('Wybierz prawidłowe emoji.');
    chat_member($db,$id,$actor['id']);$db->beginTransaction();
    try{
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);
        if(chat_blocked($db,$id))throw new DomainException('Reakcje w tej rozmowie są zablokowane.');chat_group_assert($db,$id,$actor['id']);
        $q=$db->prepare('SELECT withdrawn_at FROM chat_messages WHERE id=? AND conversation_id=? FOR UPDATE');$q->execute([$messageId,$id]);$message=$q->fetch(PDO::FETCH_ASSOC);if(!$message||$message['withdrawn_at']!==null)throw new InvalidArgumentException('Nie można zareagować na tę wiadomość.');
        $q=$db->prepare('DELETE FROM chat_message_reactions WHERE message_id=? AND user_id=? AND emoji=?');$q->execute([$messageId,$actor['id'],$emoji]);
        if(!$q->rowCount()){$db->prepare('INSERT INTO chat_message_reactions(message_id,user_id,emoji) VALUES(?,?,?)')->execute([$messageId,$actor['id'],$emoji]);$db->prepare('INSERT INTO chat_reaction_activity(conversation_id,message_id,actor_id,emoji,anchor_id) SELECT ?,?,?,?,COALESCE(MAX(id),0) FROM chat_messages WHERE conversation_id=?')->execute([$id,$messageId,$actor['id'],$emoji,$id]);}
        chat_emit($db,$id);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chat_read(PDO $db,int $id,array $actor,int $last): void
{
    chat_member($db,$id,$actor['id']);
    $s=$db->prepare('SELECT id FROM chat_messages WHERE id=? AND conversation_id=?');$s->execute([$last,$id]);
    if(!$s->fetchColumn())return;
    $db->prepare('UPDATE chat_preferences SET marked_unread=0 WHERE conversation_id=? AND user_id=?')->execute([$id,$actor['id']]);
    $s=$db->prepare('UPDATE chat_members SET last_read_id=? WHERE conversation_id=? AND user_id=? AND last_read_id<?');
    $s->execute([$last,$id,$actor['id'],$last]);if($s->rowCount())chat_emit($db,$id);
}
function chat_token(PDO $db,array $actor): string
{
    return msg_ws_token($db,'chat',$actor['id'],0,'user',$actor['username']);
}
function chat_endpoint(PDO $db): never
{
    try{
        $actor=chat_actor();
        msg_security_session($db);
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if($method==='GET'||$method==='HEAD'){
            rate_limit_guard('chat-read-'.substr(hash('sha256',$actor['id']),0,24),[240,60],'Zbyt wiele odświeżeń czatu.');
            $action=is_string($_GET['action']??null)?$_GET['action']:'inbox';
            if($action==='media'){ $file=chat_media_file($db,(int)($_GET['attachment_id']??0),$actor['id']); if(!$file){http_response_code(404);exit;} support_media_response($file); }
        if($action==='typing'){ $id=(int)($_GET['id']??0);chat_member($db,$id,$actor['id']);$q=$db->prepare('SELECT m.username,m.display_name FROM chat_typing t JOIN chat_members m ON m.conversation_id=t.conversation_id AND m.user_id=t.user_id WHERE t.conversation_id=? AND t.user_id<>? AND t.expires_at>NOW() ORDER BY t.expires_at DESC LIMIT 3');$q->execute([$id,$actor['id']]);$people=chat_blocked($db,$id)?[]:$q->fetchAll(PDO::FETCH_ASSOC);json_ok(['typing'=>(bool)$people,'people'=>$people]); }
            if($action==='inbox')json_ok(chat_inbox($db,$actor));
            if($action==='search'){
                $q=is_string($_GET['q']??null)?mb_substr($_GET['q'],0,150):'';
                rate_limit_guard('chat-search-'.substr(hash('sha256',$actor['id']),0,24),[30,60],'Odczekaj chwilę przed kolejnym wyszukiwaniem.');
                $people=chat_search($q,$actor);
                $presence=chat_presence_map($db,array_column($people,'id'),$actor['id']);
                foreach($people as &$person)$person['presence']=$presence[$person['id']]??'offline';
                unset($person);
                json_ok(['people'=>$people]);
            }
            if($action==='messages')json_ok(chat_messages($db,(int)($_GET['id']??0),$actor,max(0,(int)($_GET['before']??0))));
            json_error('Nieznana operacja.',400);
        }
        require_method('POST');csrf_guard();
        $action=request_string('action');$id=(int)request_string('id');
        $bucket=substr(hash('sha256',$actor['id']),0,24);
        if($action==='group_manage'){rate_limit_guard('chat-manage-'.$bucket,[40,60],'Zbyt wiele zmian.');chat_group_manage($db,$id,$actor,request_string('operation'),request_string('target'),request_string('setting'),(int)request_string('value'),request_string('client_key'));json_ok();}
        if($action==='customize'){rate_limit_guard('chat-customize-'.$bucket,[20,60],'Zbyt wiele zmian.');json_ok(['id'=>chat_customize($db,$id,$actor,request_string('kind'),request_string('value'),request_string('target'),request_string('client_key'),$_FILES['attachments']??[])]);}
        if($action==='reject_request'){rate_limit_guard('chat-reject-'.$bucket,[30,60],'Zbyt wiele zmian.');chat_request_reject($db,$id,$actor);json_ok();}
        if($action==='preference'){rate_limit_guard('chat-options-'.$bucket,[60,60],'Zbyt wiele zmian.');chat_preference($db,$id,$actor,request_string('operation'),(int)request_string('minutes'));json_ok();}
        if($action==='typing'){
            chat_member($db,$id,$actor['id']);
            rate_limit_guard('chat-typing-'.$bucket,[100,60],'Zbyt wiele aktualizacji pisania.');
            $groupAccess=chat_group_access($db,$id,$actor['id']);$active=request_string('active')==='1'&&!chat_blocked($db,$id)&&($groupAccess===null||$groupAccess['can_write']);
            $q=$db->prepare('INSERT INTO chat_typing(conversation_id,user_id,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at)');
            $q->execute([$id,$actor['id'],$active?5:0]);json_ok();
        }
        if($action==='presence'){
            rate_limit_guard('chat-presence-'.$bucket,[90,60],'Zbyt wiele aktualizacji obecności.');
            chat_presence_update($db,$actor,request_string('client_id'),request_string('visible')==='1',max(0,min(86400,(int)request_string('idle_seconds'))),request_string('leaving')==='1');
            $presence=chat_presence_map($db,[$actor['id']]);json_ok(['self_presence'=>$presence[$actor['id']]??'offline']);
        }
        if($action==='create_group'){
            rate_limit_guard('chat-group-'.$bucket,[5,60],'Odczekaj chwilę przed utworzeniem kolejnej grupy.');
            $names=json_decode(request_string('members'),true);
            if(!is_array($names)||count($names)<2||count($names)>19)throw new InvalidArgumentException('Wybierz od 2 do 19 osób.');
            $peers=[];foreach($names as $name){if(!is_string($name)||strlen($name)>150)throw new InvalidArgumentException('Nieprawidłowy uczestnik.');$peers[]=chat_user_by_username($name);}
            json_ok(['id'=>chat_start_group($db,$actor,$peers,request_string('name'),request_string('client_key'))]);
        }
        if($action==='start'){
            rate_limit_guard('chat-start-'.$bucket,[20,60],'Zbyt wiele nowych rozmów.');
            $peer=chat_user_by_username(request_string('username'));
            json_ok(['id'=>chat_start($db,$actor,$peer)]);
        }
        if($action==='send'){
            msg_rate_guard($db);
            json_ok(['id'=>chat_send($db,$id,$actor,request_string('message'),request_string('client_key'),(int)request_string('reply_to_id'),$_FILES['attachments']??[])]);
        }
        if($action==='edit'){
            rate_limit_guard('chat-edit-'.$bucket,[30,60],'Zbyt wiele zmian wiadomości.');
            chat_edit($db,$id,(int)request_string('message_id'),$actor,request_string('message'));json_ok();
        }
        if($action==='withdraw'){
            rate_limit_guard('chat-withdraw-'.$bucket,[30,60],'Zbyt wiele operacji na wiadomościach.');
            chat_withdraw($db,$id,(int)request_string('message_id'),$actor,request_string('scope'));json_ok();
        }
        if($action==='react'){
            rate_limit_guard('chat-react-'.$bucket,[90,60],'Zbyt wiele reakcji.');
            chat_react($db,$id,(int)request_string('message_id'),$actor,request_string('emoji'));json_ok();
        }
        if($action==='read'){
            rate_limit_guard('chat-receipts-'.$bucket,[120,60],'Zbyt wiele operacji czatu.');
            chat_read($db,$id,$actor,(int)request_string('last_id'));json_ok();
        }
        if($action==='token'){
            rate_limit_guard('chat-token-'.$bucket,[20,60],'Odczekaj chwilę przed ponownym połączeniem.');
            json_ok(['token'=>chat_token($db,$actor),'ws_path'=>'/ws/support']);
        }
        json_error('Nieznana operacja.',400);
    }catch(DomainException $e){msg_security_log($db,'chat_access_denied');json_error($e->getMessage(),403);}
    catch(InvalidArgumentException $e){json_error($e->getMessage(),422);}
    catch(Throwable $e){msg_security_log($db,'chat_backend_error');json_error('Czat jest chwilowo niedostępny. Twoja wiadomość pozostała w polu tekstowym.',503);}
}

/** Każda karta ma osobny heartbeat: zamknięcie jednej nie wylogowuje pozostałych. */
function chat_presence_update(PDO $db,array $actor,string $client,bool $visible,int $idle,bool $leaving=false): void
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$client))throw new InvalidArgumentException('Nieprawidłowa karta czatu.');
    if($leaving){
        $db->prepare('UPDATE chat_presence SET seen_at=DATE_SUB(NOW(),INTERVAL 91 SECOND),visible=0 WHERE user_id=? AND client_id=?')->execute([$actor['id'],$client]);
        return;
    }
    $idle=max(0,min(86400,$idle));
    $db->prepare('INSERT INTO chat_presence(user_id,client_id,visible,last_active_at,seen_at) VALUES(?,?,?,DATE_SUB(NOW(),INTERVAL ? SECOND),NOW()) ON DUPLICATE KEY UPDATE visible=VALUES(visible),last_active_at=VALUES(last_active_at),seen_at=NOW()')->execute([$actor['id'],$client,$visible?1:0,$idle]);
}
function chat_presence_map(PDO $db,array $ids,?string $viewer=null): array
{
    $ids=array_values(array_unique(array_filter(array_map('strval',$ids))));
    if(!$ids)return [];
    $q=$db->prepare('SELECT user_id,MAX(visible=1 AND last_active_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)) AS active FROM chat_presence WHERE seen_at>DATE_SUB(NOW(),INTERVAL 90 SECOND) AND user_id IN ('.implode(',',array_fill(0,count($ids),'?')).') GROUP BY user_id');
    $q->execute($ids);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['user_id']]=$row['active']?'online':'away';
    if($viewer!==null){$q=$db->prepare('SELECT DISTINCT peer.user_id FROM chat_members own JOIN chat_members peer ON peer.conversation_id=own.conversation_id AND peer.user_id<>own.user_id JOIN chat_preferences pref ON pref.conversation_id=own.conversation_id AND pref.blocked=1 LEFT JOIN chat_groups g ON g.conversation_id=own.conversation_id WHERE own.user_id=? AND g.conversation_id IS NULL');$q->execute([$viewer]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $peer)if(in_array((string)$peer,$ids,true))$out[$peer]='hidden';}
    return $out;
}

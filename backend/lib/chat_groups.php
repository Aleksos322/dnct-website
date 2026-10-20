<?php
declare(strict_types=1);
function chat_start_group(PDO $db,array $actor,array $peers,string $name,string $key): int {
    $name=trim($name);
    if($name===''||mb_strlen($name)>100||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Podaj nazwę grupy (do 100 znaków).');
    $members=[$actor['id']=>$actor];foreach($peers as $peer)$members[$peer['id']]=$peer;
    if(count($members)<3||count($members)>20)throw new InvalidArgumentException('Wybierz od 2 do 19 innych osób.');
    $db->beginTransaction();
    try {
        // Do not allow a new group to circumvent an existing direct-chat block.
        $blocked=$db->prepare('SELECT 1 FROM chat_members a JOIN chat_members b ON b.conversation_id=a.conversation_id JOIN chat_preferences p ON p.conversation_id=a.conversation_id AND p.blocked=1 LEFT JOIN chat_groups g ON g.conversation_id=a.conversation_id WHERE a.user_id=? AND b.user_id=? AND g.conversation_id IS NULL LIMIT 1');
        foreach($members as $id=>$member){if((string)$id===$actor['id'])continue;$blocked->execute([$actor['id'],$id]);if($blocked->fetchColumn())throw new DomainException('Nie można utworzyć grupy z wybranymi osobami z powodu blokady rozmowy.');}
        $pair=hash('sha256','group:'.$actor['id'].':'.$key);
        $db->prepare('INSERT INTO chat_conversations(pair_key) VALUES(?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$pair]);$id=(int)$db->lastInsertId();
        $q=$db->prepare('SELECT conversation_id FROM chat_groups WHERE conversation_id=?');$q->execute([$id]);
        if($q->fetchColumn()){$db->commit();return $id;}
        $db->prepare('INSERT INTO chat_groups(conversation_id,name,created_by) VALUES(?,?,?)')->execute([$id,$name,$actor['id']]);
        $q=$db->prepare('INSERT INTO chat_members(conversation_id,user_id,username,display_name,is_admin) VALUES(?,?,?,?,?)');
        foreach($members as $m){$q->execute([$id,$m['id'],$m['username'],$m['name'],$m['is_admin']?1:0]);if($m['id']!==$actor['id']){try{$contact=chat_group_contact($db,$actor['id'],$m['username']);$consent=!empty($contact['needs_consent']);}catch(DomainException $e){$consent=true;}if($consent)$db->prepare("INSERT INTO chat_group_membership(conversation_id,user_id,state) VALUES(?,?,'invited')")->execute([$id,$m['id']]);}}
        chat_emit($db,$id);$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function chat_group_inbox(PDO $db,array &$rows,array $actor): void {
    $ids=[];foreach($rows as $row)if($row['group_name']!==null)$ids[]=(int)$row['id'];
    if(!$ids)return;
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$db->prepare("SELECT m.conversation_id,m.user_id,m.username,m.display_name,m.last_read_id,
        GREATEST(COALESCE((SELECT MAX(p.last_active_at) FROM chat_presence p WHERE p.user_id=m.user_id),'1970-01-01'),COALESCE((SELECT MAX(msg.created_at) FROM chat_messages msg WHERE msg.conversation_id=m.conversation_id AND msg.author_id=m.user_id),'1970-01-01')) AS last_active
        FROM chat_members m LEFT JOIN chat_group_membership gm ON gm.conversation_id=m.conversation_id AND gm.user_id=m.user_id WHERE m.conversation_id IN ($marks) AND m.user_id<>? AND COALESCE(gm.state,'active')='active' ORDER BY m.conversation_id,last_active DESC,m.username");
    $q->execute([...$ids,$actor['id']]);$members=$q->fetchAll(PDO::FETCH_ASSOC);$presence=chat_presence_map($db,array_column($members,'user_id'),$actor['id']);$byGroup=[];
    foreach($members as $m){$m['presence']=$presence[$m['user_id']]??'offline';$byGroup[(int)$m['conversation_id']][]=$m;}
    foreach($rows as &$row){if($row['group_name']===null)continue;$others=$byGroup[$row['id']]??[];
        $row['group_access']=chat_group_access($db,(int)$row['id'],$actor['id']);$row['group_people']=chat_group_people($db,(int)$row['id']);$row['group_invitation']=$row['group_access']['state']==='invited';if($row['group_invitation']){$row['incoming_request']=true;$row['request_state']='pending';$row['preview']='Zaproszenie do grupy';$row['unread']=1;}
        $row['group_pending']=[];if($row['group_access']['manager']){$pending=$db->prepare('SELECT username,display_name FROM chat_group_invitations WHERE conversation_id=?');$pending->execute([$row['id']]);$row['group_pending']=$pending->fetchAll(PDO::FETCH_ASSOC);}
        $row['is_group']=true;$row['display_name']=$row['group_name'];$row['username']='';$row['is_admin']=false;
        $row['presence']=in_array('online',array_column($others,'presence'),true)?'online':'offline';
        $row['members']=array_map(fn($m)=>['username'=>$m['username'],'display_name'=>$m['display_name'],'presence'=>$m['presence'],'last_read_id'=>(int)$m['last_read_id']],$others);
        $row['avatar_members']=array_slice($row['members'],0,2);$row['member_count']=count(array_filter($row['group_people'],fn($p)=>$p['state']==='active'));
        $row['peer_read_id']=$others?max(array_map(fn($m)=>(int)$m['last_read_id'],$others)):0;
    }unset($row);$rows=array_values(array_filter($rows,fn($r)=>($r['group_access']['state']??'')!=='ignored'));
}

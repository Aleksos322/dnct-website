<?php
declare(strict_types=1);
function chat_custom_members(PDO $db,int $id): array {
 $q=$db->prepare("SELECT m.user_id,m.username,m.display_name,COALESCE(NULLIF(n.nickname,''),m.display_name) AS name FROM chat_members m LEFT JOIN chat_nicknames n ON n.conversation_id=m.conversation_id AND n.user_id=m.user_id WHERE m.conversation_id=?");$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function chat_system_text(string $kind,array $p,bool $mine,string $name,bool $preview=false): string {
 if($kind==='group_event')return $mine?$p['own']:($preview?'':$name.' ').$p['other'];
 if($kind==='name')return $preview?($mine?'Zmieniłeś(aś) nazwę grupy':'Zmienił(a) nazwę grupy'):($mine?'Nadałeś(aś) grupie nazwę ':$name.' zmienił(a) nazwę grupy na ').$p['value'].'.';
 if($kind==='avatar')return ($preview?'':($mine?'':$name.' ')).($mine?'Zmieniłeś(aś) zdjęcie grupy':($preview?'Zmienił(a) zdjęcie grupy':'zmienił(a) zdjęcie grupy')).'.';
 return ($preview?'':($mine?'':$name.' ')).($mine?'Ustawiłeś(aś) pseudonim ':($preview?'Ustawił(a) pseudonim ':'ustawił(a) pseudonim ')).$p['target_name'].' na „'.$p['value'].'”.';
}
function chat_customize(PDO $db,int $id,array $actor,string $kind,string $value,string $target,string $key,array $uploads=[]): int {
 if(!in_array($kind,['name','avatar','nickname'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Nieprawidłowa zmiana.');
 $value=trim($value);if($kind!=='avatar'&&($value===''||mb_strlen($value)>100))throw new InvalidArgumentException('Wpisz od 1 do 100 znaków.');
 $files=$kind==='avatar'?support_message_validate_uploaded_media($uploads):[];
 if($kind==='avatar'&&(count($files)!==1||$files[0]['kind']!=='image'))throw new InvalidArgumentException('Wybierz jedno zdjęcie grupy.');
 $stored=[];$db->beginTransaction();try{
 $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);chat_member($db,$id,$actor['id']);chat_group_assert($db,$id,$actor['id'],$kind);
 if(chat_blocked($db,$id))throw new DomainException('Rozmowa jest zablokowana.');
 $q=$db->prepare("SELECT state FROM chat_requests WHERE conversation_id=?");$q->execute([$id]);$state=$q->fetchColumn();if($state&&$state!=='accepted')throw new DomainException('Najpierw zaakceptuj prośbę o rozmowę.');
 $q=$db->prepare('SELECT id FROM chat_messages WHERE author_id=? AND client_key=? AND conversation_id=?');$q->execute([$actor['id'],$key,$id]);if($old=$q->fetchColumn()){$db->commit();return (int)$old;}
 $members=chat_custom_members($db,$id);$payload=['value'=>$value];
 if($kind==='nickname'){$member=null;foreach($members as $m)if($m['username']===$target)$member=$m;if(!$member)throw new InvalidArgumentException('Wybierz uczestnika rozmowy.');$payload['target_name']=$member['display_name'];$payload['target_id']=$member['user_id'];$db->prepare('INSERT INTO chat_nicknames(conversation_id,user_id,nickname) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nickname=VALUES(nickname)')->execute([$id,$member['user_id'],$value]);}
 else{$q=$db->prepare('SELECT name FROM chat_groups WHERE conversation_id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('To nie jest grupa.');if($kind==='name')$db->prepare('UPDATE chat_groups SET name=? WHERE conversation_id=?')->execute([$value,$id]);}
 $db->prepare('INSERT INTO chat_messages(conversation_id,author_id,body,client_key) VALUES(?,?,?,?)')->execute([$id,$actor['id'],'',$key]);$message=(int)$db->lastInsertId();
 $db->prepare('INSERT INTO chat_system_messages(message_id,kind,payload) VALUES(?,?,?)')->execute([$message,$kind,json_encode($payload,JSON_UNESCAPED_UNICODE)]);
 if($kind==='avatar'){chat_store_media($db,$message,$files,$stored);$q=$db->prepare('SELECT id FROM chat_message_attachments WHERE message_id=?');$q->execute([$message]);$db->prepare('INSERT INTO chat_group_avatars(conversation_id,attachment_id) VALUES(?,?) ON DUPLICATE KEY UPDATE attachment_id=VALUES(attachment_id)')->execute([$id,$q->fetchColumn()]);}
 $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);chat_emit($db,$id);$db->commit();return $message;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();foreach($stored as $path)if(is_file($path))@unlink($path);throw $e;}
}
function chat_customize_rows(PDO $db,int $id,array &$rows,array $actor): void {
 $members=chat_custom_members($db,$id);$byName=[];foreach($members as $m)$byName[$m['username']]=$m['name'];
 $ids=array_column($rows,'id');$systems=[];if($ids){$q=$db->prepare('SELECT * FROM chat_system_messages WHERE message_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$systems[$r['message_id']]=$r;}
 foreach($rows as &$row){$row['display_name']=$byName[$row['username']]??$row['display_name'];if(isset($systems[$row['id']])){$r=$systems[$row['id']];$row['system']=true;$row['body']=chat_system_text($r['kind'],json_decode($r['payload'],true),$row['mine'],$row['display_name']);$row['attachments']=[];$row['reactions']=[];}}
}
function chat_customize_inbox(PDO $db,array &$rows,array $actor): void {
 if(!$rows)return;$ids=array_column($rows,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
 $q=$db->prepare("SELECT m.conversation_id,m.user_id,m.username,m.display_name,COALESCE(NULLIF(n.nickname,''),m.display_name) AS name FROM chat_members m LEFT JOIN chat_nicknames n ON n.conversation_id=m.conversation_id AND n.user_id=m.user_id WHERE m.conversation_id IN ($marks)");$q->execute($ids);$membersBy=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $m)$membersBy[$m['conversation_id']][]=$m;
 $q=$db->prepare("SELECT conversation_id,attachment_id FROM chat_group_avatars WHERE conversation_id IN ($marks)");$q->execute($ids);$avatars=$q->fetchAll(PDO::FETCH_KEY_PAIR);
 $q=$db->prepare("SELECT m.conversation_id,m.id,m.author_id,s.kind,s.payload FROM chat_messages m LEFT JOIN chat_system_messages s ON s.message_id=m.id WHERE m.conversation_id IN ($marks) AND m.id=(SELECT MAX(mm.id) FROM chat_messages mm LEFT JOIN chat_message_hides h ON h.message_id=mm.id AND h.user_id=? WHERE mm.conversation_id=m.conversation_id AND h.message_id IS NULL)");$q->execute([...$ids,$actor['id']]);$lasts=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $m)$lasts[$m['conversation_id']]=$m;
 $q=$db->prepare("SELECT a.*,UNIX_TIMESTAMP(a.created_at) AS ts FROM chat_reaction_activity a JOIN chat_message_reactions r ON r.message_id=a.message_id AND r.user_id=a.actor_id AND r.emoji=a.emoji JOIN chat_messages m ON m.id=a.message_id LEFT JOIN chat_message_hides h ON h.message_id=m.id AND h.user_id=? WHERE a.conversation_id IN ($marks) AND a.actor_id<>? AND m.withdrawn_at IS NULL AND h.message_id IS NULL AND a.id=(SELECT MAX(latest.id) FROM chat_reaction_activity latest JOIN chat_message_reactions lr ON lr.message_id=latest.message_id AND lr.user_id=latest.actor_id AND lr.emoji=latest.emoji JOIN chat_messages lm ON lm.id=latest.message_id LEFT JOIN chat_message_hides lh ON lh.message_id=lm.id AND lh.user_id=? WHERE latest.conversation_id=a.conversation_id AND latest.actor_id<>? AND lm.withdrawn_at IS NULL AND lh.message_id IS NULL)");$q->execute([$actor['id'],...$ids,$actor['id'],$actor['id'],$actor['id']]);$reactions=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$reactions[$r['conversation_id']]=$r;
 foreach($rows as &$row){$id=(int)$row['id'];$members=$membersBy[$id]??[];$byId=[];$byUsername=[];foreach($members as $m){$byId[$m['user_id']]=$m['name'];$byUsername[$m['username']]=$m['name'];if(!$row['group_name']&&$m['user_id']!==$actor['id'])$row['display_name']=$m['name'];}
 $row['participants']=array_map(fn($m)=>['username'=>$m['username'],'display_name'=>$m['display_name'],'nickname'=>$m['name']],$members);
 foreach(['members','avatar_members'] as $field)if(isset($row[$field]))foreach($row[$field] as &$member)$member['display_name']=$byUsername[$member['username']]??$member['display_name'];unset($member);
 $avatar=$avatars[$id]??null;$row['group_avatar_url']=$avatar?(defined('ADMIN_BASE_PATH')?'/czat-api?action=media':'/api/chat.php?action=media').'&attachment_id='.$avatar:null;
 $last=$lasts[$id]??null;if($last&&$last['kind']){$mine=$last['author_id']===$actor['id'];$row['preview']=($mine?'Ty':($byId[$last['author_id']]??'Użytkownik')).': '.chat_system_text($last['kind'],json_decode($last['payload'],true),$mine,$byId[$last['author_id']]??'Użytkownik',true);$row['preview_formatted']=true;}
 $reaction=$reactions[$id]??null;if($reaction&&$last&&(int)$reaction['anchor_id']>=(int)$last['id']){$row['preview']=($byId[$reaction['actor_id']]??'Użytkownik').' zareagował(a) '.$reaction['emoji'].' na wiadomość';$row['preview_formatted']=true;$row['last_timestamp']=(int)$reaction['ts'];}
 }
 unset($row);usort($rows,fn($a,$b)=>((int)$b['last_timestamp']<=>(int)$a['last_timestamp'])?:($b['id']<=>$a['id']));
}

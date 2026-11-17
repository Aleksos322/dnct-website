<?php
declare(strict_types=1);
/**
 * Szyfrowanie end-to-end rozmów 1:1. Klucze prywatne zostają w przeglądarkach; serwer przechowuje klucze
 * publiczne, opcjonalną kopię klucza (zaszyfrowaną hasłem po stronie klienta) oraz szyfrogramy.
 * Koperta wiadomości: "e2e1:" + base64url(iv) + "." + base64url(AES-GCM). Serwer sprawdza tylko jej kształt.
 */
const CHAT_E2E_PREFIX='e2e1:';
const CHAT_E2E_MAX=16000;
const CHAT_E2E_ENVELOPE='/^e2e1:[A-Za-z0-9_-]{16}\.[A-Za-z0-9_-]{22,}$/D';

function chat_e2e_public(string $b64): string {
    $raw=base64_decode($b64,true);
    if($raw===false||strlen($raw)!==65||$raw[0]!=="\x04")throw new InvalidArgumentException('Nieprawidłowy klucz publiczny.');
    return base64_encode($raw);
}
function chat_e2e_fingerprint(string $publicB64): string { return hash('sha256',(string)base64_decode($publicB64,true)); }
/** Kopia klucza to JSON zrobiony w przeglądarce; sprawdzamy kształt i rozmiary, nie treść. */
function chat_e2e_backup(string $json): string {
    $d=json_decode($json,true);$b64=static fn($v,int $min,int $max): bool=>is_string($v)&&strlen($v)>=$min&&strlen($v)<=$max&&base64_decode($v,true)!==false;
    if(strlen($json)>4096||!is_array($d)||($d['v']??null)!==1||($d['kdf']??'')!=='pbkdf2-sha256'||!is_int($d['iter']??null)||$d['iter']<100000||$d['iter']>2000000||!$b64($d['salt']??null,24,24)||!$b64($d['iv']??null,16,16)||!$b64($d['data']??null,100,1500))
        throw new InvalidArgumentException('Nieprawidłowa kopia klucza.');
    return json_encode(['v'=>1,'kdf'=>'pbkdf2-sha256','iter'=>$d['iter'],'salt'=>$d['salt'],'iv'=>$d['iv'],'data'=>$d['data']],JSON_UNESCAPED_SLASHES);
}
function chat_e2e_me(PDO $db,array $actor): array {
    $q=$db->prepare('SELECT public_key,fingerprint,backup FROM chat_e2e_keys WHERE user_id=?');$q->execute([$actor['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return ['user_id'=>$actor['id'],'username'=>$actor['username'],'public_key'=>$r['public_key']??null,'fingerprint'=>$r['fingerprint']??null,'backup'=>$r?json_decode((string)$r['backup'],true):null];
}
/** Pierwsza rejestracja klucza albo świadome zastąpienie ($replace) - po zastąpieniu starych szyfrogramów nie da się odczytać. */
function chat_e2e_register(PDO $db,array $actor,string $public,string $backupJson,bool $replace): string {
    $public=chat_e2e_public($public);$fp=chat_e2e_fingerprint($public);$backup=$backupJson!==''?chat_e2e_backup($backupJson):null;
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT 1 FROM chat_e2e_keys WHERE user_id=? FOR UPDATE');$q->execute([$actor['id']]);
        if($q->fetchColumn()){
            if(!$replace)throw new DomainException('Masz już klucz szyfrowania. Aby go zastąpić, wybierz utworzenie nowego klucza.');
            $db->prepare('UPDATE chat_e2e_keys SET public_key=?,fingerprint=?,backup=? WHERE user_id=?')->execute([$public,$fp,$backup,$actor['id']]);
        }else $db->prepare('INSERT INTO chat_e2e_keys(user_id,public_key,fingerprint,backup) VALUES(?,?,?,?)')->execute([$actor['id'],$public,$fp,$backup]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    return $fp;
}
/** @return array<int,array> id rozmowy 1:1 => ['enabled'=>bool,'peer'=>?array{user_id,username,public_key,fingerprint}] */
function chat_e2e_map(PDO $db,array $ids,array $actor): array {
    if(!$ids)return [];$ids=array_values(array_map('intval',$ids));$marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$db->prepare("SELECT m.conversation_id,m.user_id,m.username,k.public_key,k.fingerprint FROM chat_members m LEFT JOIN chat_groups g ON g.conversation_id=m.conversation_id LEFT JOIN chat_e2e_keys k ON k.user_id=m.user_id WHERE m.conversation_id IN ($marks) AND g.conversation_id IS NULL AND m.user_id<>?");
    $q->execute([...$ids,$actor['id']]);$map=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$map[(int)$r['conversation_id']]=['enabled'=>false,'peer'=>$r['public_key']!==null?['user_id'=>$r['user_id'],'username'=>$r['username'],'public_key'=>$r['public_key'],'fingerprint'=>$r['fingerprint']]:null];
    $q=$db->prepare("SELECT conversation_id FROM chat_e2e_conversations WHERE conversation_id IN ($marks)");$q->execute($ids);
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)if(isset($map[(int)$id]))$map[(int)$id]['enabled']=true;
    return $map;
}
function chat_e2e_conversation(PDO $db,int $id,array $actor): ?array { return chat_e2e_map($db,[$id],$actor)[$id]??null; }
function chat_e2e_enabled(PDO $db,int $id): bool { $q=$db->prepare('SELECT 1 FROM chat_e2e_conversations WHERE conversation_id=?');$q->execute([$id]);return (bool)$q->fetchColumn(); }
/** Wołać w transakcji po zablokowaniu rozmowy: w szyfrowanej rozmowie tylko koperty, w jawnej nigdy koperty. */
function chat_e2e_check_body(PDO $db,int $id,string $body,bool $hasFiles): void {
    if(chat_e2e_enabled($db,$id)){
        if($hasFiles)throw new InvalidArgumentException('W szyfrowanych rozmowach załączniki są jeszcze niedostępne.');
        if(strlen($body)>CHAT_E2E_MAX||!preg_match(CHAT_E2E_ENVELOPE,$body))throw new InvalidArgumentException('Ta rozmowa jest szyfrowana. Odśwież stronę i spróbuj ponownie.');
        return;
    }
    if(str_starts_with($body,CHAT_E2E_PREFIX))throw new InvalidArgumentException('Ta rozmowa nie jest szyfrowana. Odśwież stronę i spróbuj ponownie.');
    if(mb_strlen($body)>2000)throw new InvalidArgumentException('Wpisz wiadomość (do 2000 znaków) albo załącz zdjęcie lub film.');
}
function chat_e2e_set(PDO $db,int $id,array $actor,bool $enabled,string $key): int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Nieprawidłowa zmiana.');
    $db->beginTransaction();
    try{
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);chat_member($db,$id,$actor['id']);
        $q=$db->prepare('SELECT 1 FROM chat_groups WHERE conversation_id=?');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Szyfrowanie end-to-end jest dostępne tylko w rozmowach 1:1.');
        if(chat_blocked($db,$id))throw new DomainException('Rozmowa jest zablokowana.');
        $q=$db->prepare('SELECT state FROM chat_requests WHERE conversation_id=?');$q->execute([$id]);$state=$q->fetchColumn();if($state&&$state!=='accepted')throw new DomainException('Najpierw zaakceptuj prośbę o rozmowę.');
        $q=$db->prepare('SELECT id FROM chat_messages WHERE author_id=? AND client_key=? AND conversation_id=?');$q->execute([$actor['id'],$key,$id]);if($old=$q->fetchColumn()){$db->commit();return (int)$old;}
        if(chat_e2e_enabled($db,$id)===$enabled)throw new InvalidArgumentException($enabled?'Szyfrowanie jest już włączone.':'Szyfrowanie jest już wyłączone.');
        if($enabled){
            $q=$db->prepare('SELECT COUNT(*) FROM chat_e2e_keys k JOIN chat_members m ON m.user_id=k.user_id WHERE m.conversation_id=?');$q->execute([$id]);
            if((int)$q->fetchColumn()<2)throw new DomainException('Obie osoby muszą mieć klucz szyfrowania. Poproś rozmówcę o włączenie szyfrowania w jego Ustawieniach czatu.');
            $db->prepare('INSERT INTO chat_e2e_conversations(conversation_id,enabled_by) VALUES(?,?)')->execute([$id,$actor['id']]);
        }else $db->prepare('DELETE FROM chat_e2e_conversations WHERE conversation_id=?')->execute([$id]);
        $db->prepare('INSERT INTO chat_messages(conversation_id,author_id,body,client_key) VALUES(?,?,?,?)')->execute([$id,$actor['id'],'',$key]);$message=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO chat_system_messages(message_id,kind,payload) VALUES(?,?,?)')->execute([$message,$enabled?'e2e_on':'e2e_off','{}']);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);chat_emit($db,$id);$db->commit();return $message;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

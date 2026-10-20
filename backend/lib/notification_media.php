<?php
declare(strict_types=1);
require_once __DIR__.'/messaging_security.php';
require_once __DIR__.'/notification_visibility.php';
/** Validate every upload before writing a notification. Never trust client MIME. */
function notification_uploaded_media(array $files): array {
    if (!$files) return [];
    if (!is_array($files['name']??null) || count($files['name'])>4) throw new InvalidArgumentException('Dodaj maksymalnie 4 zdjęcia lub filmy.');
    $result=[];$total=0;
    foreach($files['name'] as $i=>$name){
        if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Nie udało się przesłać pliku. Limit wynosi 50 MB na plik.');
        $tmp=$files['tmp_name'][$i]??'';
        if(!is_string($tmp)||!is_uploaded_file($tmp))throw new InvalidArgumentException('Nieprawidłowy plik.');
        $size=filesize($tmp);$total+=$size;
        if(!$size||$size>50*1024*1024||$total>200*1024*1024)throw new InvalidArgumentException('Limit: 50 MB na plik, 200 MB łącznie.');
        $validated=msg_validate_media_file($tmp,(string)$name,(int)$size);
        $mime=$validated['mime'];
        if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','video/quicktime'],true))throw new InvalidArgumentException('Dozwolone: JPG, PNG, WebP, GIF, MP4, WebM i MOV.');
        if(str_starts_with($mime,'image/')&&!@getimagesize($tmp))throw new InvalidArgumentException('Nieprawidłowe zdjęcie.');
        $name=mb_substr(str_replace(["\r","\n","\0",'/','\\'],'_',basename((string)$name)),0,200);
        $result[]=['name'=>$name,'mime'=>$mime,'size'=>$size,'tmp'=>$tmp,'key'=>hash('sha256',msg_actor_key().':'.hash_file('sha256',$tmp))];
    }
    return $result;
}
function notification_store_media(array $files,array &$created=[]): array {
    $dir=msg_media_root('notification');
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Brak magazynu załączników.');
    foreach($files as $file){
        $path=$dir.'/'.$file['key'];
        if(!is_file($path)){
            if(!move_uploaded_file($file['tmp'],$path))throw new RuntimeException('Nie udało się zapisać załącznika.');
            chmod($path,0660);$created[]=$path;
        }
    }
    return $files;
}
function notification_media_for_user(PDO $db,string $id,string $username): ?array {
    msg_security_bind($db);
    $visibility=notification_visible_sql();
    $q=$db->prepare("SELECT a.* FROM notification_attachments a WHERE a.id=? AND EXISTS (
      SELECT 1 FROM notification_attachment_links l JOIN notifications n ON n.id=l.notification_id
      LEFT JOIN notification_reads r ON r.notification_id=n.id AND r.user_id=n.target_user_id
      WHERE l.attachment_id=a.id {$visibility} AND n.target_type='user' AND n.target_user_id=?
      AND r.hidden_at IS NULL AND (r.saved_at IS NOT NULL OR r.read_at IS NULL OR r.read_at>DATE_SUB(NOW(),INTERVAL 7 DAY))
      AND (r.saved_at IS NOT NULL OR n.expires_at IS NULL OR n.expires_at>NOW()))");
    $q->execute([$id,$username]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}

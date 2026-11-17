<?php
declare(strict_types=1);
/**
 * Motyw rozmowy: kolor wiadomości każdego uczestnika i tło okna. Ustawienia są wspólne dla wszystkich
 * członków rozmowy (jak w Messengerze) i można je przywrócić do domyślnych - brak wiersza w bazie
 * oznacza ustawienie domyślne.
 */
function chat_theme_color(string $value): string {
    if(!preg_match('/^#[0-9a-fA-F]{6}$/D',$value))throw new InvalidArgumentException('Wybierz kolor w formacie #rrggbb.');
    return strtolower($value);
}
function chat_theme_background(string $json): array {
    $data=json_decode($json,true);
    if(!is_array($data)||!in_array($data['mode']??null,['solid','gradient'],true))throw new InvalidArgumentException('Nieprawidłowe tło rozmowy.');
    $angle=(int)($data['angle']??135);
    if($angle<0||$angle>360)throw new InvalidArgumentException('Kierunek gradientu musi mieścić się w zakresie 0-360.');
    $opacity=(int)($data['opacity']??100);
    if($opacity<20||$opacity>100)throw new InvalidArgumentException('Przezroczystość tła musi mieścić się w zakresie 20-100%.');
    return ['mode'=>$data['mode'],'c1'=>chat_theme_color((string)($data['c1']??'')),'c2'=>$data['mode']==='gradient'?chat_theme_color((string)($data['c2']??'')):null,'angle'=>$angle,'opacity'=>$opacity];
}
function chat_theme_empty(): array { return ['bg'=>null,'colors'=>new stdClass()]; }
/** @return array<int,array> id rozmowy => ['bg'=>?array,'colors'=>[username=>#rrggbb]] */
function chat_theme_load(PDO $db,array $ids): array {
    $themes=[];foreach($ids as $id)$themes[(int)$id]=chat_theme_empty();
    if(!$ids)return $themes;
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$db->prepare("SELECT c.conversation_id,m.username,c.color FROM chat_message_colors c JOIN chat_members m ON m.conversation_id=c.conversation_id AND m.user_id=c.user_id WHERE c.conversation_id IN ($marks)");
    $q->execute(array_values($ids));
    $colors=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$colors[(int)$r['conversation_id']][$r['username']]=$r['color'];
    foreach($colors as $id=>$list)$themes[$id]['colors']=$list;
    $q=$db->prepare("SELECT conversation_id,mode,color1,color2,angle,opacity FROM chat_backgrounds WHERE conversation_id IN ($marks)");
    $q->execute(array_values($ids));
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$themes[(int)$r['conversation_id']]['bg']=['mode'=>$r['mode'],'c1'=>$r['color1'],'c2'=>$r['color2'],'angle'=>(int)$r['angle'],'opacity'=>(int)$r['opacity']];
    return $themes;
}

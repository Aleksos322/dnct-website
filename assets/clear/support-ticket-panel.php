<?php
declare(strict_types=1);
if (!isset($_SESSION['authentik_username'])) return;
require_once dirname(__DIR__,2).'/backend/lib/support.php';
require_once __DIR__.'/chat-assets.php';
$groups=[];foreach(support_categories() as $key=>$label){[$group,$name]=explode(' / ',$label,2);$groups[$group][$key]=$name;}
require_once dirname(__DIR__,2).'/backend/lib/support_message_features.php';
$config=['mediaLimits'=>['image'=>SUPPORT_MESSAGE_IMAGE_MAX_BYTES,'video'=>SUPPORT_MESSAGE_VIDEO_MAX_BYTES,'files'=>SUPPORT_MESSAGE_MEDIA_MAX_FILES],'admin'=>false,'api'=>'/api/support/ticket.php','realtime'=>'/api/support/realtime.php','csrf'=>csrf_token(),'loggedIn'=>isset($_SESSION['authentik_username']),'name'=>(string)($_SESSION['authentik_username']??''),'email'=>(string)($_SESSION['authentik_email']??''),'groups'=>$groups];
?>
<script type="application/json" id="dnct-support-config"><?= json_encode($config,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="/assets/js/support-realtime.js?v=<?= (int)filemtime(dirname(__DIR__).'/js/support-realtime.js') ?>" defer></script>
<script src="/assets/js/support-controller.js?v=<?= (int)filemtime(dirname(__DIR__).'/js/support-controller.js') ?>" defer></script>

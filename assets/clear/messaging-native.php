<?php
// Production and its game alias share the existing beta messaging backend.
$nativeMessaging = isset($_SESSION['authentik_username']) && in_array(strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]),['dnct.pl','www.dnct.pl','game.dnct.pl'],true);
if($nativeMessaging):
require_once __DIR__ . '/messaging-flash.php'; ?>
<script src="/messaging/assets/js/messaging-native.js" defer></script>
<?php endif;
return $nativeMessaging;

<?php
// One renderer and stylesheet for direct messages and both sides of support.
$chatAssetsRoot = dirname(__DIR__);
?>
<link rel="stylesheet" href="/assets/css/chat-shell.css?v=<?= (int)filemtime($chatAssetsRoot . '/css/chat-shell.css') ?>">
<link rel="stylesheet" href="/assets/css/chat-ui.css?v=<?= (int)filemtime($chatAssetsRoot . '/css/chat-ui.css') ?>">
<script src="/assets/js/chat-ui.js?v=<?= (int)filemtime($chatAssetsRoot . '/js/chat-ui.js') ?>" defer></script>

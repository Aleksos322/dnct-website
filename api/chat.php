<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';
if(!isset($_SESSION['authentik_username']))json_error('Zaloguj się, aby korzystać z czatu.',401);
require_once dirname(__DIR__) . '/backend/lib/chat.php';
chat_endpoint(dnct_db());

<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backend/bootstrap.php';
require_once dirname(__DIR__,2).'/backend/lib/notification_media.php';
require_once dirname(__DIR__,2).'/backend/lib/support_media_response.php';
if(!isset($_SESSION['authentik_username'])){http_response_code(401);exit;}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);exit;}
msg_security_session(dnct_db());
$id=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{32}$/D',$id)){http_response_code(404);exit;}
$file=notification_media_for_user(dnct_db(),$id,(string)$_SESSION['authentik_username']);
if(!$file){http_response_code(404);exit;}
if(!preg_match('/^[a-f0-9]{64}$/D',$file['storage_key'])){http_response_code(404);exit;}
$file['path']=msg_media_root('notification').'/'.$file['storage_key'];
if(!is_file($file['path'])){http_response_code(404);exit;}
header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; sandbox");
support_media_response($file);

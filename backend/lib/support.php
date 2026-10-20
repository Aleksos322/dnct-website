<?php
declare(strict_types=1);
const SUPPORT_MESSAGE_MIN = 20;
const SUPPORT_MESSAGE_MAX = 2000;
function support_categories(): array { return json_decode(file_get_contents(__DIR__.'/support_categories.json'),true,512,JSON_THROW_ON_ERROR); }
function support_access_hash(): string { if(empty($_SESSION['support_access'])) $_SESSION['support_access']=bin2hex(random_bytes(32)); return hash('sha256',(string)$_SESSION['support_access']); }
function support_clean_body(string $body): string { return preg_replace("/\r\n?/","\n",trim($body))??''; }

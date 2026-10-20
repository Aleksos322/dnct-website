<?php
declare(strict_types=1);

/** Stream private media, including byte ranges required for video seeking. */
function support_media_response(array $file): never
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $size = (int)$file['byte_size']; $start = 0; $end = $size - 1;
    header('Content-Type: '.$file['mime_type']);
    header('Content-Disposition: inline; filename="'.rawurlencode($file['original_name']).'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Accept-Ranges: bytes');
    $range = (string)($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '') {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m) || ($m[1]==='' && $m[2]==='')) { http_response_code(416); header('Content-Range: bytes */'.$size); exit; }
        if ($m[1] === '') $start = max(0, $size - (int)$m[2]);
        else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end,(int)$m[2]); }
        if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */'.$size); exit; }
        http_response_code(206); header("Content-Range: bytes $start-$end/$size");
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD' && isset($GLOBALS['msg_security_db'])) {
        try {
            msg_transfer_lease($GLOBALS['msg_security_db'],'download',4);
            msg_quota_charge($GLOBALS['msg_security_db'],0,$end-$start+1);
        } catch (Throwable $e) {
            header_remove('Content-Range'); header_remove('Content-Type');
            msg_security_log($GLOBALS['msg_security_db'],'download_denied');
            json_error('Przekroczono limit pobierania lub liczbę równoległych transferów. Spróbuj później.',429);
        }
    }
    header('Content-Length: '.($end-$start+1));
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') exit;
    $handle = fopen($file['path'],'rb'); if (!$handle) { http_response_code(404); exit; }
    fseek($handle,$start); $left=$end-$start+1;
    while ($left>0 && !feof($handle) && !connection_aborted()) { $chunk=fread($handle,min(65536,$left)); if ($chunk===false || $chunk==='') break; echo $chunk; $left-=strlen($chunk); }
    fclose($handle); exit;
}

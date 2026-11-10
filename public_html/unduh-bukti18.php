<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex,nofollow,noarchive');
header('Cache-Control: private, no-store, max-age=0');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/evidence18.php';
    $user=requires_login();
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($user))throw new RuntimeException('Unauthorized');
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
    if(!$id)throw new RuntimeException('Not found');
    $file=e18_file_for_download($user,(int)$id);
    // Audit must succeed BEFORE any private bytes are streamed.
    e18_audit_insert(db('smartapp'),$user,'evidence18_downloaded',(string)$id);
    header('Content-Type: '.$file['mime']);
    header('Content-Disposition: attachment; filename="bukti-kegiatan-'.(int)$id.'.'.($file['mime']==='image/png'?'png':'jpg').'"');
    header('Content-Security-Policy: sandbox');
    header('Content-Length: '.(int)filesize($file['path']));
    readfile($file['path']);
    exit;
}catch(Throwable $e){
    error_log('Stage 18 evidence download denied: '.get_class($e));
    http_response_code(404);header('Content-Type: text/plain; charset=utf-8');
    exit('Berkas tidak ditemukan atau akses tidak diizinkan.');
}

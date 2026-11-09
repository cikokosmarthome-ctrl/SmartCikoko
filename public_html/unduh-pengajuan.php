<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/citizen_requests.php';
    require_once dirname(__DIR__).'/portal_private/citizen_followup.php';
    $actor=t9_actor(actor());
    if(!$actor){http_response_code(401);exit('Login diperlukan.');}
    $id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT);
    if(!$id||$id<1){http_response_code(400);exit('Berkas tidak valid.');}
    t10_download($actor,$id);
}catch(Throwable $e){
    error_log('SmartCikoko private citizen download failed: '.$e->getMessage());
    http_response_code(404);exit('Berkas tidak ditemukan atau akses tidak tersedia.');
}

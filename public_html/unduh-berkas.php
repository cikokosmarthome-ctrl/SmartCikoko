<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/document_workflow.php';
    $admin=require_super_admin();
    $fileId=filter_var($_GET['id']??0,FILTER_VALIDATE_INT);
    if(!$fileId || $fileId<1){http_response_code(400);exit('Berkas tidak valid.');}
    t8_download($admin,$fileId);
}catch(Throwable $e){
    error_log('SmartCikoko private file download failed: '.$e->getMessage());
    http_response_code(404);exit('Berkas tidak ditemukan atau akses tidak tersedia.');
}

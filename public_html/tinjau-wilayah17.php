<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    http_response_code(405);header('Allow: POST');exit('Gunakan formulir peninjauan pada Super Admin.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/field_reports17.php';
    $admin=require_super_admin();
    verify_csrf();
    if(!w17_ready())throw new RuntimeException('Penyimpanan laporan belum aktif.');
    w17_review($admin,(int)($_POST['report_id']??0),trim((string)($_POST['status']??'')),trim((string)($_POST['note']??'')));
    $_SESSION['w17_admin_notice']='Hasil peninjauan laporan berhasil dicatat.';
}catch(Throwable $e){
    error_log('Stage 17 admin review blocked: '.get_class($e));
    // Exception details and private form data are deliberately not reflected.
    $_SESSION['w17_admin_notice']='Tindak lanjut tidak tersimpan. Periksa status laporan dan catatan perbaikan.';
}
header('Location: /super-admin-satu.php#laporan-wilayah',true,303);exit;

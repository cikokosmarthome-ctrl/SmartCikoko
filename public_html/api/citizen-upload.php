<?php
declare(strict_types=1);
/**
 * Authenticated multipart upload for the SAME Stage 10 private file store
 * used by /tindak-lanjut.php. Never exposes a private storage path.
 */
try {
    require dirname(__DIR__,2).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__,2).'/portal_private/citizen_requests.php';
    require_once dirname(__DIR__,2).'/portal_private/citizen_followup.php';
} catch(Throwable $e) {
    http_response_code(503);header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"Layanan berkas belum tersedia."}';exit;
}
header('Cache-Control: no-store');
if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))!=='POST')
    respond(['ok'=>false,'error'=>'Gunakan metode POST.'],405);
try {
    $actor=api_actor();
    if(!$actor)respond(['ok'=>false,'error'=>'Sesi tidak aktif.'],401);
    $citizen=t9_actor($actor);
    if(!$citizen||!t9_is_citizen($citizen))respond(['ok'=>false,'error'=>'Akses lampiran ditolak.'],403);
    if(!t10_ready())respond(['ok'=>false,'error'=>'Modul lampiran belum terpasang.'],503);
    $id=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT);
    if(!$id||$id<1)respond(['ok'=>false,'error'=>'Pengajuan tidak valid.'],422);
    if((string)($_POST['confirmed']??'')!=='1')
        respond(['ok'=>false,'error'=>'Konfirmasi kepemilikan dokumen harus diberikan.'],422);
    $type=(string)($_POST['doc_type']??'');
    if(!array_key_exists($type,t10_types()))
        respond(['ok'=>false,'error'=>'Jenis lampiran tidak tersedia.'],422);
    $upload=$_FILES['document']??null;
    if(!is_array($upload))respond(['ok'=>false,'error'=>'Pilih berkas terlebih dahulu.'],422);
    t10_upload($citizen,$id,$type,$upload);
    respond(['ok'=>true,'data'=>[
       'files'=>t10_files($citizen,$id),
       'message'=>'Lampiran berhasil disimpan pada penyimpanan privat.',
    ]],201);
} catch(InvalidArgumentException|RuntimeException $e) {
    error_log('SmartCikoko multipart upload refused: '.get_class($e));
    respond(['ok'=>false,'error'=>'Lampiran tidak dapat diterima. Periksa format, ukuran, dan status permohonan.'],422);
} catch(Throwable $e) {
    error_log('SmartCikoko multipart upload failed: '.get_class($e));
    respond(['ok'=>false,'error'=>'Penyimpanan lampiran sementara tidak tersedia.'],503);
}

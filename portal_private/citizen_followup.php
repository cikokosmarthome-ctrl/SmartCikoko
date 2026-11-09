<?php
declare(strict_types=1);
/**
 * Tahap 10 — lampiran aman, notifikasi dalam portal, bukti pengajuan non-resmi.
 * Tabel baru SmartApp dan berkas baru terpisah dari Tahap 8/arsip lama.
 */
function t10_types(): array {
    return ['identitas'=>'Identitas', 'keluarga'=>'Hubungan Keluarga',
      'kematian'=>'Keterangan Kematian', 'lainnya'=>'Berkas Pendukung'];
}
function t10_ready():bool {
    try {
        foreach(['unified_citizen_files','unified_citizen_notifications'] as $table)
            db('smartapp')->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        return true;
    } catch(Throwable $e){return false;}
}
function t10_actor_case(array $actor,int $id): array {
    if(!function_exists('t9_case'))require_once __DIR__.'/citizen_requests.php';
    $actor=t9_actor($actor);
    if(!$actor)throw new RuntimeException('Sesi tidak diizinkan.');
    $case=t9_case($actor,$id);
    if(!$case)throw new RuntimeException('Permohonan tidak ditemukan atau akses tidak diizinkan.');
    return $case;
}
function t10_storage():string {
    $base=realpath(__DIR__);
    if($base===false)throw new RuntimeException('Lokasi privat tidak tersedia.');
    $folder=$base.'/private_requests';
    if(is_link($folder))throw new RuntimeException('Penyimpanan privat tidak aman.');
    if(!is_dir($folder) && !@mkdir($folder,0700,true) && !is_dir($folder))
        throw new RuntimeException('Penyimpanan privat tidak dapat dibuat.');
    $real=realpath($folder);
    if(!$real || dirname($real)!==$base || !is_writable($real))
        throw new RuntimeException('Lokasi penyimpanan tidak aman atau tidak dapat ditulis.');
    @chmod($real,0700);
    return $real;
}
/** Metadata only; never return stored disk names, paths or hashes. */
function t10_files(array $actor,int $requestId):array {
    t10_actor_case($actor,$requestId);
    $q=db('smartapp')->prepare('SELECT id,doc_type,original_name,mime_type,file_size,created_at FROM unified_citizen_files WHERE request_id=? ORDER BY id DESC LIMIT 20');
    $q->execute([$requestId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t10_notify(PDO $db,int $requestId,string $accountId,string $status):void {
    $messages=[
      'diajukan'=>'Pengajuan diterima oleh portal dan menunggu pemeriksaan.',
      'ditinjau'=>'Petugas telah mulai memeriksa pengajuan Anda.',
      'revisi'=>'Pengajuan memerlukan perbaikan. Buka riwayat untuk melihat catatan petugas.',
      'diverifikasi'=>'Pengajuan telah diverifikasi oleh petugas dan menunggu keputusan.',
      'disetujui'=>'Pengajuan memperoleh persetujuan tahapan internal. Ini bukan surat resmi.',
      'ditolak'=>'Pengajuan ditolak. Buka riwayat untuk membaca alasan keputusan.',
    ];
    if(!isset($messages[$status]))return;
    $q=$db->prepare('INSERT INTO unified_citizen_notifications (request_id,account_id,message,created_at) VALUES(?,?,?,NOW())');
    $q->execute([$requestId,$accountId,$messages[$status]]);
}
function t10_notifications(array $actor,int $limit=40):array {
    if(!t9_is_citizen($actor))throw new RuntimeException('Pemberitahuan hanya untuk akun pemohon.');
    $limit=max(1,min(60,$limit));
    $q=db('smartapp')->prepare('SELECT id,request_id,message,created_at,read_at FROM unified_citizen_notifications WHERE account_id=? ORDER BY id DESC LIMIT '.$limit);
    $q->execute([(string)$actor['id']]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t10_read_notification(array $actor,int $id):void {
    if(!t9_is_citizen($actor)||$id<1)throw new RuntimeException('Akses pemberitahuan tidak diizinkan.');
    $q=db('smartapp')->prepare('UPDATE unified_citizen_notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND account_id=?');
    $q->execute([$id,(string)$actor['id']]);
}
function t10_pending_counts(array $actor):array {
    $role=t9_staff_role($actor);
    if($role==='' && !is_super_admin($actor))return [];
    $q=db('smartapp')->query('SELECT status,COUNT(*) AS total FROM unified_citizen_requests GROUP BY status');
    $result=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$result[(string)$r['status']]=(int)$r['total'];
    return $result;
}
function t10_upload(array $actor,int $requestId,string $type,array $upload):void {
    $case=t10_actor_case($actor,$requestId);
    if(!t9_is_citizen($actor) || !hash_equals((string)$case['account_id'],(string)$actor['id']))
        throw new RuntimeException('Hanya pemohon yang dapat mengunggah berkas miliknya.');
    if(!in_array($case['status'],['draf','revisi'],true))
        throw new RuntimeException('Unggahan hanya diizinkan pada draf atau tahap perbaikan.');
    if(!isset(t10_types()[$type]))throw new InvalidArgumentException('Jenis berkas tidak tersedia.');
    if((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
        throw new RuntimeException('Unggahan gagal atau ukuran melampaui batas server.');
    $size=(int)($upload['size']??0);
    if($size<1 || $size>5*1024*1024)throw new RuntimeException('Ukuran berkas maksimum 5 MB.');
    $tmp=(string)($upload['tmp_name']??'');
    if(!is_uploaded_file($tmp))throw new RuntimeException('Berkas unggahan tidak valid.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if(!isset($types[$mime]))throw new RuntimeException('Hanya PDF, JPG, atau PNG yang diterima.');
    if($mime==='application/pdf' && strncmp((string)file_get_contents($tmp,false,null,0,5),'%PDF-',5)!==0)
        throw new RuntimeException('Struktur awal PDF tidak sesuai.');
    if($mime!=='application/pdf' && @getimagesize($tmp)===false)
        throw new RuntimeException('Berkas gambar tidak valid.');
    $db=db('smartapp');
    $original=(string)($upload['name']??'berkas');
    $original=basename(str_replace('\\','/',$original));
    $original=mb_substr(preg_replace('/[^\pL\pN ._-]/u','_', $original)??'berkas',0,120);
    if(trim($original)==='')$original='berkas.'.$types[$mime];
    $t13File=__DIR__.'/security_stage13.php';
    if(is_file($t13File)){
        require_once $t13File;
        s13_scan_upload_if_configured($tmp);
    }
    $stored=bin2hex(random_bytes(24)).'.'.$types[$mime];
    $destination=t10_storage().'/'.$stored;
    // Save a new object; no overwrite, filename never based on user input.
    if(!move_uploaded_file($tmp,$destination))throw new RuntimeException('Gagal menyimpan berkas privat.');
    @chmod($destination,0600);
    try{
        $db->beginTransaction();
        // Lock owning request to serialize uploads and ensure current status.
        $q=$db->prepare('SELECT account_id,status FROM unified_citizen_requests WHERE id=? FOR UPDATE');
        $q->execute([$requestId]);$current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current||!hash_equals((string)$current['account_id'],(string)$actor['id'])||
          !in_array($current['status'],['draf','revisi'],true))
            throw new RuntimeException('Permohonan sudah berubah dan tidak dapat menerima berkas.');
        $q=$db->prepare('SELECT COUNT(*) FROM unified_citizen_files WHERE request_id=?');
        $q->execute([$requestId]);
        if((int)$q->fetchColumn()>=10)throw new RuntimeException('Maksimum 10 berkas per pengajuan.');
        $q=$db->prepare('INSERT INTO unified_citizen_files(request_id,doc_type,original_name,stored_name,mime_type,file_size,sha256,uploaded_by_id,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');
        $q->execute([$requestId,$type,$original,$stored,$mime,filesize($destination),hash_file('sha256',$destination),(string)$actor['id']]);
        // Avoid recording original filenames or personal identifiers in audit logs.
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$actor['source'],(string)$actor['id'],'citizen_file_uploaded','unified_citizen_files',hash('sha256',(string)$requestId)]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        @unlink($destination);
        throw $e;
    }
}
/** Download must authenticate user and authorize access to the parent request. */
function t10_download(array $actor,int $fileId):never {
    if($fileId<1){http_response_code(400);exit('Nomor berkas tidak valid.');}
    $q=db('smartapp')->prepare('SELECT request_id,original_name,stored_name,mime_type,file_size,sha256 FROM unified_citizen_files WHERE id=?');
    $q->execute([$fileId]);$file=$q->fetch(PDO::FETCH_ASSOC);
    if(!$file){http_response_code(404);exit('Berkas tidak ditemukan.');}
    t10_actor_case($actor,(int)$file['request_id']);
    if(!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',(string)$file['stored_name']))
        throw new RuntimeException('Berkas tidak valid.');
    require_once __DIR__.'/archive_security.php';
    t11_require_intact('pengajuan',$file);
    $path=t10_storage().'/'.$file['stored_name'];
    if(!is_file($path)||is_link($path)){http_response_code(404);exit('Berkas tidak ditemukan.');}
    $name=preg_replace('/[^A-Za-z0-9._-]/','_', (string)$file['original_name'])?:'lampiran';
    $name=substr($name,0,110);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: '.filesize($path));
    log_event('citizen_file_downloaded','unified_citizen_files',(string)$fileId);
    readfile($path);
    exit;
}

<?php
declare(strict_types=1);

/**
 * Tahap 8 — Verifikasi berkas pelayanan dan rekap agregat.
 * Hanya untuk Super Administrator. Tidak menambah persyaratan hukum baru.
 * Berkas disimpan di portal_private/private_documents, di luar public_html.
 */
function t8_document_types(): array {
    return [
        'identitas'=>'Dokumen Identitas Pemohon',
        'keluarga'=>'Dokumen Hubungan Keluarga',
        'kematian'=>'Dokumen Kematian',
        'pernyataan'=>'Dokumen Pernyataan/Persetujuan',
        'lainnya'=>'Dokumen Pendukung Lainnya',
    ];
}
function t8_check_statuses(): array {
    return [
        'belum'=>'Belum Diperiksa',
        'sesuai'=>'Telah Diperiksa',
        'perbaikan'=>'Perlu Kelengkapan/Perbaikan',
    ];
}
function t8_month(string $month): string {
    if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month)) throw new InvalidArgumentException('Periode bulan tidak valid.');
    $date=DateTimeImmutable::createFromFormat('!Y-m',$month,new DateTimeZone('Asia/Jakarta'));
    if(!$date || $date->format('Y-m')!==$month) throw new InvalidArgumentException('Periode bulan tidak valid.');
    return $month;
}
function t8_month_range(string $month): array {
    $month=t8_month($month);
    $start=DateTimeImmutable::createFromFormat('!Y-m',$month,new DateTimeZone('Asia/Jakarta'));
    return [$start->format('Y-m-d'),$start->modify('first day of next month')->format('Y-m-d')];
}
function t8_case(int $id): ?array {
    if($id<1)return null;
    $stmt=db('smartapp')->prepare('SELECT id,reference_no,applicant_name,relationship,status,created_at FROM aw_applications WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
}
function t8_cases(string $search='',int $page=1):array {
    $page=max(1,min(10000,$page));$limit=20;
    $search=mb_substr(trim($search),0,80);
    $params=[];
    $where='';
    if($search!==''){
        $where=' WHERE (reference_no LIKE :ref OR applicant_name LIKE :name)';
        $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
        $params=[':ref'=>$term,':name'=>$term];
    }
    $db=db('smartapp');
    $count=$db->prepare('SELECT COUNT(*) FROM aw_applications'.$where);
    foreach($params as $k=>$v)$count->bindValue($k,$v);
    $count->execute();$total=(int)$count->fetchColumn();
    $pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);
    $stmt=$db->prepare('SELECT id,reference_no,applicant_name,status,created_at FROM aw_applications'.$where.' ORDER BY id DESC LIMIT :lim OFFSET :off');
    foreach($params as $k=>$v)$stmt->bindValue($k,$v);
    $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':off',($page-1)*$limit,PDO::PARAM_INT);
    $stmt->execute();
    return ['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total];
}
function t8_case_checks(int $appId):array {
    $q=db('smartapp')->prepare('SELECT doc_type,review_status,review_notes,updated_at FROM unified_document_checks WHERE application_id=?');
    $q->execute([$appId]);$checks=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$checks[$r['doc_type']]=$r;
    return $checks;
}
function t8_case_files(int $appId):array {
    $q=db('smartapp')->prepare('SELECT id,doc_type,original_name,mime_type,file_size,created_at FROM unified_case_files WHERE application_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([$appId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t8_events(int $appId):array {
    $q=db('smartapp')->prepare('SELECT event_type,doc_type,event_note,actor_source,created_at FROM unified_document_events WHERE application_id=? ORDER BY id DESC LIMIT 30');
    $q->execute([$appId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t8_record_review(array $actor,int $appId,string $type,string $status,string $note):void {
    if(!is_super_admin($actor))throw new RuntimeException('Hak akses ditolak.');
    if(!isset(t8_document_types()[$type]) || !isset(t8_check_statuses()[$status]))throw new InvalidArgumentException('Jenis dokumen atau status tidak valid.');
    if(mb_strlen($note)>500)throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
    if(!t8_case($appId))throw new RuntimeException('Permohonan tidak ditemukan.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $check=$db->prepare('SELECT review_status,review_notes FROM unified_document_checks WHERE application_id=? AND doc_type=? FOR UPDATE');
        $check->execute([$appId,$type]);$before=$check->fetch(PDO::FETCH_ASSOC);
        $update=$db->prepare('INSERT INTO unified_document_checks(application_id,doc_type,review_status,review_notes,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE review_status=VALUES(review_status),review_notes=VALUES(review_notes),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $update->execute([$appId,$type,$status,$note,$actor['source'],(string)$actor['id']]);
        if(!$before || $before['review_status']!==$status || $before['review_notes']!==$note){
            $event=$db->prepare('INSERT INTO unified_document_events(application_id,event_type,doc_type,event_note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
            $event->execute([$appId,'review_updated',$type,mb_substr(t8_check_statuses()[$status].($note!==''?' · '.$note:''),0,600),$actor['source'],(string)$actor['id']]);
        }
        $db->commit();
        log_event('document_check_updated','unified_document_checks',(string)$appId.':'.$type);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t8_storage_dir():string {
    $base=realpath(__DIR__);
    if(!$base)throw new RuntimeException('Direktori privat tidak tersedia.');
    $dir=$base.'/private_documents';
    if(is_link($dir))throw new RuntimeException('Direktori penyimpanan tidak aman.');
    if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir))throw new RuntimeException('Tidak dapat membuat direktori privat.');
    $real=realpath($dir);
    if(!$real || dirname($real)!==$base)throw new RuntimeException('Lokasi berkas tidak aman.');
    @chmod($real,0700);
    return $real;
}
function t8_upload(array $actor,int $appId,string $type,array $upload):void {
    if(!is_super_admin($actor))throw new RuntimeException('Hak akses ditolak.');
    if(!isset(t8_document_types()[$type]))throw new InvalidArgumentException('Jenis dokumen tidak valid.');
    if(!t8_case($appId))throw new RuntimeException('Permohonan tidak ditemukan.');
    if((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Berkas gagal diterima. Periksa batas ukuran upload PHP.');
    $size=(int)($upload['size']??0);
    if($size<1||$size>5*1024*1024)throw new RuntimeException('Berkas harus berukuran maksimum 5 MB.');
    $tmp=(string)($upload['tmp_name']??'');
    if(!is_uploaded_file($tmp))throw new RuntimeException('Berkas unggahan tidak valid.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if(!isset($allowed[$mime]))throw new RuntimeException('Berkas harus berupa PDF, JPG, atau PNG.');
    if($mime==='application/pdf' && !str_starts_with((string)file_get_contents($tmp,false,null,0,5),'%PDF-'))
        throw new RuntimeException('Format PDF tidak valid.');
    if(in_array($mime,['image/jpeg','image/png'],true) && @getimagesize($tmp)===false)
        throw new RuntimeException('Berkas gambar tidak valid.');
    $db=db('smartapp');
    $q=$db->prepare('SELECT COUNT(*) FROM unified_case_files WHERE application_id=?');
    $q->execute([$appId]);
    if((int)$q->fetchColumn()>=30)throw new RuntimeException('Batas 30 berkas untuk satu permohonan telah tercapai.');
    $original=trim((string)($upload['name']??'berkas'));
    $original=mb_substr(preg_replace('/[^\pL\pN ._-]/u','_',basename(str_replace('\\','/',$original)))??'berkas',0,120);
    if($original==='')$original='berkas.'.$allowed[$mime];
    $t13File=__DIR__.'/security_stage13.php';
    if(is_file($t13File)){
        require_once $t13File;
        s13_scan_upload_if_configured($tmp);
    }
    $stored=bin2hex(random_bytes(24)).'.'.$allowed[$mime];
    $path=t8_storage_dir().'/'.$stored;
    if(!move_uploaded_file($tmp,$path))throw new RuntimeException('Berkas gagal disimpan.');
    @chmod($path,0600);
    $hash=hash_file('sha256',$path);
    try{
        $db->beginTransaction();
        $q=$db->prepare('INSERT INTO unified_case_files(application_id,doc_type,original_name,stored_name,mime_type,file_size,sha256,uploaded_by_source,uploaded_by_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
        $q->execute([$appId,$type,$original,$stored,$mime,filesize($path),$hash,$actor['source'],(string)$actor['id']]);
        $event=$db->prepare('INSERT INTO unified_document_events(application_id,event_type,doc_type,event_note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $event->execute([$appId,'file_uploaded',$type,'Berkas pendukung disimpan secara privat',$actor['source'],(string)$actor['id']]);
        $db->commit();
        log_event('case_file_uploaded','unified_case_files',(string)$appId);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        @unlink($path);
        throw $e;
    }
}
function t8_download(array $actor,int $fileId):never {
    if(!is_super_admin($actor) || $fileId<1){http_response_code(403);exit('Akses ditolak.');}
    $stmt=db('smartapp')->prepare('SELECT original_name,stored_name,mime_type,file_size,sha256 FROM unified_case_files WHERE id=? LIMIT 1');
    $stmt->execute([$fileId]);$file=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$file || !preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',(string)$file['stored_name'])){http_response_code(404);exit('Berkas tidak ditemukan.');}
    require_once __DIR__.'/archive_security.php';
    t11_require_intact('pelayanan',$file);
    $path=t8_storage_dir().'/'.$file['stored_name'];
    if(!is_file($path)||is_link($path)){http_response_code(404);exit('Berkas tidak ditemukan.');}
    $safeName=preg_replace('/[^a-zA-Z0-9._-]/','_',(string)$file['original_name'])?:'dokumen';
    if(strlen($safeName)>110)$safeName=substr($safeName,0,110);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$safeName.'"');
    header('Content-Length: '.filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    log_event('case_file_downloaded','unified_case_files',(string)$fileId);
    readfile($path);exit;
}
function t8_report(string $month):array {
    [$start,$end]=t8_month_range($month);
    $stats=[];
    $q=db('smartapp')->prepare('SELECT status,COUNT(*) AS total FROM aw_applications WHERE created_at>=? AND created_at<? GROUP BY status');
    $q->execute([$start,$end]);
    $statusCount=array_fill_keys(['masuk','proses','revisi','selesai','ditolak'],0);
    $statusCount['lainnya']=0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
        $state=(string)$r['status'];$count=(int)$r['total'];
        if(isset($statusCount[$state]))$statusCount[$state]+=$count;
        else $statusCount['lainnya']+=$count;
    }
    $stats[]=['label'=>'Permohonan Ahli Waris (semua status)','count'=>array_sum($statusCount)];
    foreach(['masuk'=>'Masuk','proses'=>'Dalam proses','revisi'=>'Perlu perbaikan','selesai'=>'Selesai','ditolak'=>'Ditolak','lainnya'=>'Status lain'] as $key=>$label)
        $stats[]=['label'=>'Ahli Waris — '.$label,'count'=>$statusCount[$key]];
    $q=db('pegawai')->prepare('SELECT COUNT(*) FROM ck_pppk_kinerja WHERE month_key LIKE ?');
    $q->execute([$month.'%']);
    $stats[]=['label'=>'Laporan PPPK (periode)','count'=>(int)$q->fetchColumn()];
    $q=db('pegawai')->prepare('SELECT COUNT(*) FROM pjlp_duty_session WHERE work_date>=? AND work_date<?');
    $q->execute([$start,$end]);
    $stats[]=['label'=>'Sesi Tugas PJLP/PPSU (periode)','count'=>(int)$q->fetchColumn()];
    $q=db('smartapp')->prepare('SELECT COUNT(*) FROM unified_document_events WHERE created_at>=? AND created_at<?');
    $q->execute([$start,$end]);
    $stats[]=['label'=>'Aktivitas Verifikasi Dokumen (periode)','count'=>(int)$q->fetchColumn()];
    return $stats;
}

<?php
declare(strict_types=1);
/**
 * Tahap 18 — bukti FOTO kegiatan, scanned and re-encoded (no EXIF) before
 * private storage. NO citizens' documents, leave requests, or native table writes.
 * Scanner configuration is mandatory; upload fails closed unless it scans.
 */
function e18_sources():array {
    return ['pegawai16'=>'unified_detailed_forms16','wilayah17'=>'unified_field_reports17'];
}
function e18_storage_dir(bool $create=false):string {
    $base=realpath(__DIR__);
    $web=realpath(dirname(__DIR__).'/public_html');
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Penyimpanan privat harus di luar public_html.');
    $dir=$base.'/evidence18_storage';
    if(is_link($dir))throw new RuntimeException('Lokasi bukti tidak aman.');
    if(!is_dir($dir)){
        if(!$create||!@mkdir($dir,0700))throw new RuntimeException('Penyimpanan bukti belum siap.');
    }
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Lokasi penyimpanan bukti tidak valid.');
    return $dir;
}
function e18_scan_ready():bool {
    $settings=cfg()['upload_scanning']??[];
    return !empty($settings['enforce'])
        && !empty($settings['clamd_socket'])
        && extension_loaded('gd')
        && extension_loaded('fileinfo')
        && is_file(__DIR__.'/security_stage13.php');
}
function e18_ready():bool {
    try{db('smartapp')->query('SELECT 1 FROM unified_evidence18 LIMIT 1');return true;}
    catch(Throwable $e){return false;}
}
function e18_owner(array $actor,array $row):bool {
    return ($actor['source']??'')===($row['actor_source']??'')
        && hash_equals((string)($actor['id']??''),(string)($row['actor_id']??''));
}
function e18_report(array $actor,string $source,int $reportId,bool $lock=false):array {
    $tables=e18_sources();
    if(!isset($tables[$source])||$reportId<1)throw new RuntimeException('Laporan tidak ditemukan.');
    $q=db('smartapp')->prepare('SELECT id,kind,actor_source,actor_id,status FROM '.$tables[$source].' WHERE id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$reportId]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Laporan tidak ditemukan.');
    $admin=is_super_admin($actor);
    if(!$admin){
        require_once __DIR__.'/account_control.php';
        if(!account_control_allowed($actor)||!e18_owner($actor,$row))
            throw new RuntimeException('Laporan tidak ditemukan.');
        if($source==='pegawai16'){
            require_once __DIR__.'/detailed_forms16.php';
            if(!in_array((string)$row['kind'],['pppk','pjlp'],true)||!f16_authorized($actor,(string)$row['kind']))
                throw new RuntimeException('Laporan tidak ditemukan.');
        }else{
            require_once __DIR__.'/field_reports17.php';
            if(!w17_actor_can($actor,(string)$row['kind']))throw new RuntimeException('Laporan tidak ditemukan.');
        }
    }
    if($source==='pegawai16'&&!in_array((string)$row['kind'],['pppk','pjlp'],true))
        throw new RuntimeException('Bukti cuti dan dokumen warga tidak diizinkan.');
    return $row;
}
function e18_filename_valid(string $name):bool {
    return preg_match('/^[a-f0-9]{48}\.(jpg|png)$/D',$name)===1;
}
function e18_check_photo(array $file):array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
        throw new RuntimeException('Unggahan foto tidak lengkap.');
    $tmp=$file['tmp_name']??'';
    if(!is_string($tmp)||!is_uploaded_file($tmp))throw new RuntimeException('File bukan unggahan HTTP yang sah.');
    $size=(int)($file['size']??0);
    if($size<100||$size>3*1024*1024)throw new RuntimeException('Ukuran foto harus 100 byte sampai 3 MB.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=$finfo->file($tmp);
    if(!in_array($mime,['image/jpeg','image/png'],true))
        throw new RuntimeException('Hanya foto JPEG atau PNG yang diperbolehkan.');
    // Always scan the *actual uploaded temporary file*, never bypass scanner.
    if(!e18_scan_ready())throw new RuntimeException('Antivirus ClamAV atau GD belum siap. Foto ditolak.');
    require_once __DIR__.'/security_stage13.php';
    s13_scan_upload_if_configured($tmp);
    $info=@getimagesize($tmp);
    if(!is_array($info)||!isset($info[0],$info[1],$info[2]))
        throw new RuntimeException('Format foto tidak dapat diverifikasi.');
    [$w,$h,$typ]=$info;
    if($w<1||$h<1||$w>4500||$h>4500||$w*$h>10000000
      ||($mime==='image/jpeg'&&$typ!==IMAGETYPE_JPEG)
      ||($mime==='image/png'&&$typ!==IMAGETYPE_PNG))
        throw new RuntimeException('Ukuran piksel atau tipe foto tidak sesuai.');
    return [$tmp,$mime,$w,$h,$size];
}
function e18_reencode(array $checked,string $destination):array {
    [$tmp,$mime,$w,$h]=$checked;
    $img=$mime==='image/jpeg'?@imagecreatefromjpeg($tmp):@imagecreatefrompng($tmp);
    if(!$img)throw new RuntimeException('Foto gagal diproses.');
    $ratio=min(1.0,1800/max($w,$h));
    $nw=max(1,(int)round($w*$ratio));$nh=max(1,(int)round($h*$ratio));
    $clean=imagecreatetruecolor($nw,$nh);
    try{
        if($mime==='image/png'){
            imagealphablending($clean,false);
            imagesavealpha($clean,true);
            $transparent=imagecolorallocatealpha($clean,0,0,0,127);
            imagefilledrectangle($clean,0,0,$nw,$nh,$transparent);
        }else{
            $white=imagecolorallocate($clean,255,255,255);
            imagefilledrectangle($clean,0,0,$nw,$nh,$white);
        }
        if(!imagecopyresampled($clean,$img,0,0,0,0,$nw,$nh,$w,$h))
            throw new RuntimeException('Foto gagal disanitasi.');
        $written=$mime==='image/jpeg'?@imagejpeg($clean,$destination,85):@imagepng($clean,$destination,6);
        if(!$written||!is_file($destination))throw new RuntimeException('Foto tidak dapat disimpan.');
        $size=(int)filesize($destination);
        if($size<100||$size>4*1024*1024)throw new RuntimeException('Ukuran hasil foto di luar batas.');
        @chmod($destination,0600);
        return [$size,hash_file('sha256',$destination),$nw,$nh];
    }finally{imagedestroy($clean);imagedestroy($img);}
}
function e18_audit_insert(PDO $db,array $actor,string $event,string $subject):void {
    $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
    $q->execute([(string)$actor['source'],(string)$actor['id'],$event,'unified_evidence18',hash('sha256',$subject)]);
}
function e18_upload(array $actor,string $source,int $reportId,array $file):int {
    if(is_super_admin($actor))throw new RuntimeException('Super Admin tidak mengunggah bukti atas nama petugas.');
    if(!e18_ready())throw new RuntimeException('Penyimpanan bukti belum diinstal.');
    $report=e18_report($actor,$source,$reportId);
    if(!in_array($report['status'],['diajukan','ditinjau','perbaikan'],true))
        throw new RuntimeException('Bukti hanya dapat diunggah untuk laporan yang telah dikirim dan belum ditutup.');
    $checked=e18_check_photo($file);
    $dir=e18_storage_dir(true);
    $suffix=$checked[1]==='image/png'?'png':'jpg';
    $name=bin2hex(random_bytes(24)).'.'.$suffix;
    $path=$dir.'/'.$name;
    try{
        [$size,$sha,$w,$h]=e18_reencode($checked,$path);
        $db=db('smartapp');$db->beginTransaction();
        try{
            $current=e18_report($actor,$source,$reportId,true);
            if(!in_array($current['status'],['diajukan','ditinjau','perbaikan'],true))
                throw new RuntimeException('Status laporan berubah. Unggahan dibatalkan.');
            $q=$db->prepare('SELECT COUNT(*) FROM unified_evidence18 WHERE source_kind=? AND source_id=?');
            $q->execute([$source,$reportId]);if((int)$q->fetchColumn()>=4)
                throw new RuntimeException('Maksimal 4 foto per laporan.');
            $q=$db->prepare('INSERT INTO unified_evidence18(source_kind,source_id,actor_source,actor_id,stored_name,mime_type,bytes_size,sha256,width_px,height_px,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
            $q->execute([$source,$reportId,$actor['source'],(string)$actor['id'],$name,$checked[1],$size,$sha,$w,$h]);
            $id=(int)$db->lastInsertId();
            e18_audit_insert($db,$actor,'evidence18_uploaded',$source.':'.$reportId.':'.$id);
            $db->commit();
            return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }catch(Throwable $e){if(is_file($path))@unlink($path);throw $e;}
}
function e18_list(array $actor,string $source,int $reportId):array {
    e18_report($actor,$source,$reportId);
    if(!e18_ready())return [];
    $q=db('smartapp')->prepare('SELECT id,source_kind,source_id,mime_type,bytes_size,width_px,height_px,created_at FROM unified_evidence18 WHERE source_kind=? AND source_id=? ORDER BY id DESC LIMIT 4');
    $q->execute([$source,$reportId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function e18_file_for_download(array $actor,int $id):array {
    if($id<1||!e18_ready())throw new RuntimeException('Berkas tidak ditemukan.');
    $q=db('smartapp')->prepare('SELECT * FROM unified_evidence18 WHERE id=? LIMIT 1');
    $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('Berkas tidak ditemukan.');
    e18_report($actor,(string)$r['source_kind'],(int)$r['source_id']);
    $name=(string)$r['stored_name'];
    if(!e18_filename_valid($name))throw new RuntimeException('Berkas tidak valid.');
    $path=e18_storage_dir().'/'.$name;
    if(!is_file($path)||is_link($path)||!hash_equals((string)$r['sha256'],hash_file('sha256',$path)))
        throw new RuntimeException('Integritas bukti gagal diverifikasi.');
    return ['path'=>$path,'mime'=>$r['mime_type'],'size'=>(int)$r['bytes_size'],'id'=>$id];
}
function e18_counts():array {
    $out=['pegawai16'=>0,'wilayah17'=>0];
    if(!e18_ready())return $out;
    $q=db('smartapp')->query('SELECT source_kind,COUNT(*) total FROM unified_evidence18 GROUP BY source_kind');
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)if(isset($out[$r['source_kind']]))$out[$r['source_kind']]=(int)$r['total'];
    return $out;
}
function e18_csv_safe(mixed $v):string {
    $s=(string)($v??'');
    return preg_match('/^[\s]*[=+\-@]/u',$s)?"'".$s:$s;
}
function e18_date(string $s):string {
    if(!preg_match('/^20\d\d-\d\d-\d\d$/D',$s))throw new InvalidArgumentException('Tanggal tidak valid.');
    [$y,$m,$d]=array_map('intval',explode('-',$s));
    if(!checkdate($m,$d,$y))throw new InvalidArgumentException('Tanggal kalender tidak valid.');
    return $s;
}

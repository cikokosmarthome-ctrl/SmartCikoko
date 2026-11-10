<?php
declare(strict_types=1);
/**
 * Pusat Laporan Terpadu: intake ringan, terpisah dari transaksi warisan.
 * Tidak menulis ke tabel native PPPK/PJLP/ahli waris/cuti atau surat RT/RW.
 */
function lr_categories():array {
    return [
      'waris'=>['label'=>'Ahli Waris','source'=>'smartapp','table'=>'aw_applications','mode'=>'existing','group'=>'Pelayanan','hint'=>'Gunakan formulir pelayanan dan pemeriksaan lama; tidak membuat surat otomatis.'],
      'pppk'=>['label'=>'Kinerja PPPK','source'=>'pegawai','table'=>'ck_pppk_kinerja','mode'=>'supplement','group'=>'Kepegawaian','hint'=>'Form ringkasan tambahan; laporan kinerja resmi tetap di format PPPK lama.'],
      'pjlp'=>['label'=>'Kegiatan PJLP','source'=>'pegawai','table'=>'laporan','mode'=>'supplement','group'=>'Kepegawaian','hint'=>'Form ringkasan tambahan; dokumentasi foto/GPS resmi masih di sistem lama.'],
      'rt_rw'=>['label'=>'RT / RW','source'=>'smartapp','table'=>'mobile_rt_rw_letters','mode'=>'supplement','group'=>'Kewilayahan','hint'=>'Laporan kegiatan wilayah; bukan pengganti alur verifikasi surat pengantar RT/RW.'],
      'lmk'=>['label'=>'LMK','source'=>null,'table'=>null,'mode'=>'new','group'=>'Kewilayahan','hint'=>'Form laporan LMK baru; tabel warisan belum dapat dipastikan.'],
      'jumantik'=>['label'=>'Jumantik','source'=>'smartapp','table'=>'mobile_reports','mode'=>'supplement','group'=>'Kesehatan','hint'=>'Laporan kegiatan Jumantik tambahan; bukan migrasi laporan mobile lama.'],
      'cuti'=>['label'=>'Pengajuan Cuti','source'=>'pegawai','table'=>'cuti','mode'=>'preliminary','group'=>'Kepegawaian','hint'=>'Pengajuan awal untuk ditindaklanjuti petugas; belum tercatat sebagai persetujuan pada tabel cuti lama.'],
      'fkdm'=>['label'=>'FKDM','source'=>'smartapp','table'=>'mobile_fkdm_reports','mode'=>'supplement','group'=>'Kewilayahan','hint'=>'Laporan ringkas; laporan resmi foto tetap di modul FKDM lama.'],
      'ppsu'=>['label'=>'PPSU','source'=>'smartapp','table'=>'mobile_ppsu_reports','mode'=>'supplement','group'=>'Kewilayahan','hint'=>'Laporan ringkas; bukti foto asli tetap di sistem yang ada.'],
      'umum'=>['label'=>'Laporan Umum / Kegiatan','source'=>'smartapp','table'=>'reports','mode'=>'new','group'=>'Pelayanan','hint'=>'Laporan ringkas tambahan, tidak otomatis dipublikasikan sebagai berita/agenda.'],
    ];
}
function lr_stages():array {return [
    'diajukan'=>'Masuk','ditinjau'=>'Dalam Peninjauan',
    'perbaikan'=>'Perlu Perbaikan','ditutup'=>'Ditutup (Administratif)',
];}
function lr_source_roles():array {
    return [
      'pppk'=>[['pegawai','pppk'],['pancoran','pppk_pancoran']],
      'pjlp'=>[['pegawai','pjlp']],
      'rt_rw'=>[['smartapp','rt'],['smartapp','rw']],
      'lmk'=>[['smartapp','lmk']],
      'jumantik'=>[['smartapp','jumantik']],
      'cuti'=>[['pegawai','asn'],['pegawai','pppk'],['pegawai','pjlp'],['pegawai','admin']],
      'fkdm'=>[['smartapp','fkdm']],
      'ppsu'=>[['smartapp','ppsu'],['pegawai','pjlp']],
    ];
}
function lr_can_submit(array $user,string $category):bool {
    $catalog=lr_categories();
    if(!isset($catalog[$category])||$category==='waris')return false;
    $src=(string)($user['source']??'');$role=(string)($user['role']??'');
    if($category==='umum')return in_array($src,['pegawai','pancoran','smartapp'],true) && $role!=='';
    foreach(lr_source_roles()[$category]??[] as $pair)
        if($src===$pair[0]&&$role===$pair[1])return true;
    return false;
}
function lr_is_manager(array $actor):bool {
    return function_exists('is_super_admin') && is_super_admin($actor);
}
function lr_actor(?array $user):?array {
    if(!$user)return null;
    if(!function_exists('account_control_allowed'))require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($user))return null;
    // Role overrides are enforced for corresponding native modules too.
    $abilities=['pppk'=>'pppk','pjlp'=>'pjlp','ppsu'=>'pjlp','waris'=>'waris'];
    return $user;
}
function lr_check_permission(array $actor,string $category):void {
    if(!lr_can_submit($actor,$category))throw new RuntimeException('Kategori ini bukan kewenangan akun yang sedang login.');
    if(!function_exists('account_control_permission_decision'))require_once __DIR__.'/account_control.php';
    $permission=match($category){'pppk'=>'pppk','pjlp','ppsu'=>'pjlp','cuti'=>'pegawai',default=>$category};
    if(account_control_permission_decision($actor,$permission)===false)
        throw new RuntimeException('Izin kategori laporan ini sedang dinonaktifkan.');
}
function lr_date(string $s):string {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s))throw new InvalidArgumentException('Tanggal harus dalam format YYYY-MM-DD.');
    [$y,$m,$d]=array_map('intval',explode('-',$s));
    if(!checkdate($m,$d,$y)||$y<2020||$y>2100)throw new InvalidArgumentException('Tanggal tidak valid.');
    return $s;
}
function lr_form_check(string $category,string $title,string $content,string $location,string $start,string $end):void {
    if(!isset(lr_categories()[$category]) || $category==='waris')throw new InvalidArgumentException('Kategori input tidak tersedia.');
    if(mb_strlen(trim($title))<5||mb_strlen($title)>150)throw new InvalidArgumentException('Judul wajib 5–150 karakter.');
    if(mb_strlen(trim($content))<15||mb_strlen($content)>3000)throw new InvalidArgumentException('Uraian wajib 15–3000 karakter.');
    if(mb_strlen($location)>180)throw new InvalidArgumentException('Lokasi maksimal 180 karakter.');
    lr_date($start);
    if($end!==''){
        lr_date($end);
        if($end<$start)throw new InvalidArgumentException('Tanggal akhir tidak boleh lebih awal.');
    }
    if($category==='cuti' && $end==='')throw new InvalidArgumentException('Tanggal selesai cuti wajib diisi.');
    if(in_array($category,['rt_rw','jumantik','lmk','ppsu','fkdm'],true)&&mb_strlen(trim($location))<2)
        throw new InvalidArgumentException('Wilayah atau lokasi kegiatan wajib diisi.');
    if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$title.$content.$location))
        throw new InvalidArgumentException('Karakter tidak valid.');
}
function lr_ready():bool {
    try{
        foreach(['unified_report_intake','unified_report_intake_history'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function lr_submit(array $actor,string $category,string $title,string $content,string $location,string $start,string $end,string $nonce):int {
    if(!lr_actor($actor))throw new RuntimeException('Sesi akun tidak berwenang.');
    lr_check_permission($actor,$category);
    lr_form_check($category,$title,$content,$location,$start,$end);
    if(!preg_match('/^[a-f0-9]{32}$/D',$nonce))throw new InvalidArgumentException('Kunci formulir tidak valid.');
    $db=db('smartapp');
    $source=(string)$actor['source'];$id=(string)$actor['id'];
    if($id===''||strlen($id)>100)throw new RuntimeException('Identitas akun tidak valid.');
    $q=$db->prepare('SELECT id FROM unified_report_intake WHERE actor_source=? AND actor_id=? AND request_key=?');
    $q->execute([$source,$id,$nonce]);$existing=$q->fetchColumn();
    if($existing!==false)return (int)$existing;
    $q=$db->prepare('SELECT COUNT(*) FROM unified_report_intake WHERE actor_source=? AND actor_id=? AND created_at>=CURRENT_DATE()');
    $q->execute([$source,$id]);
    if((int)$q->fetchColumn()>=12)throw new RuntimeException('Batas pengiriman harian tercapai.');
    $db->beginTransaction();
    try{
        $q=$db->prepare("INSERT INTO unified_report_intake(category,actor_source,actor_id,request_key,title,body,location,activity_date,end_date,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,'diajukan',NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $q->execute([$category,$source,$id,$nonce,trim($title),trim($content),trim($location),$start,$end!==''?$end:null]);
        $newId=(int)$db->lastInsertId();
        $audit=$db->prepare("INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())");
        $audit->execute([$source,$id,'unified_report_submitted','unified_report_intake',hash('sha256',(string)$newId)]);
        $db->commit();
        return $newId;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function lr_query(array $actor,string $category='',int $page=1,int $limit=20):array {
    if($category!==''&&!isset(lr_categories()[$category]))throw new InvalidArgumentException('Kategori tidak tersedia.');
    $admin=lr_is_manager($actor);$page=max(1,min(10000,$page));$limit=max(1,min(30,$limit));
    $where=[];$params=[];
    if(!$admin){$where[]='actor_source=? AND actor_id=?';$params[]=$actor['source'];$params[]=(string)$actor['id'];}
    if($category!==''){$where[]='category=?';$params[]=$category;}
    $filter=$where?' WHERE '.implode(' AND ',$where):'';
    $db=db('smartapp');
    $q=$db->prepare('SELECT COUNT(*) FROM unified_report_intake'.$filter);
    $q->execute($params);$count=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($count/$limit));$page=min($page,$pages);
    $q=$db->prepare('SELECT id,category,title,body,location,activity_date,end_date,status,created_at,updated_at FROM unified_report_intake'.$filter.' ORDER BY id DESC LIMIT ? OFFSET ?');
    $n=1;foreach($params as $param)$q->bindValue($n++,$param,PDO::PARAM_STR);
    $q->bindValue($n++,$limit,PDO::PARAM_INT);
    $q->bindValue($n,($page-1)*$limit,PDO::PARAM_INT);
    $q->execute();
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$count,'page'=>$page,'pages'=>$pages];
}
function lr_review(array $admin,int $id,string $status,string $note):void {
    if(!lr_is_manager($admin))throw new RuntimeException('Hanya Super Administrator dapat mencatat peninjauan.');
    if($id<1||!isset(lr_stages()[$status]))throw new InvalidArgumentException('Status tidak valid.');
    if(mb_strlen($note)>500)throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
    if($status==='perbaikan'&&mb_strlen(trim($note))<8)
        throw new InvalidArgumentException('Alasan perbaikan wajib dijelaskan.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT status FROM unified_report_intake WHERE id=? FOR UPDATE');
        $q->execute([$id]);$old=$q->fetchColumn();
        if($old===false)throw new RuntimeException('Laporan tidak ditemukan.');
        if($old==='ditutup' && $status!=='ditutup')throw new RuntimeException('Laporan yang ditutup tidak dibuka otomatis.');
        $q=$db->prepare('UPDATE unified_report_intake SET status=?,review_note=?,reviewed_by_source=?,reviewed_by_id=?,updated_at=NOW() WHERE id=?');
        $q->execute([$status,trim($note),$admin['source'],(string)$admin['id'],$id]);
        $q=$db->prepare('INSERT INTO unified_report_intake_history(report_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$id,$old,$status,trim($note),$admin['source'],(string)$admin['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$admin['source'],(string)$admin['id'],'unified_report_reviewed','unified_report_intake',hash('sha256',(string)$id)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function lr_native_counts():array {
    $queries=[
      'waris'=>['smartapp','SELECT COUNT(*) FROM aw_applications'],
      'pppk'=>['pegawai','SELECT COUNT(*) FROM ck_pppk_kinerja'],
      'pjlp'=>['pegawai','SELECT COUNT(*) FROM laporan'],
      'rt_rw'=>['smartapp','SELECT COUNT(*) FROM mobile_rt_rw_letters'],
      'lmk'=>null,
      'jumantik'=>['smartapp',"SELECT COUNT(*) FROM mobile_reports WHERE LOWER(user_role) LIKE '%jumantik%'"],
      'cuti'=>['pegawai','SELECT COUNT(*) FROM cuti'],
      'fkdm'=>['smartapp','SELECT COUNT(*) FROM mobile_fkdm_reports WHERE deleted_at IS NULL'],
      'ppsu'=>['smartapp','SELECT COUNT(*) FROM mobile_ppsu_reports WHERE deleted_at IS NULL'],
      'umum'=>['smartapp','SELECT COUNT(*) FROM reports'],
    ];
    $result=[];
    foreach($queries as $key=>$query){
        $result[$key]=null;
        if($query===null)continue;
        try{$result[$key]=(int)db($query[0])->query($query[1])->fetchColumn();}
        catch(Throwable $e){error_log('Report center legacy count unavailable: '.$key);}
    }
    return $result;
}
function lr_new_counts():array {
    $result=array_fill_keys(array_keys(lr_categories()),0);
    try{
        $q=db('smartapp')->query('SELECT category,COUNT(*) AS total FROM unified_report_intake GROUP BY category');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)
            if(isset($result[$row['category']]))$result[$row['category']]=(int)$row['total'];
    }catch(Throwable $e){return [];}
    return $result;
}

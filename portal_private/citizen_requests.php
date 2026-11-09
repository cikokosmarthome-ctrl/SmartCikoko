<?php
declare(strict_types=1);
/**
 * Tahap 9 — Intake warga dan persetujuan berjenjang.
 * Menulis hanya pada tabel baru unified_citizen_requests serta history.
 * Tidak mengubah aw_applications/aw_history dari aplikasi lama.
 */
function t9_roles():array{return ['warga'=>'Pemohon','ptsp'=>'Petugas Verifikasi','lurah'=>'Pejabat Persetujuan'];}
function t9_stages():array{return [
  'draf'=>'Draf','diajukan'=>'Diajukan','ditinjau'=>'Dalam Pemeriksaan',
  'revisi'=>'Perlu Perbaikan','diverifikasi'=>'Diverifikasi Petugas',
  'disetujui'=>'Disetujui','ditolak'=>'Ditolak',
];}
function t9_relationships():array{return [
  'anak'=>'Anak','pasangan'=>'Suami/Istri','orang_tua'=>'Orang Tua',
  'saudara'=>'Saudara','lainnya'=>'Hubungan Lainnya',
];}
function t9_actor(?array $user):?array {
    if(!$user || !in_array((string)($user['source']??''),['smartapp','pegawai','pancoran'],true))return null;
    if(!function_exists('account_control_allowed'))require_once __DIR__.'/account_control.php';
    try{return account_control_allowed($user)?$user:null;}
    catch(Throwable $e){error_log('Tahap 9: pemeriksaan akun gagal.');return null;}
}
function t9_is_citizen(?array $u):bool {
    return $u!==null && $u['source']==='smartapp' && $u['role']==='warga';
}
function t9_staff_role(?array $u):string {
    return $u!==null && $u['source']==='smartapp' && in_array($u['role'],['ptsp','lurah'],true)?$u['role']:'';
}
function t9_can_monitor(?array $u):bool {
    return $u!==null && (t9_staff_role($u)!=='' || is_super_admin($u));
}
function t9_can_read(?array $u,array $row):bool {
    if(!$u)return false;
    if(t9_can_monitor($u))return true;
    return t9_is_citizen($u) && hash_equals((string)$row['account_id'],(string)$u['id']);
}
function t9_case(array $actor,int $id):?array {
    if($id<1)return null;
    $q=db('smartapp')->prepare('SELECT id,reference_no,account_id,applicant_name,deceased_name,relationship,purpose,status,version,created_at,updated_at FROM unified_citizen_requests WHERE id=? LIMIT 1');
    $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return $r && t9_can_read($actor,$r)?$r:null;
}
function t9_list(array $actor,int $page=1):array {
    $page=max(1,min(10000,$page));$limit=15;$params=[];
    if(!t9_is_citizen($actor) && !t9_can_monitor($actor))throw new RuntimeException('Akses tidak diizinkan.');
    $where=t9_is_citizen($actor)?' WHERE account_id=?':'';
    if($where)$params=[(string)$actor['id']];
    $db=db('smartapp');
    $q=$db->prepare('SELECT COUNT(*) FROM unified_citizen_requests'.$where);
    $q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);
    $q=$db->prepare('SELECT id,reference_no,applicant_name,deceased_name,relationship,status,version,created_at,updated_at FROM unified_citizen_requests'.$where.' ORDER BY id DESC LIMIT ? OFFSET ?');
    $bound=1;
    foreach($params as $p)$q->bindValue($bound++,$p,PDO::PARAM_STR);
    $q->bindValue($bound++,$limit,PDO::PARAM_INT);$q->bindValue($bound,($page-1)*$limit,PDO::PARAM_INT);
    $q->execute();
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total];
}
function t9_valid_form(string $deceased,string $relationship,string $purpose):void {
    if(mb_strlen($deceased)<3||mb_strlen($deceased)>160)throw new InvalidArgumentException('Nama almarhum/almarhumah harus 3–160 karakter.');
    if(!isset(t9_relationships()[$relationship]))throw new InvalidArgumentException('Hubungan keluarga tidak tersedia.');
    if(mb_strlen($purpose)>500)throw new InvalidArgumentException('Keterangan maksimal 500 karakter.');
    if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$deceased.$purpose))throw new InvalidArgumentException('Karakter tidak valid.');
}
function t9_history_insert(PDO $db,int $id,string $from,string $to,string $event,string $note,array $actor):void {
    $q=$db->prepare('INSERT INTO unified_citizen_request_history(request_id,from_status,to_status,event_type,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
    $q->execute([$id,$from,$to,$event,mb_substr($note,0,500),$actor['source'],(string)$actor['id']]);
    $audit=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES (?,?,?,?,?,NOW())');
    $audit->execute([$actor['source'],(string)$actor['id'],'citizen_request_'.$event,'unified_citizen_requests',hash('sha256',(string)$id)]);
}
function t9_create(array $actor,string $deceased,string $relationship,string $purpose,string $nonce):int {
    if(!t9_is_citizen($actor))throw new RuntimeException('Hanya akun masyarakat yang dapat membuat pengajuan.');
    t9_valid_form($deceased,$relationship,$purpose);
    if(!preg_match('/^[a-f0-9]{32}$/D',$nonce))throw new InvalidArgumentException('Kunci pengajuan tidak valid.');
    $db=db('smartapp');
    $q=$db->prepare('SELECT COUNT(*) FROM unified_citizen_requests WHERE account_id=? AND created_at>=CURRENT_DATE()');
    $q->execute([(string)$actor['id']]);
    if((int)$q->fetchColumn()>=10){
        // Allow retry of the exact same request, but not a new one.
        $q=$db->prepare('SELECT id FROM unified_citizen_requests WHERE account_id=? AND request_key=?');
        $q->execute([(string)$actor['id'],$nonce]);
        $existing=$q->fetchColumn();
        if($existing!==false)return (int)$existing;
        throw new RuntimeException('Batas pengajuan harian tercapai.');
    }
    $db->beginTransaction();
    try{
        $q=$db->prepare("INSERT INTO unified_citizen_requests(account_id,request_key,reference_no,applicant_name,deceased_name,relationship,purpose,status,version,created_at,updated_at) VALUES(?,?,'',?,?,?,?,'draf',1,NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $q->execute([(string)$actor['id'],$nonce,mb_substr((string)$actor['name'],0,160),$deceased,$relationship,$purpose]);
        $id=(int)$db->lastInsertId();
        $q=$db->prepare('SELECT reference_no FROM unified_citizen_requests WHERE id=? FOR UPDATE');
        $q->execute([$id]);$ref=(string)$q->fetchColumn();
        if($ref===''){
            $reference='CIK-'.date('Y').'-'.str_pad((string)$id,7,'0',STR_PAD_LEFT);
            $q=$db->prepare('UPDATE unified_citizen_requests SET reference_no=? WHERE id=?');
            $q->execute([$reference,$id]);
            t9_history_insert($db,$id,'','draf','created','Pengajuan dibuat sebagai draf.',$actor);
        }
        $db->commit();
        return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t9_edit(array $actor,int $id,int $version,string $deceased,string $relationship,string $purpose):void {
    if(!t9_is_citizen($actor))throw new RuntimeException('Hanya pemohon dapat mengubah draf.');
    t9_valid_form($deceased,$relationship,$purpose);
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT account_id,status,version FROM unified_citizen_requests WHERE id=? FOR UPDATE');
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||!hash_equals((string)$row['account_id'],(string)$actor['id']))throw new RuntimeException('Permohonan tidak dapat diakses.');
        if(!in_array($row['status'],['draf','revisi'],true))throw new RuntimeException('Hanya draf atau permohonan revisi yang dapat diubah.');
        if((int)$row['version']!==$version)throw new RuntimeException('Data sudah berubah. Muat ulang untuk melihat versi terbaru.');
        $q=$db->prepare('UPDATE unified_citizen_requests SET deceased_name=?,relationship=?,purpose=?,version=version+1,updated_at=NOW() WHERE id=?');
        $q->execute([$deceased,$relationship,$purpose,$id]);
        t9_history_insert($db,$id,$row['status'],$row['status'],'edited','Keterangan pengajuan diperbarui.',$actor);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t9_transition(array $actor,int $id,int $version,string $to,string $note):void {
    if($id<1 || $version<1 || mb_strlen($note)>500)throw new InvalidArgumentException('Data tindakan tidak valid.');
    $role=t9_is_citizen($actor)?'warga':t9_staff_role($actor);
    $rules=[
      'warga'=>['draf'=>['diajukan'],'revisi'=>['diajukan']],
      'ptsp'=>['diajukan'=>['ditinjau'],'ditinjau'=>['revisi','diverifikasi']],
      'lurah'=>['diverifikasi'=>['disetujui','ditolak','revisi']],
    ];
    if($role==='' || !isset(t9_stages()[$to]))throw new RuntimeException('Tidak memiliki kewenangan.');
    if($role!=='warga' && in_array($to,['revisi','ditolak'],true) && trim($note)==='')
        throw new InvalidArgumentException('Catatan alasan wajib diisi untuk revisi atau penolakan.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT account_id,status,version FROM unified_citizen_requests WHERE id=? FOR UPDATE');
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Permohonan tidak ditemukan.');
        if($role==='warga' && !hash_equals((string)$row['account_id'],(string)$actor['id']))
            throw new RuntimeException('Tidak dapat mengubah pengajuan pengguna lain.');
        if((int)$row['version']!==$version)throw new RuntimeException('Status sudah berubah. Muat ulang halaman.');
        $from=(string)$row['status'];
        if(!in_array($to,$rules[$role][$from]??[],true))throw new RuntimeException('Perubahan status tidak diizinkan.');
        $q=$db->prepare('UPDATE unified_citizen_requests SET status=?,version=version+1,updated_at=NOW() WHERE id=?');
        $q->execute([$to,$id]);
        t9_history_insert($db,$id,$from,$to,'status_changed',$note,$actor);
        // Notifications are optional until Stage 10 has been installed.
        $followup=__DIR__.'/citizen_followup.php';
        if(is_file($followup)){
            require_once $followup;
            if(t10_ready())t10_notify($db,$id,(string)$row['account_id'],$to);
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t9_history(array $actor,int $id):array {
    if(!t9_case($actor,$id))throw new RuntimeException('Pengajuan tidak ditemukan.');
    $q=db('smartapp')->prepare('SELECT from_status,to_status,event_type,note,actor_source,created_at FROM unified_citizen_request_history WHERE request_id=? ORDER BY id DESC LIMIT 100');
    $q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);
}

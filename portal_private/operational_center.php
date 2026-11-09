<?php
declare(strict_types=1);
/**
 * Tahap 7 - Pusat Operasional, khusus Super Administrator.
 * Data sumber Pegawai dan SmartApp tetap berada di tabel aslinya.
 * Tindak lanjut PPPK/PJLP disimpan di tabel terpisah pada SmartApp.
 */
function operational_modules(): array {
    return [
        'pegawai'=>['name'=>'Data Kepegawaian','db'=>'pegawai','table'=>'users','pk'=>'id'],
        'pppk'=>['name'=>'Pelaporan PPPK','db'=>'pegawai','table'=>'ck_pppk_kinerja','pk'=>'id'],
        'pjlp'=>['name'=>'Kegiatan PJLP / PPSU','db'=>'pegawai','table'=>'pjlp_duty_session','pk'=>'session_id'],
        'waris'=>['name'=>'Pelayanan Ahli Waris','db'=>'smartapp','table'=>'aw_applications','pk'=>'id'],
    ];
}
function operational_review_statuses(): array {
    return ['belum'=>'Belum Ditinjau','ditinjau'=>'Dalam Peninjauan','tindak_lanjut'=>'Perlu Tindak Lanjut','selesai'=>'Selesai Ditinjau'];
}
function operational_context(string $key): array {
    $modules=operational_modules();
    if(!isset($modules[$key]))throw new InvalidArgumentException('Modul tidak dikenali.');
    return $modules[$key];
}
function operational_list(string $key,string $search='',int $page=1): array {
    $m=operational_context($key);
    $page=max(1,min(10000,$page));$limit=20;
    $search=mb_substr(trim($search),0,80);
    $where='';$column='';$params=[];
    if($search!==''){
        $quoted='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
        if($key==='pegawai') $where=' WHERE (username LIKE :term1 OR nama_lengkap LIKE :term2)';
        elseif($key==='waris') $where=' WHERE (reference_no LIKE :term1 OR applicant_name LIKE :term2)';
        elseif($key==='pppk') $where=' WHERE (k.month_key LIKE :term1 OR u.nama_lengkap LIKE :term2)';
        else $where=' WHERE (CAST(d.work_date AS CHAR) LIKE :term1 OR u.nama_lengkap LIKE :term2)';
        $params=[':term1'=>$quoted,':term2'=>$quoted];
    }
    $from=match($key){
        'pegawai'=>'users',
        'pppk'=>'ck_pppk_kinerja k LEFT JOIN users u ON u.id=k.user_id',
        'pjlp'=>'pjlp_duty_session d LEFT JOIN users u ON u.id=d.user_id',
        'waris'=>'aw_applications',
    };
    $cols=match($key){
        'pegawai'=>'id,nama_lengkap AS title,username AS description,role AS kind,status AS state',
        'pppk'=>"k.id,COALESCE(u.nama_lengkap,'Pegawai tidak ditemukan') AS title,k.month_key AS description,k.report_type AS kind,k.status AS state",
        'pjlp'=>"d.session_id AS id,COALESCE(u.nama_lengkap,'Pegawai tidak ditemukan') AS title,CAST(d.work_date AS CHAR) AS description,CAST(d.route_no AS CHAR) AS kind,CAST(d.planned_start AS CHAR) AS state",
        'waris'=>'id,applicant_name AS title,reference_no AS description,relationship AS kind,status AS state',
    };
    $pdo=db($m['db']);
    $statement=$pdo->prepare('SELECT COUNT(*) FROM '.$from.$where);
    foreach($params as $name=>$val)$statement->bindValue($name,$val,PDO::PARAM_STR);
    $statement->execute();$total=(int)$statement->fetchColumn();
    $maxPage=max(1,(int)ceil($total/$limit));$page=min($page,$maxPage);
    $order=match($key){
        'pegawai'=>'id DESC',
        'pppk'=>'k.id DESC',
        'pjlp'=>'d.work_date DESC,d.session_id DESC',
        'waris'=>'id DESC',
    };
    $stmt=$pdo->prepare('SELECT '.$cols.' FROM '.$from.$where.' ORDER BY '.$order.' LIMIT :limit OFFSET :offset');
    foreach($params as $name=>$val)$stmt->bindValue($name,$val,PDO::PARAM_STR);
    $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT);
    $stmt->execute();
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $reviews=[];
    if(in_array($key,['pppk','pjlp'],true) && $rows){
        $ids=array_map(static fn($r)=>(string)$r['id'],$rows);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $lookup=db('smartapp')->prepare("SELECT record_id,review_status,notes,updated_at FROM unified_operational_reviews WHERE module_key=? AND record_id IN ($marks)");
        $lookup->execute(array_merge([$key],$ids));
        foreach($lookup->fetchAll(PDO::FETCH_ASSOC) as $r)$reviews[(string)$r['record_id']]=$r;
    }
    foreach($rows as &$row){
        $row['id']=(string)$row['id'];
        $review=$reviews[$row['id']]??null;
        $row['review_status']=$review['review_status']??'belum';
        $row['review_notes']=$review['notes']??'';
        $row['review_at']=$review['updated_at']??null;
    }
    unset($row);
    return ['total'=>$total,'page'=>$page,'max_page'=>$maxPage,'rows'=>$rows];
}
function operational_exists(string $key,string $id):bool {
    if(!in_array($key,['pppk','pjlp'],true)||!ctype_digit($id)||strlen($id)>20)return false;
    $m=operational_context($key);
    // Column and table from fixed server-controlled map, not input.
    $q=db('pegawai')->prepare('SELECT 1 FROM '.$m['table'].' WHERE '.$m['pk'].'=? LIMIT 1');
    $q->execute([$id]);
    return $q->fetchColumn()!==false;
}
function operational_update_review(array $user,string $key,string $id,string $status,string $notes):void {
    if(!is_super_admin($user))throw new RuntimeException('Akses ditolak.');
    if(!in_array($status,array_keys(operational_review_statuses()),true))throw new InvalidArgumentException('Status peninjauan tidak valid.');
    if(mb_strlen($notes)>500)throw new InvalidArgumentException('Catatan terlalu panjang.');
    if(!operational_exists($key,$id))throw new RuntimeException('Laporan atau sesi tugas tidak ditemukan.');
    $db=db('smartapp');$db->beginTransaction();
    try {
        $prev=$db->prepare('SELECT review_status,notes FROM unified_operational_reviews WHERE module_key=? AND record_id=? FOR UPDATE');
        $prev->execute([$key,$id]);$before=$prev->fetch(PDO::FETCH_ASSOC);
        $old=(string)($before['review_status']??'belum');
        $up=$db->prepare('INSERT INTO unified_operational_reviews (module_key,record_id,review_status,notes,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE review_status=VALUES(review_status),notes=VALUES(notes),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $up->execute([$key,$id,$status,$notes,$user['source'],$user['id']]);
        if(!$before||$old!==$status||$before['notes']!==$notes){
            $hist=$db->prepare('INSERT INTO unified_operational_history (module_key,record_id,old_status,new_status,notes,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
            $hist->execute([$key,$id,$old,$status,$notes,$user['source'],$user['id']]);
        }
        $audit=$db->prepare('INSERT INTO unified_audit (actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $audit->execute([$user['source'],(string)$user['id'],'operational_review_updated','unified_operational_reviews',hash('sha256',$key.':'.$id.':'.$status)]);
        $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
function operational_summary():array {
    $ret=[];
    foreach(operational_modules() as $key=>$m){
        try{$ret[$key]=(int)db($m['db'])->query('SELECT COUNT(*) FROM '.$m['table'])->fetchColumn();}
        catch(Throwable $e){error_log('Stage 7 module count failed: '.$key);$ret[$key]=null;}
    }
    return $ret;
}
function operational_recent(int $limit=12):array {
    $limit=max(1,min(30,$limit));
    $q=db('smartapp')->query('SELECT module_key,record_id,old_status,new_status,created_at FROM unified_operational_history ORDER BY id DESC LIMIT '.$limit);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

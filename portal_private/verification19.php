<?php
declare(strict_types=1);
/**
 * Tahap 19 — pemeriksaan berjenjang administratif lintas laporan Tahap 15–17.
 * BUKAN persetujuan cuti, penilaian PPPK, keputusan RT/RW, tanda tangan
 * digital, atau perubahan transaksi resmi pada database Pegawai.
 */
function v19_sources():array {
    return [
        'ringkas15'=>['table'=>'unified_report_intake','category'=>'category','history'=>'unified_report_intake_history','history_fk'=>'report_id'],
        'pegawai16'=>['table'=>'unified_detailed_forms16','category'=>'kind','history'=>'unified_detailed_form_history16','history_fk'=>'form_id'],
        'wilayah17'=>['table'=>'unified_field_reports17','category'=>'kind','history'=>'unified_field_history17','history_fk'=>'report_id'],
    ];
}
function v19_states():array{
    return [
        'menunggu'=>'Menunggu pemeriksa',
        'perlu_perbaikan'=>'Dikembalikan untuk perbaikan',
        'lolos_periksa'=>'Lolos pemeriksaan kelengkapan',
        'diketahui_lurah'=>'Telah ditelaah Lurah (administratif)',
    ];
}
function v19_actions():array{
    return [
        'periksa'=>'Kelengkapan telah diperiksa',
        'kembalikan'=>'Kembalikan untuk perbaikan',
        'lurah_telaah'=>'Catat telaah Lurah',
        'lurah_kembalikan'=>'Lurah meminta perbaikan',
    ];
}
function v19_hr_group(string $source,string $category):bool{
    return $source==='pegawai16'||($source==='ringkas15'&&in_array($category,['pppk','pjlp','cuti'],true));
}
function v19_reviewer(array $actor,string $source,string $category):bool{
    if(!isset(v19_sources()[$source]))return false;
    if(function_exists('is_super_admin')&&is_super_admin($actor))return true;
    $src=(string)($actor['source']??'');$role=(string)($actor['role']??'');
    if(v19_hr_group($source,$category))
        return $src==='pegawai'&&in_array($role,['admin','admin_kelurahan'],true);
    return $src==='smartapp'&&in_array($role,['ptsp','admin_kelurahan'],true);
}
function v19_lurah(array $actor):bool{
    return ($actor['source']??'')==='smartapp' && ($actor['role']??'')==='lurah';
}
function v19_eligible(array $actor):bool{
    return v19_lurah($actor)
        || (function_exists('is_super_admin')&&is_super_admin($actor))
        || (($actor['source']??'')==='pegawai'&&in_array($actor['role']??'', ['admin','admin_kelurahan'],true))
        || (($actor['source']??'')==='smartapp'&&in_array($actor['role']??'', ['ptsp','admin_kelurahan'],true));
}
function v19_actor_allowed(array $actor):bool{
    if(!v19_eligible($actor))return false;
    require_once __DIR__.'/account_control.php';
    return account_control_allowed($actor);
}
function v19_ready():bool{
    try{
        foreach(['unified_verification19','unified_verification_history19'] as $t)
            db('smartapp')->query('SELECT 1 FROM '.$t.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function v19_report(PDO $db,string $source,int $id,bool $lock=false):?array{
    $map=v19_sources();
    if(!isset($map[$source])||$id<1)return null;
    $spec=$map[$source];
    $sql='SELECT id,'.$spec['category'].' AS category,status,version,actor_source,actor_id,created_at,updated_at'
        .' FROM '.$spec['table'].' WHERE id=?'.($lock?' FOR UPDATE':'');
    $q=$db->prepare($sql);$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return $r?:null;
}
function v19_state(array $report,?array $check):string{
    $status=(string)($check['state']??'menunggu');
    if($status==='perlu_perbaikan'
       &&($report['status']??'')==='diajukan'
       &&(int)($report['version']??0)>(int)($check['report_version']??0))
        return 'menunggu';
    return $status;
}
function v19_may_act(array $actor,string $source,array $report,?array $check,string $action):bool{
    if(!isset(v19_actions()[$action]))return false;
    $category=(string)$report['category'];
    if(!in_array((string)$report['status'],['diajukan','ditinjau'],true))return false;
    if(($actor['source']??'')===$report['actor_source']
       &&hash_equals((string)$actor['id'],(string)$report['actor_id']))return false;
    $state=v19_state($report,$check);
    if(in_array($action,['periksa','kembalikan'],true))
        return v19_reviewer($actor,$source,$category)&&$state==='menunggu';
    if(in_array($action,['lurah_telaah','lurah_kembalikan'],true)){
        if(!v19_lurah($actor)||$state!=='lolos_periksa'||!$check)return false;
        if((int)$check['report_version']!==(int)$report['version'])return false;
        // A second, independent identity must conduct the Lurah's review.
        return !(($actor['source']??'')===$check['reviewer_source']
            &&hash_equals((string)$actor['id'],(string)$check['reviewer_id']));
    }
    return false;
}
function v19_write_original_correction(PDO $db,string $source,int $id,string $old,string $note,array $actor):void {
    $spec=v19_sources()[$source];
    $q=$db->prepare('UPDATE '.$spec['table']." SET status='perbaikan',review_note=?,updated_at=NOW() WHERE id=?");
    $q->execute([$note,$id]);
    $sql='INSERT INTO '.$spec['history'].'('.$spec['history_fk'].',old_status,new_status,note,actor_source,actor_id,created_at)'
        .' VALUES(?,?,?,?,?,?,NOW())';
    $q=$db->prepare($sql);
    $q->execute([$id,$old,'perbaikan',$note,$actor['source'],(string)$actor['id']]);
}
function v19_audit(PDO $db,array $actor,string $action,string $source,int $id):void{
    $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
    $q->execute([$actor['source'],(string)$actor['id'],'verification19_'.$action,'unified_verification19',hash('sha256',$source.':'.$id)]);
}
/**
 * Lock original report and workflow in a single SmartApp transaction.
 * Reject stale version, self-review, duplicate review, or closed/draft reports.
 */
function v19_act(array $actor,string $source,int $reportId,int $expectedVersion,string $action,string $note):void{
    if(!v19_actor_allowed($actor))throw new RuntimeException('Akun pemeriksa tidak berwenang.');
    if(!isset(v19_sources()[$source])||$reportId<1||$expectedVersion<1
       ||!isset(v19_actions()[$action]))throw new InvalidArgumentException('Permintaan verifikasi tidak valid.');
    if(mb_strlen($note)>500)throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
    if(in_array($action,['kembalikan','lurah_kembalikan'],true)&&mb_strlen(trim($note))<12)
        throw new InvalidArgumentException('Alasan perbaikan minimal 12 karakter.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $report=v19_report($db,$source,$reportId,true);
        if(!$report)throw new RuntimeException('Laporan tidak ditemukan.');
        if((int)$report['version']!==$expectedVersion)
            throw new RuntimeException('Versi laporan berubah; muat ulang halaman.');
        $q=$db->prepare('SELECT * FROM unified_verification19 WHERE source_kind=? AND source_id=? FOR UPDATE');
        $q->execute([$source,$reportId]);$check=$q->fetch(PDO::FETCH_ASSOC)?:null;
        if(!v19_may_act($actor,$source,$report,$check,$action))
            throw new RuntimeException('Status atau kewenangan tidak cocok; tindakan ditolak.');
        $old=v19_state($report,$check);
        $new=match($action){
            'periksa'=>'lolos_periksa',
            'kembalikan','lurah_kembalikan'=>'perlu_perbaikan',
            'lurah_telaah'=>'diketahui_lurah',
        };
        if(in_array($action,['kembalikan','lurah_kembalikan'],true))
            v19_write_original_correction($db,$source,$reportId,(string)$report['status'],trim($note),$actor);
        if(!$check){
            $q=$db->prepare('INSERT INTO unified_verification19(source_kind,source_id,report_version,state,reviewer_source,reviewer_id,review_note,lurah_source,lurah_id,lurah_note,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NULL,NULL,NULL,NOW(),NOW())');
            $q->execute([$source,$reportId,(int)$report['version'],$new,$actor['source'],(string)$actor['id'],trim($note)]);
        }elseif(in_array($action,['periksa','kembalikan'],true)){
            $q=$db->prepare('UPDATE unified_verification19 SET state=?,report_version=?,reviewer_source=?,reviewer_id=?,review_note=?,lurah_source=NULL,lurah_id=NULL,lurah_note=NULL,updated_at=NOW() WHERE source_kind=? AND source_id=?');
            $q->execute([$new,(int)$report['version'],$actor['source'],(string)$actor['id'],trim($note),$source,$reportId]);
        }else{
            $q=$db->prepare('UPDATE unified_verification19 SET state=?,lurah_source=?,lurah_id=?,lurah_note=?,updated_at=NOW() WHERE source_kind=? AND source_id=?');
            $q->execute([$new,$actor['source'],(string)$actor['id'],trim($note),$source,$reportId]);
        }
        $q=$db->prepare('INSERT INTO unified_verification_history19(source_kind,source_id,report_version,old_state,new_state,action,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
        $q->execute([$source,$reportId,(int)$report['version'],$old,$new,$action,trim($note),$actor['source'],(string)$actor['id']]);
        v19_audit($db,$actor,$action,$source,$reportId);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function v19_queue(array $actor,string $source='',int $limit=40):array{
    if(!v19_actor_allowed($actor))throw new RuntimeException('Akses antrean ditolak.');
    if($source!==''&&!isset(v19_sources()[$source]))throw new InvalidArgumentException('Modul tidak tersedia.');
    $db=db('smartapp');$max=max(1,min(50,$limit));$all=[];
    foreach(v19_sources() as $key=>$spec){
        if($source!==''&&$source!==$key)continue;
        $sql='SELECT r.id,r.'.$spec['category'].' AS category,r.status,r.version,r.actor_source,r.actor_id,r.created_at,'
            .'v.state,v.report_version,v.reviewer_source,v.reviewer_id,v.review_note,v.lurah_source,v.lurah_id,v.lurah_note'
            .' FROM '.$spec['table'].' r LEFT JOIN unified_verification19 v ON v.source_kind=? AND v.source_id=r.id'
            ." WHERE r.status IN ('diajukan','ditinjau') ORDER BY r.id DESC LIMIT 150";
        try{
            $q=$db->prepare($sql);$q->execute([$key]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                if(!v19_reviewer($actor,$key,(string)$row['category'])&&!v19_lurah($actor))continue;
                $state=v19_state($row,isset($row['state'])&&$row['state']!==null?$row:null);
                if(v19_lurah($actor)&&!v19_reviewer($actor,$key,(string)$row['category'])&&$state!=='lolos_periksa')continue;
                $row['source_kind']=$key;$row['effective_state']=$state;
                $row['can_precheck']=v19_may_act($actor,$key,$row,$row['state']!==null?$row:null,'periksa');
                $row['can_lurah']=v19_may_act($actor,$key,$row,$row['state']!==null?$row:null,'lurah_telaah');
                unset($row['actor_id'],$row['actor_source'],$row['reviewer_id'],$row['reviewer_source'],$row['lurah_id'],$row['lurah_source']);
                $all[]=$row;
            }
        }catch(Throwable $e){error_log('Tahap19 antrean tidak dapat dibaca: '.$key);}
    }
    usort($all,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
    return array_slice($all,0,$max);
}
function v19_detail(array $actor,string $source,int $id):?array{
    if(!v19_actor_allowed($actor)||!isset(v19_sources()[$source])||$id<1)return null;
    $db=db('smartapp');$spec=v19_sources()[$source];
    $q=$db->prepare('SELECT id,'.$spec['category'].' AS category,actor_source,actor_id,status,version,created_at,'
       .($source==='ringkas15'?'title,body,location,activity_date,end_date':'payload_json')
       .' FROM '.$spec['table'].' WHERE id=? LIMIT 1');
    $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r || !v19_reviewer($actor,$source,(string)$r['category'])&&!v19_lurah($actor))return null;
    if(v19_lurah($actor)&&!v19_reviewer($actor,$source,(string)$r['category'])){
        $check=$db->prepare('SELECT state,report_version FROM unified_verification19 WHERE source_kind=? AND source_id=? LIMIT 1');
        $check->execute([$source,$id]);$c=$check->fetch(PDO::FETCH_ASSOC);
        if(!$c||$c['state']!=='lolos_periksa'||(int)$c['report_version']!==(int)$r['version'])return null;
    }
    unset($r['actor_id'],$r['actor_source']);
    if(isset($r['payload_json'])){$payload=json_decode((string)$r['payload_json'],true);$r['payload']=is_array($payload)?$payload:[];unset($r['payload_json']);}
    return $r;
}
function v19_summary(array $actor):array{
    $list=v19_queue($actor,'',50);
    $total=['menunggu'=>0,'perlu_perbaikan'=>0,'lolos_periksa'=>0,'diketahui_lurah'=>0];
    foreach($list as $r)if(isset($total[$r['effective_state']]))++$total[$r['effective_state']];
    return $total;
}

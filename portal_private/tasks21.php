<?php
declare(strict_types=1);
/**
 * Stage 21 — assign internal report review and track a documented internal
 * SLA per category. No official SLA is assumed; no formal approval granted.
 * Scheduled notifications are INTERNAL ONLY, disabled by default and CLI only.
 */
require_once __DIR__.'/monitor20.php';
function t21_catalog():array {
    require_once __DIR__.'/unified_reports.php';
    require_once __DIR__.'/detailed_forms16.php';
    require_once __DIR__.'/field_reports17.php';
    $ringkas=[];
    foreach(lr_categories() as $key=>$meta)if($key!=='waris')$ringkas[$key]=$meta['label'];
    $pegawai=[];foreach(f16_kinds() as $key=>$meta)$pegawai[$key]=$meta['name'];
    $wilayah=[];foreach(w17_catalog() as $key=>$meta)$wilayah[$key]=$meta['name'];
    return ['ringkas15'=>$ringkas,'pegawai16'=>$pegawai,'wilayah17'=>$wilayah];
}
function t21_steps():array {
    return ['pemeriksa'=>'Pemeriksaan kelengkapan','lurah'=>'Telaah administratif Lurah'];
}
function t21_ready():bool {
    try{
        foreach(['unified_assignments21','unified_alerts21','unified_schedule21'] as $t)
            db('smartapp')->query('SELECT 1 FROM '.$t.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function t21_super(array $actor):void {
    m20_super_guard($actor);
    if(!t21_ready())throw new RuntimeException('Modul penugasan belum terpasang.');
}
function t21_pair(string $source,string $category,string $step):void {
    if(!isset(t21_catalog()[$source][$category])||!isset(t21_steps()[$step]))
        throw new InvalidArgumentException('Modul, kategori atau tahap tidak dikenali.');
}
function t21_date(string $s):string {
    if(!preg_match('/^20\d\d-(0[1-9]|1[0-2])-[0-3]\d$/D',$s))
        throw new InvalidArgumentException('Tanggal efektif wajib YYYY-MM-DD.');
    [$y,$m,$d]=array_map('intval',explode('-',$s));
    if(!checkdate($m,$d,$y))throw new InvalidArgumentException('Tanggal kalender tidak valid.');
    return $s;
}
/** Pull limited ACTIVE native identities, never provision new users or roles. */
function t21_candidates():array {
    $out=[];
    try{
        $q=db('pegawai')->query("SELECT id,nama_lengkap AS name,role FROM users WHERE status='active' AND role IN ('admin','admin_kelurahan') ORDER BY nama_lengkap LIMIT 150");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)
            $out[]=['source'=>'pegawai','id'=>(string)$r['id'],'name'=>(string)$r['name'],'role'=>(string)$r['role']];
    }catch(Throwable $e){error_log('Stage21 Pegawai reviewer directory unavailable.');}
    try{
        $q=db('smartapp')->query("SELECT id,name,role FROM unified_portal_users WHERE active=1 AND role IN ('ptsp','admin_kelurahan','lurah') ORDER BY name LIMIT 150");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)
            $out[]=['source'=>'smartapp','id'=>(string)$r['id'],'name'=>(string)$r['name'],'role'=>(string)$r['role']];
    }catch(Throwable $e){error_log('Stage21 SmartApp reviewer directory unavailable.');}
    return $out;
}
function t21_identity(string $source,string $id):?array {
    if(!in_array($source,['pegawai','smartapp'],true)||$id===''||strlen($id)>100||str_contains($id,"\0"))return null;
    try{
        $db=db($source==='pegawai'?'pegawai':'smartapp');
        if($source==='pegawai'){
            $q=$db->prepare("SELECT id,nama_lengkap AS name,role FROM users WHERE id=? AND status='active' LIMIT 1");
        }else{
            $q=$db->prepare("SELECT id,name,role FROM unified_portal_users WHERE id=? AND active=1 LIMIT 1");
        }
        $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;
        $actor=['source'=>$source,'id'=>(string)$r['id'],'name'=>(string)$r['name'],'role'=>(string)$r['role']];
        require_once __DIR__.'/account_control.php';
        return account_control_allowed($actor)?$actor:null;
    }catch(Throwable $e){error_log('Stage21 assignment identity unavailable.');return null;}
}
function t21_qualified(array $person,string $source,string $category,string $step):bool {
    if(!isset(t21_catalog()[$source][$category])||!isset(t21_steps()[$step]))return false;
    return $step==='pemeriksa'
        ? v19_reviewer($person,$source,$category) && !v19_lurah($person)
        : v19_lurah($person);
}
function t21_save(array $admin,string $source,string $category,string $step,string $reviewerSource,string $reviewerId,int $days,string $reference,string $effective,bool $enabled):void {
    t21_super($admin);t21_pair($source,$category,$step);
    if($days<1||$days>60)throw new InvalidArgumentException('Tenggat internal 1–60 hari kalender.');
    $reference=trim($reference);
    if(mb_strlen($reference)<8||mb_strlen($reference)>180)
        throw new InvalidArgumentException('Dasar penetapan tenggat harus 8–180 karakter (misalnya nomor SOP internal).');
    t21_date($effective);
    $person=t21_identity($reviewerSource,$reviewerId);
    if(!$person||!t21_qualified($person,$source,$category,$step))
        throw new RuntimeException('Pemeriksa tidak aktif atau tidak sesuai jenis laporan dan perannya.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('INSERT INTO unified_assignments21(source_kind,category,step,reviewer_source,reviewer_id,due_days,reference_note,effective_from,enabled,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE reviewer_source=VALUES(reviewer_source),reviewer_id=VALUES(reviewer_id),due_days=VALUES(due_days),reference_note=VALUES(reference_note),effective_from=VALUES(effective_from),enabled=VALUES(enabled),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $q->execute([$source,$category,$step,$reviewerSource,$reviewerId,$days,$reference,$effective,(int)$enabled,$admin['source'],(string)$admin['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$admin['source'],(string)$admin['id'],'assignment21_updated','unified_assignments21',hash('sha256',$source.':'.$category.':'.$step.':'.$reviewerSource.':'.$reviewerId.':'.$days.':'.(int)$enabled)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t21_assignments():array {
    if(!t21_ready())return [];
    $q=db('smartapp')->query('SELECT source_kind,category,step,reviewer_source,reviewer_id,due_days,reference_note,effective_from,enabled,updated_at FROM unified_assignments21 ORDER BY source_kind,category,step');
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t21_schedule():array {
    if(!t21_ready())return ['enabled'=>0,'last_run'=>null,'last_count'=>0,'last_incomplete'=>0];
    $q=db('smartapp')->query('SELECT enabled,last_run,last_count,last_incomplete FROM unified_schedule21 WHERE id=1');
    return $q->fetch(PDO::FETCH_ASSOC)?:['enabled'=>0,'last_run'=>null,'last_count'=>0,'last_incomplete'=>0];
}
function t21_toggle_schedule(array $admin,bool $enable):void {
    t21_super($admin);
    if($enable){
        $q=db('smartapp')->query("SELECT COUNT(*) FROM unified_assignments21 WHERE enabled=1 AND effective_from<=CURRENT_DATE()");
        if((int)$q->fetchColumn()===0)throw new RuntimeException('Aktifkan minimal satu penugasan valid dahulu.');
    }
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('UPDATE unified_schedule21 SET enabled=?,updated_by_source=?,updated_by_id=?,updated_at=NOW() WHERE id=1');
        $q->execute([(int)$enable,$admin['source'],(string)$admin['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$admin['source'],(string)$admin['id'],'assignment21_schedule_toggle','unified_schedule21',hash('sha256',(string)(int)$enable)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function t21_expected_phase(string $step):string{
    return $step==='pemeriksa'?'menunggu_pemeriksa':'menunggu_lurah';
}
function t21_assignment_valid(array $a,string $today):?array {
    if(empty($a['enabled'])||($a['effective_from']??'')>$today)return null;
    $person=t21_identity((string)$a['reviewer_source'],(string)$a['reviewer_id']);
    if(!$person||!t21_qualified($person,(string)$a['source_kind'],(string)$a['category'],(string)$a['step']))return null;
    return $person;
}
function t21_due(array $row,array $assignment,string $today):bool{
    if((string)$row['category']!==(string)$assignment['category'])return false;
    if(m20_phase($row)!==t21_expected_phase((string)$assignment['step']))return false;
    if(($row['actor_source']??'')===$assignment['reviewer_source']
       &&hash_equals((string)($row['actor_id']??''),(string)$assignment['reviewer_id']))return false;
    $days=(int)$assignment['due_days'];
    if($days<1||$days>60)return false;
    return m20_days_since(m20_reference_date($row),$today)>=$days;
}
/** Returns only existing submitted reports, per assignment, with a hard bound. */
function t21_due_candidates(int $max=50):array {
    if(!t21_ready())return ['rows'=>[],'incomplete'=>true,'active'=>0];
    $today=date('Y-m-d');$rows=[];$incomplete=false;$active=0;
    $db=db('smartapp');
    foreach(t21_assignments() as $rule){
        $person=t21_assignment_valid($rule,$today);
        if(!$person)continue;
        ++$active;
        $source=(string)$rule['source_kind'];$kind=(string)$rule['category'];
        $table=m20_sources()[$source]['table'];$cat=m20_sources()[$source]['cat'];
        $sql='SELECT r.id,r.'.$cat.' AS category,r.status AS report_status,r.version,'
            .'r.actor_source,r.actor_id,r.created_at,r.updated_at AS report_updated_at,'
            .'v.state AS verify_state,v.report_version AS verify_version,v.updated_at AS verify_updated_at'
            .' FROM '.$table.' r LEFT JOIN unified_verification19 v'
            .' ON v.source_kind=? AND v.source_id=r.id'
            ." WHERE r.".$cat."=? AND r.status IN ('diajukan','ditinjau') ORDER BY r.updated_at ASC LIMIT 121";
        try{
            $q=$db->prepare($sql);$q->execute([$source,$kind]);
            $reports=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($reports)>120){$incomplete=true;$reports=array_slice($reports,0,120);}
            foreach($reports as $row) {
                if(!t21_due($row,$rule,$today))continue;
                $rows[]=[
                    'source'=>$source,'id'=>(int)$row['id'],'category'=>$kind,
                    'version'=>(int)$row['version'],'phase'=>m20_phase($row),
                    'days'=>(int)m20_days_since(m20_reference_date($row),$today),
                    'due_days'=>(int)$rule['due_days'],'step'=>$rule['step'],
                    'recipient_source'=>$person['source'],'recipient_id'=>$person['id'],
                    'recipient_name'=>$person['name'],
                ];
            }
        }catch(Throwable $e){$incomplete=true;error_log('Stage21 due query unavailable for module '.$source);}
    }
    usort($rows,static fn($a,$b)=>($b['days']<=>$a['days'])?:strcmp($a['source'],$b['source']));
    $limit=max(1,min(100,$max));
    if(count($rows)>$limit)$incomplete=true;
    return ['rows'=>array_slice($rows,0,$limit),'incomplete'=>$incomplete,'active'=>$active];
}
function t21_write_alert(PDO $db,array $candidate):bool {
    $source=(string)$candidate['source'];$id=(int)$candidate['id'];
    $q=$db->prepare('SELECT * FROM unified_assignments21 WHERE source_kind=? AND category=? AND step=? FOR UPDATE');
    $q->execute([$source,$candidate['category'],$candidate['step']]);
    $rule=$q->fetch(PDO::FETCH_ASSOC);
    if(!$rule||$rule['reviewer_source']!==$candidate['recipient_source']
        ||!hash_equals((string)$rule['reviewer_id'],(string)$candidate['recipient_id'])
        ||!t21_assignment_valid($rule,date('Y-m-d')))return false;
    $report=m20_report_row($db,$source,$id,true);
    if(!$report||(int)$report['version']!==(int)$candidate['version']
        ||!t21_due($report,$rule,date('Y-m-d')))return false;
    $text='Penugasan pemeriksaan administratif: '.($candidate['step']==='lurah'?'telaah Lurah':'pemeriksaan kelengkapan')
        .' untuk '.$source.' #'.$id.'. Buka antrean verifikasi sesuai kewenangan.';
    $q=$db->prepare('INSERT IGNORE INTO unified_alerts21(source_kind,source_id,report_version,phase,recipient_source,recipient_id,message,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
    $q->execute([$source,$id,$candidate['version'],$candidate['phase'],$rule['reviewer_source'],$rule['reviewer_id'],$text]);
    if($q->rowCount()!==1)return false;
    $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
    $q->execute(['smartapp','system21','assignment21_internal_alert','unified_alerts21',hash('sha256',$source.':'.$id.':'.$candidate['version'].':'.$candidate['phase'])]);
    return true;
}
/**
 * Manual Super Admin trigger or CLI dispatcher. All alerts are created inside
 * portal DB; no email, WhatsApp or SMS. Cron must be enabled explicitly.
 */
function t21_run(bool $manual=false,?array $admin=null):array{
    if($manual){
        if(!$admin)throw new RuntimeException('Autentikasi Super Admin diperlukan.');
        t21_super($admin);
    }else{
        if(PHP_SAPI!=='cli')throw new RuntimeException('Penjadwal tidak boleh diakses dari HTTP.');
        if(!t21_ready()||empty(t21_schedule()['enabled']))
            return ['created'=>0,'checked'=>0,'incomplete'=>false,'status'=>'disabled'];
    }
    $db=db('smartapp');$held=(int)$db->query("SELECT GET_LOCK('smartcikoko_monitor21',2)")->fetchColumn();
    if($held!==1)throw new RuntimeException('Penjadwal sedang dijalankan proses lain.');
    try{
        $candidates=t21_due_candidates(80);
        $created=0;$seen=0;
        foreach($candidates['rows'] as $candidate){
            ++$seen;$db->beginTransaction();
            try{
                if(t21_write_alert($db,$candidate))++$created;
                $db->commit();
            }catch(Throwable $e){
                if($db->inTransaction())$db->rollBack();
                error_log('Stage21 single alert unavailable: '.get_class($e));
                $candidates['incomplete']=true;
            }
        }
        $q=$db->prepare('UPDATE unified_schedule21 SET last_run=NOW(),last_count=?,last_incomplete=? WHERE id=1');
        $q->execute([$created,(int)$candidates['incomplete']]);
        return ['created'=>$created,'checked'=>$seen,'incomplete'=>(bool)$candidates['incomplete'],
            'status'=>'completed','active'=>$candidates['active']];
    }finally{
        try{$db->query("SELECT RELEASE_LOCK('smartcikoko_monitor21')")->fetchColumn();}
        catch(Throwable $e){error_log('Stage21 scheduler lock release failed.');}
    }
}
function t21_alert_current(array $actor,array $a):bool{
    if(($actor['source']??'')!==($a['recipient_source']??'')
       ||!hash_equals((string)($actor['id']??''),(string)($a['recipient_id']??'')))return false;
    $ruleQ=db('smartapp')->prepare('SELECT * FROM unified_assignments21 WHERE source_kind=? AND category=? AND step=?');
    $ruleQ->execute([$a['source_kind'],$a['category'],$a['step']]);
    $rule=$ruleQ->fetch(PDO::FETCH_ASSOC);
    if(!$rule||$rule['reviewer_source']!==$actor['source']
        ||!hash_equals((string)$rule['reviewer_id'],(string)$actor['id'])
        ||!t21_assignment_valid($rule,date('Y-m-d')))return false;
    $report=m20_report_row(db('smartapp'),(string)$a['source_kind'],(int)$a['source_id']);
    return $report && (int)$report['version']===(int)$a['report_version']
        &&m20_phase($report)===$a['phase']
        &&t21_due($report,$rule,date('Y-m-d'));
}
function t21_inbox(array $actor,int $max=35):array{
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor)||!t21_ready())return [];
    $q=db('smartapp')->prepare('SELECT id,source_kind,source_id,report_version,phase,recipient_source,recipient_id,message,created_at,read_at FROM unified_alerts21 WHERE recipient_source=? AND recipient_id=? ORDER BY id DESC LIMIT 75');
    $q->execute([(string)$actor['source'],(string)$actor['id']]);
    $rows=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $report=m20_report_row(db('smartapp'),(string)$row['source_kind'],(int)$row['source_id']);
        if(!$report)continue;
        $row['category']=(string)$report['category'];
        $row['step']=$row['phase']==='menunggu_lurah'?'lurah':'pemeriksa';
        if(!t21_alert_current($actor,$row))continue;
        unset($row['recipient_source'],$row['recipient_id']);
        $rows[]=$row;
        if(count($rows)>=max(1,min($max,50)))break;
    }
    return $rows;
}
function t21_read(array $actor,int $id):void{
    if($id<1)throw new InvalidArgumentException('Pengingat tidak valid.');
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))throw new RuntimeException('Akses akun ditolak.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM unified_alerts21 WHERE id=? AND recipient_source=? AND recipient_id=? FOR UPDATE');
        $q->execute([$id,$actor['source'],(string)$actor['id']]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Pengingat tidak ditemukan.');
        $r=m20_report_row($db,(string)$row['source_kind'],(int)$row['source_id']);
        if(!$r)throw new RuntimeException('Laporan pengingat tidak tersedia.');
        $row['category']=$r['category'];
        $row['step']=$row['phase']==='menunggu_lurah'?'lurah':'pemeriksa';
        if(!t21_alert_current($actor,$row))throw new RuntimeException('Penugasan telah berubah atau laporan selesai.');
        $q=$db->prepare('UPDATE unified_alerts21 SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND recipient_source=? AND recipient_id=?');
        $q->execute([$id,$actor['source'],(string)$actor['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$actor['source'],(string)$actor['id'],'assignment21_alert_read','unified_alerts21',hash('sha256',(string)$id)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

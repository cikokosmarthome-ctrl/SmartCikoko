<?php
declare(strict_types=1);
/**
 * Stage 20 — monthly administrative reporting metrics and rule-based internal
 * follow-up reminders. Never interprets absence of a report as employee
 * delinquency; no official performance scoring or outbound messaging.
 */
require_once __DIR__.'/verification19.php';

function m20_sources():array {
    return [
        'ringkas15'=>['table'=>'unified_report_intake','cat'=>'category','label'=>'Laporan Ringkas'],
        'pegawai16'=>['table'=>'unified_detailed_forms16','cat'=>'kind','label'=>'Formulir Pegawai'],
        'wilayah17'=>['table'=>'unified_field_reports17','cat'=>'kind','label'=>'Laporan Wilayah'],
    ];
}
function m20_ready():bool {
    try{
        foreach(['unified_monitor_rules20','unified_monitor_reminders20'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function m20_month(string $month):array {
    if(!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/D',$month))
        throw new InvalidArgumentException('Periode harus berupa tahun-bulan yang valid.');
    $start=$month.'-01';
    $date=new DateTimeImmutable($start);
    return [$start,$date->modify('first day of next month')->format('Y-m-d')];
}
function m20_phase(array $report):string {
    $status=(string)($report['report_status']??$report['status']??'');
    if($status==='ditutup')return 'selesai';
    if($status==='draf')return 'draf';
    $version=(int)($report['version']??0);
    $verifyVersion=(int)($report['verify_version']??$report['report_version']??0);
    $verify=(string)($report['verify_state']??$report['state']??'');
    if($status==='perbaikan')return 'perbaikan';
    if($verify==='perlu_perbaikan'&&$version>$verifyVersion)return 'menunggu_pemeriksa';
    if($verify==='perlu_perbaikan')return 'perbaikan';
    if($verify==='diketahui_lurah'&&$version===$verifyVersion)return 'selesai';
    if($verify==='lolos_periksa'&&$version===$verifyVersion)return 'menunggu_lurah';
    if(in_array($status,['diajukan','ditinjau'],true))return 'menunggu_pemeriksa';
    return 'tidak_aktif';
}
function m20_phase_labels():array {
    return [
        'menunggu_pemeriksa'=>'Menunggu Pemeriksa',
        'menunggu_lurah'=>'Menunggu Telaah Lurah',
        'perbaikan'=>'Menunggu Perbaikan Pelapor',
        'selesai'=>'Administrasi Selesai',
        'draf'=>'Draf Pribadi',
        'tidak_aktif'=>'Di luar pemantauan',
    ];
}
function m20_reference_date(array $report):string {
    $value=(string)($report['report_updated_at']??$report['updated_at']??$report['created_at']??'');
    $verify=(string)($report['verify_updated_at']??'');
    if($verify!==''&&$verify>$value)$value=$verify;
    return $value;
}
function m20_days_since(string $date,string $today):int {
    if(!preg_match('/^\d{4}-\d\d-\d\d/', $date)
        ||!preg_match('/^\d{4}-\d\d-\d\d$/D',$today))return 0;
    try{
        $start=new DateTimeImmutable(substr($date,0,10));
        $end=new DateTimeImmutable($today);
        if($start>$end)return 0;
        return (int)$start->diff($end)->days;
    }catch(Throwable $e){return 0;}
}
function m20_due(array $report,array $rule,string $today):bool {
    if(empty($rule['enabled']))return false;
    $days=(int)($rule['followup_days']??0);
    if($days<1||$days>60)return false;
    if(!in_array(m20_phase($report),['menunggu_pemeriksa','menunggu_lurah','perbaikan'],true))
        return false;
    return m20_days_since(m20_reference_date($report),$today)>=$days;
}
function m20_actor_is_super(array $actor):bool {
    return function_exists('is_super_admin')&&is_super_admin($actor);
}
function m20_super_guard(array $actor):void {
    if(!m20_actor_is_super($actor))
        throw new RuntimeException('Kewenangan Super Admin dan MFA diperlukan.');
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))
        throw new RuntimeException('Akses akun sedang dibatasi.');
}
function m20_rules():array {
    $result=[];
    foreach(m20_sources() as $key=>$meta)
        $result[$key]=['source_kind'=>$key,'followup_days'=>3,'enabled'=>0];
    if(!m20_ready())return $result;
    $q=db('smartapp')->query('SELECT source_kind,followup_days,enabled FROM unified_monitor_rules20');
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)
        if(isset($result[$row['source_kind']]))$result[$row['source_kind']]=$row;
    return $result;
}
function m20_rule_change(array $actor,string $source,int $days,bool $enable):void {
    m20_super_guard($actor);
    if(!isset(m20_sources()[$source])||$days<1||$days>60)
        throw new InvalidArgumentException('Ambang pengingat 1 sampai 60 hari.');
    if(!m20_ready())throw new RuntimeException('Pusat monitoring belum diinstal.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('INSERT INTO unified_monitor_rules20(source_kind,followup_days,enabled,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE followup_days=VALUES(followup_days),enabled=VALUES(enabled),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $q->execute([$source,$days,$enable?1:0,$actor['source'],(string)$actor['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$actor['source'],(string)$actor['id'],'monitor20_rule_changed','unified_monitor_rules20',hash('sha256',$source.':'.$days.':'.(int)$enable)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function m20_report_row(PDO $db,string $source,int $id,bool $lock=false):?array {
    $sources=m20_sources();
    if(!isset($sources[$source])||$id<1)return null;
    $def=$sources[$source];
    $sql='SELECT r.id,r.'.$def['cat'].' AS category,r.status AS report_status,r.version,'
        .'r.actor_source,r.actor_id,r.created_at,r.updated_at AS report_updated_at,'
        .'v.state AS verify_state,v.report_version AS verify_version,v.updated_at AS verify_updated_at'
        .' FROM '.$def['table'].' r LEFT JOIN unified_verification19 v'
        .' ON v.source_kind=? AND v.source_id=r.id WHERE r.id=?'
        .($lock?' FOR UPDATE':'');
    $q=$db->prepare($sql);$q->execute([$source,$id]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
function m20_monthly(array $admin,string $month):array {
    m20_super_guard($admin);
    [$start,$next]=m20_month($month);
    $all=[];$missing=[];
    foreach(m20_sources() as $key=>$spec){
        $all[$key]=['submitted'=>0,'correction'=>0,'closed'=>0,'categories'=>[]];
        $sql='SELECT '.$spec['cat'].' AS category,status,COUNT(*) AS total FROM '.$spec['table']
            ." WHERE created_at>=? AND created_at<? AND status<>'draf'"
            .' GROUP BY '.$spec['cat'].',status';
        try{
            $q=db('smartapp')->prepare($sql);$q->execute([$start,$next]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                $n=(int)$row['total'];$cat=(string)$row['category'];
                $all[$key]['submitted']+=$n;
                if($row['status']==='perbaikan')$all[$key]['correction']+=$n;
                if($row['status']==='ditutup')$all[$key]['closed']+=$n;
                $all[$key]['categories'][$cat]=($all[$key]['categories'][$cat]??0)+$n;
            }
            ksort($all[$key]['categories']);
        }catch(Throwable $e){
            $missing[]=$key;error_log('Stage20 monthly summary missing source: '.$key);
        }
    }
    return ['month'=>$month,'modules'=>$all,'incomplete'=>$missing];
}
function m20_queue(array $admin,int $limit=60):array {
    m20_super_guard($admin);
    if(!m20_ready())throw new RuntimeException('Installer monitoring belum dijalankan.');
    $rules=m20_rules();$today=date('Y-m-d');
    $entries=[];$possiblyIncomplete=false;
    foreach(m20_sources() as $source=>$meta){
        if(empty($rules[$source]['enabled']))continue;
        $sql='SELECT r.id,r.'.$meta['cat'].' AS category,r.status AS report_status,r.version,'
            .'r.actor_source,r.actor_id,r.created_at,r.updated_at AS report_updated_at,'
            .'v.state AS verify_state,v.report_version AS verify_version,v.updated_at AS verify_updated_at'
            .' FROM '.$meta['table'].' r LEFT JOIN unified_verification19 v'
            .' ON v.source_kind=? AND v.source_id=r.id'
            ." WHERE r.status IN ('diajukan','ditinjau','perbaikan') ORDER BY r.updated_at ASC LIMIT 301";
        try{
            $q=db('smartapp')->prepare($sql);$q->execute([$source]);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>300){$possiblyIncomplete=true;$rows=array_slice($rows,0,300);}
            foreach($rows as $row){
                if(m20_due($row,$rules[$source],$today)){
                    $row['source_kind']=$source;
                    $row['phase']=m20_phase($row);
                    $row['days_unchanged']=m20_days_since(m20_reference_date($row),$today);
                    $row['threshold_days']=(int)$rules[$source]['followup_days'];
                    // Do not return reporter IDs or user names to HTML.
                    unset($row['actor_source'],$row['actor_id']);
                    $entries[]=$row;
                }
            }
        }catch(Throwable $e){
            $possiblyIncomplete=true;
            error_log('Stage20 monitoring source unavailable: '.$source);
        }
    }
    usort($entries,static fn($a,$b)=>($b['days_unchanged']<=>$a['days_unchanged'])
        ?:strcmp($a['source_kind'],$b['source_kind']));
    if(count($entries)>$limit)$possiblyIncomplete=true;
    return ['rows'=>array_slice($entries,0,max(1,min(100,$limit))),
        'count_in_sample'=>count($entries),'incomplete'=>$possiblyIncomplete,
        'enabled_rules'=>count(array_filter($rules,static fn($r)=>!empty($r['enabled'])))];
}
/**
 * Explicit Super Admin action, NOT automatic WhatsApp/email. One internal
 * reminder per report-version/phase; unique key prevents repeated nudges.
 */
function m20_remind(array $admin,string $source,int $id,int $version,string $expectedPhase):int {
    m20_super_guard($admin);
    if(!isset(m20_sources()[$source])||$id<1||$version<1
        ||!in_array($expectedPhase,['menunggu_pemeriksa','menunggu_lurah','perbaikan'],true))
        throw new InvalidArgumentException('Laporan pengingat tidak valid.');
    if(!m20_ready())throw new RuntimeException('Pusat pengingat belum terpasang.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $row=m20_report_row($db,$source,$id,true);
        if(!$row||$version!==(int)$row['version']||m20_phase($row)!==$expectedPhase)
            throw new RuntimeException('Versi atau status laporan berubah. Segarkan halaman.');
        $q=$db->prepare('SELECT followup_days,enabled FROM unified_monitor_rules20 WHERE source_kind=?');
        $q->execute([$source]);$rule=$q->fetch(PDO::FETCH_ASSOC);
        if(!$rule||!m20_due($row,$rule,date('Y-m-d')))
            throw new RuntimeException('Laporan belum memenuhi batas pengingat aktif.');
        // Without an authenticated assignment roster, do not assume who the
        // reviewer/Lurah is. Send those overdue alerts to Super Admin's own
        // inbox. Only correction requests go to the original reporter.
        $recipientSource=$expectedPhase==='perbaikan'?(string)$row['actor_source']:(string)$admin['source'];
        $recipientId=$expectedPhase==='perbaikan'?(string)$row['actor_id']:(string)$admin['id'];
        $message=$expectedPhase==='perbaikan'
            ?'Laporan '.$source.' #'.$id.' menunggu perbaikan Anda. Buka formulir sesuai kategori.'
            :'Laporan '.$source.' #'.$id.' memerlukan pemantauan administratif oleh petugas yang berwenang.';
        $q=$db->prepare('INSERT IGNORE INTO unified_monitor_reminders20(source_kind,source_id,report_version,phase,recipient_source,recipient_id,sender_source,sender_id,message,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
        $q->execute([$source,$id,$version,$expectedPhase,
            $recipientSource,$recipientId,(string)$admin['source'],(string)$admin['id'],$message]);
        if($q->rowCount()!==1)
            throw new RuntimeException('Pengingat untuk status dan versi ini sudah pernah dikirim.');
        $newId=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$admin['source'],(string)$admin['id'],'monitor20_reminder_created','unified_monitor_reminders20',hash('sha256',$source.':'.$id.':'.$version.':'.$expectedPhase)]);
        $db->commit();return $newId;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function m20_inbox(array $actor,int $limit=35):array {
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))throw new RuntimeException('Akun tidak memiliki akses.');
    if(!m20_ready())return [];
    $q=db('smartapp')->prepare('SELECT id,source_kind,source_id,phase,message,created_at,read_at FROM unified_monitor_reminders20 WHERE recipient_source=? AND recipient_id=? ORDER BY id DESC LIMIT '.max(1,min($limit,50)));
    $q->execute([(string)$actor['source'],(string)$actor['id']]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function m20_read(array $actor,int $reminderId):void {
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor)||$reminderId<1)throw new RuntimeException('Akses pengingat tidak diizinkan.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM unified_monitor_reminders20 WHERE id=? AND recipient_source=? AND recipient_id=? FOR UPDATE');
        $q->execute([$reminderId,$actor['source'],(string)$actor['id']]);
        if(!$q->fetchColumn())throw new RuntimeException('Pengingat tidak ditemukan.');
        $q=$db->prepare('UPDATE unified_monitor_reminders20 SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND recipient_source=? AND recipient_id=?');
        $q->execute([$reminderId,$actor['source'],(string)$actor['id']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$actor['source'],(string)$actor['id'],'monitor20_reminder_read','unified_monitor_reminders20',hash('sha256',(string)$reminderId)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

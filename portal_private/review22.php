<?php
declare(strict_types=1);
/**
 * Stage 22 — optional strict appointment check for Stage 19, delegation of an
 * authorized Stage 21 assignment, and administrative action counts.
 * NO native employment, leave or citizen service approval is modified.
 */
require_once __DIR__.'/tasks21.php';

function r22_ready():bool {
    try{
        foreach(['unified_review_policy22','unified_replacements22','unified_review_events22'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1');
        return true;
    }catch(Throwable $e){return false;}
}
function r22_super(array $admin):void{
    m20_super_guard($admin);
    if(!r22_ready())throw new RuntimeException('Kebijakan verifikasi Tahap 22 belum diinstal.');
}
function r22_same(array $actor,string $source,string $id):bool {
    return (string)($actor['source']??'')===$source
        &&$id!==''&&hash_equals((string)($actor['id']??''),$id);
}
function r22_policy(string $source,string $category):array {
    t21_pair($source,$category,'pemeriksa');
    $q=db('smartapp')->prepare('SELECT source_kind,category,enforced,updated_at FROM unified_review_policy22 WHERE source_kind=? AND category=?');
    $q->execute([$source,$category]);
    return $q->fetch(PDO::FETCH_ASSOC)?:['source_kind'=>$source,'category'=>$category,'enforced'=>0,'updated_at'=>null];
}
function r22_assignment(string $source,string $category,string $step):?array{
    $q=db('smartapp')->prepare('SELECT * FROM unified_assignments21 WHERE source_kind=? AND category=? AND step=?');
    $q->execute([$source,$category,$step]);
    return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function r22_replacement(string $source,string $category,string $step):?array {
    $q=db('smartapp')->prepare('SELECT * FROM unified_replacements22 WHERE source_kind=? AND category=? AND step=? AND revoked_at IS NULL AND starts_on<=CURRENT_DATE() AND ends_on>=CURRENT_DATE()');
    $q->execute([$source,$category,$step]);
    return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
/** Pure policy for unit testing: assignment can never bypass actual native role. */
function r22_allowed_from_rows(array $actor,string $source,string $category,string $step,array $policy,?array $assignment,?array $replacement,string $today):bool{
    if(empty($policy['enforced']))return true;
    if(!$assignment||!t21_qualified($actor,$source,$category,$step))return false;
    if(empty($assignment['enabled'])||(string)$assignment['effective_from']>$today)return false;
    $assignedSource=(string)$assignment['reviewer_source'];
    $assignedId=(string)$assignment['reviewer_id'];
    if(r22_same($actor,$assignedSource,$assignedId))return true;
    return $replacement!==null
        &&$replacement['assigned_source']===$assignedSource
        &&hash_equals((string)$replacement['assigned_id'],$assignedId)
        &&r22_same($actor,(string)$replacement['deputy_source'],(string)$replacement['deputy_id'])
        &&empty($replacement['revoked_at'])
        &&(string)$replacement['starts_on']<=$today
        &&(string)$replacement['ends_on']>=$today;
}
/** Called INSIDE Stage 19 transaction before any official form status writes. */
function r22_gate(array $actor,string $source,string $category,string $step):bool {
    try{
        if(!r22_ready())return false; // patched app must never silently bypass guard
        $policy=r22_policy($source,$category);
        if(empty($policy['enforced']))return true;
        $assignment=r22_assignment($source,$category,$step);
        if(!$assignment)return false;
        $person=t21_identity((string)$actor['source'],(string)$actor['id']);
        if(!$person||!t21_assignment_valid($assignment,date('Y-m-d')))return false;
        $delegate=r22_replacement($source,$category,$step);
        return r22_allowed_from_rows($person,$source,$category,$step,$policy,$assignment,$delegate,date('Y-m-d'));
    }catch(Throwable $e){error_log('Stage22 strict review guard denied.');return false;}
}
/** Super Admin may still inspect a report for oversight but never act unassigned. */
function r22_can_view(array $actor,string $source,string $category):bool {
    try{
        if(!r22_ready())return false;
        $policy=r22_policy($source,$category);
        if(empty($policy['enforced']))return true;
        if(is_super_admin($actor))return true;
        return r22_gate($actor,$source,$category,'pemeriksa')
            ||r22_gate($actor,$source,$category,'lurah');
    }catch(Throwable $e){error_log('Stage22 report visibility denied.');return false;}
}
function r22_audit(PDO $db,array $admin,string $event,string $id):void{
    $stmt=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
    $stmt->execute([$admin['source'],(string)$admin['id'],'review22_'.$event,'unified_review_policy22',hash('sha256',$id)]);
}
function r22_event(PDO $db,array $admin,string $source,string $category,string $step,string $event,string $description):void {
    $stmt=$db->prepare('INSERT INTO unified_review_events22(source_kind,category,step,event_type,details,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
    $stmt->execute([$source,$category,$step,$event,mb_substr($description,0,220),$admin['source'],(string)$admin['id']]);
    r22_audit($db,$admin,$event,$source.':'.$category.':'.$step);
}
function r22_set_policy(array $admin,string $source,string $category,bool $on):void {
    r22_super($admin);t21_pair($source,$category,'pemeriksa');
    if($on){
        $live=@file_get_contents(__DIR__.'/verification19.php');
        if(!is_string($live)||!str_contains($live,'SMARTCIKOKO_TAHAP22_STRICT_GUARD'))
            throw new RuntimeException('Pengaman verifikasi Tahap 19 belum tersambung. Kebijakan ketat tidak dapat diaktifkan.');
        foreach(['pemeriksa','lurah'] as $step){
            $assignment=r22_assignment($source,$category,$step);
            if(!$assignment||!t21_assignment_valid($assignment,date('Y-m-d')))
                throw new RuntimeException('Aktivasi memerlukan penugasan pemeriksa dan Lurah yang sama-sama aktif.');
        }
    }
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('INSERT INTO unified_review_policy22(source_kind,category,enforced,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE enforced=VALUES(enforced),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $q->execute([$source,$category,$on?1:0,$admin['source'],(string)$admin['id']]);
        r22_event($db,$admin,$source,$category,'kedua_tahap',$on?'strict_on':'strict_off',$on?'Hanya pemeriksa dan Lurah yang ditugaskan':'Kembali ke pemeriksaan berbasis peran Tahap 19');
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function r22_delegate(array $admin,string $source,string $category,string $step,string $deputySource,string $deputyId,string $from,string $to,string $reason):void{
    r22_super($admin);t21_pair($source,$category,$step);
    t21_date($from);t21_date($to);
    $a=new DateTimeImmutable($from);$b=new DateTimeImmutable($to);
    $today=date('Y-m-d');
    if($from<$today||$b<$a||$a->diff($b)->days>89)
        throw new InvalidArgumentException('Pengganti berlaku mulai hari ini atau setelahnya, maksimal 90 hari kalender.');
    $reason=trim($reason);
    if(mb_strlen($reason)<12||mb_strlen($reason)>220)
        throw new InvalidArgumentException('Alasan penggantian wajib 12–220 karakter.');
    $current=r22_assignment($source,$category,$step);
    if(!$current||!t21_assignment_valid($current,$today))
        throw new RuntimeException('Penugasan utama harus aktif dan valid.');
    $deputy=t21_identity($deputySource,$deputyId);
    if(!$deputy||!t21_qualified($deputy,$source,$category,$step))
        throw new RuntimeException('Akun pengganti tidak aktif atau tidak memiliki peran sesuai tahap.');
    if(r22_same($deputy,(string)$current['reviewer_source'],(string)$current['reviewer_id']))
        throw new RuntimeException('Pengganti harus berbeda dari petugas utama.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM unified_assignments21 WHERE source_kind=? AND category=? AND step=? FOR UPDATE');
        $q->execute([$source,$category,$step]);$latest=$q->fetch(PDO::FETCH_ASSOC);
        if(!$latest||$latest['reviewer_source']!==$current['reviewer_source']
            ||!hash_equals((string)$latest['reviewer_id'],(string)$current['reviewer_id'])
            ||empty($latest['enabled']))
            throw new RuntimeException('Penugasan utama berubah; muat ulang formulir.');
        $q=$db->prepare('INSERT INTO unified_replacements22(source_kind,category,step,assigned_source,assigned_id,deputy_source,deputy_id,starts_on,ends_on,reason,approved_by_source,approved_by_id,created_at,revoked_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NULL) ON DUPLICATE KEY UPDATE assigned_source=VALUES(assigned_source),assigned_id=VALUES(assigned_id),deputy_source=VALUES(deputy_source),deputy_id=VALUES(deputy_id),starts_on=VALUES(starts_on),ends_on=VALUES(ends_on),reason=VALUES(reason),approved_by_source=VALUES(approved_by_source),approved_by_id=VALUES(approved_by_id),created_at=NOW(),revoked_at=NULL');
        $q->execute([$source,$category,$step,$latest['reviewer_source'],(string)$latest['reviewer_id'],$deputySource,$deputyId,$from,$to,$reason,$admin['source'],(string)$admin['id']]);
        r22_event($db,$admin,$source,$category,$step,'delegation_set','Rentang '.$from.' s.d. '.$to.'; alasan: '.$reason);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function r22_revoke(array $admin,string $source,string $category,string $step):void{
    r22_super($admin);t21_pair($source,$category,$step);
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('UPDATE unified_replacements22 SET revoked_at=NOW() WHERE source_kind=? AND category=? AND step=? AND revoked_at IS NULL');
        $q->execute([$source,$category,$step]);
        if($q->rowCount()!==1)throw new RuntimeException('Tidak ada penugasan pengganti aktif untuk dicabut.');
        r22_event($db,$admin,$source,$category,$step,'delegation_revoked','Pengganti dibatalkan');
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function r22_policies():array{
    if(!r22_ready())return [];
    return db('smartapp')->query('SELECT source_kind,category,enforced,updated_at FROM unified_review_policy22 ORDER BY source_kind,category')->fetchAll(PDO::FETCH_ASSOC);
}
function r22_delegations():array{
    if(!r22_ready())return [];
    return db('smartapp')->query('SELECT source_kind,category,step,assigned_source,assigned_id,deputy_source,deputy_id,starts_on,ends_on,reason,revoked_at FROM unified_replacements22 ORDER BY source_kind,category,step')->fetchAll(PDO::FETCH_ASSOC);
}
/** Active deputy assignments, not just past-due alerts; all rows are permission checked. */
function r22_deputy_tasks(array $actor,int $limit=30):array {
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor)||!r22_ready())return ['rows'=>[],'incomplete'=>false];
    $candidate=t21_identity((string)$actor['source'],(string)$actor['id']);
    if(!$candidate)return ['rows'=>[],'incomplete'=>false];
    $db=db('smartapp');$today=date('Y-m-d');$items=[];$incomplete=false;
    $q=$db->prepare('SELECT * FROM unified_replacements22 WHERE deputy_source=? AND deputy_id=? AND revoked_at IS NULL AND starts_on<=CURRENT_DATE() AND ends_on>=CURRENT_DATE() ORDER BY source_kind,category,step LIMIT 50');
    $q->execute([$actor['source'],(string)$actor['id']]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $replacement){
        $source=(string)$replacement['source_kind'];
        $category=(string)$replacement['category'];$step=(string)$replacement['step'];
        try{
            if(!r22_gate($actor,$source,$category,$step))continue;
            $policy=r22_policy($source,$category);
            if(empty($policy['enforced']))continue; // no "assignment-only" claim before strict activation
            $base=m20_sources()[$source]??null;
            if(!$base)continue;
            $sql='SELECT r.id,r.'.$base['cat'].' AS category,r.status,r.version,r.actor_source,r.actor_id,'
                .'r.created_at,r.updated_at,'
                .'v.state,v.report_version,v.reviewer_source,v.reviewer_id,v.updated_at AS verify_updated_at'
                .' FROM '.$base['table'].' r LEFT JOIN unified_verification19 v ON v.source_kind=? AND v.source_id=r.id'
                ." WHERE r.".$base['cat']."=? AND r.status IN ('diajukan','ditinjau') ORDER BY r.updated_at ASC LIMIT 101";
            $query=$db->prepare($sql);$query->execute([$source,$category]);
            $reports=$query->fetchAll(PDO::FETCH_ASSOC);
            if(count($reports)>100){$incomplete=true;$reports=array_slice($reports,0,100);}
            foreach($reports as $report){
                if(($report['actor_source']??'')===$actor['source']
                    &&hash_equals((string)$report['actor_id'],(string)$actor['id']))continue;
                $expected=t21_expected_phase($step);
                if(m20_phase($report)!==$expected)continue;
                if($step==='lurah'&&$report['reviewer_source']===$actor['source']
                   &&hash_equals((string)($report['reviewer_id']??''),(string)$actor['id']))continue;
                $age=m20_days_since(m20_reference_date($report),$today);
                $assignment=r22_assignment($source,$category,$step);
                $items[]=['source'=>$source,'id'=>(int)$report['id'],'category'=>$category,
                    'step'=>$step,'age'=>$age,'due_days'=>(int)($assignment['due_days']??0),
                    'overdue'=>$age>=(int)($assignment['due_days']??60)];
            }
        }catch(Throwable $e){$incomplete=true;error_log('Stage22 deputy queue category unavailable.');}
    }
    usort($items,static fn($a,$b)=>($b['overdue']<=>$a['overdue'])?:($b['age']<=>$a['age']));
    $limit=max(1,min(50,$limit));
    if(count($items)>$limit)$incomplete=true;
    return ['rows'=>array_slice($items,0,$limit),'incomplete'=>$incomplete];
}

function r22_monthly(array $admin,string $month):array{
    r22_super($admin);
    [$from,$to]=m20_month($month);
    $db=db('smartapp');
    $q=$db->prepare('SELECT source_kind,action,COUNT(*) total FROM unified_verification_history19 WHERE created_at>=? AND created_at<? GROUP BY source_kind,action');
    $q->execute([$from,$to]);
    $out=[];$total=0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
        $count=(int)$r['total'];$total+=$count;
        $out[$r['source_kind']][$r['action']]=$count;
    }
    return ['month'=>$month,'total_actions'=>$total,'modules'=>$out,'metric'=>'Tindakan administratif yang tercatat sepanjang bulan, bukan nilai kerja individu'];
}

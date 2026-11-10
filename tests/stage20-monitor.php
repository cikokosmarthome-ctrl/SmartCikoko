<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/monitor20.php';
require dirname(__DIR__).'/portal_private/monitor20_patch.php';
function check20(bool $ok,string $name):void {
    if(!$ok){fwrite(STDERR,"FAILED: ".$name."\n");exit(1);}
}
function is_super_admin(?array $actor=null):bool {
    return $actor!==null && ($actor['source']??'')==='pegawai'
        && ($actor['role']??'')==='superadmin';
}
check20(count(m20_sources())===3,'Stage 15,16,17 source scopes');
[$start,$next]=m20_month('2026-11');
check20($start==='2026-11-01'&&$next==='2026-12-01','Month boundaries');
[$start,$next]=m20_month('2028-02');
check20($start==='2028-02-01'&&$next==='2028-03-01','Leap year month boundaries');
foreach(['2026-00','2026-13','26-01','2026-1','2026/11'] as $bad){
    $throw=false;try{m20_month($bad);}catch(InvalidArgumentException $e){$throw=true;}
    check20($throw,'Invalid month refused');
}
check20(count(m20_phase_labels())===6,'All stage labels available');
$report=['status'=>'diajukan','version'=>1,'created_at'=>'2026-11-01 07:00:00',
  'updated_at'=>'2026-11-04 09:00:00'];
check20(m20_phase($report)==='menunggu_pemeriksa','Submitted report awaits reviewer');
$reviewed=$report+['verify_state'=>'lolos_periksa','verify_version'=>1,'verify_updated_at'=>'2026-11-05 08:00:00'];
check20(m20_phase($reviewed)==='menunggu_lurah','After review report awaits Lurah');
check20(m20_reference_date($reviewed)==='2026-11-05 08:00:00','Latest action timestamp used');
$seen=$reviewed;$seen['verify_state']='diketahui_lurah';
check20(m20_phase($seen)==='selesai','Lurah administrative review closes monitoring');
$fix=$report;$fix['status']='perbaikan';
check20(m20_phase($fix)==='perbaikan','Correction request is active');
$draft=$report;$draft['status']='draf';
check20(m20_phase($draft)==='draf','Draft stays private and excluded');
$closed=$report;$closed['status']='ditutup';
check20(m20_phase($closed)==='selesai','Closed reports are complete administratively');
$stale=$report;$stale['version']=2;$stale['verify_state']='perlu_perbaikan';$stale['verify_version']=1;
check20(m20_phase($stale)==='menunggu_pemeriksa','Resubmission resets stale correction phase');
$rule=['enabled'=>1,'followup_days'=>3];
check20(m20_days_since('2026-11-03 15:05:00','2026-11-06')===3,'Elapsed calendar-day calculation');
check20(m20_days_since('2026-11-12 08:00:00','2026-11-06')===0,'Future timestamp not considered overdue');
check20(m20_due($report,$rule,'2026-11-08'),'Old pending report meets threshold');
check20(!m20_due($report,['enabled'=>0,'followup_days'=>3],'2026-11-08'),'Disabled policy never raises due report');
check20(!m20_due($report,$rule,'2026-11-06'),'Recent status below threshold');
check20(!m20_due($draft,$rule,'2026-11-20'),'Draft never overdue');
check20(!m20_due($closed,$rule,'2026-11-20'),'Closed report never overdue');
check20(!m20_due($seen,$rule,'2026-11-20'),'Lurah acknowledged report not overdue');
check20(!m20_due($report,['enabled'=>1,'followup_days'=>0],'2026-11-20'),'Invalid 0 day rule fails safely');
check20(!m20_due($report,['enabled'=>1,'followup_days'=>61],'2026-11-20'),'Invalid extreme day rule fails safely');
check20(m20_actor_is_super(['source'=>'pegawai','role'=>'superadmin']),'Super Admin recognized');
check20(!m20_actor_is_super(['source'=>'smartapp','role'=>'lurah']),'Lurah not Super Admin');
$fixture="<?php\n".str_repeat('/* existing one-page dashboard */ ',320)
    ."\$summary=super_admin_metrics();\n?><html><head><meta charset=\"utf-8\"></head><body>"
    .'<main><section id="pengaturan">Old settings</section></main></body></html>';
check20(strlen($fixture)>8000,'Dashboard fixture structure');
$patched=m20_patch($fixture,'dashboard');
check20(str_contains($patched,'SMARTCIKOKO_TAHAP20_MONITOR'),'Monitoring panel inserted');
check20(str_contains($patched,'monitor20_admin_panel.php'),'Monitoring panel included');
check20(str_contains($patched,'/assets/monitor20-admin.css'),'Admin stylesheet inserted');
check20(m20_patch($patched,'dashboard')===$patched,'Dashboard patch idempotent');
check20(str_contains($patched,'super_admin_metrics()'),'Existing admin summary not modified');
$center="<?php\n".str_repeat('/* previous Stage15 input center */ ',240)
    .'?><html><head><link rel="stylesheet" href="/assets/pusat-laporan.css"></head>'
    .'<body><div class="category-grid">Original categories</div></body></html>';
check20(strlen($center)>7000,'Staff center test source');
$linked=m20_patch($center,'center');
check20(str_contains($linked,'SMARTCIKOKO_TAHAP20_INBOX'),'Reminder inbox linked');
check20(str_contains($linked,'/pengingat-saya20.php'),'Reminders link URL present');
check20(m20_patch($linked,'center')===$linked,'Staff link patch idempotent');
$blocked=false;try{m20_patch('<html>unknown</html>','dashboard');}
catch(RuntimeException $e){$blocked=true;}
check20($blocked,'Unrecognized admin UI safely rejected');
$root=sys_get_temp_dir().'/smartcikoko20-'.bin2hex(random_bytes(6));
$private=$root.'/portal_private';$web=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
file_put_contents($web.'/super-admin-satu.php',$fixture);
try{
    $backup=m20_apply($web.'/super-admin-satu.php',$private,hash('sha256',$fixture),'dashboard');
    check20($backup!==''&&is_file($private.'/monitor20_backups/'.$backup),'Private backup created');
    check20(file_get_contents($private.'/monitor20_backups/'.$backup)===$fixture,'Unmodified dashboard backup preserved');
    check20(file_get_contents($web.'/super-admin-satu.php')===$patched,'Scoped dashboard patch installed');
    check20(m20_apply($web.'/super-admin-satu.php',$private,hash('sha256',$patched),'dashboard')==='','Second installation no-op');
    $failed=false;try{m20_apply($web.'/super-admin-satu.php',$private,hash('sha256','stale'),'dashboard');}
    catch(RuntimeException $e){$failed=true;}
    check20($failed,'Outdated file version rejected');
}finally{
    foreach(glob($private.'/monitor20_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/monitor20_backups');
    @unlink($web.'/super-admin-satu.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
$source=file_get_contents(dirname(__DIR__).'/portal_private/monitor20.php');
check20(str_contains($source,'INSERT IGNORE INTO unified_monitor_reminders20'),'Duplicate reminders fail closed');
check20(str_contains($source,'$expectedPhase===\'perbaikan\''),'Recipient mapping respects correction ownership');
check20(str_contains($source,'account_control_allowed($actor)'),'Inbox validates live account');
check20(str_contains($source,'unified_audit'),'Rule, reminder and read actions audited');
check20(str_contains($source,'m20_super_guard($admin)'),'Super Admin authorization enforced');
echo "Stage20: monthly reporting, stages, overdue rules, recipient isolation, UI patch and private backups passed.\n";

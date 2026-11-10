<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/tasks21.php';
require dirname(__DIR__).'/portal_private/tasks21_patch.php';
function t(bool $good,string $name):void{
    if(!$good){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}
}
$catalog=t21_catalog();
t(count($catalog)===3,'Three canonical reporting modules');
t(isset($catalog['ringkas15']['rt_rw'],$catalog['pegawai16']['pppk'],$catalog['wilayah17']['jumantik']),'Real form categories sourced from older modules');
t(!isset($catalog['ringkas15']['waris']),'Native inheritance approval not assigned through extra reports');
t(count(t21_steps())===2,'Two distinct review responsibilities');
foreach(['2026-02-30','2026-13-01','26-11-10'] as $bad){
    $fail=false;try{t21_date($bad);}catch(InvalidArgumentException $e){$fail=true;}
    t($fail,'Invalid effective date rejected');
}
t(t21_date('2026-11-10')==='2026-11-10','Valid effective date');
$bad=false;try{t21_pair('wilayah17','invalid','pemeriksa');}catch(InvalidArgumentException $e){$bad=true;}
t($bad,'No invented category');
$bad=false;try{t21_pair('wilayah17','jumantik','official_signature');}catch(InvalidArgumentException $e){$bad=true;}
t($bad,'No invented official approval step');
$hr=['source'=>'pegawai','id'=>'41','role'=>'admin'];
$ptsp=['source'=>'smartapp','id'=>'7','role'=>'ptsp'];
$lurah=['source'=>'smartapp','id'=>'9','role'=>'lurah'];
$warga=['source'=>'smartapp','id'=>'10','role'=>'warga'];
t(t21_qualified($hr,'pegawai16','pppk','pemeriksa'),'Pegawai admin allowed to examine staff records');
t(!t21_qualified($hr,'wilayah17','lmk','pemeriksa'),'HR cannot be assigned field work');
t(t21_qualified($ptsp,'wilayah17','lmk','pemeriksa'),'PTSP allowed for assigned field reviews');
t(!t21_qualified($ptsp,'pegawai16','pppk','pemeriksa'),'PTSP cannot review PPPK');
t(t21_qualified($lurah,'wilayah17','jumantik','lurah'),'Real SmartApp Lurah can be given review tasks');
t(!t21_qualified($ptsp,'wilayah17','jumantik','lurah'),'PTSP cannot become Lurah');
t(!t21_qualified($warga,'wilayah17','jumantik','pemeriksa'),'Residents cannot become verifiers');
$rule=['category'=>'lmk','step'=>'pemeriksa','reviewer_source'=>'smartapp','reviewer_id'=>'7','due_days'=>3];
$report=['category'=>'lmk','actor_source'=>'smartapp','actor_id'=>'20','report_status'=>'diajukan','version'=>1,
 'report_updated_at'=>'2026-11-01 10:00:00'];
t(t21_expected_phase('pemeriksa')==='menunggu_pemeriksa','Correct first stage');
t(t21_expected_phase('lurah')==='menunggu_lurah','Correct second stage');
t(t21_due($report,$rule,'2026-11-10'),'Assigned overdue report due');
t(!t21_due($report,$rule,'2026-11-02'),'Within SLA not due');
$wrongCategory=$report;$wrongCategory['category']='ppsu';
t(!t21_due($wrongCategory,$rule,'2026-11-10'),'Other category isolated');
$owner=$report;$owner['actor_id']='7';
t(!t21_due($owner,$rule,'2026-11-10'),'Self-review excluded');
$done=$report;$done['report_status']='ditutup';
t(!t21_due($done,$rule,'2026-11-10'),'Completed report cannot receive new reminder');
$draft=$report;$draft['report_status']='draf';
t(!t21_due($draft,$rule,'2026-11-10'),'Private draft cannot trigger notifier');
$step2=$rule;$step2['step']='lurah';
t(!t21_due($report,$step2,'2026-11-10'),'Lurah not alerted before pre-review');
$reviewed=$report;$reviewed['verify_state']='lolos_periksa';$reviewed['verify_version']=1;
$step2['reviewer_id']='9';
t(t21_due($reviewed,$step2,'2026-11-10'),'Lurah alerted after review and elapsed threshold');
$changed=$reviewed;$changed['version']=2;
t(!t21_due($changed,$step2,'2026-11-10'),'Old review version cannot trigger Lurah reminder');
$invalid=$rule;$invalid['due_days']=0;
t(!t21_due($report,$invalid,'2026-11-10'),'Zero-day rule refused');
$invalid=$rule;$invalid['due_days']=61;
t(!t21_due($report,$invalid,'2026-11-10'),'Above-maximum rule refused');
$fail=false;try{t21_run(true,null);}catch(RuntimeException $e){$fail=true;}
t($fail,'Manual notifications require Super Admin');
$fixture="<?php\n".str_repeat('/* original dashboard PHP code */ ',340)
    ."\$counts=super_admin_metrics();\n?><html><head><meta charset=\"utf-8\"></head><body>"
    .'<main><section id="pengaturan">Settings</section></main></body></html>';
t(strlen($fixture)>8000,'Admin source fixture');
$updated=t21_patch($fixture,'dashboard');
t(str_contains($updated,'SMARTCIKOKO_TAHAP21_ASSIGNMENTS'),'Stage21 admin panel inserted');
t(str_contains($updated,'tasks21_admin_panel.php'),'Private panel included');
t(str_contains($updated,'/assets/tasks21-admin.css'),'CSS link inserted');
t(t21_patch($updated,'dashboard')===$updated,'Admin panel patch idempotent');
t(str_contains($updated,'super_admin_metrics()'),'Core admin preserved');
$center="<?php\n".str_repeat('/* staff center php code */ ',320)
    .'?><html><head><link rel="stylesheet" href="/assets/pusat-laporan.css"></head>'
    .'<body><div class="category-grid">old categories</div></body></html>';
t(strlen($center)>7000,'Staff source fixture');
$linked=t21_patch($center,'center');
t(str_contains($linked,'/penugasan-saya21.php'),'Inbox link inserted');
t(t21_patch($linked,'center')===$linked,'Staff link idempotent');
$fail=false;try{t21_patch('<html>broken</html>','dashboard');}catch(RuntimeException $e){$fail=true;}
t($fail,'Unknown dashboard safely rejected');
$root=sys_get_temp_dir().'/t21-'.bin2hex(random_bytes(6));
$web=$root.'/public_html';$private=$root.'/portal_private';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
file_put_contents($web.'/super-admin-satu.php',$fixture);
try{
    $backup=t21_apply($web.'/super-admin-satu.php',$private,hash('sha256',$fixture),'dashboard');
    t($backup!==''&&is_file($private.'/tasks21_backups/'.$backup),'Private original backup created');
    t(file_get_contents($private.'/tasks21_backups/'.$backup)===$fixture,'Backup contains exact original');
    t(file_get_contents($web.'/super-admin-satu.php')===$updated,'Only scoped changes applied');
    t(t21_apply($web.'/super-admin-satu.php',$private,hash('sha256',$updated),'dashboard')==='','No second patch');
    $fail=false;try{t21_apply($web.'/super-admin-satu.php',$private,hash('sha256','stale'),'dashboard');}
    catch(RuntimeException $e){$fail=true;}
    t($fail,'Stale file refused');
}finally{
    foreach(glob($private.'/tasks21_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/tasks21_backups');@unlink($web.'/super-admin-satu.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
$installer=file_get_contents(dirname(__DIR__).'/public_html/instal-tugas21.php');
$worker=file_get_contents(dirname(__DIR__).'/portal_private/cron_tugas21.php');
$code=file_get_contents(dirname(__DIR__).'/portal_private/tasks21.php');
t(str_contains($installer,"enabled TINYINT(1) NOT NULL DEFAULT 0"),'Automatic notifications disabled by default');
t(str_contains($installer,'INSERT IGNORE INTO unified_schedule21(id,enabled,updated_at) VALUES(1,0,NOW())'),'Schedule singleton starts disabled');
t(str_contains($installer,'UNIQUE KEY uq_a21_once'),'Duplicate notifications structurally prevented');
t(str_contains($worker,"PHP_SAPI!=='cli'"),'Worker rejects HTTP');
t(str_contains($worker,'--dispatch'),'CLI explicit flag required');
t(str_contains($code,"GET_LOCK('smartcikoko_monitor21',2)"),'Concurrent dispatchers serialized');
t(str_contains($code,"v19_reviewer"),'Assignments enforce existing verification roles');
t(str_contains($code,"v19_lurah"),'Lurah cannot be impersonated by an ordinary reviewer');
t(str_contains($code,"account_control_allowed"),'Native account status and revocation enforced');
t(str_contains($code,'unified_audit'),'All dispatch and read changes create audit events');
echo "Stage21: role, due dates, duplicate notifications, CLI-only scheduler, and private dashboard backups passed.\n";

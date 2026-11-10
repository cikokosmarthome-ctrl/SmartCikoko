<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/verification19.php';
require dirname(__DIR__).'/portal_private/verification19_patch.php';
function test19(bool $ok,string $name):void{
    if(!$ok){fwrite(STDERR,"FAILED: ".$name."\n");exit(1);}
}
function is_super_admin(?array $a=null):bool {
    return ($a['source']??'')==='pegawai'&&($a['role']??'')==='superadmin';
}
test19(count(v19_sources())===3,'Three report sources available');
test19(count(v19_actions())===4,'Four administrative actions available');
$pegawai=['source'=>'pegawai','id'=>'staff-admin','role'=>'admin'];
$ptsp=['source'=>'smartapp','id'=>'staff-ptsp','role'=>'ptsp'];
$lurah=['source'=>'smartapp','id'=>'staff-lurah','role'=>'lurah'];
$super=['source'=>'pegawai','id'=>'super-1','role'=>'superadmin'];
$pppk=['source'=>'pegawai','id'=>'pppk-1','role'=>'pppk'];
$warga=['source'=>'smartapp','id'=>'warga-1','role'=>'warga'];
test19(v19_reviewer($pegawai,'pegawai16','pppk'),'HR reviewer can check PPPK');
test19(!v19_reviewer($ptsp,'pegawai16','pppk'),'PTSP cannot review PPPK');
test19(v19_reviewer($ptsp,'wilayah17','rt_rw'),'PTSP may check field activity');
test19(!v19_reviewer($pegawai,'wilayah17','jumantik'),'Pegawai Admin not assigned field review');
test19(v19_reviewer($super,'wilayah17','lmk'),'Super Admin can conduct first stage under own identity');
test19(v19_lurah($lurah),'Lurah recognized from SmartApp');
test19(!v19_lurah($super),'Super Admin is not automatically Lurah');
test19(!v19_reviewer($warga,'wilayah17','rt_rw'),'Citizen cannot verify');
$r=['category'=>'pppk','status'=>'diajukan','version'=>1,'actor_source'=>'pegawai','actor_id'=>'pppk-1'];
test19(v19_state($r,null)==='menunggu','New reports awaiting review');
test19(v19_may_act($pegawai,'pegawai16',$r,null,'periksa'),'Assigned HR reviewer can check');
test19(!v19_may_act($pppk,'pegawai16',$r,null,'periksa'),'Self-review rejected');
test19(!v19_may_act($lurah,'pegawai16',$r,null,'lurah_telaah'),'No skipping first review');
$check=['state'=>'lolos_periksa','report_version'=>1,'reviewer_source'=>'pegawai','reviewer_id'=>'staff-admin'];
test19(!v19_may_act($pegawai,'pegawai16',$r,$check,'periksa'),'First review cannot be repeated');
test19(v19_may_act($lurah,'pegawai16',$r,$check,'lurah_telaah'),'Lurah can perform second distinct review');
test19(!v19_may_act($super,'pegawai16',$r,$check,'lurah_telaah'),'Super Admin cannot impersonate Lurah');
$fakeLurahAsReporter=$r;$fakeLurahAsReporter['actor_source']='smartapp';$fakeLurahAsReporter['actor_id']='staff-lurah';
test19(!v19_may_act($lurah,'pegawai16',$fakeLurahAsReporter,$check,'lurah_telaah'),'Lurah cannot review own report');
$sameReviewer=['state'=>'lolos_periksa','report_version'=>1,'reviewer_source'=>'smartapp','reviewer_id'=>'staff-lurah'];
test19(!v19_may_act($lurah,'pegawai16',$r,$sameReviewer,'lurah_telaah'),'Second stage actor must differ from first');
$closed=$r;$closed['status']='ditutup';
test19(!v19_may_act($pegawai,'pegawai16',$closed,null,'periksa'),'Closed reports cannot be verified');
$draft=$r;$draft['status']='draf';
test19(!v19_may_act($pegawai,'pegawai16',$draft,null,'periksa'),'Private drafts cannot be reviewed');
$returned=['state'=>'perlu_perbaikan','report_version'=>1,'reviewer_source'=>'pegawai','reviewer_id'=>'staff-admin'];
test19(!v19_may_act($pegawai,'pegawai16',$r,$returned,'periksa'),'Cannot immediately recheck old returned version');
$revised=$r;$revised['version']=2;
test19(v19_state($revised,$returned)==='menunggu','A new submitted version resets correction workflow');
test19(v19_may_act($pegawai,'pegawai16',$revised,$returned,'periksa'),'Revised report can be checked again');
test19(v19_state($r,$returned)==='perlu_perbaikan','Old version still returned');
$src="<?php\n".str_repeat('/* original admin PHP and styling */ ',300)
    ."\$summary=super_admin_metrics();\n?>\n<!doctype html><html><head><meta charset=\"utf-8\"></head><body>"
    .'<main><section id="pengaturan">Settings</section></main></body></html>';
test19(strlen($src)>8000,'Admin fixture length');
$modified=v19_patch($src,'dashboard');
test19(str_contains($modified,'SMARTCIKOKO_TAHAP19_VERIFICATION'),'Panel marker inserted');
test19(str_contains($modified,'/assets/verifikasi19-admin.css'),'CSS link appended');
test19(v19_patch($modified,'dashboard')===$modified,'Repeated integration idempotent');
test19(str_contains($modified,'super_admin_metrics();'),'Existing admin code preserved');
$center="<?php\n".str_repeat('/* existing staff center source */ ',240)
    .'?><html><head><link rel="stylesheet" href="/assets/pusat-laporan.css"></head>'
    .'<body><div class="category-grid">Original categories</div></body></html>';
test19(strlen($center)>7000,'Center fixture length');
$centerNext=v19_patch($center,'center');
test19(str_contains($centerNext,'SMARTCIKOKO_TAHAP19_REVIEWER_LINK'),'Scoped reviewer link');
test19(v19_patch($centerNext,'center')===$centerNext,'Center patch idempotent');
$failed=false;try{v19_patch('<html>unknown</html>','dashboard');}
catch(RuntimeException $e){$failed=true;}test19($failed,'Unknown dashboard safely rejected');
$root=sys_get_temp_dir().'/v19-'.bin2hex(random_bytes(6));
$private=$root.'/portal_private';$web=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
file_put_contents($web.'/super-admin-satu.php',$src);
try{
    $backup=v19_apply($web.'/super-admin-satu.php',$private,hash('sha256',$src),'dashboard');
    test19($backup!==''&&is_file($private.'/verification19_backups/'.$backup),'Private original backup created');
    test19(file_get_contents($private.'/verification19_backups/'.$backup)===$src,'Backup content matches original');
    test19(file_get_contents($web.'/super-admin-satu.php')===$modified,'Only scoped integration applied');
    test19(v19_apply($web.'/super-admin-satu.php',$private,hash('sha256',$modified),'dashboard')==='','Duplicate action does not rewrite file');
    $failed=false;try{v19_apply($web.'/super-admin-satu.php',$private,hash('sha256','stale'),'dashboard');}
    catch(RuntimeException $e){$failed=true;}test19($failed,'Stale installer version rejected');
}finally{
    foreach(glob($private.'/verification19_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/verification19_backups');@unlink($web.'/super-admin-satu.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
echo "Stage 19: role isolation, two-step states, revisions, original backup and scoped patch tests passed.\n";

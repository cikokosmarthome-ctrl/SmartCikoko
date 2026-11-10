<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/review22.php';
require dirname(__DIR__).'/portal_private/review22_patch.php';
function r22test(bool $ok,string $name):void{
    if(!$ok){fwrite(STDERR,"FAILED: $name\n");exit(1);}
}
function is_super_admin(?array $actor=null):bool{
    return ($actor['source']??'')==='pegawai'&&($actor['role']??'')==='superadmin';
}
$catalog=t21_catalog();
r22test(isset($catalog['pegawai16']['pppk'],$catalog['wilayah17']['lmk']),'Existing source categories, no invented modules');
r22test(!isset($catalog['ringkas15']['waris']),'Native inheritance service excluded');
$r=['source'=>'smartapp','id'=>'ptsp1','role'=>'ptsp'];
$extra=['source'=>'smartapp','id'=>'ptsp2','role'=>'ptsp'];
$warga=['source'=>'smartapp','id'=>'warga1','role'=>'warga'];
$lurah=['source'=>'smartapp','id'=>'lurah1','role'=>'lurah'];
$admin=['source'=>'pegawai','id'=>'admin1','role'=>'admin'];
$assignment=['reviewer_source'=>'smartapp','reviewer_id'=>'ptsp1','enabled'=>1,'effective_from'=>'2026-11-01'];
$policyOff=['enforced'=>0];$policyOn=['enforced'=>1];$today='2026-11-10';
$delegate=['assigned_source'=>'smartapp','assigned_id'=>'ptsp1','deputy_source'=>'smartapp','deputy_id'=>'ptsp2',
    'starts_on'=>'2026-11-09','ends_on'=>'2026-11-20','revoked_at'=>null];
r22test(r22_allowed_from_rows($warga,'wilayah17','lmk','pemeriksa',$policyOff,null,null,$today),'Disabled policy keeps Stage19 role behavior');
r22test(r22_allowed_from_rows($r,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,null,$today),'Assigned reviewer can act under strict mode');
r22test(!r22_allowed_from_rows($extra,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,null,$today),'Other same-role reviewer blocked');
r22test(r22_allowed_from_rows($extra,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$delegate,$today),'Valid time-bounded substitute allowed');
r22test(!r22_allowed_from_rows($warga,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$delegate,$today),'Citizen never receives reviewer rights');
r22test(!r22_allowed_from_rows($admin,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$delegate,$today),'Different agency reviewer blocked');
r22test(!r22_allowed_from_rows($lurah,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$delegate,$today),'Lurah not automatically first reviewer');
r22test(!r22_allowed_from_rows($r,'wilayah17','lmk','pemeriksa',$policyOn,null,null,$today),'Missing assignment fails closed');
$inactive=$assignment;$inactive['enabled']=0;
r22test(!r22_allowed_from_rows($r,'wilayah17','lmk','pemeriksa',$policyOn,$inactive,null,$today),'Disabled assignment fails closed');
$future=$assignment;$future['effective_from']='2026-12-01';
r22test(!r22_allowed_from_rows($r,'wilayah17','lmk','pemeriksa',$policyOn,$future,null,$today),'Future assignment not effective');
$expired=$delegate;$expired['ends_on']='2026-11-08';
r22test(!r22_allowed_from_rows($extra,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$expired,$today),'Expired substitute refused');
$revoked=$delegate;$revoked['revoked_at']='2026-11-10 08:00:00';
r22test(!r22_allowed_from_rows($extra,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$revoked,$today),'Revoked substitute refused');
$changed=$delegate;$changed['assigned_id']='ptsp99';
r22test(!r22_allowed_from_rows($extra,'wilayah17','lmk','pemeriksa',$policyOn,$assignment,$changed,$today),'Reassigned primary invalidates old delegate');
$lurahAssign=['reviewer_source'=>'smartapp','reviewer_id'=>'lurah1','enabled'=>1,'effective_from'=>'2026-11-01'];
r22test(r22_allowed_from_rows($lurah,'wilayah17','lmk','lurah',$policyOn,$lurahAssign,null,$today),'Actual Lurah assigned for second step');
r22test(!r22_allowed_from_rows($extra,'wilayah17','lmk','lurah',$policyOn,$lurahAssign,null,$today),'PTSP cannot impersonate Lurah');
$base=file_get_contents(dirname(__DIR__).'/portal_private/verification19.php');
$patched=r22_patch($base,'verification');
r22test(str_contains($patched,'SMARTCIKOKO_TAHAP22_STRICT_GUARD'),'Stage19 includes mandatory new authorization module');
r22test(str_contains($patched,"r22_gate(\$actor,\$source,\$category,'pemeriksa')"),'Server-side first-stage action gated');
r22test(str_contains($patched,"r22_gate(\$actor,\$source,\$category,'lurah')"),'Server-side Lurah action gated');
r22test(substr_count($patched,'r22_can_view(')===2,'Both queue and ID-based report detail gated');
r22test(str_contains($patched,"if(\$status!=='menunggu'"),'Old reviewed report version resets after revision');
r22test(r22_patch($patched,'verification')===$patched,'Stage19 guard patch idempotent');
$fail=false;try{r22_patch('<html>unknown version</html>','verification');}
catch(RuntimeException $e){$fail=true;}
r22test($fail,'Unknown Stage19 code rejected without applying');
$fixture="<?php\n".str_repeat('/* existing dashboard content */ ',340)
    ."\$s=super_admin_metrics();\n?><html><head><meta charset=\"utf-8\"></head><body>"
    .'<main><section id="pengaturan">settings</section></main></body></html>';
$adminOut=r22_patch($fixture,'dashboard');
r22test(str_contains($adminOut,'SMARTCIKOKO_TAHAP22_POLICY_PANEL'),'One-page admin receives panel');
r22test(r22_patch($adminOut,'dashboard')===$adminOut,'Admin patch idempotent');
$center="<?php\n".str_repeat('/* previous staff forms */ ',320)
    .'?><html><head><link rel="stylesheet" href="/assets/pusat-laporan.css"></head>'
    .'<body><div class="category-grid">categories</div></body></html>';
$staffOut=r22_patch($center,'center');
r22test(str_contains($staffOut,'/pengganti-saya22.php'),'Staff hub link added');
r22test(r22_patch($staffOut,'center')===$staffOut,'Staff hub patch idempotent');
$root=sys_get_temp_dir().'/smartcikoko22-'.bin2hex(random_bytes(5));
$private=$root.'/portal_private';$web=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
file_put_contents($private.'/verification19.php',$base);
file_put_contents($web.'/super-admin-satu.php',$fixture);
try{
    $backup=r22_apply($private.'/verification19.php',$private,hash('sha256',$base),'verification');
    r22test($backup!==''&&is_file($private.'/review22_backups/'.$backup),'Stage19 original stored in private backup');
    r22test(file_get_contents($private.'/review22_backups/'.$backup)===$base,'Stage19 backup retains original bytes');
    r22test(file_get_contents($private.'/verification19.php')===$patched,'Stage19 authorization hook applied scoped');
    r22test(r22_apply($private.'/verification19.php',$private,hash('sha256',$patched),'verification')==='','Re-installation does not duplicate gate');
    $back2=r22_apply($web.'/super-admin-satu.php',$private,hash('sha256',$fixture),'dashboard');
    r22test(is_file($private.'/review22_backups/'.$back2),'Admin original backed up');
    r22test(file_get_contents($web.'/super-admin-satu.php')===$adminOut,'Admin panel scoped');
    $fail=false;try{r22_apply($private.'/verification19.php',$private,hash('sha256','old version'),'verification');}
    catch(RuntimeException $e){$fail=true;}
    r22test($fail,'SHA collision/stale installer refused');
}finally{
    foreach(glob($private.'/review22_backups/*')?:[] as $p)@unlink($p);
    @rmdir($private.'/review22_backups');
    @unlink($web.'/super-admin-satu.php');
    @unlink($private.'/verification19.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
$code=file_get_contents(dirname(__DIR__).'/portal_private/review22.php');
r22test(str_contains($code,"SMARTCIKOKO_TAHAP22_STRICT_GUARD"),'Cannot activate strict policy before server gate installed');
r22test(str_contains($code,'unified_audit'),'Policy and delegation actions audited');
r22test(str_contains($code,'account_control_allowed($actor)'),'Deputy task account validation');
echo "Stage22: active assignee-only action, two-person roles, expiry, privacy, safe patched Stage19 and private backup passed.\n";

<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/evidence18.php';
require dirname(__DIR__).'/portal_private/recap18.php';
require dirname(__DIR__).'/portal_private/evidence18_patch.php';
function t18(bool $ok,string $test):void{
    if(!$ok){fwrite(STDERR,"FAIL: ".$test."\n");exit(1);}
}
function cfg():array{return ['upload_scanning'=>['enforce'=>false]];}
t18(e18_sources()===['pegawai16'=>'unified_detailed_forms16','wilayah17'=>'unified_field_reports17'],'Exact supported evidence sources');
t18(!e18_scan_ready(),'Scanner not ready without mandatory enforcement');
t18(e18_filename_valid(str_repeat('a',48).'.jpg'),'Randomized JPG storage basename');
t18(e18_filename_valid(str_repeat('f',48).'.png'),'Randomized PNG storage basename');
foreach(['../../config.php','bad.jpg',str_repeat('a',48).'.php','A'.str_repeat('a',47).'.png'] as $bad)
    t18(!e18_filename_valid($bad),'Unsafe stored filename rejected');
$alice=['source'=>'smartapp','id'=>'alice'];$bob=['source'=>'smartapp','id'=>'bob'];
$owned=['actor_source'=>'smartapp','actor_id'=>'alice'];
t18(e18_owner($alice,$owned),'Owner recognized');
t18(!e18_owner($bob,$owned),'Another account blocked');
t18(!e18_owner(['source'=>'pegawai','id'=>'alice'],$owned),'Cross-database user IDs isolated');
t18(e18_csv_safe('=SUM(1,1)')==="'=SUM(1,1)",'Spreadsheet formula escaped');
t18(e18_csv_safe(' +7')==="' +7",'Leading-space formula escaped');
t18(e18_csv_safe('@x')==="'@x",'At-sign CSV injection escaped');
t18(e18_csv_safe('Rapat RT')==='Rapat RT','Normal CSV cell preserved');
t18(e18_date('2026-11-10')==='2026-11-10','Valid recap date');
foreach(['2026-02-30','2026-13-10','10/11/2026'] as $bad){
    $thrown=false;try{e18_date($bad);}catch(InvalidArgumentException $e){$thrown=true;}
    t18($thrown,'Invalid dates blocked');
}
[$start,$exclusive]=e18_recap_range('2026-11-01','2026-11-10');
t18($start==='2026-11-01'&&$exclusive==='2026-11-11','Recap end-date is inclusive for users');
$thrown=false;try{e18_recap_range('2024-01-01','2026-01-01');}
catch(InvalidArgumentException $e){$thrown=true;}
t18($thrown,'Overly broad recap range rejected');
t18(count(e18_recap_sources())===3,'All three report generations included');
$fixture="<?php\n".str_repeat('/* dashboard fixture */ ',380)
    ."\$metric=super_admin_metrics();\n?><!doctype html><html><head>"
    .'<meta charset="utf-8"></head><body><main><section id="pengaturan">Settings</section></main></body></html>';
t18(strlen($fixture)>8000,'Admin fixture big enough');
$new=e18_patch($fixture,'dashboard');
t18(str_contains($new,'SMARTCIKOKO_TAHAP18_ADMIN'),'Stage18 admin panel marker inserted');
t18(str_contains($new,'/assets/evidence18-admin.css'),'Stage18 external CSS linked');
t18(e18_patch($new,'dashboard')===$new,'Admin patch is idempotent');
t18(str_contains($new,'super_admin_metrics();'),'Existing admin logic preserved');
$center="<?php\n".str_repeat('/* center fixture */ ',400)
    .'?><!doctype html><html><head><link rel="stylesheet" href="/assets/pusat-laporan.css"></head><body><div class="category-grid">Categories</div></body></html>';
t18(strlen($center)>7000,'Center fixture large enough');
$linked=e18_patch($center,'center');
t18(str_contains($linked,'SMARTCIKOKO_TAHAP18_CENTER'),'Input center receives evidence link');
t18(e18_patch($linked,'center')===$linked,'Center patch is idempotent');
$denied=false;try{e18_patch('<html>unknown</html>','dashboard');}
catch(RuntimeException $e){$denied=true;}
t18($denied,'Unrecognized dashboard refused');
$root=sys_get_temp_dir().'/cikoko18-'.bin2hex(random_bytes(6));
mkdir($root,0700);mkdir($root.'/public_html',0700);mkdir($root.'/portal_private',0700);
$web=$root.'/public_html';$private=$root.'/portal_private';
file_put_contents($web.'/super-admin-satu.php',$fixture);
try{
    $back=e18_apply($web.'/super-admin-satu.php',$private,hash('sha256',$fixture),'dashboard');
    t18($back!==''&&is_file($private.'/evidence18_backups/'.$back),'Backup saved outside webroot');
    t18(file_get_contents($private.'/evidence18_backups/'.$back)===$fixture,'Original unmodified code backed up');
    t18(file_get_contents($web.'/super-admin-satu.php')===$new,'Only safe change applied');
    t18(e18_apply($web.'/super-admin-satu.php',$private,hash('sha256',$new),'dashboard')==='','No repeated changes');
    $mismatch=false;try{e18_apply($web.'/super-admin-satu.php',$private,hash('sha256','outdated'),'dashboard');}
    catch(RuntimeException $e){$mismatch=true;}
    t18($mismatch,'Outdated installer version refused');
}finally{
    foreach(glob($private.'/evidence18_backups/*')?:[] as $file)@unlink($file);
    @rmdir($private.'/evidence18_backups');@unlink($web.'/super-admin-satu.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
// Static verification of sensitive security boundaries that require a real HTTP upload to execute.
$code=file_get_contents(dirname(__DIR__).'/portal_private/evidence18.php');
t18(str_contains($code,'s13_scan_upload_if_configured($tmp)'),'Actual ClamAV stream scan is called');
t18(str_contains($code,'imagecreatefromjpeg')&&str_contains($code,'imagejpeg'),'Photo decoded and re-encoded');
t18(str_contains($code,"(string)\$row['kind']==='jumantik'"),'Jumantik household photo is disallowed');
echo "Stage 18: storage safety, role separation, CSV, recap, and backup patch tests passed.\n";

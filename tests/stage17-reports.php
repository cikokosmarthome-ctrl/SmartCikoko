<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/field_reports17.php';
require dirname(__DIR__).'/portal_private/wilayah17_dashboard_patch.php';
function test17(bool $result,string $name):void{
    if(!$result){fwrite(STDERR,"FAILED: $name\n");exit(1);}
}
$catalog=w17_catalog();
test17(count($catalog)===5,'Five reporting categories');
test17($catalog['lmk']['legacy']!=='','LMK legacy source is explicitly unknown');
$rt=['source'=>'smartapp','role'=>'rt','id'=>'test_rt'];
$rw=['source'=>'smartapp','role'=>'rw','id'=>'test_rw'];
$lmk=['source'=>'smartapp','role'=>'lmk','id'=>'test_lmk'];
$warga=['source'=>'smartapp','role'=>'warga','id'=>'test_warga'];
$fkdm=['source'=>'smartapp','role'=>'fkdm','id'=>'test_fkdm'];
$ppsu=['source'=>'smartapp','role'=>'ppsu','id'=>'test_ppsu'];
$pjlp=['source'=>'pegawai','role'=>'pjlp','id'=>'test_pjlp'];
$admin=['source'=>'pegawai','role'=>'admin','id'=>'test_admin'];
test17(w17_actor_can($rt,'rt_rw') && w17_actor_can($rw,'rt_rw'),'RT/RW restricted category');
test17(!w17_actor_can($rt,'fkdm')&&!w17_actor_can($rw,'lmk'),'RT/RW cannot submit other staff reports');
test17(w17_actor_can($lmk,'lmk'),'LMK can submit its report');
test17(!w17_actor_can($warga,'rt_rw') && w17_actor_can($warga,'jumantik'),'Resident only records Jumantik-type observations');
test17(w17_actor_can($fkdm,'fkdm')&&!w17_actor_can($fkdm,'ppsu'),'FKDM cannot impersonate PPSU');
test17(w17_actor_can($ppsu,'ppsu') && w17_actor_can($pjlp,'ppsu'),'PPSU/JPLP personnel may report service work');
test17(!w17_actor_can($admin,'rt_rw')&&!w17_actor_can($admin,'fkdm'),'Super Admin does not impersonate role user');
test17(!w17_actor_can(['source'=>'smartapp','role'=>'unknown'],'jumantik'),'Unknown role denied');
$rtForm=['tanggal'=>'2026-11-09','rt'=>'1','rw'=>'2','jenis'=>'kerja_bakti',
 'lokasi'=>'Area balai RW','uraian'=>'Pembersihan saluran umum di wilayah RW.',
 'hasil'=>'Area balai bersih dan saluran lancar.'];
$checked=w17_validate('rt_rw',$rtForm);
test17($checked['rt']==='01'&&$checked['rw']==='02','RT/RW normalized');
$bad=$rtForm;$bad['rt']='../../etc';$denied=false;
try{w17_validate('rt_rw',$bad);}catch(InvalidArgumentException $e){$denied=true;}
test17($denied,'RT traversal rejected');
$bad=$rtForm;$bad['tanggal']='2026-02-30';$denied=false;
try{w17_validate('rt_rw',$bad);}catch(InvalidArgumentException $e){$denied=true;}
test17($denied,'Impossible date rejected');
$jumantik=[
 'tanggal'=>'2026-11-09','rt'=>'03','rw'=>'04','lokasi'=>'Lingkungan RW 04',
 'rumah_diperiksa'=>'20','rumah_positif'=>'2','kontainer_diperiksa'=>'30','kontainer_positif'=>'3',
 'psn'=>'Edukasi pengurasan penampungan secara rutin.','catatan'=>'Tidak ada kontak pribadi.'
];
$checked=w17_validate('jumantik',$jumantik);
test17($checked['rumah_positif']==='2','Aggregate count accepted');
$bad=$jumantik;$bad['rumah_positif']='21';$denied=false;
try{w17_validate('jumantik',$bad);}catch(InvalidArgumentException $e){$denied=true;}
test17($denied,'Positive houses cannot exceed inspected');
$bad=$jumantik;$bad['kontainer_positif']='31';$denied=false;
try{w17_validate('jumantik',$bad);}catch(InvalidArgumentException $e){$denied=true;}
test17($denied,'Positive containers cannot exceed inspected');
$bad=$jumantik;$bad['rumah_diperiksa']='-1';$denied=false;
try{w17_validate('jumantik',$bad);}catch(InvalidArgumentException $e){$denied=true;}
test17($denied,'Negative counts refused');
test17(!w17_own($warga,['actor_source'=>'smartapp','actor_id'=>'another_warga']),'Other citizen reports cannot be edited');
test17(w17_own($warga,['actor_source'=>'smartapp','actor_id'=>'test_warga']),'Own report recognized');
$fixture="<?php\n".str_repeat('/* no secrets */ ',200)
    ."\$data=super_admin_metrics();\n?><!doctype html><html><head>"
    .'<link rel="stylesheet" href="/assets/super-admin-satu.css"></head><body>'
    .'<main><section id="kemajuan" class="section">Progress</section></main></body></html>';
test17(strlen($fixture)>3000,'Dashboard fixture length');
$patched=w17_dashboard_patch($fixture);
test17(w17_dashboard_ready($patched),'Panel link and include inserted');
test17(w17_dashboard_patch($patched)===$patched,'Dashboard patch idempotent');
test17(substr_count($patched,'SMARTCIKOKO_TAHAP17_PANEL')===1,'One injection only');
test17(str_contains($patched,'super_admin_metrics();'),'Original dashboard content intact');
$invalid=false;
try{w17_dashboard_patch('<html><head></head><body>unknown</body></html>');}
catch(RuntimeException $e){$invalid=true;}
test17($invalid,'Unknown dashboard structure refused');
$root=sys_get_temp_dir().'/cikoko-stage17-'.bin2hex(random_bytes(5));
$web=$root.'/public_html';$private=$root.'/portal_private';
mkdir($root,0700);mkdir($web,0700);mkdir($private,0700);
file_put_contents($web.'/super-admin-satu.php',$fixture);
try{
 $backup=w17_dashboard_apply($web.'/super-admin-satu.php',$private,hash('sha256',$fixture));
 test17($backup!=='' && is_file($private.'/wilayah17_backups/'.$backup),'Private backup created');
 test17(file_get_contents($private.'/wilayah17_backups/'.$backup)===$fixture,'Backup preserves original PHP');
 test17(file_get_contents($web.'/super-admin-satu.php')===$patched,'New dashboard only has scoped integration');
 test17(w17_dashboard_apply($web.'/super-admin-satu.php',$private,hash('sha256',$patched))==='','No additional patch on second run');
}finally{
 foreach(glob($private.'/wilayah17_backups/*')?:[] as $f)@unlink($f);
 @rmdir($private.'/wilayah17_backups');
 @unlink($web.'/super-admin-satu.php');@rmdir($web);@rmdir($private);@rmdir($root);
}
echo "Stage 17: permissions, structured field validation and safe dashboard patch tests passed.\n";

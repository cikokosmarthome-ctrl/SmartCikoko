<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/unified_reports.php';
function assert15(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAILED: $message\n");exit(1);}}
assert15(count(lr_categories())===10,'Ten catalog categories');
assert15(lr_categories()['waris']['mode']==='existing','Heir existing flow not duplicated');
assert15(lr_categories()['cuti']['mode']==='preliminary','Leave form not official approval');
assert15(lr_categories()['lmk']['table']===null,'LMK legacy source not fabricated');
$rt=['source'=>'smartapp','role'=>'rt','id'=>'fake_rt'];
$warga=['source'=>'smartapp','role'=>'warga','id'=>'fake_warga'];
$pppk=['source'=>'pegawai','role'=>'pppk','id'=>'fake_pppk'];
$pjlp=['source'=>'pegawai','role'=>'pjlp','id'=>'fake_pjlp'];
$admin=['source'=>'pegawai','role'=>'admin','id'=>'fake_admin'];
assert15(lr_can_submit($rt,'rt_rw'),'RT activity allowed');
assert15(!lr_can_submit($rt,'pppk'),'RT cannot impersonate PPPK');
assert15(lr_can_submit($pppk,'pppk'),'PPPK summary allowed');
assert15(!lr_can_submit($warga,'cuti'),'Citizen cannot request employee leave');
assert15(lr_can_submit($pjlp,'pjlp'),'PJLP activity allowed');
assert15(lr_can_submit($admin,'umum'),'Admin generic report allowed');
assert15(!lr_can_submit($admin,'rt_rw'),'Admin cannot impersonate RT');
assert15(!lr_can_submit($warga,'waris'),'Heir intake must remain native');
assert15(lr_date('2026-11-10')==='2026-11-10','Valid date');
foreach(['2026-02-30','2026-13-10','10/11/2026'] as $bad){
 $failed=false;try{lr_date($bad);}catch(InvalidArgumentException $e){$failed=true;}
 assert15($failed,'Invalid date blocked');
}
lr_form_check('cuti','Permohonan Cuti Tahunan','Pengajuan cuti sesuai kebutuhan dinas.','','2026-11-12','2026-11-14');
foreach([
 ['cuti','Permohonan Cuti','Alasan permohonan cukup panjang','','2026-11-12',''],
 ['rt_rw','Kegiatan RT','Ada kegiatan dengan uraian yang cukup panjang','','2026-11-12',''],
 ['pppk','A','Uraian kegiatan kinerja PPPK harian.','Jakarta','2026-11-12',''],
 ['pppk','Kegiatan PPPK','Uraian kegiatan kinerja PPPK harian.','Jakarta','2026-11-12','2026-11-10'],
] as $bad){
 $failed=false;try{lr_form_check(...$bad);}catch(InvalidArgumentException $e){$failed=true;}
 assert15($failed,'Invalid form rejected');
}
assert15(count(lr_stages())===4,'Administrative state separate from official approvals');
echo "Stage 15 reporting validation passed.\n";

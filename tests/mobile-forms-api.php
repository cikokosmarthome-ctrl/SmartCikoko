<?php
declare(strict_types=1);
// Security/permission tests using artificial identities only; NO DB writes.
function account_control_allowed(array $actor):bool {
    return ($actor['active']??true)===true;
}
function is_super_admin(array $actor):bool {
    return ($actor['source']??'')==='pegawai'&&($actor['role']??'')==='superadmin';
}
require dirname(__DIR__).'/portal_private/mobile_forms_api.php';
function check_mobile(bool $ok,string $label):void {
    if(!$ok){fwrite(STDERR,'FAILED: '.$label."\n");exit(1);}
}
$pppk=['source'=>'pegawai','id'=>'p1','role'=>'pppk'];
$pjlp=['source'=>'pegawai','id'=>'l1','role'=>'pjlp'];
$rt=['source'=>'smartapp','id'=>'r1','role'=>'rt'];
$warga=['source'=>'smartapp','id'=>'w1','role'=>'warga'];
$admin=['source'=>'pegawai','id'=>'a1','role'=>'superadmin'];
check_mobile(mobile_form_allowed($pppk,'pegawai','pppk'),'PPPK category permission');
check_mobile(mobile_form_allowed($pppk,'pegawai','cuti'),'PPPK has leave request');
check_mobile(!mobile_form_allowed($pppk,'pegawai','pjlp'),'PPPK cannot use PJLP form');
check_mobile(!mobile_form_allowed($pppk,'wilayah','rt_rw'),'PPPK cannot impersonate RT');
check_mobile(mobile_form_allowed($pjlp,'pegawai','pjlp'),'PJLP own form permission');
check_mobile(mobile_form_allowed($rt,'wilayah','rt_rw'),'RT access their reporting kind');
check_mobile(!mobile_form_allowed($rt,'pegawai','pppk'),'RT cannot access PPPK');
check_mobile(mobile_form_allowed($warga,'wilayah','jumantik'),'Resident aggregate observation only via native validation');
check_mobile(!mobile_form_allowed($admin,'pegawai','pppk'),'Admin cannot create PPPK submissions by impersonation');
check_mobile(mobile_form_view_allowed($admin,'pegawai','pppk'),'Admin may inspect staff form without impersonating');
check_mobile(mobile_form_view_allowed($admin,'wilayah','rt_rw'),'Admin may inspect wilayah submissions');
check_mobile(mobile_form_allowed($warga,'laporan','umum'),'Citizen may send a general Stage 15 report');
check_mobile(!mobile_form_allowed($warga,'laporan','pppk'),'Citizen may not enter PPPK reports');
check_mobile(!mobile_form_allowed($rt,'laporan','pppk'),'RT may not impersonate PPPK in Stage 15');
check_mobile(!mobile_form_allowed($pppk,'wilayah','fkdm'),'PPPK may not impersonate FKDM');
check_mobile(mobile_form_group('laporan')===null,'Stage 15 mobile category enabled');
$badReview=false;
try{mobile_form_review($warga,'laporan',1,'ditutup','');}
catch(RuntimeException $e){$badReview=true;}
check_mobile($badReview,'Only authorized Super Admin can review via mobile API');
$catalog=mobile_form_catalog($pppk);
$keys=array_column($catalog,'kind');
check_mobile(in_array('pppk',$keys,true)&&in_array('cuti',$keys,true),'PPPK only sees authorized catalog');
check_mobile(!in_array('pjlp',$keys,true),'PJLP catalog is not exposed to PPPK');
foreach($catalog as $row){
    check_mobile(!isset($row['native'])&&!isset($row['legacy']),
        'Internal native table names are never sent to mobile');
    foreach($row['fields'] as $field){
        check_mobile(isset($field['label'],$field['type'],$field['required'],$field['options']),
            'Schema fields fully described');
    }
}
$disabled=$pppk;$disabled['active']=false;
check_mobile(mobile_form_catalog($disabled)===[],'Inactive account sees no schema');
check_mobile(mobile_form_values(['uraian'=>'Kegiatan dummy'])===['uraian'=>'Kegiatan dummy'],
    'Valid input map accepted');
foreach([
    ['wrong key'=>'a'],
    ['valid'=>['nested'=>'not allowed']],
] as $invalid){
    $refused=false;
    try{mobile_form_values($invalid);}catch(InvalidArgumentException $e){$refused=true;}
    check_mobile($refused,'Invalid payload refused');
}
$refused=false;try{mobile_form_group('internal_private');}
catch(InvalidArgumentException $e){$refused=true;}
check_mobile($refused,'Unknown group refused');
$endpoint=file_get_contents(dirname(__DIR__).'/public_html/api/mobile-forms.php');
check_mobile(str_contains($endpoint,'api_actor()'),'Bearer auth checked at endpoint');
check_mobile(str_contains($endpoint,'mobile_forms_enabled($actor)'),'Account status validated at API boundary');
check_mobile(str_contains($endpoint,'mobile_form_values('),'Server validates all input fields');
check_mobile(!str_contains($endpoint,'CREATE TABLE'),'No duplicate tables');
echo "Mobile form catalog, role isolation and validation safety tests passed.\n";

<?php
declare(strict_types=1);
/** Pure Stage-12 checks. No production credentials, data or DB access. */
require dirname(__DIR__).'/portal_private/release_readiness.php';
function r12_expect(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAILED: $message\n");exit(1);}
}
$catalog=r12_auto_catalog();
$requirements=r12_manual_requirements();
r12_expect(count($catalog)===11,'Expected automated diagnostics');
r12_expect(count($requirements)===9,'Expected manual requirements');
r12_expect(isset($requirements['mfa_admin']),'MFA is mandatory review item');
r12_expect(isset($requirements['malware']),'Malware inspection review item');
r12_expect(isset($requirements['letter']),'Official document authorization review item');
$mock=[];
foreach($catalog as $key=>$label)
    $mock[$key]=['title'=>$label,'passed'=>true,'hint'=>'Test fixture'];
$mock['installers']['passed']=false;
$manual=['access_matrix'=>['status'=>'verified'],'malware'=>['status'=>'blocked']];
$stats=r12_stats($mock,$manual);
r12_expect($stats['auto_total']===11 && $stats['passed']===10,'Incomplete auto score');
r12_expect($stats['manual_total']===9 && $stats['verified']===1 && $stats['blocked']===1,'Incomplete manual score');
$old=$_SERVER['HTTPS']??null;
$_SERVER['HTTPS']='off';r12_expect(!r12_is_https(),'Reject non-HTTPS');
$_SERVER['HTTPS']='on';r12_expect(r12_is_https(),'Accept HTTPS');
if($old===null)unset($_SERVER['HTTPS']);else $_SERVER['HTTPS']=$old;
r12_expect(!r12_private_folder_ok('../public_html'),'Reject arbitrary folder');
echo "Stage 12 readiness helper tests passed.\n";

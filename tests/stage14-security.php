<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/security_audit14.php';
function t14_test(bool $passed,string $label):void {
 if(!$passed){fwrite(STDERR,"FAILED: $label\n");exit(1);}
}
t14_test(t14_php_ok(80300),'PHP 8.3 compatible');
t14_test(!t14_php_ok(70400),'Old PHP rejected');
t14_test(t14_upload_size_ok(2048),'Small upload allowed');
t14_test(!t14_upload_size_ok(0),'Empty upload rejected');
t14_test(!t14_upload_size_ok(6*1024*1024),'Oversized upload rejected');
t14_test(t14_safe_stored_filename(str_repeat('a',48).'.pdf'),'Random stored name accepted');
t14_test(!t14_safe_stored_filename('../../config.php'),'Path traversal rejected');
t14_test(!t14_safe_stored_filename(str_repeat('a',48).'.php'),'Executable extension rejected');
t14_test(t14_clamd_status([])['ok']===false,'Unconfigured malware scanner never shown as active');
$root=sys_get_temp_dir().'/cikoko14-'.bin2hex(random_bytes(7));
mkdir($root,0700);
mkdir($root.'/private_requests',0700);
try {
 t14_test(t14_private_dir('private_requests',$root)['ok'],'Private directory accepted');
 t14_test(!t14_private_dir('../public_html',$root)['ok'],'Unknown directory rejected');
 t14_test(t14_restricted_files($root)===[],'No risky webroot fixtures');
 file_put_contents($root.'/error_log','dummy fixture');
 t14_test(in_array('error_log',t14_restricted_files($root),true),'Risky webroot file detected');
}finally{
 @unlink($root.'/error_log');@rmdir($root.'/private_requests');@rmdir($root);
}
function is_super_admin(?array $user=null):bool{return false;}
$sim=t14_access_simulation();
t14_test(count($sim)===6,'Six synthetic permissions checked');
foreach($sim as $key=>$passed)t14_test($passed,'Permission simulation '.$key);
echo "Stage 14 tests passed.\n";

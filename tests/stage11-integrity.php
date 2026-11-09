<?php
declare(strict_types=1);
/** Non-production tests of integrity validation. Never touches user files. */
require dirname(__DIR__).'/portal_private/archive_security.php';
function t11_expect(bool $value,string $message):void{
    if(!$value){fwrite(STDERR,"FAILED: ".$message."\n");exit(1);}
}
t11_expect(count(t11_sources())===2,'two archive sources');
t11_expect(isset(t11_review_labels()['tindak_lanjut']),'review statuses');
$folder=sys_get_temp_dir().'/smartcikoko-stage11-'.bin2hex(random_bytes(8));
if(!mkdir($folder,0700))throw new RuntimeException('Temporary test folder unavailable.');
$token=str_repeat('a',48).'.pdf';
$path=$folder.'/'.$token;
$content="%PDF-1.4\nTest fixture without personal data.\n";
file_put_contents($path,$content);
$row=['stored_name'=>$token,'sha256'=>hash('sha256',$content),'file_size'=>strlen($content)];
try{
    t11_expect(t11_check_content($folder,$row)==='sesuai','correct hash and size accepted');
    $tampered=$row;$tampered['sha256']=str_repeat('b',64);
    t11_expect(t11_check_content($folder,$tampered)==='berbeda','hash mismatch rejected');
    $wrongSize=$row;$wrongSize['file_size']=1;
    t11_expect(t11_check_content($folder,$wrongSize)==='berbeda','size mismatch rejected');
    $invalidName=$row;$invalidName['stored_name']='../../secrets.txt';
    t11_expect(t11_check_content($folder,$invalidName)==='tidak_aman','path traversal rejected');
    unlink($path);
    t11_expect(t11_check_content($folder,$row)==='hilang','missing file reported');
}finally{
    if(is_file($path))@unlink($path);
    @rmdir($folder);
}
echo "Stage 11 integrity tests passed.\n";

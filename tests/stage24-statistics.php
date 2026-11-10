<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/stats24_repair.php';
function s24test(bool $pass,string $label):void {
    if(!$pass){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
}
$repo=dirname(__DIR__);
$live=s24_files('/home/test/portal_private','/home/test/public_html');
s24test(count($live)===2,'Only two Stage23 files can be replaced');
s24test($live['engine']['old']==='f1a11681a969992a0c8e4fabaea327e3e27a50e3','Original deployed V2 engine SHA pinned');
s24test($live['page']['old']==='a88d9ad688c798bd1c9a180fd281118017513c41','Original deployed V2 page SHA pinned');
foreach(['engine'=>'portal_private/hub23.php','page'=>'public_html/pusat-operasional23.php'] as $name=>$path){
    $contents=file_get_contents($repo.'/'.$path);
    s24test(s24_blob($contents)===$live[$name]['new'],'Current release checksum matches installer expected candidate: '.$name);
}
$root=sys_get_temp_dir().'/s24-'.bin2hex(random_bytes(5));
$private=$root.'/portal_private';$web=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
$oldEngine="<?php /* Stage23 known old engine */";
$newEngine="<?php /* Stage24 approved engine */";
$oldPage="<?php /* Stage23 known old page */";
$newPage="<?php /* Stage24 approved page */";
$specs=[
    'engine'=>['target'=>$private.'/hub23.php','candidate'=>$private.'/recovery24/hub23.php',
        'old'=>s24_blob($oldEngine),'new'=>s24_blob($newEngine)],
    'page'=>['target'=>$web.'/pusat-operasional23.php','candidate'=>$private.'/recovery24/pusat-operasional23.php',
        'old'=>s24_blob($oldPage),'new'=>s24_blob($newPage)],
];
mkdir($private.'/recovery24',0700);
file_put_contents($specs['engine']['target'],$oldEngine);
file_put_contents($specs['page']['target'],$oldPage);
file_put_contents($specs['engine']['candidate'],$newEngine);
file_put_contents($specs['page']['candidate'],$newPage);
try{
    foreach($specs as $name=>$sp){
        s24test(s24_state($sp)['status']==='repairable','Original file detected: '.$name);
    }
    $backup1=s24_apply($specs['engine'],$private,hash('sha256',$oldEngine),'engine');
    s24test(is_file($private.'/stats24_backups/'.$backup1),'Engine backup outside webroot');
    s24test(file_get_contents($private.'/stats24_backups/'.$backup1)===$oldEngine,'Engine original preserved exactly');
    s24test(file_get_contents($specs['engine']['target'])===$newEngine,'Engine replacement applied atomically');
    s24test(s24_apply($specs['engine'],$private,hash('sha256',$newEngine),'engine')==='','Repeated engine install is no-op');
    $backup2=s24_apply($specs['page'],$private,hash('sha256',$oldPage),'page');
    s24test(is_file($private.'/stats24_backups/'.$backup2),'Page backup outside webroot');
    s24test(file_get_contents($private.'/stats24_backups/'.$backup2)===$oldPage,'Page backup retains exact original bytes');
    s24test(file_get_contents($specs['page']['target'])===$newPage,'Page replacement applied atomically');
    file_put_contents($specs['engine']['target'],$oldEngine."\n/* custom changes */");
    s24test(s24_state($specs['engine'])['status']==='modified','Modified custom file is protected');
    $failed=false;
    try{s24_apply($specs['engine'],$private,hash('sha256',file_get_contents($specs['engine']['target'])),'engine');}
    catch(RuntimeException $e){$failed=true;}
    s24test($failed,'Custom file changes are never overwritten');
    file_put_contents($specs['engine']['target'],$oldEngine);
    $failed=false;
    try{s24_install($private,$web,['engine'=>hash('sha256','wrong'),'page'=>hash('sha256',$newPage)]);}
    catch(RuntimeException $e){$failed=true;}
    s24test($failed,'Stale form hash rejected');
    file_put_contents($specs['engine']['candidate'],$newEngine."\n/* tampered package */");
    s24test(s24_state($specs['engine'])['status']==='invalid_package','Modified candidate rejected');
    file_put_contents($specs['engine']['candidate'],$newEngine);
    s24test(s24_state($specs['engine'])['status']==='repairable','Package can be retried after correcting candidate');
}finally{
    foreach(glob($private.'/stats24_backups/*')?:[] as $x)@unlink($x);
    @rmdir($private.'/stats24_backups');
    @unlink($specs['engine']['target']);@unlink($specs['page']['target']);
    @unlink($specs['engine']['candidate']);@unlink($specs['page']['candidate']);
    @rmdir($private.'/recovery24');@rmdir($private);@rmdir($web);@rmdir($root);
}
echo "Stage24: new file SHA, known V2 release, safe private backups, atomic recovery and modified-file refusal passed.\n";

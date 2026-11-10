<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/hub23_repair.php';
function test23r(bool $condition,string $label):void{
    if(!$condition){fwrite(STDERR,'FAIL: '.$label."\n");exit(1);}
}
$root=sys_get_temp_dir().'/smartcikoko23r-'.bin2hex(random_bytes(5));
$private=$root.'/portal_private';$public=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($public,0700);
$old="<?php\n/* Stage23 official previous release */\nfunction h23_overview():array{return [];}\n";
$new="<?php\n/* Stage23 resilient replacement */\nfunction h23_guarded():array{return [];}\n";
$orig=$private.'/hub23.php';
$candidate=$private.'/hub23-fixed.php';
file_put_contents($orig,$old);file_put_contents($candidate,$new);
$spec=['target'=>$orig,'candidate'=>$candidate,
  'original'=>recover23_blob($old),'fixed'=>recover23_blob($new)];
try{
    test23r(recover23_state($spec)['status']==='repairable','Original recognized safely');
    $hash=hash('sha256',$old);
    $backup=recover23_apply($spec,$private,$hash,'hub');
    test23r($backup!==''&&is_file($private.'/hub23_repair_backups/'.$backup),'Private backup file exists');
    test23r(file_get_contents($private.'/hub23_repair_backups/'.$backup)===$old,'Backup retains byte-exact original');
    test23r(file_get_contents($orig)===$new,'New replacement is installed');
    test23r(recover23_state($spec)['status']==='ready','Version now fixed');
    test23r(recover23_apply($spec,$private,hash('sha256',$new),'hub')==='','Repeated repair does not rewrite existing fixed file');
    file_put_contents($orig,$old);
    $failed=false;try{recover23_apply($spec,$private,hash('sha256','stale'),'hub');}
    catch(RuntimeException $e){$failed=true;}
    test23r($failed,'Wrong version token refused');
    file_put_contents($orig,$old."/* user modified */\n");
    test23r(recover23_state($spec)['status']==='modified','Custom file protected');
    $failed=false;try{recover23_apply($spec,$private,hash('sha256',file_get_contents($orig)),'hub');}
    catch(RuntimeException $e){$failed=true;}
    test23r($failed,'Custom or manually modified code not overwritten');
    file_put_contents($orig,$old);
    file_put_contents($candidate,$new."/* altered package */\n");
    test23r(recover23_state($spec)['status']==='invalid_candidate','Altered package is rejected');
    $failed=false;try{recover23_apply($spec,$private,hash('sha256',$old),'hub');}
    catch(RuntimeException $e){$failed=true;}
    test23r($failed,'Tampered replacement not applied');
    file_put_contents($candidate,$new);
    test23r(recover23_state($spec)['status']==='repairable','Valid package can resume');
    require dirname(__DIR__).'/portal_private/hub23.php';
    $warnings=[];
    $fallback=['available'=>0,'missing'=>['15','16','17']];
    $out=h23_guarded('ringkasan laporan',static fn()=>throw new RuntimeException('secret-db-information'),$fallback,$warnings);
    test23r($out===$fallback,'Optional runtime failure returns safe fallback');
    test23r($warnings===['ringkasan laporan'],'Only non-sensitive section name returned');
    $warnings=[];
    $out=h23_guarded('menu',static fn()=>['available'=>true],[], $warnings);
    test23r($out['available']===true&&$warnings===[],'Successful section unaffected');
    $p=file_get_contents(dirname(__DIR__).'/public_html/pusat-operasional23.php');
    test23r(str_contains($p,"\$data['warnings']"),'Workspace displays redacted fallback warning');
    test23r(!str_contains($p,'display_errors'), 'No raw production error exposure');
    $localRecover=recover23_files('/a/private','/a/public');
    test23r(count($localRecover)===2,'Only two Stage23 files may be modified');
    test23r($localRecover['hub']['original']==='ab836ce385ff599f2b0e7785f62f3a1af5cb0dc7','Previous official engine signature pinned');
    test23r($localRecover['page']['original']==='bff61805b7f31dbb88c8fae9e3a24cfa1ad7d9ff','Previous official UI signature pinned');
    $newEngine=file_get_contents(dirname(__DIR__).'/portal_private/hub23.php');
    test23r(recover23_blob($newEngine)===$localRecover['hub']['fixed'],'Actual engine release matches pinned replacement');
    test23r(recover23_blob($p)===$localRecover['page']['fixed'],'Actual page release matches pinned replacement');
}finally{
    foreach(glob($private.'/hub23_repair_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/hub23_repair_backups');@unlink($candidate);@unlink($orig);
    @rmdir($private);@rmdir($public);@rmdir($root);
}
echo "Stage23 repair: pinned official files, rollback backups, atomic repair, refusal of modified files, isolated dashboard failures PASSED.\n";

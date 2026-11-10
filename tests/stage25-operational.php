<?php
declare(strict_types=1);
/** Stage25 unit/integration checks with synthetic SQLite records only. */
function test25(bool $pass,string $label):void{
    if(!$pass){fwrite(STDERR,"FAILED: ".$label."\n");exit(1);}
}
function db(string $source='smartapp'):PDO{
    static $pool=[];
    if(!isset($pool[$source])){
        $q=new PDO('sqlite::memory:');
        $q->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        if($source==='pegawai'){
            $q->exec('CREATE TABLE users(id TEXT PRIMARY KEY,role TEXT,status TEXT)');
            $q->exec("INSERT INTO users VALUES('admin1','superadmin','active'),('disabled','superadmin','inactive')");
            $q->exec('CREATE TABLE ck_pancoran_users(id TEXT PRIMARY KEY,is_active INTEGER)');
        }else{
            $q->exec('CREATE TABLE unified_account_access(actor_source TEXT,actor_id TEXT,login_allowed INTEGER)');
            $q->exec('CREATE TABLE unified_portal_users(id TEXT PRIMARY KEY,role TEXT,active INTEGER)');
            $q->exec('CREATE TABLE unified_module_access(actor_source TEXT,actor_id TEXT,ability TEXT,decision TEXT)');
            foreach(['unified_report_intake','unified_detailed_forms16','unified_field_reports17'] as $table){
                $q->exec('CREATE TABLE '.$table.'(id INTEGER PRIMARY KEY,status TEXT,actor_source TEXT,actor_id TEXT)');
            }
            foreach(['unified_report_intake_history','unified_detailed_form_history16','unified_field_history17'] as $table)
                $q->exec('CREATE TABLE '.$table.'(id INTEGER PRIMARY KEY)');
            $q->exec("INSERT INTO unified_report_intake VALUES(1,'draf','pegawai','admin1'),(2,'diajukan','pegawai','admin1')");
            $q->exec("INSERT INTO unified_detailed_forms16 VALUES(1,'perbaikan','pegawai','admin1'),(2,'diajukan','pegawai','admin1')");
            $q->exec("INSERT INTO unified_field_reports17 VALUES(1,'ditutup','pegawai','admin1'),(2,'draf','pegawai','admin1')");
        }
        $pool[$source]=$q;
    }
    return $pool[$source];
}
function permitted(string $ability,?array $actor=null):bool{return false;}
function is_super_admin(?array $actor=null):bool{
    return ($actor['source']??'')==='pegawai'&&($actor['role']??'')==='superadmin';
}
require dirname(__DIR__).'/portal_private/audit25.php';
require dirname(__DIR__).'/portal_private/audit25_patch.php';
$admin=['source'=>'pegawai','id'=>'admin1','role'=>'superadmin','name'=>'Test Admin'];
$denied=['source'=>'pegawai','id'=>'disabled','role'=>'superadmin','name'=>'Test Disabled'];
test25(account_control_allowed($admin),'Native active Super Admin account validated');
test25(!account_control_allowed($denied),'Inactive Super Admin refused by control layer');
test25(count(a25_modules())===8,'Modules 15-22 accounted for');
test25(count(a25_count_map())===3,'Only report intake modules 15-17 counted');
test25(a25_reconcile([15=>2,16=>2,17=>2],['15'=>2,'16'=>2,'17'=>2])['state']==='pass','Independent exact count consistency accepted');
test25(a25_reconcile(['15'=>2,'16'=>2,'17'=>2],[15=>2,16=>9,17=>2])['state']==='attention','Mismatched source returns attention');
test25(a25_reconcile(['15'=>2,'16'=>2],['15'=>2,'16'=>2,'17'=>2])['state']==='attention','Incomplete source returns attention instead of zero');
test25(a25_reconcile(['15'=>2,'16'=>2,'17'=>2],[15=>2,16=>-1,17=>2])['state']==='attention','Invalid negative counts refused');
test25(a25_guard_state(false,2)['state']==='unavailable','Strict active policy without guard is critical');
test25(a25_guard_state(true,2)['state']==='pass','Guard exists for active strict categories');
test25(a25_guard_state(false,0)['state']==='attention','Missing guard with no strict rule is not falsely passed');
test25(a25_guard_state(true,0)['state']==='pass','Installed guard with default-off strict rules accepted');
test25(a25_guard_state(true,-1)['state']==='unavailable','Unknown policy state not described as safe');
test25(a25_aggregate([a25_entry('X','Good','pass',''),a25_entry('X','Warn','attention',''),a25_entry('X','No','unavailable','')])
    ===['pass'=>1,'attention'=>1,'unavailable'=>1],'Status totals correctly grouped');
$bad=false;try{a25_entry('x','x','success','x');}catch(InvalidArgumentException $e){$bad=true;}
test25($bad,'Invalid forged health status refused');
test25(a25_stage24_fixed(dirname(__DIR__).'/portal_private/hub23.php'),'Stage24 numeric-key summary fix present in repository');
$root=sys_get_temp_dir().'/sc25-'.bin2hex(random_bytes(5));
$private=$root.'/portal_private';$public=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($public,0700);
file_put_contents($private.'/hub23.php',"<?php\n\$key=(string)\$stage; h23_ready(\$key); // by_stage\n");
$initial=$public.'/super-admin-satu.php';
$dashboard=file_get_contents(dirname(__DIR__).'/public_html/super-admin-satu.php');
file_put_contents($initial,$dashboard);
$realReport=null;
try{
    $report=a25_report($admin,$private,$public);
    test25($report['counts']['available']===3,'Read-only diagnostic finds three synthetic reporting sources');
    test25($report['counts']['missing']===[],'All available Stage15-17 sources are recognized');
    test25($report['counts']['totals']===['draf'=>2,'aktif'=>2,'perbaikan'=>1,'selesai'=>1],'Stage25 displays correct synthetic aggregate statuses');
    test25($report['source_totals']===[15=>2,16=>2,17=>2],'Independent table count returns three correct values');
    $compare=array_values(array_filter($report['entries'],fn($e)=>$e['label']==='Kesamaan ringkasan dan sumber'));
    test25(count($compare)===1&&$compare[0]['state']==='pass','Integrated three-source reconciliation succeeds');
    $dbs=array_values(array_filter($report['entries'],fn($e)=>$e['group']==='Database'));
    test25(count($dbs)===2&&$dbs[0]['state']==='pass'&&$dbs[1]['state']==='pass','Both database connection checks execute');
    test25($report['counts']['scope']==='semua laporan','Super Admin aggregation has explicit scope');
    test25(!array_key_exists('name',$report['counts']),'Operational aggregates expose no personal identity fields');
    $rejected=false;try{a25_report($denied,$private,$public);}
    catch(RuntimeException $e){$rejected=true;}
    test25($rejected,'Disabled Super Admin not permitted to read diagnostics');
    $patched=a25_patch($dashboard);
    test25(str_contains($patched,'SMARTCIKOKO_TAHAP25_AUDIT_PANEL'),'Compact Super Admin audit marker inserted');
    test25(str_contains($patched,'/assets/audit25-admin.css'),'One-page admin stylesheet linked');
    test25(a25_patch($patched)===$patched,'Repeated patch is idempotent');
    test25(str_contains($patched,'super_admin_metrics()'),'Old Super Admin logic preserved');
    $backup=a25_apply($initial,$private,hash('sha256',$dashboard));
    test25(is_file($private.'/audit25_backups/'.$backup),'Original Super Admin preserved in private backup');
    test25(file_get_contents($private.'/audit25_backups/'.$backup)===$dashboard,'Private backup holds exact original bytes');
    test25(file_get_contents($initial)===$patched,'Dashboard patch applied without rewriting other controls');
    test25(a25_apply($initial,$private,hash('sha256',$patched))==='','Repeated installer does not rewrite files');
    $rejected=false;try{a25_apply($initial,$private,hash('sha256','stale'));}
    catch(RuntimeException $e){$rejected=true;}
    test25($rejected,'Stale content SHA refused');
    $rejected=false;try{a25_patch('<html>custom dashboard</html>');}
    catch(RuntimeException $e){$rejected=true;}
    test25($rejected,'Unknown dashboard version refused');
    $fake=$private.'/not-linked.php';file_put_contents($fake,'<?php echo 1;');
    test25(a25_valid_route($fake),'Existing route accepted');
    if(function_exists('symlink')){
        $link=$private.'/symlink.php';
        if(@symlink($fake,$link)){
            test25(!a25_valid_route($link),'Symlink cannot impersonate protected route');
            @unlink($link);
        }
    }
}finally{
    foreach(glob($private.'/audit25_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/audit25_backups');
    @unlink($private.'/not-linked.php');
    @unlink($private.'/hub23.php');
    @unlink($initial);
    @rmdir($private);@rmdir($public);@rmdir($root);
}
echo "Stage25: privilege, module checks, three-source reconciliation, guard flags, read-only status and private dashboard backup passed.\n";

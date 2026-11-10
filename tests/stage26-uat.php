<?php
declare(strict_types=1);
function x26(bool $yes,string $what):void{
    if(!$yes){fwrite(STDERR,"FAIL: $what\n");exit(1);}
}
function db(string $source='smartapp'):PDO{
    static $pool=[];
    if(!isset($pool[$source])){
        $p=new PDO('sqlite::memory:');
        $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $p->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
        if($source==='pegawai'){
            $p->exec('CREATE TABLE users(id TEXT PRIMARY KEY,role TEXT,status TEXT)');
            $p->exec("INSERT INTO users VALUES('admin1','superadmin','active'),('inactive','superadmin','inactive')");
            $p->exec('CREATE TABLE ck_pancoran_users(id TEXT PRIMARY KEY,is_active INTEGER)');
        }else{
            $p->exec('CREATE TABLE unified_portal_users(id TEXT PRIMARY KEY,role TEXT,active INTEGER)');
            $p->exec('CREATE TABLE unified_account_access(actor_source TEXT,actor_id TEXT,login_allowed INTEGER)');
            $p->exec('CREATE TABLE unified_module_access(actor_source TEXT,actor_id TEXT,ability TEXT,decision TEXT)');
            $p->exec('CREATE TABLE unified_uat_events26(id INTEGER PRIMARY KEY AUTOINCREMENT,case_key TEXT,result TEXT,note TEXT,tested_confirmed INTEGER,actor_source TEXT,actor_id TEXT,submit_token TEXT UNIQUE,recorded_at TEXT)');
            $p->exec('CREATE TABLE unified_audit(actor_source TEXT,actor_id TEXT,event_type TEXT,entity TEXT,subject_hash TEXT,created_at TEXT)');
        }
        $pool[$source]=$p;
    }
    return $pool[$source];
}
function permitted(string $ability,?array $actor=null):bool{return false;}
function is_super_admin(?array $actor=null):bool {
    return $actor!==null&&($actor['source']??'')==='pegawai'&&($actor['role']??'')==='superadmin';
}
require dirname(__DIR__).'/portal_private/uat26.php';
require dirname(__DIR__).'/portal_private/uat26_patch.php';
$admin=['source'=>'pegawai','id'=>'admin1','role'=>'superadmin','name'=>'Synthetic Super'];
$bad=['source'=>'pegawai','id'=>'inactive','role'=>'superadmin'];
$staff=['source'=>'pegawai','id'=>'admin1','role'=>'pppk'];
x26(count(u26_catalog())===16,'16 canonical UAT cases available');
x26(isset(u26_catalog()['pppk'],u26_catalog()['pjlp'],u26_catalog()['cuti'],u26_catalog()['rt_rw'],
    u26_catalog()['lmk'],u26_catalog()['jumantik'],u26_catalog()['fkdm'],u26_catalog()['ppsu'],
    u26_catalog()['waris'],u26_catalog()['review'],u26_catalog()['deputy'],u26_catalog()['security']),
    'Coverage includes all reporting, citizen and security stages');
x26(u26_ready(),'Journal is readable');
x26(account_control_allowed($admin),'Active Super Admin valid');
x26(!account_control_allowed($bad),'Inactive admin rejected');
$rejected=false;try{u26_guard($bad);}catch(RuntimeException $e){$rejected=true;}
x26($rejected,'Inactive Super Admin cannot open UAT');
$rejected=false;try{u26_guard($staff);}catch(RuntimeException $e){$rejected=true;}
x26($rejected,'Different role cannot be treated as Super Admin');
x26(u26_snapshot($admin)['totals']===['pending'=>16,'pass'=>0,'fail'=>0,'blocked'=>0],
    'All scenarios initially pending, never automatically passed');
x26(!u26_snapshot($admin)['all_passed'],'Initially no production readiness claim');
foreach([
    ['invalid','fail','Alur tidak dapat digunakan.'],
    ['pppk','fake','Alur tidak dapat digunakan.'],
    ['pppk','pass','Alur diuji pada akun dummy.',false],
    ['pppk','fail','pendek'],
    ['pppk','fail','NIK 3175010203040506 sebaiknya tidak ditulis.'],
    ['pppk','fail','Email penguji@example.com harus dirahasiakan.'],
    ['pppk','fail','OTP: 123456 tidak boleh dicatat.'],
] as $case){
    $rejected=false;
    try{u26_validate($case[0],$case[1],$case[2],$case[3]??true,str_repeat('a',32));}
    catch(InvalidArgumentException $e){$rejected=true;}
    x26($rejected,'Invalid or secret UAT observation rejected: '.implode(' | ',$case));
}
$rejected=false;try{u26_validate('pppk','fail','Alur uji dummy telah dicoba.',true,'wrong-token');}
catch(InvalidArgumentException $e){$rejected=true;}
x26($rejected,'Invalid submission token rejected');
$v=u26_validate('pppk','pending','',false,str_repeat('f',32));
x26($v['note']===''&&!$v['tested'],'Explicit reset to pending possible');
$rejected=false;try{u26_validate('pppk','pending','Tidak seharusnya ada catatan.',false,str_repeat('f',32));}
catch(InvalidArgumentException $e){$rejected=true;}
x26($rejected,'Reset requires clearing previous note');
$token=str_repeat('a',32);
x26(u26_save($admin,'pppk','fail','Akun PPPK dummy gagal menyimpan draf awal.',false,$token),'First manual failure recorded');
x26(u26_save($admin,'pppk','fail','Akun PPPK dummy gagal menyimpan draf awal.',false,$token)===false,'Duplicate POST idempotent');
x26((int)db('smartapp')->query('SELECT COUNT(*) FROM unified_uat_events26')->fetchColumn()===1,
    'Duplicate does not create multiple observations');
x26((int)db('smartapp')->query('SELECT COUNT(*) FROM unified_audit')->fetchColumn()===1,
    'Exactly one audit event created');
$snap=u26_snapshot($admin);
x26($snap['totals']['fail']===1&&$snap['totals']['pending']===15,'Failure surfaced in compact totals');
x26(count(u26_blockers($snap))===1&&u26_blockers($snap)[0]['key']==='pppk','Failed test appears in blocker list');
x26(u26_save($admin,'pppk','pass','Uji ulang PPPK dummy berhasil menyimpan dan mengajukan draf.',true,str_repeat('b',32)),
    'Valid re-test recorded as new immutable entry');
$snap=u26_snapshot($admin);
x26($snap['totals']['pass']===1&&$snap['totals']['fail']===0,'Latest state reflects successful re-test');
x26(count(u26_blockers($snap))===0,'Solved issue no longer current blocker');
x26(count(u26_history($admin,25))===2,'History retains old failure and later pass');
x26((int)db('smartapp')->query('SELECT COUNT(*) FROM unified_uat_events26')->fetchColumn()===2,
    'Immutable entries never overwritten');
x26((int)db('smartapp')->query('SELECT COUNT(*) FROM unified_audit')->fetchColumn()===2,
    'All changes audited');
x26(u26_save($admin,'pppk','pending','',false,str_repeat('c',32)),
    'Admin can reset outdated evidence to pending');
$snap=u26_snapshot($admin);
x26($snap['totals']['pending']===16&&$snap['totals']['pass']===0,'Reset not mistaken for successful test');
x26(count(u26_history($admin,25))===3,'Immutable trace retained after reset');
$source=file_get_contents(dirname(__DIR__).'/public_html/super-admin-satu.php');
$updated=u26_patch($source);
x26(str_contains($updated,'SMARTCIKOKO_STAGE26_UAT_PANEL'),'One-page UAT panel inserted');
x26(str_contains($updated,'/assets/uat26-admin.css'),'UAT styles inserted');
x26(u26_patch($updated)===$updated,'Repeated insertion idempotent');
x26(str_contains($updated,'super_admin_metrics()'),'Existing Super Admin core preserved');
$rejected=false;try{u26_patch('<html>custom unknown</html>');}
catch(RuntimeException $e){$rejected=true;}
x26($rejected,'Unknown dashboard version cannot be overwritten');
$root=sys_get_temp_dir().'/uat26-'.bin2hex(random_bytes(5));
$private=$root.'/portal_private';$web=$root.'/public_html';
mkdir($root,0700);mkdir($private,0700);mkdir($web,0700);
$file=$web.'/super-admin-satu.php';
file_put_contents($file,$source);
try{
    $backup=u26_apply($file,$private,hash('sha256',$source));
    x26(is_file($private.'/uat26_backups/'.$backup),'Private dashboard backup created');
    x26(file_get_contents($private.'/uat26_backups/'.$backup)===$source,'Dashboard backup retains exact original');
    x26(file_get_contents($file)===$updated,'Only scoped panel integrated');
    x26(u26_apply($file,$private,hash('sha256',$updated))==='','Repeated install does not duplicate');
    $rejected=false;try{u26_apply($file,$private,hash('sha256','stale'));}
    catch(RuntimeException $e){$rejected=true;}
    x26($rejected,'Stale installer input refused');
}finally{
    foreach(glob($private.'/uat26_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/uat26_backups');@unlink($file);
    @rmdir($web);@rmdir($private);@rmdir($root);
}
echo "Stage26: manual pass gate, privacy checks, all-pending default, blockers, immutable observations, audit, duplicate protection and dashboard backups passed.\n";

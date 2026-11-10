<?php
declare(strict_types=1);
/** Stage23 integration unit tests with native identities in temporary SQLite.
 * No real personal records, MySQL credentials, production network or file IO.
 */
function ok23(bool $test,string $label):void {
    if(!$test){fwrite(STDERR,"FAILED: ".$label."\n");exit(1);}
}
function db(string $source='smartapp'):PDO {
    static $pool=[];
    if(!isset($pool[$source])){
        $db=new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        if($source==='pegawai'){
            $db->exec("CREATE TABLE users(id TEXT PRIMARY KEY,role TEXT,status TEXT)");
            $db->exec("CREATE TABLE ck_pancoran_users(id TEXT PRIMARY KEY,is_active INTEGER)");
            $db->exec("INSERT INTO users VALUES('pppk1','pppk','active'),('pjlp1','pjlp','active'),('admin1','superadmin','active'),('disabled','pppk','inactive')");
        }else{
            $db->exec("CREATE TABLE unified_portal_users(id TEXT PRIMARY KEY,role TEXT,active INTEGER)");
            $db->exec("CREATE TABLE unified_account_access(actor_source TEXT,actor_id TEXT,login_allowed INTEGER)");
            $db->exec("CREATE TABLE unified_module_access(actor_source TEXT,actor_id TEXT,ability TEXT,decision TEXT)");
            $db->exec("INSERT INTO unified_portal_users VALUES('rt1','rt',1),('warga1','warga',1),('lurah1','lurah',1),('ptsp1','ptsp',1),('blocked','rt',0)");
            foreach(['unified_report_intake','unified_detailed_forms16','unified_field_reports17'] as $table)
                $db->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY, status TEXT, actor_source TEXT, actor_id TEXT)");
            foreach(['unified_report_intake_history','unified_detailed_form_history16','unified_field_history17'] as $table)
                $db->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY)");
            $db->exec("INSERT INTO unified_report_intake VALUES
                (1,'draf','smartapp','rt1'),
                (2,'diajukan','smartapp','rt1'),
                (3,'perbaikan','smartapp','warga1')");
            $db->exec("INSERT INTO unified_detailed_forms16 VALUES
                (1,'ditinjau','pegawai','pppk1'),
                (2,'ditutup','pegawai','pjlp1')");
            $db->exec("INSERT INTO unified_field_reports17 VALUES
                (1,'ditutup','smartapp','rt1'),
                (2,'perbaikan','smartapp','rt1'),
                (3,'draf','smartapp','warga1')");
        }
        $pool[$source]=$db;
    }
    return $pool[$source];
}
function permitted(string $ability,?array $u=null):bool {
    if(!$u)return false;
    return $ability==='waris'&&($u['source']??'')==='smartapp'
        &&in_array($u['role']??'',['warga','lurah','ptsp'],true);
}
function is_super_admin(?array $u=null):bool {
    return $u!==null&&($u['source']??'')==='pegawai'&&($u['role']??'')==='superadmin';
}
require dirname(__DIR__).'/portal_private/hub23.php';
require dirname(__DIR__).'/portal_private/hub23_patch.php';
$rt=['source'=>'smartapp','id'=>'rt1','role'=>'rt','name'=>'Test RT'];
$warga=['source'=>'smartapp','id'=>'warga1','role'=>'warga','name'=>'Test Warga'];
$lurah=['source'=>'smartapp','id'=>'lurah1','role'=>'lurah','name'=>'Test Lurah'];
$ptsp=['source'=>'smartapp','id'=>'ptsp1','role'=>'ptsp','name'=>'Test PTSP'];
$pppk=['source'=>'pegawai','id'=>'pppk1','role'=>'pppk','name'=>'Test PPPK'];
$pjlp=['source'=>'pegawai','id'=>'pjlp1','role'=>'pjlp','name'=>'Test PJLP'];
$admin=['source'=>'pegawai','id'=>'admin1','role'=>'superadmin','name'=>'Test Super'];
ok23(account_control_allowed($pppk),'Active test employee recognized');
ok23(!account_control_allowed(['source'=>'pegawai','id'=>'disabled','role'=>'pppk']),'Inactive native account refused');
ok23(!account_control_allowed(['source'=>'smartapp','id'=>'blocked','role'=>'rt']),'Inactive SmartApp refused');
function keys23(array $cards):array{return array_column($cards,'key');}
$rtKeys=keys23(h23_cards($rt));
ok23(in_array('wilayah17_rt_rw',$rtKeys,true),'RT role sees own field report');
ok23(!in_array('form16_pppk',$rtKeys,true),'RT cannot see PPPK form');
ok23(!in_array('kendali22',$rtKeys,true),'RT cannot see Super Admin policy');
ok23(!in_array('waris',$rtKeys,true),'RT cannot see Ahli Waris service');
$wKeys=keys23(h23_cards($warga));
ok23(in_array('waris',$wKeys,true),'Citizen with native permission sees own inheritance service');
ok23(in_array('wilayah17_jumantik',$wKeys,true),'Resident observations appear as allowed by legacy field-report module');
ok23(!in_array('bukti18',$wKeys,true),'Resident Jumantik cannot access private photo upload');
ok23(!in_array('verifikasi19',$wKeys,true),'Citizen cannot open reviewer console shortcut');
$pKeys=keys23(h23_cards($pppk));
ok23(in_array('form16_pppk',$pKeys,true),'PPPK sees own detailed form');
ok23(!in_array('form16_pjlp',$pKeys,true),'PPPK cannot see PJLP-only detailed form');
ok23(!in_array('kendali22',$pKeys,true),'PPPK cannot see security controls');
$pjKeys=keys23(h23_cards($pjlp));
ok23(in_array('form16_pjlp',$pjKeys,true),'PJLP sees own detailed form');
ok23(in_array('form16_cuti',$pjKeys,true),'PJLP sees eligible preliminary leave application');
$superKeys=keys23(h23_cards($admin));
ok23(in_array('super',$superKeys,true),'Super Admin sees compact existing dashboard');
ok23(in_array('kendali22',$superKeys,true),'Super Admin sees Stage22 policy');
ok23(in_array('rekap18',$superKeys,true),'Super Admin sees Stage18 metadata-only recap');
ok23(!in_array('form16_pppk',$superKeys,true),'Super Admin cannot impersonate PPPK detailed form');
$reviewerKeys=keys23(h23_cards($lurah));
ok23(in_array('verifikasi19',$reviewerKeys,true),'Lurah has Stage19 review entry');
ok23(!in_array('kendali22',$reviewerKeys,true),'Lurah not automatically a Super Admin');
$ptspKeys=keys23(h23_cards($ptsp));
ok23(in_array('penugasan21',$ptspKeys,true),'PTSP has reviewer task link');
ok23(!in_array('rekap18',$ptspKeys,true),'PTSP cannot export Super Admin recap');
db('smartapp')->exec("INSERT INTO unified_module_access VALUES('pegawai','pppk1','pppk','deny')");
$pppkRestricted=keys23(h23_cards($pppk));
ok23(!in_array('form16_pppk',$pppkRestricted,true),'Explicit Stage6 deny hides PPPK form');
ok23(!in_array('lr_pppk',$pppkRestricted,true),'Explicit Stage6 deny hides PPPK intake');
db('smartapp')->exec("INSERT INTO unified_account_access VALUES('smartapp','rt1',0)");
ok23(!account_control_allowed($rt),'Disabled portal account denied by native access policy');
$all=h23_cards($admin);
$filtered=h23_filter_cards($all,'Kendali','pengendalian');
ok23(count($filtered)>0,'Keyword and group filtering works');
foreach($filtered as $card)ok23($card['group']==='pengendalian','Filter never leaks another group');
ok23(h23_filter_cards($all,'completely-nonexistent-module')===[],'Unknown search has empty results');
foreach($all as $card)ok23(str_starts_with($card['href'],'/')&&!str_starts_with($card['href'],'//'),'All destinations are local paths');
$bad=false;try{h23_card('evil','Evil','','https://evil.test','umum','Portal');}
catch(InvalidArgumentException $e){$bad=true;}
ok23($bad,'External unsafe navigation target is rejected');
$base=file_get_contents(dirname(__DIR__).'/public_html/super-admin-satu.php');
$center=file_get_contents(dirname(__DIR__).'/public_html/pusat-laporan.php');
$adminPatch=h23_patch($base,'dashboard');
$staffPatch=h23_patch($center,'center');
ok23(substr_count($adminPatch,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK')===1,'One Super Admin navigation marker');
ok23(substr_count($staffPatch,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK')===1,'One staff center navigation marker');
ok23(str_contains($adminPatch,'super_admin_metrics()'),'Old Super Admin reporting unchanged');
ok23(str_contains($staffPatch,'lr_query('),'Old staff center input reporting unchanged');
ok23(h23_patch($adminPatch,'dashboard')===$adminPatch,'Admin patch is idempotent');
ok23(h23_patch($staffPatch,'center')===$staffPatch,'Staff center patch is idempotent');
$bad=false;try{h23_patch('<html>unknown markup</html>','dashboard');}
catch(RuntimeException $e){$bad=true;}
ok23($bad,'Unknown Super Admin layout safely refused');
$root=sys_get_temp_dir().'/hub23-'.bin2hex(random_bytes(5));
$web=$root.'/public_html';$private=$root.'/portal_private';
mkdir($root,0700);mkdir($web,0700);mkdir($private,0700);
file_put_contents($web.'/super-admin-satu.php',$base);
try{
    $backup=h23_apply($web.'/super-admin-satu.php',$private,hash('sha256',$base),'dashboard');
    ok23(is_file($private.'/hub23_backups/'.$backup),'Exact dashboard backup stored privately');
    ok23(file_get_contents($private.'/hub23_backups/'.$backup)===$base,'Backup unchanged from original');
    ok23(file_get_contents($web.'/super-admin-satu.php')===$adminPatch,'Only scoped nav patch installed');
    ok23(h23_apply($web.'/super-admin-satu.php',$private,hash('sha256',$adminPatch),'dashboard')==='','Second install makes no changes');
    $bad=false;
    try{h23_apply($web.'/super-admin-satu.php',$private,hash('sha256','stale'),'dashboard');}
    catch(RuntimeException $e){$bad=true;}
    ok23($bad,'Version mismatch blocked');
}finally{
    foreach(glob($private.'/hub23_backups/*')?:[] as $f)@unlink($f);
    @rmdir($private.'/hub23_backups');@unlink($web.'/super-admin-satu.php');
    @rmdir($web);@rmdir($private);@rmdir($root);
}
// Stage24 regression: reports from numeric module keys must not produce PHP
// TypeError under strict_types=1; verify exact per-user and Super Admin totals.
$adminSummary=h23_counts($admin);
ok23($adminSummary['available']===3&&$adminSummary['missing']===[],'All 3 reporting modules are readable under strict PHP types');
ok23($adminSummary['totals']===['draf'=>2,'aktif'=>2,'perbaikan'=>2,'selesai'=>2],'Super Admin correct aggregated statuses');
ok23($adminSummary['by_stage'][15]['total']===3
    &&$adminSummary['by_stage'][16]['total']===2
    &&$adminSummary['by_stage'][17]['total']===3,'No duplicate module totals');
$rtSummary=h23_counts($rt);
ok23($rtSummary['totals']===['draf'=>1,'aktif'=>1,'perbaikan'=>1,'selesai'=>1],'RT account sees only own cross-module records');
$pppkSummary=h23_counts($pppk);
ok23($pppkSummary['totals']===['draf'=>0,'aktif'=>1,'perbaikan'=>0,'selesai'=>0],'Pegawai report isolated by actor source and ID');
$wargaSummary=h23_counts($warga);
ok23($wargaSummary['totals']===['draf'=>1,'aktif'=>0,'perbaikan'=>1,'selesai'=>0],'Citizen reports not mixed with other accounts');
$noDataSummary=h23_counts(['source'=>'pegawai','id'=>'missing','role'=>'pppk']);
ok23($noDataSummary['available']===3&&array_sum($noDataSummary['totals'])===0,'Empty records show zero only when sources are readable');
$overview=h23_overview($admin);
ok23(!in_array('ringkasan laporan',$overview['warnings'],true),'Entire Stage23 hub no longer loses report summary to a numeric-key TypeError');
echo "Stage23 role-filtered navigation, withheld modules, safe internal links, dashboard backups and search passed.\n";

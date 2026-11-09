<?php
/**
 * Migrasi akun website lama secara aman ke database SmartApp.
 * Berkas JSON tetap berada di luar public_html.
 * php tools/import_accounts.php /path/portal_users.json /path/admin.json
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
if($argc!==3){fwrite(STDERR,"Pemakaian: php tools/import_accounts.php <portal_users.json> <admin.json>\n");exit(2);}
require dirname(__DIR__).'/portal_private/bootstrap.php';
function account_json(string $file):mixed{
 if(!is_file($file)||!is_readable($file)||filesize($file)>5000000)throw new RuntimeException('Berkas JSON tidak dapat dibaca.');
 return json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
}
$users=account_json($argv[1]);$admin=account_json($argv[2]);
if(!is_array($users)||!array_is_list($users)||!is_array($admin))throw new RuntimeException('Format data lama tidak valid.');
if(!isset($admin['username'],$admin['password_hash']))throw new RuntimeException('Rekaman administrator tidak lengkap.');
$roles=['admin','admin_kelurahan','lurah','warga','rt','rw','asn','pppk','pjlp','ptsp','dukcapil','bendahara_rt','bendahara_rw','lmk','ppsu','fkdm','dasawisma','posyandu','superadmin'];
$records=[];
foreach($users as $r){
 if(!is_array($r))throw new RuntimeException('Rekaman pengguna tidak valid.');
 $id=(string)($r['id']??'');$username=trim((string)($r['username']??''));
 $hash=(string)($r['password_hash']??'');$role=strtolower((string)($r['role']??''));
 if($id===''||strlen($id)>100||$username===''||strlen($username)>190||password_get_info($hash)['algo']===null||!in_array($role,$roles,true))throw new RuntimeException('Rekaman pengguna tidak memenuhi persyaratan; impor dibatalkan.');
 $records[]=['id'=>$id,'username'=>$username,'name'=>mb_substr((string)($r['name']??$username),0,190),'hash'=>$hash,'role'=>$role,'department'=>mb_substr((string)($r['department']??''),0,160),'email'=>mb_substr((string)($r['email']??''),0,190),'active'=>empty($r['active'])?0:1,'created_at'=>empty($r['created_at'])?null:$r['created_at']];
}
if(password_get_info((string)$admin['password_hash'])['algo']===null)throw new RuntimeException('Hash administrator tidak kompatibel.');
$records[]=['id'=>'legacy_admin','username'=>(string)$admin['username'],'name'=>'Administrator Portal','hash'=>(string)$admin['password_hash'],'role'=>'superadmin','department'=>'Kelurahan Cikoko','email'=>'','active'=>1,'created_at'=>null];
$seen=[];foreach($records as $r){if(isset($seen[$r['id']]))throw new RuntimeException('Terdapat ID ganda.');$seen[$r['id']]=1;}
$db=db('smartapp');$db->beginTransaction();
try{
 $st=$db->prepare('INSERT INTO unified_portal_users (id,username,name,password_hash,role,department,email,active,created_at) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),name=VALUES(name),password_hash=VALUES(password_hash),role=VALUES(role),department=VALUES(department),email=VALUES(email),active=VALUES(active)');
 foreach($records as $r)$st->execute([$r['id'],$r['username'],$r['name'],$r['hash'],$r['role'],$r['department'],$r['email'],$r['active'],$r['created_at']]);
 $db->commit();
 echo 'Akun berhasil dimigrasikan: '.count($records)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Impor gagal: '.$e->getMessage()."\n");exit(1);}

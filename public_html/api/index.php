<?php
declare(strict_types=1);
try { require dirname(__DIR__,2).'/portal_private/bootstrap.php'; }
catch(Throwable $e) {http_response_code(503);header('Content-Type: application/json');echo '{"ok":false,"error":"Portal belum dikonfigurasi."}';exit;}
header('Cache-Control: no-store');
$resource=strtolower(trim((string)($_GET['resource']??'health')));
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$body=[];
if (in_array($method,['POST','PUT','PATCH'],true)) {
    if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')===false) respond(['ok'=>false,'error'=>'Gunakan Content-Type application/json.'],415);
    $raw=file_get_contents('php://input',false,null,0,32769);
    if(strlen($raw)>32768) respond(['ok'=>false,'error'=>'Permintaan terlalu besar.'],413);
    try {$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}
    catch(Throwable $e) {respond(['ok'=>false,'error'=>'JSON tidak valid.'],400);}
    if(!is_array($body)) respond(['ok'=>false,'error'=>'Format data tidak valid.'],400);
}
try {
    if($resource==='health'&&$method==='GET') respond(['ok'=>true,'app'=>'Portal Terpadu Kelurahan Cikoko','version'=>'1.0']);
    if($resource==='posts'&&$method==='GET') respond(['ok'=>true,'data'=>public_posts(15)]);
    if($resource==='events'&&$method==='GET') respond(['ok'=>true,'data'=>public_events(15)]);
    if($resource==='login'&&$method==='POST'){
        $identity=trim((string)($body['username']??''));$password=(string)($body['password']??'');
        throttle_login($identity);
        $matches=login_matches($identity,$password);
        if(!$matches) respond(['ok'=>false,'error'=>'Identitas akun atau kata sandi tidak sesuai.'],401);
        $source=(string)($body['source']??'');$id=(string)($body['id']??'');
        if($source!==''&&$id!=='')$matches=array_values(array_filter($matches,fn($u)=>$u['source']===$source&&(string)$u['id']===$id));
        if(!$matches)respond(['ok'=>false,'error'=>'Pilihan identitas tidak sesuai.'],401);
        if(count($matches)>1)respond(['ok'=>true,'selection_required'=>true,'accounts'=>array_map(fn($u)=>[
          'id'=>$u['id'],'source'=>$u['source'],'name'=>$u['name'],'role_label'=>role_label($u['role'])
        ],$matches)]);
        clear_login_limit($identity);
        $u=$matches[0];$token=issue_api_token($u);
        respond(['ok'=>true,'token'=>$token,'expires_in'=>2592000,'user'=>array_diff_key($u,['username'=>true])]);
    }
    $u=api_actor();
    if(!$u)respond(['ok'=>false,'error'=>'Sesi tidak valid atau telah berakhir.'],401);
    if($resource==='me'&&$method==='GET')respond(['ok'=>true,'user'=>$u,'permissions'=>[
        'admin'=>is_admin($u),'pegawai'=>is_admin($u),
        'pppk'=>permitted('pppk',$u),'pjlp'=>permitted('pjlp',$u),
        'waris'=>permitted('waris',$u),'waris_manage'=>permitted('waris_manage',$u)
    ]]);
    if($resource==='dashboard'&&$method==='GET')respond(['ok'=>true,'data'=>dashboard_data($u)]);
    if($resource==='pegawai'&&$method==='GET'){
        if(!is_admin($u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        respond(['ok'=>true,'data'=>employee_list($u)]);
    }
    if($resource==='pppk'&&$method==='GET'){
        if(!permitted('pppk',$u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        respond(['ok'=>true,'data'=>pppk_list($u)]);
    }
    if($resource==='pjlp'&&$method==='GET'){
        if(!permitted('pjlp',$u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        respond(['ok'=>true,'data'=>pjlp_list($u)]);
    }
    if($resource==='waris'&&$method==='GET'){
        if(!permitted('waris',$u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        respond(['ok'=>true,'data'=>waris_list($u)]);
    }
    if($resource==='waris_detail'&&$method==='GET'){
        if(!permitted('waris',$u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        $r=waris_details($u,(int)($_GET['id']??0));
        if(!$r)respond(['ok'=>false,'error'=>'Data tidak ditemukan.'],404);
        respond(['ok'=>true,'data'=>$r]);
    }
    if($resource==='waris_status'&&$method==='POST'){
        if(!permitted('waris_manage',$u))respond(['ok'=>false,'error'=>'Akses ditolak.'],403);
        waris_change_status($u,(int)($body['id']??0),(string)($body['status']??''),trim((string)($body['notes']??'')));
        respond(['ok'=>true,'message'=>'Status permohonan berhasil diperbarui.']);
    }
    if($resource==='logout'&&$method==='POST'){
        $h=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(preg_match('/^Bearer ([a-f0-9]{64})$/i',$h,$m)) {
            db()->prepare('UPDATE unified_api_tokens SET revoked_at=NOW() WHERE token_hash=?')->execute([hash('sha256',strtolower($m[1]))]);
        }
        respond(['ok'=>true]);
    }
    respond(['ok'=>false,'error'=>'Layanan tidak tersedia.'],404);
} catch(Throwable $e){
    error_log('SmartCikoko API: '.$e->getMessage());
    respond(['ok'=>false,'error'=>'Layanan sedang mengalami kendala.'],503);
}

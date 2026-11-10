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
        if(!$matches){log_event('login_failed','identity',$identity);respond(['ok'=>false,'error'=>'Identitas akun atau kata sandi tidak sesuai.'],401);}
        $source=(string)($body['source']??'');$id=(string)($body['id']??'');
        if($source!==''&&$id!=='')$matches=array_values(array_filter($matches,fn($u)=>$u['source']===$source&&(string)$u['id']===$id));
        if(!$matches)respond(['ok'=>false,'error'=>'Pilihan identitas tidak sesuai.'],401);
        if(count($matches)>1)respond(['ok'=>true,'selection_required'=>true,'accounts'=>array_map(fn($u)=>[
          'id'=>$u['id'],'source'=>$u['source'],'name'=>$u['name'],'role_label'=>role_label($u['role'])
        ],$matches)]);
        clear_login_limit($identity);
        $u=$matches[0];
        $mfaAttested=false;
        $security=dirname(__DIR__,2).'/portal_private/security_stage13.php';
        if(is_file($security)&&is_super_admin_identity($u)){
            require_once $security;
            if(s13_enrolled($u)){
                if(!s13_installed())respond(['ok'=>false,'error'=>'Layanan MFA tidak tersedia.'],503);
                if(!isset($body['otp']) || trim((string)$body['otp'])==='')
                    respond(['ok'=>false,'mfa_required'=>true,'error'=>'Kode autentikator diperlukan.'],401);
                throttle_login($identity.'|api-mfa');
                if(!s13_mfa_verified($u,trim((string)$body['otp']))){
                    log_event('mfa_api_failed','security','login');
                    respond(['ok'=>false,'mfa_required'=>true,'error'=>'Kode MFA tidak sesuai.'],401);
                }
                clear_login_limit($identity.'|api-mfa');
                $mfaAttested=true;
            }
        }
        $token=issue_api_token($u);
        if($mfaAttested){
            try{s13_store_api_attestation($u,$token);}
            catch(Throwable $e){
                db('smartapp')->prepare('DELETE FROM unified_api_tokens WHERE token_hash=?')->execute([hash('sha256',$token)]);
                error_log('Stage 13 API MFA attestation failed.');
                respond(['ok'=>false,'error'=>'Penerbitan token aman gagal.'],503);
            }
        }
        respond(['ok'=>true,'token'=>$token,'expires_in'=>$mfaAttested?43200:2592000,'user'=>array_diff_key($u,['username'=>true])]);
    }
    $u=api_actor();
    if(!$u)respond(['ok'=>false,'error'=>'Sesi tidak valid atau telah berakhir.'],401);
    if($resource==='workflow_feed'&&$method==='GET'){
        require_once dirname(__DIR__,2).'/portal_private/workflow_feed.php';
        respond(['ok'=>true,'data'=>sc_workflow_feed($u)]);
    }
    if(str_starts_with($resource,'citizen_')){
        require_once dirname(__DIR__,2).'/portal_private/citizen_requests.php';
        $citizen=t9_actor($u);
        if(!$citizen)respond(['ok'=>false,'error'=>'Sesi atau hak akses akun tidak valid.'],401);
        try{
            if(in_array($resource,['citizen_notifications','citizen_notice_read','citizen_files','citizen_requirements','citizen_pending'],true)){
                require_once dirname(__DIR__,2).'/portal_private/citizen_followup.php';
                if(!t10_ready())respond(['ok'=>false,'error'=>'Modul tindak lanjut belum terpasang.'],503);
                if($resource==='citizen_notifications'&&$method==='GET')
                    respond(['ok'=>true,'data'=>t10_notifications($citizen)]);
                if($resource==='citizen_notice_read'&&$method==='POST'){
                    t10_read_notification($citizen,(int)($body['id']??0));
                    respond(['ok'=>true]);
                }
                if($resource==='citizen_files'&&$method==='GET')
                    respond(['ok'=>true,'data'=>t10_files($citizen,(int)($_GET['id']??0))]);
                if($resource==='citizen_requirements'&&$method==='GET'){
                    $requestId=(int)($_GET['id']??0);
                    $files=t10_files($citizen,$requestId);
                    respond(['ok'=>true,'data'=>[
                        'requirements'=>t10_requirement_status($files),
                        'files'=>$files,
                        'accepted_extensions'=>['pdf','jpg','jpeg','png'],
                        'max_file_bytes'=>5242880,
                        'notice'=>'Lampiran diunggah secara privat dan diverifikasi petugas. Checklist tidak otomatis menerbitkan surat.',
                    ]]);
                }
                if($resource==='citizen_pending'&&$method==='GET')
                    respond(['ok'=>true,'data'=>t10_pending_counts($citizen)]);
                respond(['ok'=>false,'error'=>'Metode tidak diizinkan.'],405);
            }
            if($resource==='citizen_list'&&$method==='GET'){
                require_once dirname(__DIR__,2).'/portal_private/workflow_progress.php';
                $list=t9_list($citizen,max(1,(int)($_GET['page']??1)));
                foreach($list['rows'] as &$entry)$entry['progress']=sc_workflow_progress('warga',(string)$entry['status']);
                unset($entry);
                respond(['ok'=>true,'data'=>$list]);
            }
            if($resource==='citizen_detail'&&$method==='GET'){
                $id=(int)($_GET['id']??0);
                $case=t9_case($citizen,$id);
                if(!$case)respond(['ok'=>false,'error'=>'Permohonan tidak ditemukan.'],404);
                unset($case['account_id']);
                require_once dirname(__DIR__,2).'/portal_private/workflow_progress.php';
                $case['progress']=sc_workflow_progress('warga',(string)$case['status']);
                respond(['ok'=>true,'data'=>['case'=>$case,'history'=>t9_history($citizen,$id)]]);
            }
            if($resource==='citizen_create'&&$method==='POST'){
                $id=t9_create($citizen,trim((string)($body['deceased_name']??'')),(string)($body['relationship']??''),trim((string)($body['purpose']??'')),(string)($body['request_key']??''));
                respond(['ok'=>true,'id'=>$id],201);
            }
            if($resource==='citizen_edit'&&$method==='POST'){
                t9_edit($citizen,(int)($body['id']??0),(int)($body['version']??0),trim((string)($body['deceased_name']??'')),(string)($body['relationship']??''),trim((string)($body['purpose']??'')));
                respond(['ok'=>true,'message'=>'Draf permohonan diperbarui.']);
            }
            if($resource==='citizen_transition'&&$method==='POST'){
                t9_transition($citizen,(int)($body['id']??0),(int)($body['version']??0),(string)($body['to_status']??''),trim((string)($body['note']??'')));
                respond(['ok'=>true,'message'=>'Tahapan permohonan berhasil diperbarui.']);
            }
            respond(['ok'=>false,'error'=>'Layanan pengajuan tidak ditemukan.'],404);
        }catch(PDOException $e){
            error_log('SmartCikoko citizen API database unavailable: '.$e->getMessage());
            respond(['ok'=>false,'error'=>'Layanan sedang mengalami kendala.'],503);
        }catch(InvalidArgumentException|RuntimeException $e){
            // Never expose database metadata in API error messages.
            error_log('SmartCikoko citizen API rejected request: '.$e->getMessage());
            respond(['ok'=>false,'error'=>'Permintaan tidak dapat diproses. Periksa status dan kelengkapan data.'],422);
        }
    }
    if($resource==='me'&&$method==='GET')respond(['ok'=>true,'user'=>$u,'permissions'=>[
        'admin'=>is_admin($u),'pegawai'=>is_admin($u),
        'pppk'=>permitted('pppk',$u),'pjlp'=>permitted('pjlp',$u),
        'waris'=>permitted('waris',$u),'waris_manage'=>permitted('waris_manage',$u),
        'super_admin'=>is_super_admin($u)
    ]]);
    if($resource==='dashboard'&&$method==='GET')respond(['ok'=>true,'data'=>dashboard_data($u)]);
    if($resource==='superadmin_dashboard'&&$method==='GET'){
        if(!is_super_admin($u))respond(['ok'=>false,'error'=>'Akses Super Admin ditolak.'],403);
        $items=[];
        $progress=super_admin_progress();
        foreach(super_admin_modules() as $key=>$meta){
            $p=$progress[$key]??null;
            $items[]=[
                'key'=>$key,'label'=>$meta['label'],'status'=>$p['status']??'belum',
                'progress_percent'=>(int)($p['progress_percent']??0),
                'notes'=>$p['notes']??'','configured'=>$p!==null
            ];
        }
        respond(['ok'=>true,'data'=>[
            'health'=>super_admin_metrics(),
            'modules'=>$items,
            'progress_configured'=>count($progress)===count(super_admin_modules())
        ]]);
    }
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

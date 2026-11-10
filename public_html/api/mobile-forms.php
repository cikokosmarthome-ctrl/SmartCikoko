<?php
declare(strict_types=1);
/**
 * Mobile endpoint for existing Stage16 and Stage17 modules.
 * Separate from legacy /api/index.php so existing mobile clients are stable.
 */
try {
    require dirname(__DIR__,2).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__,2).'/portal_private/mobile_forms_api.php';
    header('Cache-Control: no-store');
    $actor=api_actor();
    if(!$actor||!mobile_forms_enabled($actor))
        respond(['ok'=>false,'error'=>'Sesi tidak aktif atau hak akses ditolak.'],401);

    $operation=strtolower((string)($_GET['action']??'catalog'));
    $method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
    $input=[];
    if($method==='POST'){
        if(!str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))
            respond(['ok'=>false,'error'=>'Gunakan data JSON.'],415);
        $raw=file_get_contents('php://input',false,null,0,32769);
        if(strlen($raw)>32768)respond(['ok'=>false,'error'=>'Data terlalu besar.'],413);
        $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($input))respond(['ok'=>false,'error'=>'Isi formulir tidak valid.'],400);
    }
    if($operation==='catalog'&&$method==='GET')
        respond(['ok'=>true,'data'=>mobile_form_catalog($actor)]);

    $group=(string)(($method==='GET'?$_GET:$input)['group']??'');
    if($operation==='list'&&$method==='GET'){
        $kind=(string)($_GET['kind']??'');
        respond(['ok'=>true,'data'=>mobile_form_list($actor,$group,$kind,
            max(1,(int)($_GET['page']??1)))]);
    }
    if($operation==='detail'&&$method==='GET'){
        $item=mobile_form_detail($actor,$group,(int)($_GET['id']??0));
        if(!$item)respond(['ok'=>false,'error'=>'Formulir tidak ditemukan.'],404);
        respond(['ok'=>true,'data'=>$item]);
    }
    if($operation==='create'&&$method==='POST'){
        $id=mobile_form_create($actor,$group,(string)($input['kind']??''),
            mobile_form_values($input['values']??null),(string)($input['nonce']??''),
            ($input['submit']??false)===true);
        respond(['ok'=>true,'id'=>$id,'message'=>'Formulir berhasil disimpan.'],201);
    }
    if($operation==='update'&&$method==='POST'){
        mobile_form_update($actor,$group,(int)($input['id']??0),
            (int)($input['version']??0),mobile_form_values($input['values']??null),
            ($input['submit']??false)===true);
        respond(['ok'=>true,'message'=>'Perubahan formulir tersimpan.']);
    }
    if($operation==='review'&&$method==='POST'){
        mobile_form_review($actor,$group,(int)($input['id']??0),
            (string)($input['status']??''),trim((string)($input['note']??'')));
        respond(['ok'=>true,'message'=>'Hasil pemeriksaan tercatat dalam riwayat.']);
    }
    respond(['ok'=>false,'error'=>'Operasi tidak tersedia.'],405);
} catch(JsonException $e){
    respond(['ok'=>false,'error'=>'JSON tidak valid.'],400);
} catch(InvalidArgumentException $e){
    respond(['ok'=>false,'error'=>'Data formulir belum valid. Periksa kolom yang wajib diisi.'],422);
} catch(RuntimeException $e){
    error_log('SmartCikoko mobile forms rejected: '.get_class($e));
    respond(['ok'=>false,'error'=>'Formulir tidak tersedia untuk akun atau status saat ini.'],403);
} catch(Throwable $e){
    error_log('SmartCikoko mobile forms unavailable: '.get_class($e));
    respond(['ok'=>false,'error'=>'Layanan formulir sedang tidak tersedia.'],503);
}

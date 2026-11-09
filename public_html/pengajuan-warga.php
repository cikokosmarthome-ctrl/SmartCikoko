<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/citizen_requests.php';
    $actor=t9_actor(actor());
    if(!$actor){header('Location: /?page=login',true,303);exit;}
    if(!t9_is_citizen($actor) && !t9_can_monitor($actor)){http_response_code(403);exit('Akun tidak memiliki kewenangan untuk layanan ini.');}
}catch(Throwable $e){http_response_code(503);exit('Layanan pengajuan sementara belum tersedia.');}
function req_url(int $id=0,int $page=1):string {
    $q=['hal'=>$page];if($id>0)$q['id']=$id;
    return '/pengajuan-warga.php?'.http_build_query($q,'','&',PHP_QUERY_RFC3986);
}
function req_transitions(array $actor,array $case):array {
    $role=t9_is_citizen($actor)?'warga':t9_staff_role($actor);
    $status=$case['status'];
    if($role==='warga' && in_array($status,['draf','revisi'],true))return ['diajukan'=>'Ajukan untuk Pemeriksaan'];
    if($role==='ptsp'){
        if($status==='diajukan')return ['ditinjau'=>'Mulai Pemeriksaan'];
        if($status==='ditinjau')return ['revisi'=>'Minta Kelengkapan','diverifikasi'=>'Verifikasi Administratif'];
    }
    if($role==='lurah' && $status==='diverifikasi')
        return ['disetujui'=>'Berikan Persetujuan','ditolak'=>'Tolak dengan Alasan','revisi'=>'Kembalikan untuk Perbaikan'];
    return [];
}
$notice=(string)($_SESSION['t9_notice']??'');unset($_SESSION['t9_notice']);
$error='';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT,['options'=>['default'=>0,'min_range'=>1]])?:0;
$page=filter_var($_GET['hal']??1,FILTER_VALIDATE_INT,['options'=>['default'=>1,'min_range'=>1]])?:1;
if(!isset($_SESSION['t9_create_nonce']))$_SESSION['t9_create_nonce']=bin2hex(random_bytes(16));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if($action==='create'){
            $id=t9_create($actor,trim((string)($_POST['deceased_name']??'')),(string)($_POST['relationship']??''),trim((string)($_POST['purpose']??'')),(string)($_POST['request_key']??''));
            $_SESSION['t9_create_nonce']=bin2hex(random_bytes(16));
            $_SESSION['t9_notice']='Draf permohonan berhasil dibuat. Periksa kembali informasi sebelum diajukan.';
        } elseif($action==='edit'){
            $id=(int)($_POST['id']??0);
            t9_edit($actor,$id,(int)($_POST['version']??0),trim((string)($_POST['deceased_name']??'')),(string)($_POST['relationship']??''),trim((string)($_POST['purpose']??'')));
            $_SESSION['t9_notice']='Informasi draf berhasil diperbarui.';
        } elseif($action==='transition'){
            if(empty($_POST['confirmed']))throw new InvalidArgumentException('Konfirmasi tindakan wajib diberikan.');
            $id=(int)($_POST['id']??0);
            t9_transition($actor,$id,(int)($_POST['version']??0),(string)($_POST['to_status']??''),trim((string)($_POST['note']??'')));
            $_SESSION['t9_notice']='Tahap pelayanan berhasil diperbarui dan dicatat dalam riwayat.';
        } else throw new InvalidArgumentException('Tindakan tidak dikenal.');
        header('Location: '.req_url($id),true,303);exit;
    }catch(Throwable $e){
        error_log('SmartCikoko citizen workflow: '.$e->getMessage());
        $error=$e instanceof InvalidArgumentException || $e instanceof RuntimeException
             ? $e->getMessage():'Tindakan tidak dapat diproses. Periksa data dan coba kembali.';
    }
}
$case=null;$history=[];
try{
    $list=t9_list($actor,$page);
    if($id>0){
        $case=t9_case($actor,$id);
        if($case)$history=t9_history($actor,$id);
    }
}catch(Throwable $e){
    error_log('SmartCikoko citizen workflow list failed: '.$e->getMessage());
    $list=['total'=>0,'rows'=>[],'page'=>1,'pages'=>1];
    $error='Daftar permohonan belum tersedia. Pastikan instalasi Tahap 9 sudah selesai.';
}
$role=t9_is_citizen($actor)?'warga':(t9_staff_role($actor)?:'superadmin');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3557">
<title>Pengajuan dan Pelayanan Terpadu | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/pengajuan-warga.css"></head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap navinner">
<a href="/" class="brand"><span class="logo">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav><a href="/">Beranda</a><a href="/?page=dashboard">Ruang Kerja</a><a class="selected" href="/pengajuan-warga.php">Pengajuan Pelayanan</a>
<?php if(is_super_admin($actor)):?><a href="/super-admin.php">Pusat Super Admin</a><?php endif;?></nav>
</div></header>
<section class="hero"><div class="wrap hero-inner"><div><span class="kicker">LAYANAN DIGITAL KELURAHAN CIKOKO</span>
<h1><?=h(t9_is_citizen($actor)?'Pengajuan Pelayanan Masyarakat':'Antrean dan Persetujuan Pelayanan')?></h1>
<p><?=h(t9_is_citizen($actor)?'Sampaikan permohonan secara tertib dan pantau setiap tahapan pemeriksaan melalui akun Anda.':'Pemrosesan permohonan dilakukan sesuai kewenangan dan tahapan pelayanan yang telah ditetapkan.')?></p>
</div><div class="account"><span class="avatar"><?=h(mb_strtoupper(mb_substr($actor['name'],0,1)))?></span><span><small>IDENTITAS TEROTORISASI</small><strong><?=h($actor['name'])?></strong><em><?=h(t9_roles()[$role]??'Super Administrator')?></em></span></div></div></section>
<main class="wrap content">
<?php if($notice):?><p class="alert success"><?=h($notice)?></p><?php endif;?>
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php endif;?>
<div class="summary"><div><small>TOTAL PERMOHONAN <?=t9_is_citizen($actor)?'ANDA':'TERCATAT'?></small><strong><?=number_format((int)$list['total'],0,',','.')?></strong></div>
<div><small>JALUR PELAYANAN</small><strong>3 Tahap</strong><span>Pengajuan · Pemeriksaan PTSP · Keputusan Lurah</span></div></div>
<div class="main-grid">
<section class="panel">
<div class="panel-head"><span class="eyebrow"><?=t9_is_citizen($actor)?'LAYANAN AKUN MASYARAKAT':'DAFTAR PENGAJUAN TERPADU'?></span><h2>Daftar Permohonan</h2><p><?=t9_is_citizen($actor)?'Hanya pengajuan milik akun Anda yang ditampilkan.':'Daftar pengajuan baru Tahap 9, terpisah dari arsip pelayanan lama.'?></p></div>
<?php if(t9_is_citizen($actor)):?>
<details class="new-form"><summary>Buat Permohonan Baru</summary>
<form method="post" action="/pengajuan-warga.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="create">
<input type="hidden" name="request_key" value="<?=h($_SESSION['t9_create_nonce'])?>">
<label>Nama Almarhum/Almarhumah<input name="deceased_name" maxlength="160" minlength="3" required placeholder="Nama lengkap sesuai dokumen pendukung"></label>
<label>Hubungan Keluarga<select name="relationship" required><option value="">Pilih hubungan</option>
<?php foreach(t9_relationships() as $value=>$label):?><option value="<?=h($value)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<label>Keterangan/Keperluan (opsional)<textarea name="purpose" maxlength="500" rows="3" placeholder="Ringkasan keperluan permohonan"></textarea></label>
<p class="help">Pengajuan diawali sebagai draf. Tidak perlu memasukkan NIK atau mengunggah dokumen pribadi pada formulir ini.</p>
<button class="button primary" type="submit">Simpan Draf Permohonan</button>
</form></details>
<?php endif;?>
<div class="request-list">
<?php if(!$list['rows']):?><p class="empty">Belum ada permohonan untuk ditampilkan.</p><?php endif;?>
<?php foreach($list['rows'] as $item):?>
<a class="request <?=((int)$item['id']===$id)?'current':''?>" href="<?=h(req_url((int)$item['id'],$page))?>">
<span><b><?=h($item['reference_no'])?></b><small><?=h($item['applicant_name'])?></small><small><?=h($item['created_at'])?> WIB</small></span>
<span class="pill"><?=h(t9_stages()[$item['status']]??$item['status'])?></span>
</a><?php endforeach;?></div>
<div class="pagination">
<?php if($list['page']>1):?><a href="<?=h(req_url(0,$list['page']-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($list['page']<$list['pages']):?><a href="<?=h(req_url(0,$list['page']+1))?>">Berikutnya →</a><?php endif;?>
</div></section>
<section class="panel detail">
<?php if(!$case):?>
<div class="placeholder"><span>▤</span><h2>Pilih Permohonan</h2><p>Pilih nomor permohonan untuk melihat rincian, riwayat, dan langkah yang tersedia sesuai kewenangan.</p></div>
<?php else:?>
<span class="eyebrow">RINCIAN PELAYANAN</span><h2><?=h($case['reference_no'])?></h2>
<div class="case-status"><?=h(t9_stages()[$case['status']]??$case['status'])?></div>
<dl class="facts"><dt>Pemohon</dt><dd><?=h($case['applicant_name'])?></dd><dt>Nama Almarhum/Almarhumah</dt><dd><?=h($case['deceased_name'])?></dd>
<dt>Hubungan</dt><dd><?=h(t9_relationships()[$case['relationship']]??$case['relationship'])?></dd>
<dt>Keperluan</dt><dd><?=h($case['purpose']?:'Tidak ada keterangan tambahan.')?></dd></dl>
<?php if(t9_is_citizen($actor) && in_array($case['status'],['draf','revisi'],true)):?>
<details class="edit-form"><summary>Perbarui Keterangan Permohonan</summary>
<form method="post" action="<?=h(req_url($id))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="edit">
<input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="version" value="<?=(int)$case['version']?>">
<label>Nama Almarhum/Almarhumah<input name="deceased_name" maxlength="160" minlength="3" required value="<?=h($case['deceased_name'])?>"></label>
<label>Hubungan Keluarga<select name="relationship"><?php foreach(t9_relationships() as $v=>$l):?><option value="<?=h($v)?>" <?=$case['relationship']===$v?'selected':''?>><?=h($l)?></option><?php endforeach;?></select></label>
<label>Keterangan<textarea name="purpose" maxlength="500" rows="3"><?=h($case['purpose'])?></textarea></label>
<button class="button primary" type="submit">Simpan Perubahan</button></form></details>
<?php endif;?>
<?php $actions=req_transitions($actor,$case);if($actions):?>
<div class="review"><h3>Langkah Selanjutnya</h3>
<form method="post" action="<?=h(req_url($id))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="transition">
<input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="version" value="<?=(int)$case['version']?>">
<label>Keputusan / Tahap<select name="to_status" required>
<?php foreach($actions as $value=>$label):?><option value="<?=h($value)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<?php if(!t9_is_citizen($actor)):?>
<label>Catatan Pemeriksaan / Alasan<textarea name="note" maxlength="500" rows="3" placeholder="Wajib diisi untuk revisi atau penolakan"></textarea></label>
<?php else:?><input type="hidden" name="note" value="Permohonan diajukan oleh pemohon."><?php endif;?>
<label class="confirm"><input type="checkbox" name="confirmed" value="1" required>Saya telah memeriksa informasi dan memahami bahwa tindakan ini akan dicatat.</label>
<button class="button primary" type="submit">Simpan dan Lanjutkan</button>
</form></div>
<?php endif;?>
<h3 class="subheading">Riwayat Proses</h3><div class="timeline">
<?php foreach($history as $event):?><div><small><?=h($event['created_at'])?> WIB</small><b><?=h(t9_stages()[$event['to_status']]??'Pengajuan dibuat')?></b><p><?=h($event['note'])?></p></div><?php endforeach;?>
<?php if(!$history):?><p class="empty">Belum tersedia riwayat.</p><?php endif;?></div>
<?php endif;?>
</section></div>
<div class="notice"><strong>Ketentuan Pengelolaan</strong><p>Pengajuan baru dicatat pada sistem Tahap 9 yang terpisah dari database pengajuan lama. Keputusan pejabat di portal ini merupakan status alur kerja internal dan belum menghasilkan surat resmi yang ditandatangani secara elektronik. Dokumen pendukung dari Tahap 8 belum terhubung otomatis dengan permohonan baru; verifikasi akhir harus mengikuti prosedur kelurahan yang berlaku.</p></div>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pengajuan dan Pelayanan Masyarakat</div></footer></body></html>
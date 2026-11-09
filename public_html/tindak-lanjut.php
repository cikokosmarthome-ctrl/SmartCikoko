<?php
declare(strict_types=1);
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/citizen_requests.php';
    require_once dirname(__DIR__).'/portal_private/citizen_followup.php';
    $actor=t9_actor(actor());
    if(!$actor){header('Location: /?page=login',true,303);exit;}
    if(!t9_is_citizen($actor) && !t9_can_monitor($actor)){http_response_code(403);exit('Akses ditolak.');}
}catch(Throwable $e){http_response_code(503);exit('Pusat tindak lanjut tidak tersedia.');}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT)?:0;
$error='';$notice=(string)($_SESSION['t10_notice']??'');unset($_SESSION['t10_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['action']??'');
        if($action==='upload'){
            $id=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT)?:0;
            if(empty($_POST['confirmed']))throw new InvalidArgumentException('Konfirmasi wajib diberikan.');
            t10_upload($actor,$id,(string)($_POST['doc_type']??''),$_FILES['document']??[]);
            $_SESSION['t10_notice']='Berkas tambahan tersimpan secara privat. Pemeriksaan petugas tetap diperlukan.';
        }elseif($action==='read'){
            t10_read_notification($actor,(int)($_POST['notification_id']??0));
            $_SESSION['t10_notice']='Pemberitahuan sudah ditandai dibaca.';
        }else throw new InvalidArgumentException('Tindakan tidak tersedia.');
        header('Location: /tindak-lanjut.php'.($id>0?'?id='.$id:''),true,303);exit;
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 10 form failed: '.$e->getMessage());
        $error='Tindakan belum berhasil. Periksa format berkas, tahap permohonan, dan izin penyimpanan.';
    }
}
$case=null;$files=[];$notes=[];$list=[];$inbox=[];
try{
    $list=t9_list($actor,1)['rows'];
    if($id>0){
        $case=t10_actor_case($actor,$id);
        $files=t10_files($actor,$id);
    }
    if(t9_is_citizen($actor))$inbox=t10_notifications($actor);
    else $notes=t10_pending_counts($actor);
}catch(Throwable $e){error_log('SmartCikoko Stage 10 list failed: '.$e->getMessage());$error='Data belum tersedia. Jalankan instalasi Tahap 10 lebih dahulu.';}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d3557">
<title>Tindak Lanjut Pelayanan | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/tindak-lanjut.css">
</head><body>
<div class="topline"><div class="wrap">PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA · KECAMATAN PANCORAN</div></div>
<header class="nav"><div class="wrap nav-inner">
<a class="brand" href="/"><span class="logo">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav><a href="/">Beranda</a><a href="/pengajuan-warga.php">Pengajuan</a><a class="active" href="/tindak-lanjut.php">Tindak Lanjut</a>
<?php if(is_super_admin($actor)):?><a href="/super-admin.php">Pusat Super Admin</a><?php endif;?></nav>
</div></header>
<section class="hero"><div class="wrap hero-inner"><div><small>PELAYANAN DIGITAL YANG TERINTEGRASI</small><h1>Dokumen dan Tindak Lanjut</h1>
<p>Memantau informasi pengajuan, mengelola lampiran secara privat, dan membaca pemberitahuan perubahan status pelayanan.</p></div>
<div class="identity"><span><?=h(mb_strtoupper(mb_substr((string)$actor['name'],0,1)))?></span><div><small>AKUN TEROTORISASI</small><strong><?=h($actor['name'])?></strong></div></div></div></section>
<main class="wrap content">
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php endif;?>
<?php if($notice):?><p class="alert success"><?=h($notice)?></p><?php endif;?>
<div class="columns"><section class="panel"><span class="eyebrow">DAFTAR PENGAJUAN</span><h2>Pilih Permohonan</h2>
<p class="muted">Daftar 15 pengajuan terbaru. Untuk halaman berikutnya buka menu Pengajuan.</p>
<?php if(!$list):?><p class="muted">Belum ada pengajuan.</p><?php endif;?>
<?php foreach($list as $item):?><a class="case <?=$id===(int)$item['id']?'selected':''?>" href="/tindak-lanjut.php?id=<?=(int)$item['id']?>">
<span><strong><?=h($item['reference_no'])?></strong><small><?=h($item['applicant_name'])?></small></span><em><?=h(t9_stages()[$item['status']]??$item['status'])?></em>
</a><?php endforeach;?>
<?php if(!t9_is_citizen($actor)):?>
<div class="queue"><h3>Ringkasan Antrean</h3>
<?php foreach($notes as $state=>$count):?><div><span><?=h(t9_stages()[$state]??$state)?></span><strong><?=(int)$count?></strong></div><?php endforeach;?>
<p class="muted">Status diperoleh langsung dari permohonan Tahap 9.</p></div>
<?php endif;?>
</section><section class="panel">
<?php if(!$case):?><div class="empty"><span>▤</span><h2>Pilih Pengajuan</h2><p>Pilih nomor pengajuan untuk memeriksa lampiran serta mengunduh bukti informasi.</p></div>
<?php else:?>
<span class="eyebrow">RINCIAN PERMOHONAN</span><h2><?=h($case['reference_no'])?></h2>
<div class="facts"><div><small>PEMOHON</small><strong><?=h($case['applicant_name'])?></strong></div><div><small>STATUS</small><strong><?=h(t9_stages()[$case['status']]??$case['status'])?></strong></div></div>
<a class="button outline" href="/bukti-pengajuan.php?id=<?=$id?>" target="_blank" rel="noopener">Cetak Bukti Informasi ↗</a>
<h3>Lampiran yang Tersimpan</h3>
<p class="muted">Lampiran dapat diakses oleh pemohon sendiri dan petugas berwenang. Tidak dibagikan melalui URL penyimpanan publik.</p>
<?php if(!$files):?><p class="muted">Belum ada lampiran pada pengajuan ini.</p><?php endif;?>
<?php foreach($files as $file):?>
<div class="file"><span><strong><?=h(t10_types()[$file['doc_type']]??$file['doc_type'])?></strong><small><?=h($file['original_name'])?> · <?=number_format((int)$file['file_size']/1024,1,',','.')?> KB</small></span>
<a href="/unduh-pengajuan.php?id=<?=(int)$file['id']?>">Unduh ↗</a></div>
<?php endforeach;?>
<?php if(t9_is_citizen($actor) && in_array($case['status'],['draf','revisi'],true)):?>
<details class="form-details"><summary>Tambahkan Lampiran (Draf / Revisi)</summary>
<form method="post" action="/tindak-lanjut.php?id=<?=$id?>" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="upload">
<input type="hidden" name="request_id" value="<?=$id?>">
<label>Jenis Berkas<select name="doc_type" required><?php foreach(t10_types() as $code=>$label):?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<label>Pilih berkas PDF/JPG/PNG, maksimum 5 MB<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label>
<label class="confirm"><input type="checkbox" name="confirmed" value="1" required>Saya berwenang memberikan lampiran ini untuk diproses dalam permohonan.</label>
<button type="submit" class="button primary">Simpan Lampiran</button>
</form></details>
<?php elseif(t9_is_citizen($actor)):?><p class="muted">Unggahan ditutup selama pemeriksaan. Perbaikan dokumen dapat dilakukan ketika status kembali menjadi Perlu Perbaikan.</p><?php endif;?>
<?php endif;?>
</section></div>
<?php if(t9_is_citizen($actor)):?>
<section class="panel inbox"><span class="eyebrow">PEMBERITAHUAN DALAM PORTAL</span><h2>Pembaruan Status</h2>
<p class="muted">Pemberitahuan ini hanya muncul di akun Anda; belum dikirim melalui email, WhatsApp, atau push notification.</p>
<?php if(!$inbox):?><p class="muted">Belum ada pemberitahuan.</p><?php endif;?>
<?php foreach($inbox as $n):?>
<div class="notice-row"><div><strong><?=h($n['message'])?></strong><small><?=h($n['created_at'])?> WIB · Pengajuan #<?=(int)$n['request_id']?></small></div>
<?php if($n['read_at']===null):?>
<form method="post" action="/tindak-lanjut.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="read">
<input type="hidden" name="notification_id" value="<?=(int)$n['id']?>">
<button class="button outline" type="submit">Tandai Dibaca</button></form>
<?php else:?><span class="read">Sudah dibaca</span><?php endif;?></div>
<?php endforeach;?></section>
<?php endif;?>
<div class="caution"><strong>Perlindungan Informasi</strong><p>Lampiran disimpan di direktori privat di luar <code>public_html</code>. Kode memeriksa tipe dan ukuran berkas, tetapi **belum melakukan pemindaian malware**. Gunakan dokumen uji tanpa data pribadi sampai verifikasi keamanan, retensi data, dan pengendalian akses dinyatakan memadai.</p></div>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Tindak Lanjut Pelayanan</div></footer>
</body></html>
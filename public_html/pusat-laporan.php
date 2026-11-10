<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/unified_reports.php';
    $u=requires_login();
    $u=lr_actor($u);
    if(!$u){http_response_code(403);exit('Akun tidak diizinkan membuka pusat laporan.');}
}catch(Throwable $e){http_response_code(503);exit('Pusat laporan belum tersedia.');}
$manager=lr_is_manager($u);
$catalog=lr_categories();
$category=(string)($_GET['kategori']??'');
if(!isset($catalog[$category]))$category='';
$selected=$category!==''?$category:'umum';
$allowed=lr_can_submit($u,$selected);
$notice=(string)($_SESSION['lr_notice']??'');unset($_SESSION['lr_notice']);
$error='';
$ready=lr_ready();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Instalasi Pusat Laporan belum dilakukan.');
        $action=(string)($_POST['action']??'');
        if($action==='submit'){
            $key=(string)($_POST['category']??'');
            $id=lr_submit($u,$key,trim((string)($_POST['title']??'')),trim((string)($_POST['body']??'')),
                trim((string)($_POST['location']??'')),(string)($_POST['activity_date']??''),
                (string)($_POST['end_date']??''),(string)($_POST['request_key']??''));
            $_SESSION['lr_notice']='Laporan #'.$id.' tercatat sebagai laporan terpusat. Jika jenis laporan memiliki proses lama, laporan ini belum menggantikannya.';
        }elseif($action==='review' && $manager){
            lr_review($u,(int)($_POST['report_id']??0),(string)($_POST['status']??''),trim((string)($_POST['note']??'')));
            $_SESSION['lr_notice']='Tindak lanjut laporan tersimpan dalam audit.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        header('Location: /pusat-laporan.php?'.http_build_query(['kategori'=>(string)($_POST['category']??'')]),true,303);exit;
    }catch(Throwable $e){
        error_log('Unified report request failed: '.get_class($e));
        $error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Pencatatan belum berhasil. Periksa hak akses dan sambungan database.';
    }
}
$page=max(1,min(10000,filter_var($_GET['hal']??1,FILTER_VALIDATE_INT)?:1));
$items=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
$native=[];$counts=[];
if($ready)try{$items=lr_query($u,$category,$page);if($manager){$native=lr_native_counts();$counts=lr_new_counts();}}
catch(Throwable $e){error_log('Unified report list failed.');$error='Daftar laporan belum dapat dimuat. Periksa tabel SmartApp.';}
$nonce=bin2hex(random_bytes(16));
function lr_url(string $cat='',int $page=1):string {
    return '/pusat-laporan.php?'.http_build_query(['kategori'=>$cat,'hal'=>$page],'','&',PHP_QUERY_RFC3986);
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3154">
<title>Pusat Input Laporan | SmartCikoko</title>
<link rel="stylesheet" href="/assets/pusat-laporan.css"></head>
<body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="top"><div class="wrap bar">
<a href="/" class="brand"><span class="crest">KC</span><span><b>KELURAHAN CIKOKO</b><small>Portal Administrasi Terpadu</small></span></a>
<nav><a href="/">Beranda</a><a href="/?page=dashboard">Ruang Kerja</a><a class="active" href="/pusat-laporan.php">Input Laporan</a><?php if($manager):?><a href="/super-admin-satu.php">Super Admin Ringkas</a><?php endif;?></nav>
</div></header>
<section class="hero"><div class="wrap hero-row"><div><small>PUSAT INPUT TERPADU</small><h1>Satu Pintu Pelaporan</h1><p>Input laporan, pantau perkembangan, dan lihat jalur layanan lama tanpa menggabungkan tabel sumber. Berkas pribadi dan tanda tangan resmi tidak diunggah pada formulir ringkas ini.</p></div><div class="actor"><span><?=h(mb_strtoupper(mb_substr((string)$u['name'],0,1)))?></span><div><strong><?=h($u['name'])?></strong><small><?=h(strtoupper($u['role']))?></small></div></div></div></section>
<main class="wrap content">
<?php if($notice):?><div class="alert success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert bad"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="alert bad">Database laporan tambahan belum disiapkan. Super Admin perlu menjalankan installer satu tombol Tahap 15. Tidak perlu mengimpor SQL lama.</div><?php endif;?>
<section class="intro">
<div><small class="eyebrow">PILIH MODUL</small><h2>Jenis Laporan dan Layanan</h2><p>Laporan dalam portal ini merupakan pengajuan/ringkasan baru. Data resmi pada website sebelumnya tetap tersimpan pada modul lama.</p></div>
</section>
<div class="category-grid">
<?php foreach($catalog as $key=>$mod):
  $isAllowed=lr_can_submit($u,$key);
  $nativeValue=$native[$key]??null;
?>
<a class="category <?=$selected===$key?'selected':''?>" href="<?=h(lr_url($key))?>">
<span class="category-title"><?=h($mod['label'])?></span>
<span class="category-sub"><?=$key==='waris'?'Alur Pelayanan Lama':($isAllowed?'Bisa mengirim':'Akses sesuai peran')?></span>
<?php if($manager):?><span class="category-meta">Lama: <?=$nativeValue===null?'—':number_format($nativeValue,0,',','.')?> · Baru: <?=(int)($counts[$key]??0)?></span><?php endif;?>
</a>
<?php endforeach;?>
</div>
<div class="cols">
<section class="panel" id="form">
<small class="eyebrow">PENGINPUTAN</small>
<h2><?=h($catalog[$selected]['label'])?></h2>
<p class="muted"><?=h($catalog[$selected]['hint'])?></p>
<?php if($selected==='waris'):?>
<div class="subtle"><strong>Pengajuan Ahli Waris</strong><p>Gunakan formulir dan alur status Ahli Waris yang sudah ada. Sistem tidak membuat permohonan duplikat melalui input laporan umum.</p>
<a class="btn primary" href="/pengajuan-warga.php">Buka Pengajuan Warga →</a>
<?php if($manager):?><a class="btn outline" href="/operasional.php?modul=waris">Tinjau Data Lama →</a><?php endif;?></div>
<?php elseif(!$allowed):?>
<div class="subtle"><strong>Akses peran dibatasi.</strong><p>Formulir ini hanya untuk akun sesuai kewenangan. Super Admin dapat memantau seluruh laporan, tetapi tidak membuat laporan atas nama petugas lain.</p></div>
<?php elseif($ready):?>
<form class="intake" method="post" action="/pusat-laporan.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="action" value="submit">
<input type="hidden" name="category" value="<?=h($selected)?>">
<input type="hidden" name="request_key" value="<?=h($nonce)?>">
<label>Judul Kegiatan / Jenis Permohonan<input name="title" maxlength="150" minlength="5" required placeholder="<?=$selected==='cuti'?'Contoh: Permohonan Cuti Tahunan':'Contoh: Kegiatan monitoring wilayah'?>"></label>
<div class="two"><label><?=$selected==='cuti'?'Tanggal Mulai Cuti':'Tanggal Kegiatan'?><input type="date" name="activity_date" required value="<?=h(date('Y-m-d'))?>"></label>
<label><?=$selected==='cuti'?'Tanggal Selesai Cuti':'Tanggal Selesai (opsional)'?><input type="date" name="end_date" <?=$selected==='cuti'?'required':''?>></label></div>
<label>Lokasi / Wilayah Kegiatan<input name="location" maxlength="180" <?=$selected==='cuti'?'':'required'?> placeholder="Lokasi, RT/RW, atau wilayah kerja; tanpa NIK"></label>
<label>Uraian / Alasan Pengajuan<textarea name="body" minlength="15" maxlength="3000" rows="5" required placeholder="Isi kegiatan, hasil, kendala, atau alasan pengajuan. Jangan cantumkan NIK dan password."></textarea></label>
<div class="alert neutral">Data disimpan sebagai <b>laporan tambahan dengan status Masuk</b>. Ini belum menjadi laporan PPPK/PJLP resmi, keputusan cuti, ataupun surat RT/RW yang disahkan.</div>
<button class="btn primary" type="submit">Kirim Laporan Ringkas →</button>
</form>
<?php else:?><p class="muted">Form belum tersedia sampai installer dijalankan.</p><?php endif;?>
</section>
<section class="panel" id="history">
<div class="panel-head"><div><small class="eyebrow">MONITORING</small><h2><?=$manager?'Laporan Terbaru Seluruh Akun':'Riwayat Laporan Saya'?></h2></div><b class="count"><?=(int)$items['total']?> laporan</b></div>
<p class="muted">Data baru di pusat laporan ini terpisah dari arsip laporan lama.</p>
<?php if(!$items['rows']):?><div class="empty">Belum ada laporan untuk filter ini.</div><?php endif;?>
<?php foreach($items['rows'] as $item):?>
<details class="entry"><summary><span><b><?=h($item['title'])?></b><small><?=h($catalog[$item['category']]['label']??$item['category'])?> · <?=h($item['activity_date'])?></small></span><span class="status"><?=h(lr_stages()[$item['status']]??$item['status'])?></span></summary>
<div class="entry-body"><p><?=nl2br(h($item['body']))?></p><p class="muted">Wilayah: <?=h($item['location']?:'—')?> · Tanggal selesai: <?=h($item['end_date']?:'—')?></p>
<?php if($manager):?>
<form class="review" method="post" action="/pusat-laporan.php?<?=h(http_build_query(['kategori'=>$category]))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="review">
<input type="hidden" name="report_id" value="<?=(int)$item['id']?>">
<input type="hidden" name="category" value="<?=h($category)?>">
<label>Tindak Lanjut<select name="status" required><?php foreach(lr_stages() as $code=>$label):?><option value="<?=h($code)?>" <?=$item['status']===$code?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
<label>Catatan untuk riwayat<textarea name="note" maxlength="500" rows="2" placeholder="Alasan bila meminta perbaikan"></textarea></label>
<button type="submit" class="btn outline">Simpan Tindak Lanjut</button>
</form>
<?php endif;?></div></details>
<?php endforeach;?>
<div class="pages">
<?php if($items['page']>1):?><a href="<?=h(lr_url($category,$items['page']-1))?>">← Sebelumnya</a><?php endif;?>
<span>Halaman <?=$items['page']?> dari <?=$items['pages']?></span>
<?php if($items['page']<$items['pages']):?><a href="<?=h(lr_url($category,$items['page']+1))?>">Berikutnya →</a><?php endif;?>
</div>
</section>
</div>
<details class="legacy"><summary><strong>Modul Asli dan Pengelolaan Lanjutan</strong><span>Lihat pilihan layanan sebelumnya</span></summary>
<div class="legacy-links">
<a href="/?page=pppk">Laporan PPPK Lama</a><a href="/?page=pjlp">Laporan PJLP Lama</a>
<a href="/?page=waris">Ahli Waris Lama</a><a href="/pengajuan-warga.php">Pengajuan Warga</a>
<?php if($manager):?><a href="/operasional.php">Pengawasan Operasional</a><a href="/verifikasi-layanan.php">Verifikasi Dokumen</a><?php endif;?>
</div></details>
<p class="disclaimer">Keterangan: Tidak semua fungsi di website sebelumnya sudah menjadi formulir baru. Kategori “ringkas” tidak otomatis menggantikan bukti foto, GPS, verifikasi lapangan, tata naskah dinas, maupun proses persetujuan pada aplikasi lama.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Input Laporan Terpadu</div></footer>
</body></html>
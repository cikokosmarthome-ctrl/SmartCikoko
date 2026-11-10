<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/evidence18.php';
    require_once dirname(__DIR__).'/portal_private/recap18.php';
    $u=requires_login();
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($u)){http_response_code(403);exit('Akun belum diizinkan membuka bukti kegiatan.');}
}catch(Throwable $e){http_response_code(503);exit('Fitur bukti belum tersedia.');}
$source=(string)($_GET['source']??'');
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
$ready=e18_ready();$scanning=e18_scan_ready();
$selected=null;$files=[];$error='';
$notice=(string)($_SESSION['e18_notice']??'');unset($_SESSION['e18_notice']);
if($source!==''&&$id&&isset(e18_sources()[$source])){
    try{$selected=e18_report($u,$source,(int)$id);if($ready)$files=e18_list($u,$source,(int)$id);}
    catch(Throwable $e){http_response_code(404);$error='Laporan tidak ditemukan atau akses tidak diizinkan.';}
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Penyimpanan bukti belum dipasang.');
        if(!$selected)throw new RuntimeException('Laporan yang dipilih tidak ditemukan.');
        if(($_POST['consent']??'')!=='1')throw new RuntimeException('Konfirmasi perlindungan foto wajib diisi.');
        if(!isset($_FILES['bukti'])||!is_array($_FILES['bukti']))
            throw new RuntimeException('Pilih satu foto kegiatan.');
        $newId=e18_upload($u,$source,(int)$id,$_FILES['bukti']);
        $_SESSION['e18_notice']='Foto #'.$newId.' berhasil dipindai, disanitasi, dan disimpan di folder privat.';
        header('Location: /bukti-laporan18.php?'.http_build_query(['source'=>$source,'id'=>(int)$id]),true,303);exit;
    }catch(Throwable $e){
        error_log('Stage18 evidence upload blocked: '.get_class($e));
        $error=($e instanceof RuntimeException||$e instanceof InvalidArgumentException)
            ?$e->getMessage():'Unggahan ditolak demi keamanan. Periksa konfigurasi antivirus.';
    }
}
$recent=e18_recent_reports($u);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3251">
<title>Bukti Foto Laporan | SmartCikoko</title><link rel="stylesheet" href="/assets/bukti18.css"></head><body>
<div class="gov"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a class="brand" href="/"><span>KC</span><b>SMARTCIKOKO<small>Bukti Kegiatan Terlindungi</small></b></a><nav>
<a href="/pusat-laporan.php">Pusat Laporan</a><a href="/formulir-pegawai16.php">Formulir Pegawai</a><a href="/laporan-wilayah17.php">Formulir Wilayah</a>
<?php if(is_super_admin($u)):?><a href="/super-admin-satu.php#bukti-dan-rekap">Super Admin Ringkas</a><?php endif;?>
</nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 18 · LAMPIRAN FOTO TERLINDUNGI</small><h1>Bukti Kegiatan &amp; Pemeriksaan</h1>
<p>Foto JPEG/PNG saja. Bukti dipindai antivirus dan diolah kembali untuk membuang metadata lokasi sebelum disimpan di luar direktori publik.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert bad"><?=h($error)?></div><?php endif;?>
<div class="signals"><span>Database lampiran: <b><?=$ready?'Siap':'Belum diinstal'?></b></span><span>Konfigurasi ClamAV + pengolahan gambar: <b><?=$scanning?'Tersedia untuk pengujian':'Belum siap'?></b></span></div>
<div class="columns"><section class="panel">
<h2>Laporan yang Dapat Dilampiri</h2><p class="sub">Lampiran hanya untuk laporan PPPK/PJLP dan wilayah Tahap 16–17 yang sudah dikirim. Form Cuti, Ahli Waris, dan laporan warga beridentitas tidak menerima dokumen pada tahap ini.</p>
<?php if(!$recent):?><p class="empty">Tidak ditemukan laporan yang bisa diperiksa dari akun ini. Buat dan kirim laporan Tahap 16/17 terlebih dahulu menggunakan akun yang sesuai.</p><?php endif;?>
<div class="reports">
<?php foreach($recent as $r):?>
<a class="report <?=($source===$r['source']&&(int)$id===(int)$r['id'])?'on':''?>" href="/bukti-laporan18.php?source=<?=h(rawurlencode($r['source']))?>&amp;id=<?=(int)$r['id']?>">
<span><b><?=h($r['source']==='pegawai16'?'Kepegawaian':'Kewilayahan')?> · #<?=(int)$r['id']?></b><small><?=h(strtoupper($r['kind']))?> · <?=h($r['created_at'])?> WIB</small></span><i><?=h($r['status'])?></i></a>
<?php endforeach;?>
</div></section>
<section class="panel">
<h2><?=($selected?'Bukti pada Laporan #'.(int)$selected['id']:'Pilih Laporan dari Daftar')?></h2>
<?php if(!$selected):?><p class="empty">Pilih satu laporan di kiri untuk melihat bukti dan syarat unggah.</p>
<?php else:?>
<p class="sub">Kategori <?=h($selected['kind'])?> · Status <?=h($selected['status'])?> · Maksimum empat foto per laporan.</p>
<?php if(!$files):?><p class="empty">Belum ada foto yang tersimpan untuk laporan ini.</p><?php endif;?>
<div class="files"><?php foreach($files as $f):?><a class="file" href="/unduh-bukti18.php?id=<?=(int)$f['id']?>">
<span><b> Bukti foto #<?=(int)$f['id']?></b><small><?=h($f['mime_type'])?> · <?=number_format((int)$f['bytes_size']/1024,0,',','.')?> KB · <?=h($f['created_at'])?> WIB</small></span>
<strong>Unduh (terproteksi)</strong></a><?php endforeach;?></div>
<?php if(is_super_admin($u)):?>
<div class="alert neutral">Super Admin dapat memeriksa bukti, tetapi tidak dapat mengunggah atas nama petugas.</div>
<?php elseif(!$ready||!$scanning):?><div class="alert bad">Unggahan nonaktif. Pemindai ClamAV yang diwajibkan dan ekstensi GD/fileinfo harus disiapkan dan diuji pada hosting. Jangan unggah foto warga.</div>
<?php elseif(!in_array($selected['status'],['diajukan','ditinjau','perbaikan'],true)):?>
<div class="alert neutral">Laporan telah ditutup atau masih draf. Lampiran baru tidak diterima.</div>
<?php elseif(count($files)>=4):?><div class="alert neutral">Batas maksimal empat foto per laporan telah tercapai.</div>
<?php else:?>
<form method="post" enctype="multipart/form-data" class="upload-form" action="/bukti-laporan18.php?source=<?=h(rawurlencode($source))?>&amp;id=<?=(int)$id?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label>Foto kegiatan (JPEG atau PNG, maksimal 3 MB)<input type="file" name="bukti" accept="image/jpeg,image/png" required></label>
<label class="check"><input type="checkbox" name="consent" value="1" required>
Saya memastikan foto hanya memuat kegiatan umum, bukan NIK, dokumen warga, wajah anak, alamat privat, atau informasi sensitif.</label>
<button class="btn" type="submit">Pindai &amp; Simpan Bukti →</button>
</form>
<?php endif;?>
<?php endif;?>
</section></div>
<p class="footnote">Tidak ada unggahan PDF, Word, foto KTP, atau data kesehatan pribadi. Balasan pemindai ClamAV tetap bukan jaminan mutlak keamanan foto; gunakan hanya untuk pengujian internal hingga pengelola hosting menyetujui prosedurnya.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Bukti Kegiatan Terproteksi</div></footer></body></html>
<?php
declare(strict_types=1);
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/homepage_repair_v2.php';
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);exit('Akses Super Administrator belum tersedia.');
}
$cssFile=__DIR__.'/assets/beranda-fix.css';
$indexFile=__DIR__.'/index.php';
$css=is_file($cssFile)&&!is_link($cssFile)?@file_get_contents($cssFile):false;
$source=is_file($indexFile)&&!is_link($indexFile)?@file_get_contents($indexFile):false;
$cssOK=is_string($css)&&b14_home_css_ok($css);
$sourceOK=is_string($source)&&b14_home_target_ok($source);
$version=$cssOK?substr(hash('sha256',$css),0,12):'';
$installed=$sourceOK && str_contains($source,'SMARTCIKOKO_HOMEPAGE_V2');
$cssLinked=$installed && str_contains($source,'/assets/beranda-fix.css?v='.$version);
$error='';$success=(string)($_SESSION['beranda_fix_notice']??'');
unset($_SESSION['beranda_fix_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try {
        if(!$cssOK)throw new RuntimeException('CSS V2 tidak ditemukan atau belum lengkap di public_html/assets.');
        if(!$sourceOK)throw new RuntimeException('index.php aktif tidak sesuai dengan struktur yang dikenali. File belum diubah.');
        $sha=(string)($_POST['index_sha']??'');
        if(!hash_equals(hash('sha256',$source),$sha))
            throw new RuntimeException('index.php berubah sejak halaman dibuka. Muat ulang sebelum menerapkan perbaikan.');
        if(!isset($_POST['confirmed']))
            throw new RuntimeException('Konfirmasi backup diperlukan.');
        $new=b14_home_inject($source,$version);
        $backup=b14_home_write_atomically($source,$new,$indexFile,dirname(__DIR__).'/portal_private');
        $_SESSION['beranda_fix_notice']=$backup!==''
            ?'Tautan CSS V2 sudah dipasang. Backup index.php dibuat di folder privat.':
             'Tautan CSS V2 sudah terpasang dan tidak memerlukan perubahan lagi.';
        header('Location: /perbaiki-beranda-v2.php',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage 14 homepage rescue failed: '.get_class($e));
        $error=$e instanceof RuntimeException?$e->getMessage():'Pemasangan belum berhasil. Periksa izin file di cPanel.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3454">
<title>Pemulihan Beranda V2 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Perbaikan Beranda V2</h1><p>Memulihkan tampilan tanpa mengganti kode login, MFA, atau database.</p></header>
<section class="panel">
<div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Akses Super Administrator terverifikasi</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($success):?><div class="alert good"><?=h($success)?></div><?php endif;?>
<h2>Hasil Pengecekan</h2>
<p>File CSS V2: <strong><?= $cssOK?'Siap':'Belum tersedia' ?></strong></p>
<p>Struktur index.php: <strong><?= $sourceOK?'Sesuai':'Perlu ditinjau' ?></strong></p>
<p>Tautan CSS terbaru: <strong><?= $cssLinked?'Terpasang':'Belum terpasang' ?></strong></p>
<?php if($cssOK):?><p>Versi CSS: <code><?=h($version)?></code></p><?php endif;?>
<?php if($cssLinked):?>
<div class="alert good">CSS V2 sudah terpasang pada halaman utama. Buka Beranda dengan parameter versi untuk memastikan browser mengambil tampilan terbaru.</div>
<a class="button" href="/?page=beranda&layout=v2-<?=h($version)?>">Lihat Beranda yang Diperbaiki →</a>
<?php elseif($cssOK && $sourceOK):?>
<p>Perbaikan hanya menambahkan tautan CSS baru sebelum penutup head. Sistem terlebih dahulu menyimpan salinan index.php di luar public_html.</p>
<form method="post" action="/perbaiki-beranda-v2.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="index_sha" value="<?=h(hash('sha256',$source))?>">
<label style="display:block"><input type="checkbox" name="confirmed" value="1" required>
Saya sudah memastikan website dan database memiliki backup, serta menyetujui penambahan satu tautan CSS pada index.php.</label>
<button class="button" type="submit">Perbaiki Tampilan Beranda →</button>
</form>
<?php else:?>
<p>File belum diubah. Pastikan ZIP V2 diekstrak pada direktori <code>/home/datd1747/</code> (bukan di dalam public_html). Jika CSS ada tetapi struktur index.php berbeda, perlu dibandingkan dengan file live.</p>
<?php endif;?>
<p class="footnote">Setelah berhasil, hapus <code>public_html/perbaiki-beranda-v2.php</code> dan ZIP dari hosting. Pertahankan <code>assets/beranda-fix.css</code>. Tidak perlu menghapus MFA atau mengganti PHP 8.4.</p>
</section></main></body></html>
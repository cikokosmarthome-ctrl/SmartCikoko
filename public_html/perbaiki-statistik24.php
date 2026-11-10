<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk perbaikan statistik.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    require_once dirname(__DIR__).'/portal_private/stats24_repair.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))throw new RuntimeException('Akun tidak aktif.');
}catch(Throwable $e){http_response_code(503);exit('Pemulihan hanya tersedia bagi Super Admin dengan MFA aktif.');}
$root=dirname(__DIR__).'/portal_private';
$files=s24_files($root,__DIR__);$states=[];
foreach($files as $name=>$f)$states[$name]=s24_state($f);
$success='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $backups=s24_install($root,__DIR__,[
            'engine'=>(string)($_POST['engine_sha']??''),
            'page'=>(string)($_POST['page_sha']??''),
        ]);
        $success='Perbaikan statistik Tahap 24 selesai. Kedua file Tahap 23 sudah diperbarui dengan backup privat; tidak ada perubahan database.';
        if($backups)log_event('stage24_stats_fixed','system','hub23');
    }catch(Throwable $e){
        error_log('Stage24 statistics fix denied: '.get_class($e));
        $error='Perbaikan belum diterapkan sepenuhnya. Berkas hosting berbeda, tidak bisa ditulis, atau paket tidak valid. Sistem tidak akan menimpa perubahan yang tidak dikenal.';
    }
    foreach($files as $name=>$f)$states[$name]=s24_state($f);
}
$labels=[
    'repairable'=>'Versi Tahap 23 V2 dikenali — siap diperbaiki',
    'ready'=>'Versi Tahap 24 sudah terpasang',
    'modified'=>'Versi berbeda — tidak akan ditimpa',
    'invalid_package'=>'Paket pemulihan tidak cocok',
    'missing'=>'Berkas belum tersedia',
];
$allValid=true;$done=true;
foreach($states as $r){
    if(!in_array($r['status'],['ready','repairable'],true))$allValid=false;
    if($r['status']!=='ready')$done=false;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Perbaikan Statistik Tahap 24 · SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Perbaikan Statistik Tahap 24</h1>
<p>Memulihkan ringkasan laporan Tahap 15–17 yang masih menampilkan tanda —</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator · MFA terverifikasi</small></div></div>
<?php if($success):?><div class="alert good"><?=h($success)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Pemeriksaan Kode Terpasang</h2>
<p>Mesin statistik privat: <strong><?=h($labels[$states['engine']['status']]??'Tidak dikenal')?></strong></p>
<p>Halaman Pusat Operasional: <strong><?=h($labels[$states['page']['status']]??'Tidak dikenal')?></strong></p>
<p>Database dan tabel laporan lama: <strong>Tidak diubah</strong></p>
<?php if($done):?>
<div class="alert good">Perbaikan sudah terpasang. Silakan buka Pusat Operasional dan periksa empat angka statistik.</div>
<a class="button" href="/pusat-operasional23.php?v=24">Buka Statistik yang Diperbaiki →</a>
<?php elseif(!$allValid):?>
<div class="alert error">Installer menolak versi file yang tidak dikenali agar pengaturan lokal tidak hilang. Jangan menimpa file secara manual. Periksa versi melalui cPanel atau laporkan status ini.</div>
<?php else:?>
<p>Installer memeriksa checksum file Tahap 23 V2, membuat backup di <code>portal_private/stats24_backups/</code>, kemudian memperbaiki hanya dua file Pusat Operasional.</p>
<form method="post" action="/perbaiki-statistik24.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="engine_sha" value="<?=h($states['engine']['sha'])?>">
<input type="hidden" name="page_sha" value="<?=h($states['page']['sha'])?>">
<button class="button" type="submit">Perbaiki Statistik Tahap 24 →</button>
</form>
<?php endif;?>
<p class="footnote">Lanjutkan pengujian dengan akun dummy Super Admin dan petugas. Jika masih ada sumber laporan bertanda belum terbaca, itu menunjukkan masalah database khusus sumber tersebut; jangan mengisi angka fiktif.</p>
<p class="footnote">Setelah pengujian, hapus <code>public_html/perbaiki-statistik24.php</code> dan ZIP dari hosting, tetapi simpan backup privat. Login, MFA, Beranda, modul Tahap 19–22 tidak diubah.</p>
</section></main></body></html>
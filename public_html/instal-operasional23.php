<?php
declare(strict_types=1);
/**
 * Stage 23 read-only workspace installer. NO tables created or altered.
 * Adds only one link to each existing page with original privately backed up.
 */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk memasang Pusat Operasional.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/hub23.php';
    require_once dirname(__DIR__).'/portal_private/hub23_patch.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))throw new RuntimeException('Status akun Super Admin tidak aktif.');
}catch(Throwable $e){http_response_code(503);exit('Login Super Admin dan MFA diperlukan untuk pemasangan.');}
$dashboard=__DIR__.'/super-admin-satu.php';
$staff=__DIR__.'/pusat-laporan.php';
$dashSource=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$staffSource=is_file($staff)&&!is_link($staff)?@file_get_contents($staff):false;
$dashReady=is_string($dashSource)&&str_contains($dashSource,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK');
$staffReady=is_string($staffSource)&&str_contains($staffSource,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK');
$required=[
    'Laporan Tahap 15'=>h23_ready('15'),
    'Formulir Tahap 16'=>h23_ready('16'),
    'Laporan Wilayah Tahap 17'=>h23_ready('17'),
    'Bukti Tahap 18'=>h23_ready('18'),
    'Verifikasi Tahap 19'=>h23_ready('19'),
    'Monitoring Tahap 20'=>h23_ready('20'),
    'Penugasan Tahap 21'=>h23_ready('21'),
    'Kendali Tahap 22'=>h23_ready('22'),
];
$degraded=in_array(false,$required,true);
$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!is_string($dashSource)||!is_string($staffSource))
            throw new RuntimeException('Super Admin Ringkas atau Pusat Laporan tidak tersedia.');
        // Stage23 is only a read-only navigation hub. If a legacy module is
        // temporarily unavailable, it is visibly flagged, not silently "ready".
        if(!$dashReady){
            h23_apply($dashboard,dirname(__DIR__).'/portal_private',(string)($_POST['dash_hash']??''),'dashboard');
            $dashSource=@file_get_contents($dashboard);
            $dashReady=is_string($dashSource)&&str_contains($dashSource,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK');
        }
        if(!$staffReady){
            h23_apply($staff,dirname(__DIR__).'/portal_private',(string)($_POST['staff_hash']??''),'center');
            $staffSource=@file_get_contents($staff);
            $staffReady=is_string($staffSource)&&str_contains($staffSource,'SMARTCIKOKO_TAHAP23_OPERATIONS_LINK');
        }
        if(!$dashReady||!$staffReady)
            throw new RuntimeException('Tautan navigasi belum tersambung dengan benar.');
        $notice='Pusat Operasional terhubung ke dashboard dan pusat laporan. Tidak ada database atau alur lama yang diubah.';
    }catch(Throwable $e){
        error_log('Stage23 installer blocked: '.get_class($e));
        $error='Pemasangan tautan belum lengkap. Cek versi dan izin tulis kedua halaman; backup asli sudah disiapkan sebelum perubahan.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Installer Pusat Operasional Tahap 23 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Pusat Operasional</h1><p>Tahap 23 · Akses kerja terpusat berdasarkan peran, tanpa mengganti sistem lama</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator · MFA terverifikasi</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Kesiapan Modul</h2>
<?php foreach($required as $label=>$ok):?>
<p><?=h($label)?>: <strong><?=$ok?'Tersedia':'Belum terpasang / belum dapat dibaca'?></strong></p>
<?php endforeach;?>
<?php if($degraded):?><div class="alert error">Sebagian modul belum siap. Pusat Operasional tetap dapat digunakan untuk modul yang tersedia, tetapi pastikan modul yang masih bermasalah diperbaiki sebelum operasional penuh.</div><?php endif;?>
<h2>Integrasi Menu Lama</h2>
<p>Super Admin Ringkas: <strong><?=$dashReady?'Terhubung':'Belum terhubung'?></strong></p>
<p>Pusat Laporan: <strong><?=$staffReady?'Terhubung':'Belum terhubung'?></strong></p>
<?php if($dashReady&&$staffReady):?>
<div class="alert good">Pusat Operasional sudah terpasang dalam kedua menu. Halaman lama tetap digunakan untuk transaksi dan pemeriksaan resmi.</div>
<a class="button" href="/pusat-operasional23.php">Buka Pusat Operasional →</a>
<p><a href="/super-admin-satu.php">Super Admin Ringkas</a> · <a href="/pusat-laporan.php">Pusat Laporan</a></p>
<p class="footnote">Setelah pengujian, hapus public_html/instal-operasional23.php dan ZIP dari hosting. Simpan backup privat.</p>
<?php else:?>
<p>Installer menambahkan satu tautan “Pusat Operasional” ke Super Admin Ringkas dan Pusat Laporan. Sebelum perubahan, file asli dibackup di <code>portal_private/hub23_backups/</code>, di luar folder publik. Tidak membuat tabel baru.</p>
<?php if(!is_string($dashSource)||!is_string($staffSource)):?><div class="alert error">Halaman Super Admin Ringkas atau Pusat Laporan tidak ditemukan. Pasang tahap pendahulu terlebih dahulu.</div>
<?php else:?>
<form method="post" action="/instal-operasional23.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dash_hash" value="<?=h(hash('sha256',$dashSource))?>">
<input type="hidden" name="staff_hash" value="<?=h(hash('sha256',$staffSource))?>">
<button class="button" type="submit">Hubungkan Pusat Operasional →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Tautan baru tidak mengubah kewenangan, status MFA, verifikasi surat, saldo cuti, ataupun penilaian kinerja resmi. Semua izin tetap diputuskan oleh modul asal.</p>
</section></main></body></html>
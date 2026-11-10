<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Pemulihan hanya dapat dijalankan melalui HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    require_once dirname(__DIR__).'/portal_private/hub23_repair.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))throw new RuntimeException('Akun tidak memiliki izin.');
}catch(Throwable $e){
    http_response_code(503);exit('Akses pemulihan membutuhkan Super Admin dan MFA.');
}
$private=dirname(__DIR__).'/portal_private';
$files=recover23_files($private,__DIR__);
$statuses=[];
foreach($files as $key=>$spec)$statuses[$key]=recover23_state($spec);
$success='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $backups=recover23_all($private,__DIR__,[
            'hub'=>(string)($_POST['hub_sha']??''),
            'page'=>(string)($_POST['page_sha']??'')
        ]);
        $success='Pemulihan Pusat Operasional berhasil. File lama telah dibackup di folder privat; tidak ada SQL atau perubahan login/MFA.';
        if($backups)log_event('stage23_recovery','system','hub23');
    }catch(Throwable $e){
        error_log('Stage23 recovery refused: '.get_class($e));
        $error='Pemulihan dihentikan karena versi berkas tidak cocok, berubah, atau tidak dapat ditulis. Tidak ada penggantian paksa. Simpan tangkapan layar ini untuk pemeriksaan.';
    }
    foreach($files as $key=>$spec)$statuses[$key]=recover23_state($spec);
}
$ok=$statuses['hub']['status']==='ready'&&$statuses['page']['status']==='ready';
$allowed=true;
foreach($statuses as $status)
    if(!in_array($status['status'],['ready','repairable'],true))$allowed=false;
$label=['hub'=>'Mesin Pusat Operasional (privat)','page'=>'Halaman Pusat Operasional'];
$stateLabel=[
    'ready'=>'Versi perbaikan sudah terpasang',
    'repairable'=>'Versi lama dikenali — dapat diperbaiki',
    'modified'=>'Versi tidak dikenali — jangan ditimpa',
    'missing'=>'File belum ditemukan',
    'invalid_candidate'=>'File paket pemulihan tidak lengkap atau berubah'
];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Perbaikan Pusat Operasional Tahap 23 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Perbaikan Pusat Operasional V2</h1>
<p>Memulihkan halaman Tahap 23 yang tampil putih tanpa mengganti login, MFA, atau database</p></header>
<section class="panel">
<div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Admin · MFA terverifikasi</small></div></div>
<?php if($success):?><div class="alert good"><?=h($success)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Hasil Pengecekan</h2>
<?php foreach($statuses as $key=>$state):?>
<p><?=h($label[$key])?>: <strong><?=h($stateLabel[$state['status']]??'Tidak diketahui')?></strong></p>
<?php endforeach;?>
<?php if($ok):?>
<div class="alert good">Kedua file sudah menggunakan versi perbaikan. Silakan buka Pusat Operasional.</div>
<a class="button" href="/pusat-operasional23.php?v=2">Buka Pusat Operasional yang Diperbaiki →</a>
<p class="footnote">Jika terdapat peringatan di halaman baru, cukup kirim tangkapan layar bagian tersebut untuk penelusuran lebih spesifik.</p>
<?php elseif(!$allowed):?>
<div class="alert error">Versi file tidak sama dengan paket resmi Tahap 23 atau paket pemulihan kurang lengkap. Installer tidak akan menimpa file modifikasi yang tidak dikenal.</div>
<?php else:?>
<p>Paket ini hanya memperbaiki dua berkas Tahap 23. Sebelum setiap perubahan, sistem membuat backup lengkap di <code>portal_private/hub23_repair_backups/</code>, di luar folder publik.</p>
<form method="post" action="/perbaiki-operasional23.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="hub_sha" value="<?=h($statuses['hub']['hash'])?>">
<input type="hidden" name="page_sha" value="<?=h($statuses['page']['hash'])?>">
<button class="button" type="submit">Perbaiki Pusat Operasional →</button></form>
<?php endif;?>
<p class="footnote">Setelah halaman berhasil diuji, hapus public_html/perbaiki-operasional23.php dan arsip ZIP dari hosting. Jangan hapus backup privat. Halaman lama Super Admin, Pusat Laporan, serta modul Tahap 19–22 tidak berubah.</p>
</section></main></body></html>
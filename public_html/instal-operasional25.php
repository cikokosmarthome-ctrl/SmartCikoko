<?php
declare(strict_types=1);
/** Stage25: no SQL installer; new files are already extracted in place. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk memasang pemeriksaan operasional.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    require_once dirname(__DIR__).'/portal_private/audit25_patch.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))throw new RuntimeException('Akses akun dibatasi.');
}catch(Throwable $e){http_response_code(503);exit('Installer Tahap 25 membutuhkan Super Admin dan MFA.');}
$path=__DIR__.'/super-admin-satu.php';
$source=is_file($path)&&!is_link($path)?@file_get_contents($path):false;
$connected=is_string($source)&&str_contains($source,'SMARTCIKOKO_TAHAP25_AUDIT_PANEL');
$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!is_string($source))throw new RuntimeException('Super Admin Ringkas belum tersedia.');
        if(!$connected){
            a25_apply($path,dirname(__DIR__).'/portal_private',(string)($_POST['page_sha']??''));
            $source=@file_get_contents($path);
            $connected=is_string($source)&&str_contains($source,'SMARTCIKOKO_TAHAP25_AUDIT_PANEL');
        }
        if(!$connected)throw new RuntimeException('Panel belum tersambung ke dashboard.');
        $notice='Panel pemeriksaan Tahap 25 berhasil terhubung. Halaman lama sudah dibackup, tidak ada tabel database dibuat atau diubah.';
    }catch(Throwable $e){
        error_log('Stage25 admin panel installer refused: '.get_class($e));
        $error='Pemasangan panel ditahan karena versi halaman berbeda, tidak dapat ditulis, atau backup gagal. File lama tidak akan dipaksa ditimpa.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Installer Pemeriksaan Tahap 25 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Pemeriksaan Operasional</h1>
<p>Tahap 25 · Pemeriksaan baca-saja yang terhubung ke Super Admin Ringkas</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator · MFA terverifikasi</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Status Integrasi</h2>
<p>Super Admin Ringkas: <strong><?=is_string($source)?'Terdeteksi':'Belum ditemukan'?></strong></p>
<p>Panel Pemeriksaan Tahap 25: <strong><?=$connected?'Terhubung':'Belum terhubung'?></strong></p>
<p>Database: <strong>Tidak dibuat tabel baru dan tidak diubah isi laporan</strong></p>
<?php if($connected):?>
<div class="alert good">Panel pemeriksaan telah tersambung. Bukalah halaman pemeriksaan untuk melihat hasil dari server.</div>
<a class="button" href="/cek-operasional25.php">Buka Pemeriksaan Operasional →</a>
<p><a href="/super-admin-satu.php#cek-operasional">Kembali ke Super Admin Ringkas</a></p>
<?php elseif(!is_string($source)):?>
<div class="alert error">Super Admin Ringkas tidak ditemukan; hentikan pemasangan sampai struktur hosting diperiksa.</div>
<?php else:?>
<p>Installer hanya menambah satu panel ringkas. Sebelum perubahan, berkas aslinya disimpan di <code>portal_private/audit25_backups/</code>, di luar folder web publik.</p>
<form method="post" action="/instal-operasional25.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="page_sha" value="<?=h(hash('sha256',$source))?>">
<button class="button" type="submit">Hubungkan Pemeriksaan Tahap 25 →</button></form>
<?php endif;?>
<p class="footnote">Pemeriksaan tidak mengirim laporan, tidak menyetujui cuti, tidak mengirim WhatsApp, dan tidak mengubah hak Lurah atau pemeriksa. Setelah diuji, hapus installer dan arsip ZIP dari hosting, simpan backup privat.</p>
</section></main></body></html>
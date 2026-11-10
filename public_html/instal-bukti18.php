<?php
declare(strict_types=1);
/**
 * Tahap 18: installs ONE new table in SmartApp, backed-up links to existing UI.
 * Does not modify native reporting, identity, MFA or older report tables.
 */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk memasang fitur.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/evidence18.php';
    require_once dirname(__DIR__).'/portal_private/evidence18_patch.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Login Super Admin dan MFA diperlukan.');}
$dashboard=__DIR__.'/super-admin-satu.php';
$center=__DIR__.'/pusat-laporan.php';
$dashSource=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$centerSource=is_file($center)&&!is_link($center)?@file_get_contents($center):false;
$dashReady=is_string($dashSource)&&str_contains($dashSource,'SMARTCIKOKO_TAHAP18_ADMIN');
$centerReady=is_string($centerSource)&&str_contains($centerSource,'SMARTCIKOKO_TAHAP18_CENTER');
$ready=e18_ready();$antivirus=e18_scan_ready();$message='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_evidence18 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('pegawai16','wilayah17') NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                stored_name VARCHAR(60) NOT NULL,
                mime_type ENUM('image/jpeg','image/png') NOT NULL,
                bytes_size INT UNSIGNED NOT NULL,
                sha256 CHAR(64) NOT NULL,
                width_px SMALLINT UNSIGNED NOT NULL,
                height_px SMALLINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_e18_name(stored_name),
                KEY idx_e18_source(source_kind,source_id,id),
                KEY idx_e18_owner(actor_source,actor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if(!e18_ready())throw new RuntimeException('Tabel bukti belum dapat dibaca.');
            $ready=true;log_event('evidence18_installed','system','evidence18');
        }
        if(!$dashReady){
            if(!is_string($dashSource))throw new RuntimeException('Super Admin Ringkas belum tersedia; pasang Tahap 15 dahulu.');
            e18_apply($dashboard,dirname(__DIR__).'/portal_private',(string)($_POST['dashboard_sha']??''),'dashboard');
            $dashSource=@file_get_contents($dashboard);
            $dashReady=is_string($dashSource)&&str_contains($dashSource,'SMARTCIKOKO_TAHAP18_ADMIN');
        }
        if(!$centerReady&&is_string($centerSource)){
            try{
                e18_apply($center,dirname(__DIR__).'/portal_private',(string)($_POST['center_sha']??''),'center');
                $centerSource=@file_get_contents($center);
                $centerReady=is_string($centerSource)&&str_contains($centerSource,'SMARTCIKOKO_TAHAP18_CENTER');
            }catch(Throwable $e){
                error_log('Stage 18 optional center link unavailable: '.get_class($e));
            }
        }
        $message='Penyimpanan dan panel ringkas sudah disiapkan. Lakukan pengujian menggunakan laporan/foto dummy.';
    }catch(Throwable $e){
        error_log('Stage 18 installer failed: '.get_class($e));
        $error='Pemasangan belum lengkap. Periksa izin CREATE TABLE, dashboard Tahap 15, dan akses tulis file; data lama tidak diubah.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Tahap 18 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head>
<body><main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Aktivasi Bukti &amp; Rekap</h1>
<p>Tahap 18 · Foto kegiatan terproteksi dan rekap laporan tanpa data pribadi</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator dengan MFA</small></div></div>
<?php if($message):?><div class="alert good"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Pemeriksaan Pemasangan</h2>
<p>Database bukti foto: <strong><?=$ready?'Siap':'Belum dipasang'?></strong></p>
<p>Panel Super Admin Ringkas: <strong><?=$dashReady?'Terhubung':'Belum dihubungkan'?></strong></p>
<p>Menu Pusat Input Laporan: <strong><?=$centerReady?'Terhubung':'Opsional, belum dihubungkan'?></strong></p>
<p>Pemindai ClamAV &amp; GD: <strong><?=$antivirus?'Terkonfigurasi, harus diuji':'Belum siap — unggah ditolak'?></strong></p>
<?php if($ready&&$dashReady):?>
<div class="alert good">Installer telah memasang tabel dan panel. Fitur ekspor CSV dapat diuji tanpa mengaktifkan unggahan.</div>
<a class="button" href="/super-admin-satu.php#bukti-dan-rekap">Buka Super Admin Ringkas →</a>
<p><a href="/rekap-laporan18.php">Buka Rekap CSV</a> · <a href="/bukti-laporan18.php">Buka Bukti Foto</a></p>
<p class="footnote">Hapus instal-bukti18.php dan arsip ZIP setelah sukses. Backup halaman asli disimpan di luar public_html.</p>
<?php else:?>
<p>Pasang satu tabel baru pada SmartApp. Dashboard dan pusat laporan hanya diberi tautan/komponen kecil dan dibackup secara privat sebelumnya.</p>
<?php if(!is_string($dashSource)):?><div class="alert error">File Super Admin Ringkas belum ditemukan. Pasang Tahap 15 terlebih dahulu.</div>
<?php else:?>
<form method="post" action="/instal-bukti18.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$dashSource))?>">
<input type="hidden" name="center_sha" value="<?=is_string($centerSource)?h(hash('sha256',$centerSource)):''?>">
<button class="button" type="submit">Aktifkan Tahap 18 dan Hubungkan ke Dashboard →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Tidak menerima KTP, dokumen warga, PDF atau bukti cuti. Lampiran foto hanya bisa diunggah ketika ClamAV diwajibkan dan tersedia, serta pengolahan gambar aman berhasil. Ekspor tidak berisi identitas warga.</p>
</section></main></body></html>
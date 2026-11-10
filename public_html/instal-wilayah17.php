<?php
declare(strict_types=1);
/**
 * Tahap 17 one-click additive installer:
 *   - two NEW SmartApp tables only;
 *   - small, backed-up inclusion on existing compact admin dashboard.
 */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk melakukan pemasangan.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/field_reports17.php';
    require_once dirname(__DIR__).'/portal_private/wilayah17_dashboard_patch.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akses Super Admin atau MFA belum siap.');}
$path=__DIR__.'/super-admin-satu.php';
$current=is_file($path)&&!is_link($path)?@file_get_contents($path):false;
$panelReady=is_string($current)&&w17_dashboard_ready($current);
$ready=w17_ready();$error='';$notice='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_field_reports17 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                kind ENUM('rt_rw','lmk','jumantik','fkdm','ppsu') NOT NULL,
                actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                request_key CHAR(32) NOT NULL,
                payload_json MEDIUMTEXT NOT NULL,
                status ENUM('draf','diajukan','ditinjau','perbaikan','ditutup') NOT NULL DEFAULT 'draf',
                version INT UNSIGNED NOT NULL DEFAULT 1,
                review_note VARCHAR(500) NULL,
                reviewed_by_source VARCHAR(30) NULL,
                reviewed_by_id VARCHAR(100) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                submitted_at DATETIME NULL,
                UNIQUE KEY uq_w17_idempotent(actor_source,actor_id,request_key),
                KEY idx_w17_owner(actor_source,actor_id,id),
                KEY idx_w17_kind(kind,id),
                KEY idx_w17_status(status,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_field_history17 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                old_status VARCHAR(24) NOT NULL,
                new_status VARCHAR(24) NOT NULL,
                note VARCHAR(500) NOT NULL DEFAULT '',
                actor_source VARCHAR(30) NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_w17_history(report_id,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if(!w17_ready())throw new RuntimeException('Tabel laporan belum bisa dibaca.');
            $ready=true;log_event('stage17_installed','system','field_reports');
        }
        if(!$panelReady){
            $sha=(string)($_POST['dashboard_sha']??'');
            if(!is_string($current))throw new RuntimeException('Dashboard Super Admin Ringkas belum terpasang. Data laporan sudah siap.');
            $backup=w17_dashboard_apply($path,dirname(__DIR__).'/portal_private',$sha);
            $notice=$backup!==''?'Panel dipasang. Backup dashboard asli disimpan di folder privat.':'Panel dashboard sudah tersedia.';
            $current=@file_get_contents($path);
            $panelReady=is_string($current)&&w17_dashboard_ready($current);
        }
        if(!$notice)$notice='Penyimpanan dan panel ringkas sudah siap.';
    }catch(Throwable $e){
        error_log('Stage 17 installer failed: '.get_class($e));
        // Avoid leaking SQL syntax, account IDs and server file paths to browser.
        $error='Pemasangan belum sepenuhnya berhasil. Periksa hak CREATE TABLE, izin tulis dashboard, dan kompatibilitas file Tahap 15. Data lama tidak diubah.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#123956">
<title>Instalasi Formulir Wilayah Tahap 17 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Pemasangan Tahap 17</h1>
<p>RT/RW · LMK · Jumantik · FKDM · PPSU, terhubung ke satu Pusat Super Admin</p></header>
<section class="panel">
<div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator dengan MFA</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<h2>Status Pemasangan</h2>
<p>Database input laporan tambahan: <strong><?=$ready?'Siap':'Belum dipasang'?></strong></p>
<p>Panel Super Admin Ringkas: <strong><?=$panelReady?'Terhubung':'Belum tersambung'?></strong></p>
<?php if($ready&&$panelReady):?>
<div class="alert good">Tahap 17 sudah terpasang secara struktural. Lanjutkan pengujian dengan akun dummy yang sesuai peran.</div>
<a class="button" href="/super-admin-satu.php#laporan-wilayah">Buka Super Admin Ringkas →</a>
<p><a href="/laporan-wilayah17.php">Buka Form Input Wilayah</a></p>
<p class="footnote">Hapus installer ini serta ZIP setelah pengujian selesai; jangan hapus tabel laporan atau folder backup privat.</p>
<?php else:?>
<p>Installer membuat dua tabel baru pada SmartApp dan menambahkan panel ke dashboard Super Admin lama dengan menyimpan backup terlebih dahulu. Tidak menyentuh file login, MFA, Beranda, atau tabel laporan lama.</p>
<?php if(!is_string($current)):?>
<div class="alert error">Halaman super-admin-satu.php belum ditemukan. Instal Tahap 15 dahulu, kemudian ulangi.</div>
<?php else:?>
<form method="post" action="/instal-wilayah17.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$current))?>">
<button class="button" type="submit">Pasang & Sambungkan ke Super Admin →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Input baru hanya berupa laporan administratif. Tidak otomatis menerbitkan surat RT/RW, memverifikasi Jumantik, atau mengunggah foto/GPS. Hanya akun yang berwenang dapat mengirim.</p>
</section></main></body></html>
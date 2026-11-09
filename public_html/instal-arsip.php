<?php
declare(strict_types=1);
/** Installer Super Admin only: additive archive tables, no SQL/manual changes. */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit('Gunakan HTTPS untuk instalasi.');}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/archive_security.php';
    $actor=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akun Super Administrator tidak dapat diverifikasi.');}
function t11_installed():bool{
    try{
        foreach(['unified_archive_registry','unified_archive_events'] as $name)
            db('smartapp')->query('SELECT 1 FROM '.$name.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
$installed=t11_installed();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed){
    verify_csrf();
    try{
        // Ensure prerequisite storage tables exist. Do not operate on unknown/legacy data.
        foreach(['unified_case_files','unified_citizen_files'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        $pdo=db('smartapp');
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_archive_registry (
            source_key ENUM('pelayanan','pengajuan') NOT NULL,
            file_id BIGINT UNSIGNED NOT NULL,
            review_status ENUM('belum','ditinjau','tindak_lanjut') NOT NULL DEFAULT 'belum',
            integrity_status ENUM('belum','sesuai','berbeda','hilang','tidak_aman') NOT NULL DEFAULT 'belum',
            preserve_flag TINYINT(1) NOT NULL DEFAULT 0,
            retention_note VARCHAR(300) NOT NULL DEFAULT '',
            updated_by_source VARCHAR(30) NOT NULL DEFAULT '',
            updated_by_id VARCHAR(100) NOT NULL DEFAULT '',
            updated_at DATETIME NULL DEFAULT NULL,
            checked_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY(source_key,file_id),
            KEY idx_archive_review(review_status),
            KEY idx_archive_integrity(integrity_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_archive_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_key ENUM('pelayanan','pengajuan') NOT NULL,
            file_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            event_summary VARCHAR(500) NOT NULL,
            actor_source VARCHAR(30) NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_archive_event_time(created_at),
            KEY idx_archive_event_file(source_key,file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!t11_installed())throw new RuntimeException('Verifikasi tabel gagal.');
        $installed=true;
        try{log_event('stage11_archive_installed','system','stage11');}catch(Throwable $e){error_log('Archive installed, audit announcement failed.');}
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 11 install failed: '.$e->getMessage());
        $error='Instalasi belum berhasil. Pastikan Tahap 8 dan 10 telah terpasang serta izin CREATE TABLE database SmartApp tersedia.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Instalasi Tahap 11 | Kelurahan Cikoko</title><link rel="stylesheet" href="/assets/instal-arsip.css"></head><body>
<main class="shell"><header><span>PORTAL TERPADU KELURAHAN CIKOKO</span><h1>Instalasi Tahap 11</h1><p>Keamanan Berkas dan Pengelolaan Arsip Digital</p></header>
<section class="panel"><div class="actor"><span><?=h(mb_strtoupper(mb_substr((string)$actor['name'],0,1)))?></span><div><b><?=h($actor['name'])?></b><small>Super Administrator terverifikasi</small></div></div>
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php endif;?>
<?php if($installed):?><div class="alert success"><b>Tahap 11 berhasil dipasang.</b><p>Registry arsip dan audit pemeriksaan sudah tersedia.</p></div>
<a class="button" href="/arsip-keamanan.php">Buka Arsip dan Keamanan →</a>
<p class="warn">Hapus <code>public_html/instal-arsip.php</code> dan ZIP setelah pengujian berhasil.</p>
<?php else:?>
<h2>Instalasi satu tombol</h2>
<p>Installer menambahkan dua tabel pencatatan arsip di database SmartApp. File Tahap 8/10 tidak dipindahkan, dimodifikasi atau dihapus.</p>
<div class="point">✓ Tidak perlu masuk phpMyAdmin atau menjalankan SQL</div>
<div class="point">✓ Pemeriksaan integritas menggunakan SHA-256</div>
<div class="point">✓ Pemeriksaan arsip tidak otomatis mengesahkan dokumen</div>
<form method="post" action="/instal-arsip.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><button class="button" type="submit">Pasang Tahap 11 →</button></form>
<?php endif;?></section></main></body></html>
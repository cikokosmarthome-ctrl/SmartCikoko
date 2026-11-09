<?php
declare(strict_types=1);
/** Tahap 10 — installer tambahan, hanya Super Admin, tanpa SQL manual. */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Instalasi memerlukan HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/citizen_requests.php';
    require_once dirname(__DIR__).'/portal_private/citizen_followup.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akun Super Admin atau konfigurasi portal belum siap.');}
$installed=t10_ready();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && !$installed){
    verify_csrf();
    try{
        // Only additive SQL; never mutate source citizen, heir, PPPK or PJLP tables.
        $db=db('smartapp');
        $db->exec("CREATE TABLE IF NOT EXISTS unified_citizen_files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NOT NULL,
            doc_type ENUM('identitas','keluarga','kematian','lainnya') NOT NULL,
            original_name VARCHAR(160) NOT NULL,
            stored_name VARCHAR(100) NOT NULL UNIQUE,
            mime_type VARCHAR(80) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            uploaded_by_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_citizen_file_request(request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_citizen_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NOT NULL,
            account_id VARCHAR(100) NOT NULL,
            message VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL DEFAULT NULL,
            KEY idx_citizen_notices_owner(account_id,id),
            KEY idx_citizen_notices_request(request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        t10_storage();
        if(!t10_ready())throw new RuntimeException('Tabel baru tidak siap.');
        $installed=true;
        log_event('stage10_installed','system','stage10');
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 10 installation failed: '.$e->getMessage());
        $error='Pemasangan belum selesai. Periksa izin membuat tabel database SmartApp serta izin tulis folder privat.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Tahap 10 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/instal-tindak-lanjut.css"></head>
<body><main class="card">
<header><small>KELURAHAN CIKOKO · PORTAL TERPADU</small><h1>Instalasi Tahap 10</h1><p>Lampiran Privat, Pemberitahuan Status dan Bukti Informasi</p></header>
<section><div class="user"><span><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><div><b><?=h($admin['name'])?></b><small>Super Administrator terautentikasi</small></div></div>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?>
<?php if($installed):?>
<p class="success">Tahap 10 telah terpasang. Dua tabel baru dan folder penyimpanan privat tersedia.</p>
<a class="button" href="/tindak-lanjut.php">Buka Pusat Tindak Lanjut ↗</a>
<p class="warn">Setelah pengujian, hapus <code>public_html/instal-tindak-lanjut.php</code> dan arsip ZIP dari hosting.</p>
<?php else:?>
<h2>Pasang dengan satu tombol</h2><p>Installer menambahkan dua tabel pada database SmartApp dan membuat folder lampiran di luar <code>public_html</code>. Pengajuan lama serta kedua database sumber tetap dipertahankan.</p>
<p class="point">✓ Kewenangan Super Admin sudah diverifikasi</p>
<p class="point">✓ Tidak perlu membuka phpMyAdmin atau mengimpor SQL lama</p>
<p class="point">✓ Keputusan resmi tidak diterbitkan otomatis</p>
<form method="post" action="/instal-tindak-lanjut.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="button" type="submit">Pasang Tahap 10 ↗</button></form>
<?php endif;?>
</section></main></body></html>
<?php
declare(strict_types=1);
/** Tahap 8 — Instalasi satu kali oleh Super Admin, tanpa impor SQL manual. */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan koneksi HTTPS untuk instalasi.');
}
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/document_workflow.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akses Super Admin belum tersedia.');}
function t8_installed():bool {
    try{
        $db=db('smartapp');
        foreach(['unified_document_checks','unified_case_files','unified_document_events'] as $table)
            $db->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        $dir=t8_storage_dir();
        return is_dir($dir)&&is_writable($dir);
    }catch(Throwable $e){return false;}
}
$installed=t8_installed();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed){
    verify_csrf();
    try{
        $db=db('smartapp');
        $db->exec("CREATE TABLE IF NOT EXISTS unified_document_checks (
          application_id BIGINT UNSIGNED NOT NULL,
          doc_type ENUM('identitas','keluarga','kematian','pernyataan','lainnya') NOT NULL,
          review_status ENUM('belum','sesuai','perbaikan') NOT NULL DEFAULT 'belum',
          review_notes VARCHAR(500) NOT NULL DEFAULT '',
          updated_by_source VARCHAR(32) NOT NULL,
          updated_by_id VARCHAR(100) NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY(application_id,doc_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_case_files (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          application_id BIGINT UNSIGNED NOT NULL,
          doc_type ENUM('identitas','keluarga','kematian','pernyataan','lainnya') NOT NULL,
          original_name VARCHAR(160) NOT NULL,
          stored_name VARCHAR(100) NOT NULL UNIQUE,
          mime_type VARCHAR(80) NOT NULL,
          file_size INT UNSIGNED NOT NULL,
          sha256 CHAR(64) NOT NULL,
          uploaded_by_source VARCHAR(32) NOT NULL,
          uploaded_by_id VARCHAR(100) NOT NULL,
          created_at DATETIME NOT NULL,
          KEY idx_case_file_app(application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_document_events (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          application_id BIGINT UNSIGNED NOT NULL,
          event_type ENUM('review_updated','file_uploaded') NOT NULL,
          doc_type ENUM('identitas','keluarga','kematian','pernyataan','lainnya') NOT NULL,
          event_note VARCHAR(700) NOT NULL DEFAULT '',
          actor_source VARCHAR(32) NOT NULL,
          actor_id VARCHAR(100) NOT NULL,
          created_at DATETIME NOT NULL,
          KEY idx_doc_event_app(application_id),
          KEY idx_doc_event_date(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $folder=t8_storage_dir();
        if(!t8_installed())throw new RuntimeException('Pengujian tabel atau direktori privat tidak berhasil.');
        $installed=true;
        log_event('stage8_documents_installed','system','stage8');
    }catch(Throwable $e){
        error_log('SmartCikoko stage 8 installer: '.$e->getMessage());
        $error='Pemasangan belum selesai. Periksa hak CREATE TABLE pada database SmartApp serta izin tulis direktori portal_private.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Instalasi Tahap 8 | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/instal-layanan.css"></head><body>
<main class="shell"><header><span class="eyebrow">SMARTCIKOKO / PELAYANAN TERPADU</span><h1>Instalasi Tahap 8</h1><p>Verifikasi Dokumen dan Laporan Administrasi Kelurahan Cikoko</p></header>
<section class="panel">
<div class="profile"><span><?=h(mb_strtoupper(mb_substr($admin['name'],0,1)))?></span><div><strong><?=h($admin['name'])?></strong><small>Super Administrator terautentikasi</small></div></div>
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php endif;?>
<?php if($installed):?>
<p class="alert success"><strong>Tahap 8 sudah siap digunakan.</strong> Tabel verifikasi dan folder penyimpanan privat telah tersedia.</p>
<a href="/verifikasi-layanan.php" class="btn">Buka Verifikasi dan Laporan ↗</a>
<p class="warning">Hapus file <code>public_html/instal-layanan.php</code> serta ZIP instalasi dari hosting setelah pengujian.</p>
<?php else:?>
<h2>Pasang dengan satu tombol</h2>
<p>Installer menambahkan tiga tabel pemeriksaan pada database SmartApp dan membuat folder dokumen privat di luar <code>public_html</code>. Database Pegawai, data sumber ahli waris, dan seluruh akun lama tetap dipertahankan.</p>
<div class="feature"><b>✓</b> Super Admin terverifikasi.</div>
<div class="feature"><b>✓</b> Pengelolaan berkas dibatasi melalui sesi Super Admin.</div>
<div class="feature"><b>✓</b> Laporan bulanan tidak menampilkan data pribadi.</div>
<form method="post" action="/instal-layanan.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="btn" type="submit">Pasang Tahap 8 ↗</button></form>
<?php endif;?>
</section></main></body></html>
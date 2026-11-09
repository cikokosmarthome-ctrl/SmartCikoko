<?php
declare(strict_types=1);
/**
 * One-time Stage 7 setup; Super Admin only, creates two isolated tables.
 * No legacy migrations, no file mutation, no SQL editing by cPanel operator.
 */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {
    http_response_code(403);exit('Instalasi harus dilakukan menggunakan HTTPS.');
}
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Sesi Super Admin belum tersedia.');}
$error='';$success=false;
function stage7_tables_ready():bool {
    try{
        $db=db('smartapp');
        foreach(['unified_operational_reviews','unified_operational_history'] as $table)
            $db->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
$installed=stage7_tables_ready();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed){
    verify_csrf();
    try{
        $db=db('smartapp');
        $db->exec("CREATE TABLE IF NOT EXISTS unified_operational_reviews (
          module_key ENUM('pppk','pjlp') NOT NULL,
          record_id VARCHAR(100) NOT NULL,
          review_status ENUM('belum','ditinjau','tindak_lanjut','selesai') NOT NULL DEFAULT 'belum',
          notes VARCHAR(500) NOT NULL DEFAULT '',
          updated_by_source VARCHAR(30) NOT NULL,
          updated_by_id VARCHAR(100) NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY(module_key,record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_operational_history (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          module_key ENUM('pppk','pjlp') NOT NULL,
          record_id VARCHAR(100) NOT NULL,
          old_status VARCHAR(25) NOT NULL,
          new_status VARCHAR(25) NOT NULL,
          notes VARCHAR(500) NOT NULL DEFAULT '',
          actor_source VARCHAR(30) NOT NULL,
          actor_id VARCHAR(100) NOT NULL,
          created_at DATETIME NOT NULL,
          KEY idx_op_history_date(created_at),
          KEY idx_op_history_record(module_key,record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!stage7_tables_ready())throw new RuntimeException('Pemeriksaan tabel operasional belum berhasil.');
        $installed=true;$success=true;
        log_event('stage7_operations_installed','system','stage7');
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 7 installation error: '.$e->getMessage());
        $error='Pemasangan belum selesai. Pastikan database SmartApp dapat ditulis dan user MySQL memiliki izin CREATE TABLE.';
    }
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Instalasi Tahap 7 | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/instal-operasional.css"></head>
<body><main class="shell">
<header><span class="eyebrow">PORTAL TERPADU KELURAHAN CIKOKO</span><h1>Instalasi Tahap 7</h1><p>Pusat Operasional Pegawai, PPPK, PJLP/PPSU dan Ahli Waris</p></header>
<section class="body">
<div class="admin"><div class="avatar"><?=h(mb_strtoupper(mb_substr($admin['name'],0,1)))?></div><div><strong><?=h($admin['name'])?></strong><small>Super Administrator · Terautentikasi</small></div></div>
<?php if($error):?><div class="notice error"><?=h($error)?></div><?php endif;?>
<?php if($installed):?>
<div class="notice success"><strong>Modul operasional sudah terpasang.</strong> Kedua tabel peninjauan siap digunakan.</div>
<a href="/operasional.php" class="btn">Buka Pusat Operasional ↗</a>
<p class="warning">Setelah memastikan halaman operasional berjalan, hapus <code>public_html/instal-operasional.php</code> dan file ZIP dari cPanel.</p>
<?php else:?>
<h2>Cukup satu klik</h2>
<p>Installer membuat dua tabel pencatatan tindak lanjut di database SmartApp. Tidak ada database lama yang dihapus atau dimodifikasi, dan tidak perlu membuka phpMyAdmin.</p>
<div class="point"><b>✓</b> Sesi Super Admin berhasil diverifikasi.</div>
<div class="point"><b>✓</b> Data Pegawai dan SmartApp tetap berada pada sumbernya.</div>
<div class="point"><b>✓</b> Laporan PPPK/PJLP asli tidak diubah oleh pencatatan tindak lanjut.</div>
<form method="post" action="/instal-operasional.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="btn" type="submit">Pasang Pusat Operasional ↗</button></form>
<?php endif;?>
</section></main></body></html>
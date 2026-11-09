<?php
declare(strict_types=1);
/** Instalasi tambahan Tahap 9. Tidak mengubah tabel pengajuan lama. */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Instalasi harus dilakukan melalui HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Sesi Super Administrator tidak tersedia.');}
function t9_schema_ready():bool {
    try{
        foreach(['unified_citizen_requests','unified_citizen_request_history'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
$installed=t9_schema_ready();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed){
    verify_csrf();
    try{
        $pdo=db('smartapp');
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_citizen_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_id VARCHAR(100) NOT NULL,
            request_key CHAR(32) NOT NULL,
            reference_no VARCHAR(40) NOT NULL DEFAULT '',
            applicant_name VARCHAR(160) NOT NULL,
            deceased_name VARCHAR(160) NOT NULL,
            relationship ENUM('anak','pasangan','orang_tua','saudara','lainnya') NOT NULL,
            purpose VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('draf','diajukan','ditinjau','revisi','diverifikasi','disetujui','ditolak') NOT NULL DEFAULT 'draf',
            version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_citizen_key (account_id,request_key),
            KEY idx_citizen_owner (account_id,id),
            KEY idx_citizen_status (status,id),
            KEY idx_citizen_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_citizen_request_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NOT NULL,
            from_status VARCHAR(24) NOT NULL DEFAULT '',
            to_status VARCHAR(24) NOT NULL,
            event_type ENUM('created','edited','status_changed') NOT NULL,
            note VARCHAR(500) NOT NULL DEFAULT '',
            actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_citizen_history_case (request_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!t9_schema_ready())throw new RuntimeException('Pemeriksaan tabel gagal.');
        $installed=true;
        log_event('stage9_intake_installed','system','stage9');
    }catch(Throwable $e){
        error_log('SmartCikoko stage 9 installer failed: '.$e->getMessage());
        $error='Instalasi belum berhasil. Periksa izin membuat tabel pada database SmartApp.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Instalasi Tahap 9 | SmartCikoko</title><link rel="stylesheet" href="/assets/instal-pengajuan.css">
</head><body><main class="shell">
<header><small>SMARTCIKOKO · KELURAHAN CIKOKO</small><h1>Instalasi Tahap 9</h1><p>Pengajuan Pelayanan Masyarakat dan Persetujuan Berjenjang</p></header>
<section class="panel"><div class="identity"><span><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><div><strong><?=h($admin['name'])?></strong><small>Super Administrator terautentikasi</small></div></div>
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php endif;?>
<?php if($installed):?>
<p class="alert ok"><strong>Tahap 9 telah terpasang.</strong> Tabel pengajuan baru dan riwayat alur kerja sudah tersedia.</p>
<a href="/pengajuan-warga.php" class="button">Buka Pengajuan Pelayanan ↗</a>
<p class="warning">Hapus <code>public_html/instal-pengajuan.php</code> serta ZIP dari hosting setelah proses instalasi dan pengujian.</p>
<?php else:?>
<h2>Cukup satu kali pemasangan</h2>
<p>Paket hanya menambahkan dua tabel baru pada database SmartApp. Data permohonan ahli waris sebelumnya tetap tersimpan dan tidak diubah.</p>
<div class="step">✓ Akun Super Administrator sudah diverifikasi</div>
<div class="step">✓ Database Pegawai dan SmartApp tetap terpisah</div>
<div class="step">✓ Hak persetujuan PTSP dan Lurah dipisahkan berdasarkan peran</div>
<form method="post" action="/instal-pengajuan.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="button" type="submit">Pasang Tahap 9 ↗</button></form>
<?php endif;?>
</section></main></body></html>
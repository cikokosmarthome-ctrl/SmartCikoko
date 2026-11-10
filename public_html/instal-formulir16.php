<?php
declare(strict_types=1);
/** Tahap 16 one-click additive installation. No changes to legacy Pegawai tables. */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk pemasangan.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/detailed_forms16.php';
    $admin=require_super_admin();
}catch(Throwable $e){
    http_response_code(503);exit('Super Administrator belum tersedia. Periksa akun dan MFA.');
}
$ready=f16_ready();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$ready){
    verify_csrf();
    try{
        $db=db('smartapp');
        $db->exec("CREATE TABLE IF NOT EXISTS unified_detailed_forms16 (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            kind ENUM('cuti','pppk','pjlp') NOT NULL,
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
            UNIQUE KEY uq_form_owner_request(actor_source,actor_id,request_key),
            KEY idx_form_owner(actor_source,actor_id,id),
            KEY idx_form_kind(kind,id),
            KEY idx_form_status(status,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_detailed_form_history16 (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(24) NOT NULL,
            new_status VARCHAR(24) NOT NULL,
            note VARCHAR(500) NOT NULL DEFAULT '',
            actor_source VARCHAR(30) NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_form_history(form_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!f16_ready())throw new RuntimeException('Tabel Tahap 16 belum dapat dibaca.');
        $ready=true;
        log_event('stage16_installed','system','detailed_forms');
    }catch(Throwable $e){
        error_log('Stage 16 installation failed: '.get_class($e));
        $error='Gagal membuat tabel baru di database SmartApp. Periksa izin CREATE TABLE dan backup. Jangan impor ulang SQL.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Pasang Formulir Terstruktur | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Aktifkan Formulir Tahap 16</h1>
<p>PPPK, PJLP/PPSU dan Cuti dengan format lebih lengkap dan terstruktur</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Admin terverifikasi</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($ready):?><div class="alert good"><strong>Formulir Tahap 16 telah disiapkan.</strong> Dua tabel tambahan sudah dapat dibaca. Database Pegawai asli tidak diubah.</div>
<a class="button" href="/formulir-pegawai16.php">Buka Formulir Terstruktur →</a>
<p><a href="/super-admin-satu.php">Kembali ke Super Admin Ringkas</a></p>
<p class="footnote">Setelah seluruh pengujian selesai, hapus installer ini dan ZIP dari public_html. Jangan menghapus tabel laporan baru jika telah berisi data.</p>
<?php else:?>
<h2>Aktifkan dengan satu tombol</h2><p>Installer hanya menambah tabel formulir dan riwayat pada database SmartApp. Format lama di Pegawai tidak berubah, saldo cuti tidak dikurangi, dan data pribadi tidak dipublikasikan.</p>
<form method="post" action="/instal-formulir16.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button type="submit" class="button">Pasang Tahap 16 →</button></form>
<?php endif;?></section></main></body></html>
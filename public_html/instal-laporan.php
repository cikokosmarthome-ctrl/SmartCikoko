<?php
declare(strict_types=1);
/** Stage 15: additive, one-click installer; never modifies original tables. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk pemasangan.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/unified_reports.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akun Super Admin dan MFA harus siap sebelum instalasi.');}
$ready=lr_ready();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$ready){
    verify_csrf();
    try {
        $db=db('smartapp');
        $db->exec("CREATE TABLE IF NOT EXISTS unified_report_intake (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(30) NOT NULL,
            actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            request_key CHAR(32) NOT NULL,
            title VARCHAR(150) NOT NULL,
            body TEXT NOT NULL,
            location VARCHAR(180) NOT NULL DEFAULT '',
            activity_date DATE NOT NULL,
            end_date DATE NULL,
            status ENUM('diajukan','ditinjau','perbaikan','ditutup') NOT NULL DEFAULT 'diajukan',
            review_note VARCHAR(500) NULL,
            reviewed_by_source VARCHAR(30) NULL,
            reviewed_by_id VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_intake_request(actor_source,actor_id,request_key),
            KEY idx_intake_category_time(category,id),
            KEY idx_intake_owner(actor_source,actor_id,id),
            KEY idx_intake_status(status,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS unified_report_intake_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(20) NOT NULL,
            new_status VARCHAR(20) NOT NULL,
            note VARCHAR(500) NOT NULL,
            actor_source VARCHAR(30) NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_intake_history(report_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!lr_ready())throw new RuntimeException('Verifikasi tabel baru gagal.');
        $ready=true;
        try{log_event('unified_report_intake_installed','system','stage15');}
        catch(Throwable $e){error_log('Stage 15 installer audit entry unavailable.');}
    }catch(Throwable $e){
        error_log('Stage 15 installer error: '.get_class($e));
        $error='Tabel input laporan belum berhasil dipasang. Periksa izin CREATE TABLE database SmartApp tanpa mengimpor SQL lama.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Pusat Laporan | SmartCikoko</title><link rel="stylesheet" href="/assets/mfa.css">
</head><body><main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Pusat Laporan</h1><p>Tahap 15 · Satu layar administrasi dan satu pintu penginputan</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b><div><strong><?=h($admin['name'])?></strong><small>Super Administrator terotorisasi</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($ready):?>
<div class="alert good"><strong>Database laporan baru sudah siap.</strong> Data lama dan MFA tetap dipertahankan.</div>
<a class="button" href="/super-admin-satu.php">Buka Super Admin Satu Halaman →</a>
<p><a href="/pusat-laporan.php">Buka Pusat Input Laporan →</a></p>
<p class="footnote">Setelah pengujian, hapus <code>public_html/instal-laporan.php</code> dan arsip ZIP dari hosting. Jangan menghapus tabel laporan baru setelah digunakan.</p>
<?php else:?>
<h2>Pasang dengan satu tombol</h2><p>Installer hanya menambahkan dua tabel di SmartApp untuk laporan ringkas dan riwayat peninjauan. Formulir lama ahli waris, PPPK, PJLP, cuti, RT/RW dan laporan lainnya tidak dihapus.</p>
<p>Tidak perlu masuk phpMyAdmin, mengubah password, atau menginstal ulang Google Authenticator.</p>
<form method="post" action="/instal-laporan.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="button" type="submit">Aktifkan Pusat Laporan →</button></form>
<?php endif;?>
</section></main></body></html>
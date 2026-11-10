<?php
declare(strict_types=1);
/** Stage26 additive one-table installer. Never writes business report data. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Instalasi wajib menggunakan HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/uat26.php';
    require_once dirname(__DIR__).'/portal_private/uat26_patch.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))
        throw new RuntimeException('Akun Super Admin tidak aktif.');
}catch(Throwable $e){
    http_response_code(503);exit('Instalasi memerlukan Super Admin dengan MFA.');
}
$private=dirname(__DIR__).'/portal_private';
$dashboard=__DIR__.'/super-admin-satu.php';
$original=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$linked=is_string($original)&&str_contains($original,'SMARTCIKOKO_STAGE26_UAT_PANEL');
$prerequisite=is_file($private.'/audit25.php')&&!is_link($private.'/audit25.php');
$ready=u26_ready();$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$prerequisite)throw new RuntimeException('Pemeriksaan Tahap 25 belum terpasang.');
        if(!is_string($original))throw new RuntimeException('Super Admin Ringkas tidak ditemukan.');
        if(!$ready){
            db('smartapp')->exec("CREATE TABLE IF NOT EXISTS unified_uat_events26 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                case_key VARCHAR(40) NOT NULL,
                result ENUM('pending','pass','fail','blocked') NOT NULL,
                note VARCHAR(240) NOT NULL DEFAULT '',
                tested_confirmed TINYINT(1) NOT NULL DEFAULT 0,
                actor_source VARCHAR(25) NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                submit_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                recorded_at DATETIME NOT NULL,
                UNIQUE KEY uq_uat26_submit_token(submit_token),
                KEY idx_uat26_case_latest(case_key,id),
                KEY idx_uat26_created(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if(!u26_ready())throw new RuntimeException('Jurnal UAT belum dapat dibaca.');
            $ready=true;
            log_event('stage26_installed','system','uat26');
        }
        if(!$linked){
            u26_apply($dashboard,$private,(string)($_POST['page_sha']??''));
            $original=@file_get_contents($dashboard);
            $linked=is_string($original)&&str_contains($original,'SMARTCIKOKO_STAGE26_UAT_PANEL');
        }
        if(!$linked)throw new RuntimeException('Panel ringkas belum tersambung.');
        $notice='Jurnal UAT Tahap 26 dan tautan Super Admin sudah siap. Semua skenario masih Belum diuji sampai dicatat secara manual.';
    }catch(Throwable $e){
        error_log('Stage26 installer blocked: '.get_class($e));
        $error='Aktivasi belum lengkap. Periksa Tahap 25, izin CREATE TABLE pada SmartApp, dan versi Super Admin. Data lama tidak ditimpa.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Installer Uji Operasional Tahap 26 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Uji Operasional Tahap 26</h1>
<p>Pengujian manual, temuan dan perbaikan dalam Super Admin Ringkas</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)($admin['name']??'A'),0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator · MFA terverifikasi</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Status Pemasangan</h2>
<p>Prasyarat Tahap 25: <strong><?=$prerequisite?'Kode tersedia':'Belum ditemukan'?></strong></p>
<p>Jurnal hasil uji manual: <strong><?=$ready?'Tersedia':'Belum dibuat'?></strong></p>
<p>Panel Super Admin Ringkas: <strong><?=$linked?'Terhubung':'Belum terhubung'?></strong></p>
<?php if($ready&&$linked):?>
<div class="alert good">Pencatatan uji telah dipasang. Sistem tidak menganggap skenario telah lulus sebelum diuji dan dicatat.</div>
<a class="button" href="/uji-operasional26.php">Buka Uji Operasional Tahap 26 →</a>
<p><a href="/super-admin-satu.php#uji-operasional26">Kembali ke Super Admin Ringkas</a></p>
<?php elseif(!$prerequisite||!is_string($original)):?>
<div class="alert error">Selesaikan pemasangan Tahap 25 dan pastikan halaman Super Admin Ringkas masih tersedia.</div>
<?php else:?>
<p>Installer menambahkan satu tabel jurnal baru pada SmartApp, tanpa mengubah tabel laporan. Sebelum menambah panel pada Super Admin, file aslinya dibackup ke <code>portal_private/uat26_backups/</code>.</p>
<form method="post" action="/instal-uji26.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="page_sha" value="<?=h(hash('sha256',$original))?>">
<button class="button" type="submit">Aktifkan Uji Operasional Tahap 26 →</button></form>
<?php endif;?>
<p class="footnote">Uji harus dilakukan pada akun dummy oleh pengelola yang berwenang. Jangan menyalin data warga atau pegawai asli ke catatan uji. Setelah berhasil, hapus installer dan ZIP dari hosting; simpan backup privat.</p>
</section></main></body></html>
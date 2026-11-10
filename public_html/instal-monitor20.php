<?php
declare(strict_types=1);
/** Stage20 additive installer: new SmartApp tables + versioned backed-up UI links. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Pemasangan Tahap 20 hanya melalui HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/monitor20.php';
    require_once dirname(__DIR__).'/portal_private/monitor20_patch.php';
    $admin=require_super_admin();
    m20_super_guard($admin);
}catch(Throwable $e){http_response_code(503);exit('Akses Super Admin dengan MFA belum tersedia.');}
$dashboard=__DIR__.'/super-admin-satu.php';$center=__DIR__.'/pusat-laporan.php';
$dash=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$staff=is_file($center)&&!is_link($center)?@file_get_contents($center):false;
$panelReady=is_string($dash)&&str_contains($dash,'SMARTCIKOKO_TAHAP20_MONITOR');
$centerReady=is_string($staff)&&str_contains($staff,'SMARTCIKOKO_TAHAP20_INBOX');
$dependencies= v19_dependencies();
$prerequisites=v19_ready()&&!in_array(false,$dependencies,true);
$ready=m20_ready();$error='';$notice='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$prerequisites)throw new RuntimeException('Tahap 15–19 harus dipasang lebih dulu.');
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_monitor_rules20 (
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL PRIMARY KEY,
                followup_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                updated_by_source VARCHAR(25) NULL,
                updated_by_id VARCHAR(100) NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_monitor_reminders20 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                report_version INT UNSIGNED NOT NULL,
                phase ENUM('menunggu_pemeriksa','menunggu_lurah','perbaikan') NOT NULL,
                recipient_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
                recipient_id VARCHAR(100) NOT NULL,
                sender_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
                sender_id VARCHAR(100) NOT NULL,
                message VARCHAR(200) NOT NULL,
                created_at DATETIME NOT NULL,
                read_at DATETIME NULL,
                UNIQUE KEY uq_m20_one_reminder(source_kind,source_id,report_version,phase),
                KEY idx_m20_recipient(recipient_source,recipient_id,id),
                KEY idx_m20_source(source_kind,source_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $q=$db->prepare('INSERT IGNORE INTO unified_monitor_rules20(source_kind,followup_days,enabled,updated_at) VALUES(?,3,0,NOW())');
            foreach(array_keys(m20_sources()) as $source)$q->execute([$source]);
            if(!m20_ready())throw new RuntimeException('Tabel monitoring tidak dapat dibaca.');
            $ready=true;log_event('stage20_installed','system','monitor20');
        }
        if(!$panelReady){
            if(!is_string($dash))throw new RuntimeException('Super Admin Ringkas belum tersedia.');
            m20_apply($dashboard,dirname(__DIR__).'/portal_private',(string)($_POST['dashboard_sha']??''),'dashboard');
            $dash=@file_get_contents($dashboard);
            $panelReady=is_string($dash)&&str_contains($dash,'SMARTCIKOKO_TAHAP20_MONITOR');
        }
        if(!$centerReady&&is_string($staff)){
            try{
                m20_apply($center,dirname(__DIR__).'/portal_private',(string)($_POST['center_sha']??''),'center');
                $staff=@file_get_contents($center);
                $centerReady=is_string($staff)&&str_contains($staff,'SMARTCIKOKO_TAHAP20_INBOX');
            }catch(Throwable $e){error_log('Stage20 staff reminder link could not be inserted: '.get_class($e));}
        }
        $notice='Monitoring sudah disiapkan. Aturan awal NONAKTIF; aktifkan hanya setelah kebijakan tindak lanjut ditentukan.';
    }catch(Throwable $e){
        error_log('Stage20 installation blocked: '.get_class($e));
        $error='Pemasangan belum lengkap. Pastikan Tahap 15–19 siap, CREATE TABLE di SmartApp diizinkan, dan file dashboard dapat diubah dengan backup. Data lama tidak ditimpa.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Monitoring Tahap 20 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Monitoring &amp; Pengingat Tahap 20</h1>
<p>Rekap laporan bulanan, ambang tindak lanjut dan pengingat internal</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Admin terotorisasi</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Kesiapan Pemasangan</h2>
<p>Prasyarat Tahap 15–19: <strong><?=$prerequisites?'Siap':'Belum lengkap'?></strong></p>
<p>Tabel monitoring: <strong><?=$ready?'Siap':'Belum terpasang'?></strong></p>
<p>Panel Super Admin Ringkas: <strong><?=$panelReady?'Terhubung':'Belum terhubung'?></strong></p>
<p>Menu Pengingat Petugas: <strong><?=$centerReady?'Terhubung':'Opsional — belum terhubung'?></strong></p>
<?php if($ready&&$panelReady):?>
<div class="alert good">Tahap 20 sudah terpasang. Aturan pemantauan belum aktif secara otomatis.</div>
<a class="button" href="/super-admin-satu.php#monitor-bulanan">Buka Super Admin Ringkas →</a>
<p><a href="/monitor-laporan20.php">Buka Monitoring Bulanan</a> · <a href="/pengingat-saya20.php">Buka Pengingat Saya</a></p>
<p class="footnote">Setelah pengujian berhasil, hapus public_html/instal-monitor20.php dan ZIP dari hosting. Jangan hapus tabel dan backup privat.</p>
<?php else:?>
<p>Installer hanya membuat dua tabel baru pada SmartApp dan menambahkan panel kecil pada Super Admin Ringkas, serta tautan kotak pengingat pada Pusat Input Laporan. Halaman lama dibackup lebih dahulu di luar public_html.</p>
<?php if(!$prerequisites):?><div class="alert error">Pemasangan ditahan. Pasang Tahap 15, 16, 17 dan 19 lebih dahulu; tidak perlu mengimpor ulang SQL lama.</div>
<?php elseif(!is_string($dash)):?><div class="alert error">Halaman super-admin-satu.php belum ditemukan.</div>
<?php else:?>
<form method="post" action="/instal-monitor20.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$dash))?>">
<input type="hidden" name="center_sha" value="<?=is_string($staff)?h(hash('sha256',$staff)):''?>">
<button class="button" type="submit">Aktifkan Monitoring Tahap 20 →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Pengingat tidak dikirim otomatis dan tidak terhubung ke WhatsApp/SMS/email. Monitoring hanya menghitung laporan yang sudah tercatat, bukan menetapkan kewajiban atau sanksi pegawai.</p>
</section></main></body></html>
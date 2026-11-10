<?php
declare(strict_types=1);
/** Stage21 installer: additive SmartApp schema, backed-up UI links, no cron creation. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk aktivasi modul.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/tasks21.php';
    require_once dirname(__DIR__).'/portal_private/tasks21_patch.php';
    $admin=require_super_admin();
    m20_super_guard($admin);
}catch(Throwable $e){http_response_code(503);exit('Super Admin dengan MFA belum dapat dibuka.');}
$dashboard=__DIR__.'/super-admin-satu.php';$center=__DIR__.'/pusat-laporan.php';
$dash=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$staff=is_file($center)&&!is_link($center)?@file_get_contents($center):false;
$dashLinked=is_string($dash)&&str_contains($dash,'SMARTCIKOKO_TAHAP21_ASSIGNMENTS');
$staffLinked=is_string($staff)&&str_contains($staff,'SMARTCIKOKO_TAHAP21_INBOX');
$deps=v19_dependencies();
$prerequisites=v19_ready()&&m20_ready()&&!in_array(false,$deps,true);
$ready=t21_ready();$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$prerequisites)throw new RuntimeException('Tahap 15–20 perlu dipasang dan diperiksa.');
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_assignments21 (
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                category VARCHAR(50) NOT NULL,
                step ENUM('pemeriksa','lurah') NOT NULL,
                reviewer_source ENUM('pegawai','smartapp') NOT NULL,
                reviewer_id VARCHAR(100) NOT NULL,
                due_days TINYINT UNSIGNED NOT NULL,
                reference_note VARCHAR(180) NOT NULL,
                effective_from DATE NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                updated_by_source VARCHAR(25) NOT NULL,
                updated_by_id VARCHAR(100) NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(source_kind,category,step),
                KEY idx_a21_staff(reviewer_source,reviewer_id,enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_alerts21 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                report_version INT UNSIGNED NOT NULL,
                phase ENUM('menunggu_pemeriksa','menunggu_lurah') NOT NULL,
                recipient_source ENUM('pegawai','smartapp') NOT NULL,
                recipient_id VARCHAR(100) NOT NULL,
                message VARCHAR(240) NOT NULL,
                created_at DATETIME NOT NULL,
                read_at DATETIME NULL,
                UNIQUE KEY uq_a21_once(source_kind,source_id,report_version,phase,recipient_source,recipient_id),
                KEY idx_a21_inbox(recipient_source,recipient_id,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_schedule21 (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                last_run DATETIME NULL,
                last_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                last_incomplete TINYINT(1) NOT NULL DEFAULT 0,
                updated_by_source VARCHAR(25) NULL,
                updated_by_id VARCHAR(100) NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("INSERT IGNORE INTO unified_schedule21(id,enabled,updated_at) VALUES(1,0,NOW())");
            if(!t21_ready())throw new RuntimeException('Tabel penugasan belum dapat dibaca.');
            $ready=true;log_event('stage21_installed','system','tasks21');
        }
        if(!$dashLinked){
            if(!is_string($dash))throw new RuntimeException('Dashboard ringkas belum ditemukan.');
            t21_apply($dashboard,dirname(__DIR__).'/portal_private',(string)($_POST['dashboard_sha']??''),'dashboard');
            $dash=@file_get_contents($dashboard);
            $dashLinked=is_string($dash)&&str_contains($dash,'SMARTCIKOKO_TAHAP21_ASSIGNMENTS');
        }
        if(!$staffLinked&&is_string($staff)){
            try{
                t21_apply($center,dirname(__DIR__).'/portal_private',(string)($_POST['center_sha']??''),'center');
                $staff=@file_get_contents($center);
                $staffLinked=is_string($staff)&&str_contains($staff,'SMARTCIKOKO_TAHAP21_INBOX');
            }catch(Throwable $e){error_log('Stage21 staff inbox link optional patch failed: '.get_class($e));}
        }
        $notice='Penugasan telah disiapkan. Semua penugasan dan izin jadwal NONAKTIF sampai Anda mengonfigurasinya.';
    }catch(Throwable $e){
        error_log('Stage21 installer failed: '.get_class($e));
        $error='Pemasangan belum lengkap. Periksa Tahap 15–20, izin CREATE TABLE dan hak tulis dashboard. Data lama tidak ditimpa.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Aktivasi Penugasan Tahap 21 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Penugasan Tahap 21</h1>
<p>Pemeriksa per kategori, tenggat terdokumentasi, dan notifikasi internal opsional</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Admin dan MFA terverifikasi</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Pemeriksaan Prasyarat</h2>
<p>Laporan dan verifikasi Tahap 15–20: <strong><?=$prerequisites?'Siap':'Belum lengkap'?></strong></p>
<p>Tabel penugasan dan pengingat: <strong><?=$ready?'Siap':'Belum terpasang'?></strong></p>
<p>Panel Super Admin Ringkas: <strong><?=$dashLinked?'Terhubung':'Belum terhubung'?></strong></p>
<p>Tautan Pusat Input: <strong><?=$staffLinked?'Terhubung':'Opsional, belum terhubung'?></strong></p>
<?php if($ready&&$dashLinked):?>
<div class="alert good">Modul penugasan terpasang. Cron Jobs tidak dibuat; izin notifikasi terjadwal nonaktif secara default.</div>
<a class="button" href="/super-admin-satu.php#penugasan-pemeriksa">Buka Super Admin Ringkas →</a>
<p><a href="/tugas-pemeriksa21.php">Atur Penugasan</a> · <a href="/penugasan-saya21.php">Kotak Tugas</a></p>
<p class="footnote">Hapus public_html/instal-tugas21.php dan ZIP dari hosting setelah pengujian. Jangan hapus tabel, riwayat atau backup privat.</p>
<?php else:?>
<p>Installer menambah 3 tabel baru di SmartApp tanpa mengubah tabel Pegawai. Sebelum panel ditambahkan ke dua halaman lama, berkas aslinya disalin ke folder privat.</p>
<?php if(!$prerequisites):?><div class="alert error">Pasang dan uji Tahap 15, 16, 17, 19 dan 20 lebih dahulu. Tidak perlu impor SQL asli.</div>
<?php elseif(!is_string($dash)):?><div class="alert error">Halaman Super Admin Ringkas belum ditemukan.</div>
<?php else:?>
<form method="post" action="/instal-tugas21.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$dash))?>">
<input type="hidden" name="center_sha" value="<?=is_string($staff)?h(hash('sha256',$staff)):''?>">
<button class="button" type="submit">Pasang Penugasan Tahap 21 →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Penugasan di sini hanya untuk koordinasi administratif dan tidak memberikan hak menandatangani surat, menyetujui cuti, menilai PPPK, atau memutuskan layanan warga.</p>
</section></main></body></html>
<?php
declare(strict_types=1);
/** Stage 22 opt-in verifier lock, private backup, no core files overwritten by ZIP. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Installer harus diakses melalui HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/review22.php';
    require_once dirname(__DIR__).'/portal_private/review22_patch.php';
    $admin=require_super_admin();
    m20_super_guard($admin);
}catch(Throwable $e){http_response_code(503);exit('Akses Super Admin atau MFA belum siap.');}
$private=dirname(__DIR__).'/portal_private';
$verify=$private.'/verification19.php';
$dashboard=__DIR__.'/super-admin-satu.php';
$center=__DIR__.'/pusat-laporan.php';
$verificationSource=is_file($verify)&&!is_link($verify)?@file_get_contents($verify):false;
$dashboardSource=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$centerSource=is_file($center)&&!is_link($center)?@file_get_contents($center):false;
$verificationLinked=is_string($verificationSource)&&str_contains($verificationSource,'SMARTCIKOKO_TAHAP22_STRICT_GUARD');
$dashboardLinked=is_string($dashboardSource)&&str_contains($dashboardSource,'SMARTCIKOKO_TAHAP22_POLICY_PANEL');
$centerLinked=is_string($centerSource)&&str_contains($centerSource,'SMARTCIKOKO_TAHAP22_DEPUTY_INBOX');
$dependencies=v19_dependencies();
$prerequisites=v19_ready()&&m20_ready()&&t21_ready()&&!in_array(false,$dependencies,true);
$ready=r22_ready();$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$prerequisites)throw new RuntimeException('Tahap 15–21 belum lengkap.');
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_review_policy22 (
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                category VARCHAR(50) NOT NULL,
                enforced TINYINT(1) NOT NULL DEFAULT 0,
                updated_by_source VARCHAR(25) NOT NULL,
                updated_by_id VARCHAR(100) NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(source_kind,category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_replacements22 (
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                category VARCHAR(50) NOT NULL,
                step ENUM('pemeriksa','lurah') NOT NULL,
                assigned_source ENUM('pegawai','smartapp') NOT NULL,
                assigned_id VARCHAR(100) NOT NULL,
                deputy_source ENUM('pegawai','smartapp') NOT NULL,
                deputy_id VARCHAR(100) NOT NULL,
                starts_on DATE NOT NULL,
                ends_on DATE NOT NULL,
                reason VARCHAR(220) NOT NULL,
                approved_by_source VARCHAR(25) NOT NULL,
                approved_by_id VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                PRIMARY KEY(source_kind,category,step),
                KEY idx_r22_deputy(deputy_source,deputy_id,ends_on)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_review_events22 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                category VARCHAR(50) NOT NULL,
                step VARCHAR(30) NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                details VARCHAR(220) NOT NULL,
                actor_source VARCHAR(25) NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_r22_history(source_kind,category,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if(!r22_ready())throw new RuntimeException('Tabel pengaman Tahap 22 belum dapat dibaca.');
            $ready=true;log_event('stage22_installed','system','review22');
        }
        // Authorization function is patched first; if version differs, installer
        // STOPS before enabling any UI policy controls.
        if(!$verificationLinked){
            if(!is_string($verificationSource))throw new RuntimeException('Modul Tahap 19 belum ditemukan.');
            r22_apply($verify,$private,(string)($_POST['verification_sha']??''),'verification');
            $verificationSource=@file_get_contents($verify);
            $verificationLinked=is_string($verificationSource)&&str_contains($verificationSource,'SMARTCIKOKO_TAHAP22_STRICT_GUARD');
            if(!$verificationLinked)throw new RuntimeException('Pengaman otorisasi Tahap 19 belum tersambung.');
        }
        if(!$dashboardLinked){
            if(!is_string($dashboardSource))throw new RuntimeException('Super Admin Ringkas belum ditemukan.');
            r22_apply($dashboard,$private,(string)($_POST['dashboard_sha']??''),'dashboard');
            $dashboardSource=@file_get_contents($dashboard);
            $dashboardLinked=is_string($dashboardSource)&&str_contains($dashboardSource,'SMARTCIKOKO_TAHAP22_POLICY_PANEL');
        }
        if(!$centerLinked&&is_string($centerSource)){
            try{
                r22_apply($center,$private,(string)($_POST['center_sha']??''),'center');
                $centerSource=@file_get_contents($center);
                $centerLinked=is_string($centerSource)&&str_contains($centerSource,'SMARTCIKOKO_TAHAP22_DEPUTY_INBOX');
            }catch(Throwable $e){error_log('Stage22 optional center link unavailable: '.get_class($e));}
        }
        $notice='Pengaman sudah disambungkan. Semua kebijakan ketat masih NONAKTIF hingga diaktifkan satu per satu.';
    }catch(Throwable $e){
        error_log('Stage22 installer blocked: '.get_class($e));
        $error='Pemasangan belum lengkap. Periksa Tahap 15–21, izin CREATE TABLE dan versi file Tahap 19. Jangan mengganti file lama secara manual; backup tetap tersedia.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Installer Kendali Tahap 22 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Kendali Verifikasi Tahap 22</h1>
<p>Pembatasan pemeriksa sesuai penugasan, cadangan sementara dan monitoring tindakan</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Admin dengan MFA</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Prasyarat dan Kesiapan</h2>
<p>Modul laporan Tahap 15–21: <strong><?=$prerequisites?'Siap':'Belum lengkap'?></strong></p>
<p>Database kebijakan baru: <strong><?=$ready?'Siap':'Belum dipasang'?></strong></p>
<p>Pengaman fungsi Tahap 19: <strong><?=$verificationLinked?'Terhubung':'Belum terhubung'?></strong></p>
<p>Super Admin Ringkas: <strong><?=$dashboardLinked?'Terhubung':'Belum terhubung'?></strong></p>
<p>Tautan Pusat Input: <strong><?=$centerLinked?'Terhubung':'Opsional · belum terhubung'?></strong></p>
<?php if($ready&&$verificationLinked&&$dashboardLinked):?>
<div class="alert good">Pemasangan struktural berhasil. Kebijakan ketat default nonaktif; uji dengan petugas dan Lurah dummy sebelum aktivasi produksi.</div>
<a class="button" href="/super-admin-satu.php#kendali-verifikasi">Buka Super Admin Ringkas →</a>
<p><a href="/kendali-verifikasi22.php">Buka Pengendalian Verifikasi</a> · <a href="/pengganti-saya22.php">Buka Tugas Pengganti</a></p>
<p class="footnote">Hapus public_html/instal-review22.php dan file ZIP setelah pengujian. Backup fungsi Tahap 19 ada di folder privat dan jangan dihapus.</p>
<?php else:?>
<p>Installer menambah tiga tabel baru di SmartApp, menyambungkan pengaman ke fungsi verifikasi Tahap 19, dan menambahkan panel pada satu Super Admin Ringkas. Semua file asli dibackup ke direktori privat sebelum diubah. File yang strukturnya berbeda akan ditolak.</p>
<?php if(!$prerequisites):?><div class="alert error">Pasang dan uji Tahap 15–21 terlebih dahulu. Jangan impor ulang database lama.</div>
<?php elseif(!is_string($verificationSource)||!is_string($dashboardSource)):?><div class="alert error">Halaman Super Admin atau fungsi Tahap 19 belum ditemukan.</div>
<?php else:?>
<form method="post" action="/instal-review22.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="verification_sha" value="<?=h(hash('sha256',$verificationSource))?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$dashboardSource))?>">
<input type="hidden" name="center_sha" value="<?=is_string($centerSource)?h(hash('sha256',$centerSource)):''?>">
<button class="button" type="submit">Pasang Kendali Verifikasi Tahap 22 →</button>
</form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Mode ketat tidak mengesahkan surat RT/RW, permohonan cuti, atau penilaian kinerja ASN. Jika perlu rollback kode, nonaktifkan dulu kebijakan ketat agar hak pemeriksaan tidak melemah saat pengaman dipulihkan.</p>
</section></main></body></html>
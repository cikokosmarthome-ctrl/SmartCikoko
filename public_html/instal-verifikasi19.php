<?php
declare(strict_types=1);
/** Stage 19 additive, one-click: 2 new SmartApp tables, backed-up UI links. */
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Instalasi hanya melalui HTTPS.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/verification19.php';
    require_once dirname(__DIR__).'/portal_private/verification19_patch.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Super Admin dan MFA belum siap.');}
$dashboard=__DIR__.'/super-admin-satu.php';$center=__DIR__.'/pusat-laporan.php';
$original=is_file($dashboard)&&!is_link($dashboard)?@file_get_contents($dashboard):false;
$centerOriginal=is_file($center)&&!is_link($center)?@file_get_contents($center):false;
$panelReady=is_string($original)&&str_contains($original,'SMARTCIKOKO_TAHAP19_VERIFICATION');
$centerReady=is_string($centerOriginal)&&str_contains($centerOriginal,'SMARTCIKOKO_TAHAP19_REVIEWER_LINK');
$ready=v19_ready();$dependencies=v19_dependencies();$prerequisites=!in_array(false,$dependencies,true);$notice='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$prerequisites)throw new RuntimeException('Pasang formulir Tahap 15–17 lebih dahulu.');
        if(!$ready){
            $db=db('smartapp');
            $db->exec("CREATE TABLE IF NOT EXISTS unified_verification19 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                report_version INT UNSIGNED NOT NULL,
                state ENUM('menunggu','perlu_perbaikan','lolos_periksa','diketahui_lurah') NOT NULL,
                reviewer_source VARCHAR(25) NOT NULL,
                reviewer_id VARCHAR(100) NOT NULL,
                review_note VARCHAR(500) NOT NULL DEFAULT '',
                lurah_source VARCHAR(25) NULL,
                lurah_id VARCHAR(100) NULL,
                lurah_note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_v19_report(source_kind,source_id),
                KEY idx_v19_state(state,updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS unified_verification_history19 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_kind ENUM('ringkas15','pegawai16','wilayah17') NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                report_version INT UNSIGNED NOT NULL,
                old_state VARCHAR(30) NOT NULL,
                new_state VARCHAR(30) NOT NULL,
                action VARCHAR(30) NOT NULL,
                note VARCHAR(500) NOT NULL DEFAULT '',
                actor_source VARCHAR(25) NOT NULL,
                actor_id VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_v19_history(source_kind,source_id,id),
                KEY idx_v19_date(created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if(!v19_ready())throw new RuntimeException('Tabel verifikasi tidak dapat dibaca.');
            $ready=true;log_event('stage19_installed','system','verification19');
        }
        if(!$panelReady){
            if(!is_string($original))
                throw new RuntimeException('Super Admin Ringkas belum tersedia. Instal Tahap 15 lebih dulu.');
            v19_apply($dashboard,dirname(__DIR__).'/portal_private',(string)($_POST['dashboard_sha']??''),'dashboard');
            $original=@file_get_contents($dashboard);
            $panelReady=is_string($original)&&str_contains($original,'SMARTCIKOKO_TAHAP19_VERIFICATION');
        }
        if(!$centerReady&&is_string($centerOriginal)){
            try{
                v19_apply($center,dirname(__DIR__).'/portal_private',(string)($_POST['center_sha']??''),'center');
                $centerOriginal=@file_get_contents($center);
                $centerReady=is_string($centerOriginal)&&str_contains($centerOriginal,'SMARTCIKOKO_TAHAP19_REVIEWER_LINK');
            }catch(Throwable $e){error_log('Stage19 optional staff-menu link failed: '.get_class($e));}
        }
        $notice='Tabel verifikasi dan panel Super Admin siap. Uji menggunakan akun petugas dan Lurah dummy.';
    }catch(Throwable $e){
        error_log('Stage19 installer failed: '.get_class($e));
        $error='Instalasi belum lengkap. Periksa izin CREATE TABLE, struktur Super Admin Ringkas, dan izin file. Data lama tidak ditimpa.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Verifikasi Berjenjang Tahap 19</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span>
<h1>Aktivasi Verifikasi Berjenjang</h1><p>Tahap 19 · Pemeriksaan Petugas dan Telaah Administratif Lurah</p></header>
<section class="panel"><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b>
<div><strong><?=h($admin['name'])?></strong><small>Super Administrator yang telah melewati MFA</small></div></div>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Status Modul</h2>
<p>Prasyarat Tahap 15–17: <strong><?=$prerequisites?'Lengkap':'Belum lengkap'?></strong></p>
<?php foreach(['ringkas15'=>'Tahap 15','pegawai16'=>'Tahap 16','wilayah17'=>'Tahap 17'] as $key=>$label):?>
<p><?=h($label)?>: <strong><?=!empty($dependencies[$key])?'Siap':'Perlu Pemasangan / Perbaikan Skema'?></strong></p>
<?php endforeach;?>
<p>Tabel verifikasi: <strong><?=$ready?'Siap':'Belum dipasang'?></strong></p>
<p>Dashboard Super Admin: <strong><?=$panelReady?'Terhubung':'Belum terhubung'?></strong></p>
<p>Menu Pusat Input: <strong><?=$centerReady?'Terhubung':'Opsional — belum terhubung'?></strong></p>
<?php if($ready&&$panelReady):?>
<div class="alert good">Modul sudah terpasang. Tindak lanjut wajib diuji dengan akun berbeda untuk pemeriksa dan Lurah.</div>
<a class="button" href="/super-admin-satu.php#verifikasi-berjenjang">Buka Super Admin Ringkas →</a>
<p><a href="/verifikasi-laporan19.php">Buka Pemeriksaan Berjenjang</a></p>
<p class="footnote">Hapus instal-verifikasi19.php dan ZIP setelah pengujian selesai. Jangan hapus tabel dan riwayat verifikasi.</p>
<?php else:?>
<p>Installer hanya menambah dua tabel di database SmartApp. Panel Super Admin dan tautan petugas ditambahkan melalui perubahan kecil yang dibackup terlebih dahulu di luar public_html.</p>
<?php if(!$prerequisites):?><div class="alert error">Pemasangan tidak dijalankan. Pastikan Tahap 15, 16 dan 17 sudah terpasang dengan tabel laporan berkolom version dan tabel riwayat. Tidak perlu mengimpor ulang SQL lama.</div>
<?php elseif(!is_string($original)):?><div class="alert error">Halaman super-admin-satu.php belum ditemukan. Instal Tahap 15 sebelum melanjutkan.</div>
<?php else:?>
<form method="post" action="/instal-verifikasi19.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="dashboard_sha" value="<?=h(hash('sha256',$original))?>">
<input type="hidden" name="center_sha" value="<?=is_string($centerOriginal)?h(hash('sha256',$centerOriginal)):''?>">
<button class="button" type="submit">Aktifkan Tahap 19 →</button></form>
<?php endif;?>
<?php endif;?>
<p class="footnote">Keterangan: pemeriksaan kelengkapan dan telaah administratif bukan keputusan resmi Lurah, bukan penandatanganan surat, dan tidak mengurangi saldo cuti atau mengubah penilaian PPPK lama.</p>
</section></main></body></html>
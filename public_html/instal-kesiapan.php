<?php
declare(strict_types=1);
/** One-time Stage-12 preparation; no mutation of legacy account or service data. */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Instalasi harus melalui HTTPS.');
}
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    $admin=require_super_admin();
} catch(Throwable $e){
    http_response_code(503);exit('Sesi Super Administrator belum tersedia.');
}
function r12_schema_ready():bool {
    try{
        foreach(['unified_release_checks','unified_release_events'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
$installed=r12_schema_ready();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed){
    verify_csrf();
    try{
        $pdo=db('smartapp');
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_release_checks (
            requirement_key VARCHAR(60) NOT NULL PRIMARY KEY,
            status ENUM('pending','verified','blocked') NOT NULL DEFAULT 'pending',
            evidence_note VARCHAR(250) NOT NULL DEFAULT '',
            updated_by_source VARCHAR(30) NOT NULL,
            updated_by_id VARCHAR(100) NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_release_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            requirement_key VARCHAR(60) NOT NULL,
            old_status VARCHAR(16) NOT NULL,
            new_status VARCHAR(16) NOT NULL,
            actor_source VARCHAR(30) NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_release_time(created_at),
            KEY idx_release_item(requirement_key,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!r12_schema_ready())throw new RuntimeException('Pemeriksaan tabel baru gagal.');
        $installed=true;
        try{log_event('stage12_readiness_installed','system','stage12');}
        catch(Throwable $e){error_log('Stage 12 install audit notification failed');}
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 12 one-click installer failed: '.$e->getMessage());
        $error='Pemasangan belum berhasil. Periksa hak membuat tabel pada database SmartApp, lalu ulangi tanpa mengimpor database lama.';
    }
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2d4b">
<title>Instalasi Tahap 12 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/instal-kesiapan.css"></head>
<body><main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Instalasi Tahap 12</h1><p>Pusat Kesiapan Produksi dan Audit Keamanan</p></header>
<section class="panel">
<div class="identity"><span><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><div><b><?=h($admin['name'])?></b><small>Super Administrator terautentikasi</small></div></div>
<?php if($error):?><p class="alert bad"><?=h($error)?></p><?php endif;?>
<?php if($installed):?>
<p class="alert good"><b>Tahap 12 sudah dipasang.</b> Checklist dan riwayat pemeriksaan telah tersedia.</p>
<a class="button" href="/kesiapan-produksi.php">Buka Pusat Kesiapan Produksi →</a>
<p class="warning">Setelah pengujian, hapus <code>public_html/instal-kesiapan.php</code> serta ZIP dari hosting.</p>
<?php else:?>
<h2>Satu tombol, tanpa SQL manual</h2><p>Installer hanya menambahkan dua tabel audit kesiapan di database SmartApp. Tidak mengubah status pengajuan, data pegawai, arsip, atau peran pengguna.</p>
<p class="step">✓ Autentikasi Super Admin terverifikasi</p>
<p class="step">✓ Dua database lama tetap terpisah</p>
<p class="step">✓ Tidak mengaktifkan pengeluaran surat atau tanda tangan resmi</p>
<form method="post" action="/instal-kesiapan.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="button" type="submit">Pasang Tahap 12 →</button></form>
<?php endif;?>
</section></main></body></html>
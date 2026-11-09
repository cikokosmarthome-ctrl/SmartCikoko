<?php
declare(strict_types=1);
/** One-time installer for Stage 13, login-protected by the existing Super Admin. */
header('Cache-Control: no-store,private,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){
    http_response_code(403);exit('Gunakan HTTPS untuk memasang modul keamanan.');
}
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/security_stage13.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akun Super Administrator atau konfigurasi portal belum siap.');}
$ready=s13_installed();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$ready){
    verify_csrf();
    try{
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('Ekstensi OpenSSL belum tersedia.');
        $keyfile=dirname(__DIR__).'/portal_private/mfa_key.php';
        if(is_link($keyfile))throw new RuntimeException('Penyimpanan kunci MFA tidak aman.');
        $pdo=db('smartapp');
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_super_mfa (
            actor_source ENUM('pegawai','smartapp') NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            secret_cipher VARCHAR(512) NOT NULL,
            last_step BIGINT NOT NULL DEFAULT -1,
            enrolled_at DATETIME NULL,
            PRIMARY KEY (actor_source,actor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_mfa_recovery (
            actor_source ENUM('pegawai','smartapp') NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (actor_source,actor_id,code_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unified_mfa_api_tokens (
            token_hash CHAR(64) NOT NULL PRIMARY KEY,
            actor_source ENUM('pegawai','smartapp') NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_mfa_token_owner(actor_source,actor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(!is_file($keyfile)){
            // x mode prevents accidental overwrite; PHP file outside public_html.
            $handle=@fopen($keyfile,'x');
            if(!$handle)throw new RuntimeException('Tidak dapat membuat kunci rahasia di portal_private.');
            try{
                @chmod($keyfile,0600);
                $secret="<?php\nreturn '".bin2hex(random_bytes(32))."';\n";
                if(fwrite($handle,$secret)!==strlen($secret))throw new RuntimeException('Kunci rahasia belum tersimpan.');
            }finally{fclose($handle);}
        }
        if(!s13_installed())throw new RuntimeException('Pemeriksaan basis data atau kunci MFA gagal.');
        $ready=true;
        log_event('stage13_installed','security','mfa');
    }catch(Throwable $e){
        error_log('Stage 13 installer: '.get_class($e));
        $error='Pemasangan belum selesai. Periksa hak CREATE TABLE pada SmartApp, OpenSSL, dan izin tulis portal_private. Tidak perlu mengimpor ulang SQL.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Instalasi Keamanan Tahap 13 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>SMARTCIKOKO · KELURAHAN CIKOKO</span><h1>Instalasi Tahap 13</h1><p>Autentikasi Dua Faktor, Audit Login dan Kesiapan Antivirus</p></header><section class="panel">
<div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b><div><strong><?=h($admin['name'])?></strong><small>Super Administrator terotorisasi</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($ready):?>
<div class="alert good"><b>Struktur keamanan Tahap 13 terpasang.</b> MFA tidak diwajibkan hingga Anda selesai mendaftar dan memverifikasi kode autentikator.</div>
<?php if(function_exists('is_super_admin_identity')):?>
<a class="button" href="/mfa.php">Daftarkan MFA Super Admin →</a>
<?php else:?>
<p><strong>Persiapan berhasil.</strong> Berikutnya, lakukan backup file inti dan pasang ZIP Aktivasi Tahap 13 sebelum membuka halaman MFA.</p>
<?php endif;?>
<p><a href="/keamanan-superadmin.php">Buka Dashboard Keamanan</a></p>
<p class="footnote">Simpan kunci privat dalam backup terenkripsi, kemudian hapus file installer dan ZIP setelah seluruh pengujian berhasil.</p>
<?php else:?>
<h2>Pasang dengan satu tombol</h2>
<p>Installer menambah tiga tabel MFA pada database SmartApp dan kunci enkripsi unik dalam folder privat. Tidak mengganti password atau data pengguna lama.</p>
<p>Sesudah instalasi, MFA perlu didaftarkan oleh Super Admin sendiri menggunakan kata sandi dan aplikasi autentikator.</p>
<form method="post" action="/instal-keamanan13.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button class="button" type="submit">Pasang Tahap 13 →</button></form>
<?php endif;?>
</section></main></body></html>
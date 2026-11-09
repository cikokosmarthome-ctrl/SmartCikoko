<?php
declare(strict_types=1);
/**
 * Aktivasi Super Administrator sekali pakai.
 * Syarat: sesi admin aktif + kode acak yang tersimpan di luar public_html.
 * Identitas akun dibaca dari sesi, TIDAK perlu memilih ID dari SQL.
 * Berkas ini HARUS dihapus dari public_html setelah aktivasi.
 */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {
    http_response_code(403);
    exit('Buka halaman melalui HTTPS untuk keamanan aktivasi.');
}
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if (!function_exists('is_super_admin')) {
        require_once dirname(__DIR__).'/portal_private/super_admin.php';
    }
} catch(Throwable $e) {
    http_response_code(503);
    exit('Konfigurasi portal belum tersedia. Periksa file aplikasi.');
}
$user=actor();
if (!$user) {
    header('Location: /?page=login',true,303);
    exit;
}
if (!is_admin($user) || !in_array($user['source']??'', ['pegawai','smartapp'],true)) {
    http_response_code(403);
    exit('Aktivasi hanya tersedia bagi akun Administrator yang berwenang.');
}

function admin_still_active(array $u):bool {
    try {
        $source=(string)$u['source'];$id=(string)$u['id'];
        if($source==='pegawai') {
            $q=db('pegawai')->prepare('SELECT role,status FROM users WHERE id=? LIMIT 1');
            $q->execute([$id]);$r=$q->fetch();
            return (bool)$r && $r['status']==='active'
              && in_array($r['role'],['admin','admin_kelurahan','superadmin'],true);
        }
        if($source==='smartapp') {
            $q=db('smartapp')->prepare('SELECT role,active FROM unified_portal_users WHERE id=? LIMIT 1');
            $q->execute([$id]);$r=$q->fetch();
            return (bool)$r && (int)$r['active']===1
              && in_array($r['role'],['admin','admin_kelurahan','superadmin'],true);
        }
    }catch(Throwable $e){error_log('Super Admin activation: identity validation failed.');}
    return false;
}
if(!admin_still_active($user)) {
    http_response_code(403);
    exit('Akun administrator tidak aktif atau tidak memenuhi persyaratan.');
}
$privateDir=dirname(__DIR__).'/portal_private';
$keyFile=$privateDir.'/KODE-SUPERADMIN.txt';
$accessFile=$privateDir.'/super_admin_access.php';
$lockFile=$privateDir.'/aktivasi-super-admin.lock';
$error='';
$done=is_file($accessFile);
if($done && !is_super_admin($user)) {
    http_response_code(403);
    exit('Super Administrator telah diaktifkan untuk akun lain. Hubungi pemilik hosting untuk perubahan.');
}
if(!$done && !is_file($keyFile)) {
    try {
        $secret=strtoupper(bin2hex(random_bytes(24)));
        $fh=@fopen($keyFile,'x');
        if(!$fh)throw new RuntimeException('Gagal membuat kode aktivasi.');
        @chmod($keyFile,0600);
        if(fwrite($fh,$secret."\n")!==strlen($secret)+1) {
            fclose($fh);@unlink($keyFile);
            throw new RuntimeException('Gagal menyimpan kode aktivasi.');
        }
        fclose($fh);
    }catch(Throwable $e) {
        error_log('Super Admin activation key generation failed: '.$e->getMessage());
        $error='Kode aktivasi tidak dapat dibuat. Pastikan folder portal_private dapat ditulis.';
    }
}
function install_progress_schema():void {
    $pdo=db('smartapp');
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS unified_module_progress (
            module_key VARCHAR(32) NOT NULL PRIMARY KEY,
            status ENUM('belum','berjalan','tertunda','selesai') NOT NULL DEFAULT 'belum',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            notes VARCHAR(500) NOT NULL DEFAULT '',
            updated_by_source VARCHAR(32) DEFAULT NULL,
            updated_by_id VARCHAR(100) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            CONSTRAINT chk_progress_pct CHECK (progress_percent BETWEEN 0 AND 100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "INSERT IGNORE INTO unified_module_progress (module_key) VALUES
        ('portal'),('pegawai'),('pppk'),('pjlp'),('waris'),('informasi'),('android')"
    );
}
function add_bootstrap_include(string $file):void {
    $content=@file_get_contents($file);
    if(!is_string($content))throw new RuntimeException('bootstrap.php tidak dapat dibaca.');
    if(str_contains($content,"require_once __DIR__.'/super_admin.php';"))return;
    $needle="require_once __DIR__.'/data.php';";
    if(substr_count($content,$needle)!==1)throw new RuntimeException('Versi bootstrap.php berbeda. Perbarui secara manual.');
    $modified=str_replace($needle,$needle."\nrequire_once __DIR__.'/super_admin.php';",$content);
    $backup=$file.'.sebelum-superadmin-'.date('YmdHis').'.bak';
    if(@file_put_contents($backup,$content,LOCK_EX)!==strlen($content))throw new RuntimeException('Backup bootstrap.php gagal dibuat.');
    @chmod($backup,0600);
    $tmp=$file.'.pending-'.bin2hex(random_bytes(5));
    if(@file_put_contents($tmp,$modified,LOCK_EX)!==strlen($modified)) {
        @unlink($tmp);
        throw new RuntimeException('Bootstrap tidak dapat diperbarui.');
    }
    @chmod($tmp,0640);
    if(!@rename($tmp,$file)) {
        @unlink($tmp);
        throw new RuntimeException('Bootstrap tidak dapat diperbarui.');
    }
}
if(!$done && ($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    verify_csrf();
    $code=strtoupper(trim((string)($_POST['kode']??'')));
    $attempts=(int)($_SESSION['super_admin_activation_attempts']??0);
    if($attempts>=5) {
        $error='Batas percobaan tercapai. Keluar dan masuk kembali sebelum mencoba lagi.';
    } elseif(!preg_match('/^[A-F0-9]{48}$/',$code)) {
        $_SESSION['super_admin_activation_attempts']=$attempts+1;
        $error='Format kode aktivasi tidak sesuai.';
    } else {
        $expected=is_file($keyFile)?strtoupper(trim((string)@file_get_contents($keyFile))):'';
        if(strlen($expected)!==48 || !hash_equals($expected,$code)) {
            $_SESSION['super_admin_activation_attempts']=$attempts+1;
            $error='Kode aktivasi tidak sesuai.';
        } else {
            $lock=@fopen($lockFile,'c');
            if(!$lock||!flock($lock,LOCK_EX)) {
                $error='Aktivasi tidak dapat diproses. Periksa izin folder privat.';
            } else {
                @chmod($lockFile,0600);
                try {
                    if(is_file($accessFile))throw new RuntimeException('Akun Super Admin sudah ditetapkan.');
                    install_progress_schema();
                    add_bootstrap_include($privateDir.'/bootstrap.php');
                    $definition=[
                        ['source'=>(string)$user['source'],'id'=>(string)$user['id']]
                    ];
                    $body="<?php\n/** Super Admin: dibuat sekali melalui aktivasi cPanel. */\nreturn ".var_export($definition,true).";\n";
                    $out=@fopen($accessFile,'x');
                    if(!$out)throw new RuntimeException('Konfigurasi Super Admin tidak dapat disimpan.');
                    @chmod($accessFile,0600);
                    if(fwrite($out,$body)!==strlen($body)) {
                        fclose($out);@unlink($accessFile);
                        throw new RuntimeException('Konfigurasi Super Admin tidak lengkap.');
                    }
                    fclose($out);
                    @unlink($keyFile);
                    $_SESSION['super_admin_activation_attempts']=0;
                    log_event('super_admin_activated','system','super_admin');
                    header('Location: /super-admin.php',true,303);
                    exit;
                } catch(Throwable $e) {
                    error_log('Super Admin activation error: '.$e->getMessage());
                    $error='Aktivasi belum berhasil. Pastikan database SmartApp dan folder portal_private dapat ditulis.';
                } finally {
                    flock($lock,LOCK_UN);
                    fclose($lock);
                }
            }
        }
    }
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#103e68">
<title>Aktivasi Super Administrator | Kelurahan Cikoko</title>
<style>
*{box-sizing:border-box}body{background:#f3f7fb;margin:0;color:#193b58;font:15px/1.6 "Segoe UI",Arial,sans-serif}.wrap{max-width:680px;padding:24px;margin:65px auto}
header{background:linear-gradient(110deg,#0b2e50,#175888);padding:29px;border-radius:16px 16px 0 0;color:white}
header small{font-size:11px;font-weight:800;letter-spacing:1.6px;color:#efc277}
header h1{font-size:27px;line-height:1.25;margin:10px 0}header p{margin:0;color:#dae9f3}
main{padding:30px;background:white;border:1px solid #dbe6f0;border-top:0;border-radius:0 0 16px 16px;box-shadow:0 16px 50px #163e6015}
h2{font-size:18px;color:#0e385b;margin:4px 0 13px}p{margin:8px 0 14px}.person{display:flex;gap:12px;align-items:center;padding:16px;background:#eaf3fb;border-radius:9px;margin-bottom:23px}
.person b{display:block}.person small{color:#6385a2}.step{display:flex;gap:12px;margin:22px 0}.number{width:28px;height:28px;display:grid;place-items:center;flex:0 0 auto;border-radius:50%;background:#15598e;color:white;font-weight:800}.step div{flex:1}.path{display:block;margin:10px 0;padding:11px;background:#edf3f9;border-radius:7px;color:#174b75;font:12px/1.55 ui-monospace,monospace;overflow-wrap:anywhere}
label{font-weight:700;display:block;margin:20px 0 8px}.code{display:block;width:100%;border:1px solid #b7cddd;border-radius:9px;padding:14px;font:14px ui-monospace,monospace;letter-spacing:1px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;background:#155b8d;color:#fff;padding:13px 20px;border-radius:8px;font-weight:800;cursor:pointer}.alert{padding:14px 17px;border-radius:8px;margin:13px 0}.error{background:#fff0f1;color:#9a3b44}.done{background:#ebf9ef;color:#156f3e}footer{font-size:12px;color:#7790a4;text-align:center;padding-top:20px}
</style></head>
<body><div class="wrap"><header>
<small>PORTAL TERPADU KELURAHAN CIKOKO</small>
<h1>Aktivasi Super Administrator</h1>
<p>Jadikan akun yang sedang digunakan sebagai pengendali utama kedua database, tanpa mengubah SQL secara manual.</p>
</header><main>
<div class="person"><span>✓</span><div><b><?=h($user['name'])?></b><small>Akun Administrator aktif · <?=h(strtoupper((string)$user['source']))?></small></div></div>
<?php if($done):?>
<div class="alert done"><b>Super Admin telah aktif untuk akun ini.</b></div>
<a class="btn" href="/super-admin.php">Buka Pusat Kendali →</a>
<?php else:?>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<h2>Cukup satu kali aktivasi</h2>
<div class="step"><span class="number">1</span><div>Buka <b>cPanel → File Manager</b> dan lihat isi file berikut:<code class="path">/home/datd1747/portal_private/KODE-SUPERADMIN.txt</code></div></div>
<div class="step"><span class="number">2</span><div>Salin kode dari file tersebut, lalu tempel pada kotak di bawah. <b>Jangan kirim kode kepada siapa pun.</b></div></div>
<div class="step"><span class="number">3</span><div>Klik <b>Aktifkan Akun Saya</b>. Sistem membuat tabel progres dan menetapkan akun Anda secara otomatis.</div></div>
<form method="post" action="/aktivasi-super-admin.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label for="kode">Kode aktivasi dari cPanel</label>
<input id="kode" name="kode" class="code" type="password" minlength="48" maxlength="48" autocomplete="off" required placeholder="Tempel kode 48 karakter">
<p><button class="btn" type="submit">Aktifkan Akun Saya →</button></p>
</form>
<?php endif;?>
</main><footer>Setelah berhasil, hapus file aktivasi dari public_html. Konfigurasi privat tetap disimpan.</footer></div></body></html>

<?php
declare(strict_types=1);
/**
 * Setup Tahap 6, satu kali oleh Super Admin, tanpa phpMyAdmin.
 * Patches existing private PHP files only if their known integration points
 * match exactly. Makes private backups and writes files atomically.
 */
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
if (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') {
    http_response_code(403);exit('Gunakan HTTPS untuk pemasangan.');
}
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);exit('Portal belum dapat memverifikasi Super Admin.');
}
$root=dirname(__DIR__).'/portal_private';
$bootFile=$root.'/bootstrap.php';
$authFile=$root.'/auth.php';
$controlFile=$root.'/account_control.php';
$error='';$success=false;
function patch_stage6(string $bootstrap,string $auth):array {
    $b1="function actor(): ?array {return \$_SESSION['actor'] ?? null;}";
    $b2="    \$u ??= actor(); if (!\$u) return false; if (is_admin(\$u)) return true;";
    $b3="require_once __DIR__.'/super_admin.php';";
    $a1="    return \$matches;\n}";
    $a2="    return ['source'=>\$token['actor_source'],'id'=>(string)\$u['id'],'username'=>\$u['username'],'name'=>\$u['name'],'role'=>\$u['role']];";
    foreach([[$bootstrap,$b1],[$bootstrap,$b2],[$bootstrap,$b3],[$auth,$a1],[$auth,$a2]] as $pair){
        if(substr_count($pair[0],$pair[1])!==1)throw new RuntimeException('Versi file aplikasi berbeda. Pemasangan otomatis dihentikan tanpa mengubah file.');
    }
    $b1new="function actor(): ?array { \$u=\$_SESSION['actor']??null; if(!\$u)return null; if(function_exists('account_control_allowed')&&!account_control_allowed(\$u)){unset(\$_SESSION['actor']);return null;} return \$u; }";
    $b2new="    \$u ??= actor(); if (!\$u) return false; if (function_exists('account_control_permission_decision')) { \$override=account_control_permission_decision(\$u,\$ability); if (\$override!==null)return \$override; } if (is_admin(\$u)) return true;";
    $bootstrap=str_replace($b1,$b1new,$bootstrap);
    $bootstrap=str_replace($b2,$b2new,$bootstrap);
    $bootstrap=str_replace($b3,$b3."\nrequire_once __DIR__.'/account_control.php';",$bootstrap);
    $a1new="    if(function_exists('account_control_allowed')) {\n        \$matches=array_values(array_filter(\$matches,'account_control_allowed'));\n    }\n    return \$matches;\n}";
    $a2new="    \$account=['source'=>\$token['actor_source'],'id'=>(string)\$u['id'],'username'=>\$u['username'],'name'=>\$u['name'],'role'=>\$u['role']];\n    if(function_exists('account_control_allowed')&&!account_control_allowed(\$account))return null;\n    return \$account;";
    $auth=str_replace($a1,$a1new,$auth);
    $auth=str_replace($a2,$a2new,$auth);
    return [$bootstrap,$auth];
}
function atomic_private_write(string $path,string $content):void {
    $tmp=$path.'.stage6.'.bin2hex(random_bytes(6));
    if(@file_put_contents($tmp,$content,LOCK_EX)!==strlen($content)){
        @unlink($tmp);throw new RuntimeException('File perubahan tidak dapat ditulis.');
    }
    @chmod($tmp,0640);
    if(!@rename($tmp,$path)){
        @unlink($tmp);throw new RuntimeException('File perubahan tidak dapat dipasang.');
    }
}
function schema_stage6():void {
    $pdo=db('smartapp');
    $pdo->exec("CREATE TABLE IF NOT EXISTS unified_account_access (
        actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
        actor_id VARCHAR(100) NOT NULL,
        login_allowed TINYINT(1) NOT NULL DEFAULT 1,
        updated_by_source VARCHAR(30) NOT NULL,
        updated_by_id VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY(actor_source,actor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS unified_module_access (
        actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
        actor_id VARCHAR(100) NOT NULL,
        ability ENUM('pppk','pjlp','waris') NOT NULL,
        decision ENUM('grant','deny') NOT NULL,
        updated_by_source VARCHAR(30) NOT NULL,
        updated_by_id VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY(actor_source,actor_id,ability)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS unified_access_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
        actor_id VARCHAR(100) NOT NULL,
        action_type ENUM('login','permission') NOT NULL,
        ability VARCHAR(32) NOT NULL DEFAULT '',
        old_value VARCHAR(16) NOT NULL,
        new_value VARCHAR(16) NOT NULL,
        changed_by_source VARCHAR(30) NOT NULL,
        changed_by_id VARCHAR(100) NOT NULL,
        changed_at DATETIME NOT NULL,
        KEY idx_history_timestamp (changed_at),
        KEY idx_history_target (actor_source,actor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach(['unified_account_access','unified_module_access','unified_access_history'] as $table){
        $pdo->query('SELECT COUNT(*) FROM '.$table.' LIMIT 1')->fetchColumn();
    }
}
$bootstrap=@file_get_contents($bootFile);
$auth=@file_get_contents($authFile);
$installed=is_string($bootstrap)&&str_contains($bootstrap,"require_once __DIR__.'/account_control.php';")
           &&is_string($auth)&&str_contains($auth,'account_control_allowed');
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!$installed) {
    verify_csrf();
    $lock=@fopen($root.'/pasang-tahap6.lock','c');
    if(!$lock || !flock($lock,LOCK_EX)){
        $error='Sistem sedang menyiapkan pembaruan lain. Coba kembali.';
    }else{
        @chmod($root.'/pasang-tahap6.lock',0600);
        try{
            if(!is_file($controlFile))throw new RuntimeException('File account_control.php tidak ditemukan.');
            if(!is_string($bootstrap)||!is_string($auth))throw new RuntimeException('File autentikasi tidak dapat dibaca.');
            [$newBootstrap,$newAuth]=patch_stage6($bootstrap,$auth);
            // Validate both patch targets before writing anything.
            schema_stage6();
            $stamp=date('YmdHis').'-'.bin2hex(random_bytes(4));
            $backupBoot=$bootFile.'.sebelum-tahap6-'.$stamp.'.bak';
            $backupAuth=$authFile.'.sebelum-tahap6-'.$stamp.'.bak';
            if(@file_put_contents($backupBoot,$bootstrap,LOCK_EX)!==strlen($bootstrap))throw new RuntimeException('Gagal mencadangkan bootstrap.');
            @chmod($backupBoot,0600);
            if(@file_put_contents($backupAuth,$auth,LOCK_EX)!==strlen($auth))throw new RuntimeException('Gagal mencadangkan autentikasi.');
            @chmod($backupAuth,0600);
            try{
                atomic_private_write($bootFile,$newBootstrap);
                atomic_private_write($authFile,$newAuth);
            }catch(Throwable $writeError){
                // Restore both originals if either write fails.
                atomic_private_write($bootFile,$bootstrap);
                atomic_private_write($authFile,$auth);
                throw $writeError;
            }
            $installed=true;$success=true;
            log_event('account_management_installed','system','stage6');
        }catch(Throwable $e) {
            error_log('SmartCikoko stage 6 installer: '.$e->getMessage());
            $error='Pemasangan belum berhasil. File asli tetap dicadangkan bila proses penulisan telah dimulai. Periksa izin tulis folder portal_private; apabila kode server sudah dimodifikasi, jangan memaksa penggantian.';
        }finally{
            flock($lock,LOCK_UN);fclose($lock);
        }
    }
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Pasang Tahap 6 | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/pasang-tahap6.css">
</head><body>
<main class="install">
<header><span class="eyebrow">SMARTCIKOKO / PEMBARUAN TERPADU</span><h1>Aktivasi Tahap 6</h1><p>Manajemen Pengguna dan Hak Akses Portal</p></header>
<div class="main">
<div class="actor"><span class="avatar"><?=h(mb_strtoupper(mb_substr($admin['name'],0,1)))?></span><span><strong><?=h($admin['name'])?></strong><small>Super Administrator terautentikasi</small></span></div>
<?php if($error):?><p class="message error"><?=h($error)?></p><?php endif;?>
<?php if($installed):?>
<p class="message success"><strong>Modul manajemen pengguna telah dipasang.</strong> Anda dapat membuka pengelolaan akun sekarang.</p>
<a class="btn" href="/kelola-pengguna.php">Buka Manajemen Pengguna ↗</a>
<p class="warning">Untuk keamanan, <b>hapus file public_html/pasang-tahap6.php</b> dan arsip ZIP dari hosting setelah pengujian.</p>
<?php else:?>
<h2>Instalasi satu tombol</h2>
<p>Sistem membuat dua tabel izin akses di database SmartApp dan menghubungkan pemeriksaan akses ke portal yang sudah berjalan. Akun, kata sandi, dan database sumber tidak dipindahkan atau dihapus.</p>
<div class="check"><span>✓</span><div>Super Administrator berhasil dikenali</div></div>
<div class="check"><span>✓</span><div>Kedua database tetap terpisah</div></div>
<div class="check"><span>✓</span><div>Perubahan sistem dilengkapi backup otomatis</div></div>
<form method="post" action="/pasang-tahap6.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<button type="submit" class="btn">Pasang Manajemen Pengguna ↗</button></form>
<?php endif;?>
</div></main></body></html>
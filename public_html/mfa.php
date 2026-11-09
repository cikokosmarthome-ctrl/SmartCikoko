<?php
declare(strict_types=1);
header('Cache-Control: no-store,private,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/security_stage13.php';
    $u=actor();
    if(!$u || !is_super_admin_identity($u)){http_response_code(403);exit('Halaman ini khusus akun Super Administrator.');}
    if(!s13_installed()){http_response_code(503);exit('Pemasangan keamanan Tahap 13 belum tersedia.');}
}catch(Throwable $e){http_response_code(503);exit('Layanan MFA belum tersedia.');}
$error='';$notice='';
$enrolled=s13_enrolled($u);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='logout'){
            end_actor_session();header('Location: /?page=login',true,303);exit;
        }
        if(!$enrolled && $action==='reauth'){
            $password=(string)($_POST['password']??'');
            throttle_login((string)$u['username'].'|mfa-enroll');
            $verified=false;
            foreach(login_matches((string)$u['username'],$password) as $m){
                if($m['source']===$u['source'] && hash_equals((string)$m['id'],(string)$u['id'])){$verified=true;break;}
            }
            if(!$verified)throw new RuntimeException('Verifikasi kata sandi belum berhasil.');
            clear_login_limit((string)$u['username'].'|mfa-enroll');
            $_SESSION['s13_pending']=[
                'secret'=>s13_secret_new(),'expires'=>time()+600,
                'source'=>$u['source'],'id'=>(string)$u['id']
            ];
            $notice='Kata sandi telah diverifikasi. Tambahkan kunci ke aplikasi autentikator, lalu masukkan kode enam digit.';
        }elseif(!$enrolled && $action==='enroll'){
            $pending=$_SESSION['s13_pending']??null;
            if(!is_array($pending)||($pending['expires']??0)<time()||
                $pending['source']!==$u['source']||$pending['id']!==(string)$u['id'])
                throw new RuntimeException('Sesi pendaftaran berakhir. Ulangi verifikasi kata sandi.');
            throttle_login((string)$u['username'].'|mfa-code');
            $codes=s13_enroll($u,(string)$pending['secret'],trim((string)($_POST['otp']??'')));
            clear_login_limit((string)$u['username'].'|mfa-code');
            $_SESSION['s13_recovery_once']=$codes;
            header('Location: /mfa.php?recovery=1',true,303);exit;
        }elseif($enrolled && $action==='verify'){
            throttle_login((string)$u['username'].'|mfa-code');
            if(!s13_mfa_verified($u,trim((string)($_POST['code']??'')))){
                log_event('mfa_failed','security','challenge');
                throw new RuntimeException('Kode autentikator atau kode cadangan tidak valid.');
            }
            clear_login_limit((string)$u['username'].'|mfa-code');
            s13_mark_verified($u);
            log_event('mfa_verified','security','challenge');
            header('Location: /super-admin.php',true,303);exit;
        }else throw new RuntimeException('Tindakan tidak tersedia.');
    }catch(Throwable $e){
        error_log('SmartCikoko MFA action rejected: '.get_class($e));
        $error=$e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage():'Verifikasi tidak berhasil. Periksa kode dan coba lagi.';
    }
}
$recovery=[];
if($enrolled && ($_GET['recovery']??'')==='1' && isset($_SESSION['s13_recovery_once'])){
    $recovery=$_SESSION['s13_recovery_once'];
    unset($_SESSION['s13_recovery_once']);
}
$pending=$_SESSION['s13_pending']??null;
if(is_array($pending) && (($pending['expires']??0)<time()||$pending['source']!==$u['source']||$pending['id']!==(string)$u['id'])){
    unset($_SESSION['s13_pending']);$pending=null;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0a3153">
<title>Verifikasi Dua Langkah | SmartCikoko</title>
<link rel="stylesheet" href="/assets/mfa.css"></head><body>
<main class="shell"><header><span>PORTAL TERPADU KELURAHAN CIKOKO</span><h1>Verifikasi Dua Langkah</h1>
<p>Perlindungan tambahan untuk Pusat Super Administrator</p></header>
<section class="panel">
<div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$u['name'],0,1)))?></b>
<div><strong><?=h($u['name'])?></strong><small>Super Administrator terotorisasi</small></div></div>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($notice):?><div class="alert good"><?=h($notice)?></div><?php endif;?>
<?php if($recovery):?>
<h2>Simpan Kode Cadangan Sekarang</h2>
<p>Setiap kode hanya dapat digunakan sekali. Kode ini tidak dapat ditampilkan kembali. Simpan di tempat aman yang terpisah dari kata sandi.</p>
<div class="recovery">
<?php foreach($recovery as $code):?><code><?=h($code)?></code><?php endforeach;?>
</div><a class="button" href="/super-admin.php">Saya Sudah Menyimpan Kode →</a>
<?php elseif($enrolled):?>
<h2>Masukkan kode autentikator</h2>
<p>Buka aplikasi autentikator untuk akun ini dan masukkan kode enam digit. Kode cadangan juga dapat dipakai jika perangkat tidak tersedia.</p>
<form method="post" action="/mfa.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="verify">
<label>Kode 6 Digit atau Kode Cadangan<input name="code" autocomplete="one-time-code" spellcheck="false" required maxlength="19" inputmode="text" autofocus placeholder="000000"></label>
<button class="button" type="submit">Verifikasi dan Masuk →</button></form>
<?php elseif($pending):?>
<h2>Hubungkan aplikasi autentikator</h2>
<p>Tambahkan akun secara manual pada Google Authenticator, Microsoft Authenticator, atau aplikasi TOTP yang mendukung kode enam digit setiap 30 detik.</p>
<div class="secret-label">Nama akun: SmartCikoko · <?=h($u['username'])?></div>
<div class="secret"><?=h(implode(' ',str_split((string)$pending['secret'],4)))?></div>
<p class="hint">Jenis: berbasis waktu (TOTP) · Algoritma SHA-1 · 6 digit · 30 detik. Jangan membagikan kunci ini atau tangkapan layar halaman.</p>
<form method="post" action="/mfa.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="enroll">
<label>Kode Autentikator<input name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required placeholder="123456"></label>
<button class="button" type="submit">Aktifkan MFA →</button></form>
<?php else:?>
<h2>Aktifkan Perlindungan MFA</h2>
<p>Sebelum membuat kunci autentikator, konfirmasi kembali kata sandi akun Super Admin Anda. MFA tidak akan diaktifkan sebelum kode autentikator berhasil diverifikasi.</p>
<form method="post" action="/mfa.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="reauth">
<label>Kata Sandi Saat Ini<input name="password" type="password" autocomplete="current-password" required maxlength="256"></label>
<button class="button" type="submit">Verifikasi Kata Sandi →</button></form>
<?php endif;?>
<form class="signout" method="post" action="/mfa.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout">
<button type="submit">Keluar dari Akun</button></form>
<p class="footnote">MFA tidak meminta Anda mengirim kode, password, atau kunci ke admin melalui WhatsApp. Jika kode cadangan hilang, pemulihan memerlukan proses verifikasi pengelola secara terpisah.</p>
</section></main></body></html>
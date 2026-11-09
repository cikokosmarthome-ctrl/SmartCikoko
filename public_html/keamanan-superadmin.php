<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/security_stage13.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Pusat keamanan belum tersedia.');}
$installed=s13_installed();
$enrolled=$installed && s13_enrolled($admin);
$scan=cfg()['upload_scanning']??[];
$enforce=($scan['enforce']??false)===true;
$socket=(string)($scan['clamd_socket']??'');
$scannerReady=$enforce && $socket!=='' && file_exists($socket) && is_readable($socket);
$https=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
$sessionFlags=session_get_cookie_params();
$cookieSafe=!empty($sessionFlags['secure'])&&!empty($sessionFlags['httponly']);
$audit=[];$failed=0;$error='';
try {
    $pdo=db('smartapp');
    $q=$pdo->query("SELECT event_type,created_at FROM unified_audit WHERE event_type IN ('login','logout','login_failed','mfa_failed','mfa_verified','mfa_enrolled','mfa_api_failed','mfa_recovery_used') ORDER BY created_at DESC LIMIT 25");
    $audit=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->query("SELECT COUNT(*) FROM unified_audit WHERE event_type IN ('login_failed','mfa_failed','mfa_api_failed') AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
    $failed=(int)$q->fetchColumn();
}catch(Throwable $e){
    error_log('Stage 13 security audit report not available.');
    $error='Riwayat aktivitas belum dapat ditampilkan. Periksa koneksi database SmartApp.';
}
$indicators=[
    ['name'=>'Koneksi HTTPS','ok'=>$https,'detail'=>'Seluruh proses login dan sesi harus memakai HTTPS.'],
    ['name'=>'Cookie sesi aman','ok'=>$cookieSafe,'detail'=>'Periksa atribut Secure dan HttpOnly.'],
    ['name'=>'MFA Super Admin','ok'=>$enrolled,'detail'=>$enrolled?'Pendaftaran TOTP berhasil; kode tambahan diwajibkan.':'Belum didaftarkan. MFA menjadi wajib setelah pendaftaran selesai.'],
    ['name'=>'Antivirus untuk unggahan','ok'=>$scannerReady,'detail'=>$enforce?'Pemindaian diwajibkan; kegagalan koneksi harus menolak unggahan.':'Belum diwajibkan. Jangan gunakan dokumen warga nyata sebelum scanner tersedia dan diuji.'],
    ['name'=>'Audit autentikasi','ok'=>$error==='','detail'=>'Log aktivitas masuk, keluar, dan kegagalan login terbaru.'],
];
function s13_event_label(string $code):string{
    return [
        'login'=>'Login berhasil','logout'=>'Keluar akun','login_failed'=>'Login gagal',
        'mfa_failed'=>'Kode MFA ditolak','mfa_verified'=>'MFA berhasil',
        'mfa_enrolled'=>'Pendaftaran MFA','mfa_api_failed'=>'MFA Android gagal',
        'mfa_recovery_used'=>'Kode pemulihan dipakai'
    ][$code]??'Aktivitas keamanan';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3050">
<title>Pusat Keamanan Super Admin | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/keamanan13.css"></head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a class="brand" href="/"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks"><a href="/super-admin.php">Pusat Super Admin</a><a href="/kesiapan-produksi.php">Kesiapan Produksi</a><a class="selected" href="/keamanan-superadmin.php">Keamanan</a></nav>
</div></header>
<main><section class="hero"><div class="wrap hero-inner"><div>
<span class="kicker">TAHAP 13 / PERLINDUNGAN AKSES</span><h1>Pusat Keamanan Super Admin</h1>
<p>Status autentikasi dua faktor, perlindungan unggahan, dan aktivitas akun dalam satu panel pengawasan.</p></div>
<div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><span><small>TEROTORISASI</small><strong><?=h($admin['name'])?></strong><em>Super Administrator</em></span></div>
</div></section>
<div class="wrap content">
<div class="status-bar"><div><small>STATUS PENGAMANAN</small><h2>Pengujian Keamanan Berlangsung</h2><p>Belum merupakan sertifikasi keamanan atau persetujuan produksi.</p></div>
<span>PERLU VERIFIKASI HOSTING</span></div>
<div class="cards">
<?php foreach($indicators as $item):?>
<div class="status-card"><div><strong><?=h($item['name'])?></strong><span class="<?=$item['ok']?'ok':'warn'?>"><?=$item['ok']?'Terdeteksi':'Perlu Tindakan'?></span></div>
<p><?=h($item['detail'])?></p></div>
<?php endforeach;?>
</div>
<div class="modules">
<section class="panel"><span class="eyebrow">KENDALI AKUN</span><h2>Autentikasi Dua Faktor</h2>
<p><?=h($enrolled?'MFA telah aktif untuk akun ini. Setiap sesi yang memerlukan akses Super Admin harus melewati verifikasi kode.':'Daftarkan akun Super Admin menggunakan kata sandi dan aplikasi TOTP. Pendaftaran tidak mengubah akun biasa.')?></p>
<a class="button" href="/mfa.php"><?=$enrolled?'Buka Halaman MFA →':'Daftarkan MFA Sekarang →'?></a>
<p class="helper">Simpan delapan kode pemulihan pada saat pendaftaran. Jika seluruh akses MFA hilang, pemulihan memerlukan verifikasi terpisah oleh pengelola hosting.</p>
</section>
<section class="panel"><span class="eyebrow">PERLINDUNGAN LAMPIRAN</span><h2>Pemeriksaan Antivirus</h2>
<p><?=h($enforce?'Kebijakan ClamAV diwajibkan pada proses unggah. Bila pemindai gagal dihubungi, unggahan ditolak.':'Pemeriksaan antivirus belum diwajibkan pada hosting. Validasi tipe dan SHA-256 tidak membuktikan file bebas malware.')?></p>
<div class="scanner-data"><span>Mode unggahan</span><strong><?=$enforce?'Wajib dipindai':'Belum diwajibkan'?></strong></div>
<div class="scanner-data"><span>Socket pemindai</span><strong><?=$scannerReady?'Terdeteksi':'Belum terverifikasi'?></strong></div>
<p class="helper">Konfigurasi socket ClamAV hanya melalui pengaturan privat server. Jangan memasukkan alamat socket atau password ke kolom yang dapat diakses publik. Pengujian berkas EICAR di lingkungan terisolasi tetap diperlukan sebelum penggunaan nyata.</p>
</section>
</div>
<section class="panel audit"><span class="eyebrow">RIWAYAT AUTENTIKASI</span><h2>25 Aktivitas Terakhir</h2>
<p>Riwayat tidak menampilkan password, OTP, alamat IP lengkap, atau identitas pengguna.</p>
<div class="audit-count"><strong><?=$failed?></strong><span>kegagalan login/MFA yang tercatat dalam 24 jam terakhir</span></div>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?>
<div class="table-container"><table><thead><tr><th>Waktu</th><th>Aktivitas</th></tr></thead><tbody>
<?php if(!$audit):?><tr><td colspan="2">Belum ada riwayat yang tersedia.</td></tr><?php endif;?>
<?php foreach($audit as $record):?><tr><td><?=h($record['created_at'])?> WIB</td><td><?=h(s13_event_label((string)$record['event_type']))?></td></tr><?php endforeach;?></tbody></table></div>
</section>
<div class="security-note"><strong>Langkah Sebelum Digunakan Untuk Pelayanan Nyata</strong><p>Aktifkan dan uji MFA serta pemulihan akun; verifikasi kebijakan antivirus pada cPanel; jalankan uji akses Warga A/B, PTSP, Lurah dan Super Admin; siapkan backup terenkripsi dan audit keamanan. Jika antivirus belum tersedia, jangan menganggap unggahan dokumen pribadi sudah aman.</p></div>
</div></main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Keamanan Internal</div></footer></body></html>
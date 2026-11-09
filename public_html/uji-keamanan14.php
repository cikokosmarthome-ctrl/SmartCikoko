<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/security_audit14.php';
    $admin=require_super_admin();
    $report=t14_status_report($admin);
}catch(Throwable $e){
    error_log('SmartCikoko Stage 14 dashboard check failed: '.get_class($e));
    http_response_code(503);
    exit('Pemeriksaan keamanan sementara belum tersedia. Periksa login MFA dan konfigurasi.');
}
$labels=[
 'owner_read'=>'Warga dapat membaca pengajuannya sendiri',
 'other_denied'=>'Warga lain ditolak',
 'ptsp_read'=>'PTSP dapat memeriksa pengajuan',
 'lurah_read'=>'Lurah dapat melihat pengajuan',
 'staff_cannot_submit'=>'PTSP tidak dianggap sebagai warga pengaju',
 'warga_not_officer'=>'Warga tidak dianggap sebagai petugas',
];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b304e">
<title>Uji Keamanan Tahap 14 | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/uji-keamanan14.css"></head>
<body>
<div class="govbar"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap navinner">
<a href="/" class="brand"><span class="crest">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav><a href="/super-admin.php">Super Admin</a><a href="/kesiapan-produksi.php">Kesiapan Produksi</a><a href="/keamanan-superadmin.php">Tahap 13</a><a class="on" href="/uji-keamanan14.php">Uji Keamanan</a></nav>
</div></header>
<section class="hero"><div class="wrap hero-inner"><div><span class="eyebrow">TAHAP 14 / PENGAWASAN PERLINDUNGAN DOKUMEN</span>
<h1>Pemeriksaan Keamanan Pelayanan</h1><p>Diagnostik server, kondisi penyimpanan dokumen, pemeriksaan antivirus, dan simulasi aturan akses. Semua pemeriksaan bersifat baca-saja.</p></div>
<div class="identity"><span><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><div><small>AKSES TERBATAS</small><strong><?=h($admin['name'])?></strong><em>Super Administrator</em></div></div></div></section>
<main class="wrap content">
<div class="summary">
<div><small>HASIL DIAGNOSTIK</small><strong><?=$report['passed']?> / <?=$report['total']?></strong><span>Indikator yang memenuhi pemeriksaan saat ini</span></div>
<div><small>WAKTU PEMERIKSAAN</small><strong class="time"><?=h($report['checked_at'])?></strong><span>Waktu server aplikasi (WIB)</span></div>
<div><small>STATUS PRODUKSI</small><strong class="pending">Belum Disahkan</strong><span>Hasil otomatis bukan sertifikasi keamanan</span></div>
</div>
<section class="block">
<div class="heading"><span class="eyebrow">MONITORING OTOMATIS</span><h2>Keamanan Hosting dan Aplikasi</h2>
<p>Hasil tidak menampilkan password database, rahasia MFA, lokasi socket, atau nama dokumen warga.</p></div>
<div class="checks">
<?php foreach($report['findings'] as $item):?>
<div class="check"><div><strong><?=h($item['title'])?></strong><span class="<?=$item['ok']?'pass':'fail'?>"><?=$item['ok']?'Memenuhi':'Perlu Tindakan'?></span></div>
<p><?=h($item['detail'])?></p></div>
<?php endforeach;?>
</div></section>
<section class="block">
<div class="heading"><span class="eyebrow">SIMULASI LOGIKA</span><h2>Pemisahan Hak Akses</h2>
<p>Hanya menggunakan identitas dan permohonan dummy di memori; hasil ini tidak menggantikan pengujian akun Warga A/B, PTSP, dan Lurah di lingkungan staging.</p></div>
<div class="simulations">
<?php foreach($labels as $key=>$label):?>
<div class="sim"><span><?=h($label)?></span><strong class="<?=!empty($report['simulations'][$key])?'pass':'fail'?>"><?=!empty($report['simulations'][$key])?'Lulus Simulasi':'Perlu Ditinjau'?></strong></div>
<?php endforeach;?>
</div></section>
<section class="block">
<div class="heading"><span class="eyebrow">PROSEDUR PENGUJIAN NYATA</span><h2>Pemeriksaan yang Tetap Diperlukan</h2>
<p>Gunakan akun dan dokumen dummy. Jangan menguji dengan informasi pribadi warga sungguhan.</p></div>
<ol class="steps">
<li>Uji login Super Admin: password, kode autentikator, logout, lalu login ulang. Pastikan MFA tidak bisa dilewati.</li>
<li>Gunakan Warga A dan Warga B: B harus ditolak saat membuka permohonan dan lampiran A dengan ID yang diketahui.</li>
<li>Uji PTSP dan Lurah: hanya peran berwenang yang boleh mengubah status sesuai alur Tahap 9.</li>
<li>Konfirmasikan layanan pemindai malware ke Rumahweb, lalu uji koneksi dan pemindaian melalui prosedur keamanan yang disetujui.</li>
<li>Periksa backup terenkripsi, pemulihan data, retensi arsip, dan audit akses sebelum dokumen warga asli diunggah.</li>
</ol>
<div class="actions"><a href="/kesiapan-produksi.php">Lihat Checklist Pra-Produksi ↗</a><a href="/keamanan-superadmin.php">Lihat Keamanan MFA ↗</a></div></section>
<div class="warning"><strong>Belum merupakan izin operasional</strong><p>Indikator “Memenuhi” hanya menunjukkan pemeriksaan teknis yang terbatas. Pemindai ClamAV yang belum aktif tidak boleh dinyatakan sudah melindungi unggahan. Pengujian keamanan independen, kebijakan privasi, dan persetujuan instansi masih diperlukan sebelum proses dokumen pribadi warga nyata.</p></div>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Audit Keamanan Internal</div></footer>
</body></html>
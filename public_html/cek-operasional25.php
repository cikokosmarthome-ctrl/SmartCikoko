<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/audit25.php';
    $admin=require_super_admin();
    if(!account_control_allowed($admin))throw new RuntimeException('Akun tidak aktif.');
    $report=a25_report($admin,dirname(__DIR__).'/portal_private',__DIR__);
}catch(Throwable $e){
    error_log('Stage25 privileged diagnostic failed: '.get_class($e));
    http_response_code(503);
    exit('Pemeriksaan operasional belum tersedia. Login Super Admin dan periksa pemasangan.');
}
$groups=[
    'Database'=>'Koneksi dua database',
    'Modul'=>'Kesiapan modul laporan dan verifikasi',
    'Konsistensi'=>'Ketepatan ringkasan laporan',
    'Hak Akses'=>'Kebijakan pembatasan verifikasi',
    'Pengingat'=>'Pengingat internal',
    'Navigasi'=>'Keberadaan berkas modul',
];
$captions=['pass'=>'Terbaca','attention'=>'Perlu diperiksa','unavailable'=>'Tidak tersedia'];
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0c3556">
<title>Pemeriksaan Operasional Tahap 25 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/cek-operasional25.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="top"><div class="wrap bar">
<a class="brand" href="/"><span class="crest">KC</span><span><b>SMARTCIKOKO</b><small>Pemeriksaan Operasional Internal</small></span></a>
<nav><a href="/super-admin-satu.php#cek-operasional">Super Admin Ringkas</a>
<a href="/pusat-operasional23.php">Pusat Operasional</a>
<a href="/cek-operasional25.php?ulang=1">Periksa Ulang</a></nav></div></header>
<main class="wrap main">
<div class="heading"><div><small>TAHAP 25 · AUDIT KESIAPAN TEKNIS TERBATAS</small>
<h1>Pemeriksaan Operasional <em>SmartCikoko</em></h1>
<p>Membandingkan angka status laporan, kesiapan modul, dan keterhubungan layanan tanpa membuat laporan, mengubah data, atau membuka identitas warga.</p></div>
<div class="identity"><span><?=h(mb_strtoupper(mb_substr((string)($admin['name']??'A'),0,1)))?></span>
<div><b>Super Administrator</b><small>MFA terverifikasi · <?=h($report['generated_at'])?> WIB</small></div></div></div>
<div class="notice">Pemeriksaan ini hanya memvalidasi fungsi baca dan keberadaan kode. **Tidak** menguji pengajuan, persetujuan resmi, atau operasional Cron Jobs dan tidak menggantikan uji langsung dengan akun dummy.</div>
<div class="metrics">
<div><small>Pemeriksaan terbaca</small><strong><?=$report['totals']['pass']?></strong><span>Terbatas pada cek teknis</span></div>
<div><small>Perlu peninjauan</small><strong><?=$report['totals']['attention']?></strong><span>Bukan otomatis kegagalan layanan</span></div>
<div><small>Tidak tersedia</small><strong><?=$report['totals']['unavailable']?></strong><span>Periksa izin dan kode</span></div>
<div><small>Sumber laporan</small><strong><?=$report['counts']['available']?> / 3</strong><span>Tahap 15–17</span></div>
</div>
<section class="panel">
<div class="section-title"><h2>Kesesuaian Data Ringkasan</h2><span class="muted">Agregat tanpa nama, NIK, atau isi laporan</span></div>
<div class="sources">
<?php foreach(['15'=>'Ringkasan','16'=>'Kepegawaian','17'=>'Kewilayahan'] as $stage=>$title):
$info=$report['counts']['by_stage'][$stage]??['ready'=>false,'total'=>null];
$source=$report['source_totals'][$stage]??null;?>
<div class="source"><small>Tahap <?=h($stage)?> · <?=h($title)?></small>
<strong><?=!empty($info['ready'])?number_format((int)$info['total'],0,',','.'): '—'?></strong>
<span>Hitung sumber independen:
<?=is_int($source)?number_format($source,0,',','.'): 'belum terbaca'?></span></div>
<?php endforeach;?></div>
<p class="subtle">Angka hanya mencakup **tabel laporan tambahan Tahap 15–17**. Data kepegawaian asli, surat RT/RW, pelayanan Ahli Waris dan persetujuan cuti tidak otomatis dihitung di sini.</p>
</section>
<?php foreach($groups as $group=>$heading):
$items=array_values(array_filter($report['entries'],static fn($entry)=>$entry['group']===$group));
if(!$items)continue;?>
<section class="panel">
<div class="section-title"><h2><?=h($heading)?></h2><span class="muted"><?=count($items)?> pemeriksaan</span></div>
<div class="checks">
<?php foreach($items as $entry):?>
<div class="check">
<div><b><?=h($entry['label'])?></b><p><?=h($entry['detail'])?></p></div>
<span class="status status-<?=h($entry['state'])?>"><?=h($captions[$entry['state']]??'Tidak dikenal')?></span>
</div><?php endforeach;?></div>
</section>
<?php endforeach;?>
<section class="panel">
<div class="section-title"><h2>Uji Langsung yang Masih Diperlukan</h2><span class="muted">Gunakan akun dan laporan dummy</span></div>
<ol class="manual">
<li>PPPK: buat draf dan kirim laporan; periksa statusnya dari akun asal dan ringkasan Super Admin.</li>
<li>PJLP dan cuti: pastikan formulir tersedia sesuai peran; pengajuan cuti tidak dianggap sudah disetujui.</li>
<li>RT/RW, LMK, Jumantik, FKDM, PPSU: uji formulir yang diizinkan untuk masing-masing akun dummy.</li>
<li>Pemeriksa dan Lurah: uji alur Tahap 19 sesuai penugasan dan pembatasan Tahap 22 yang sudah dikonfigurasi.</li>
<li>Pengganti: uji penunjukan, masa berlaku, pencabutan, dan penolakan akun yang tidak ditugaskan.</li>
<li>Pengingat: uji pesan internal dan tandai dibaca; jika Cron Jobs aktif, cek log cron pada cPanel secara terpisah.</li>
<li>Periksa halaman pada desktop dan ponsel; pastikan menu khusus Super Admin tidak muncul pada akun biasa.</li>
</ol>
</section>
<div class="footer-actions"><a class="button" href="/super-admin-satu.php#cek-operasional">Kembali ke Super Admin Ringkas</a>
<a class="button outlined" href="/cek-operasional25.php?ulang=1">Jalankan Pemeriksaan Ulang</a></div>
<p class="footnote"><?=h($report['limitations'])?> Bila ada hasil “Tidak tersedia”, simpan tangkapan layar dan periksa log PHP privat. Jangan mengirim kata sandi, token MFA, atau data warga.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pemeriksaan Internal Baca-Saja</div></footer>
</body></html>
<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
} catch(Throwable $e) {
    http_response_code(503);exit('Sistem belum tersedia. Hubungi pengelola aplikasi.');
}
if (!function_exists('require_super_admin')) {
    require_once dirname(__DIR__).'/portal_private/super_admin.php';
}
$actor=require_super_admin();
$errors='';
$notice=(string)($_SESSION['super_admin_notice']??'');
unset($_SESSION['super_admin_notice']);
$modules=super_admin_modules();
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    verify_csrf();
    try {
        $key=(string)($_POST['module_key']??'');
        $status=(string)($_POST['status']??'');
        $percent=filter_var($_POST['progress_percent']??null,FILTER_VALIDATE_INT);
        if($percent===false)throw new InvalidArgumentException('Persentase progres harus antara 0 dan 100.');
        $notes=trim((string)($_POST['notes']??''));
        super_admin_set_progress($actor,$key,$status,$percent,$notes);
        $_SESSION['super_admin_notice']='Progres modul berhasil disimpan.';
        header('Location: /super-admin.php',true,303);exit;
    } catch(Throwable $e) {
        $errors='Progres belum tersimpan. Periksa status dan migrasi tabel modul.';
        error_log('SmartCikoko Super Admin progress update error: '.$e->getMessage());
    }
}
$summary=super_admin_metrics();
$progress=super_admin_progress();
$tracked=0;$sum=0;$completed=0;$ongoing=0;
foreach($progress as $row) {
    $tracked++;$sum+=(int)$row['progress_percent'];
    if($row['status']==='selesai')$completed++;
    if($row['status']==='berjalan')$ongoing++;
}
$overall=$tracked? (int)round($sum/$tracked):null;
function fmt(?int $n):string{return $n===null?'—':number_format($n,0,',','.');}
function status_label(string $s):string{return match($s) {
    'berjalan'=>'Sedang Dikerjakan','tertunda'=>'Memerlukan Tindak Lanjut',
    'selesai'=>'Selesai','belum'=>'Belum Dimulai',default=>'Belum Dikonfigurasi'
};}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Super Admin | SmartCikoko Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
</head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
  <a class="brand" href="/"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
  <div class="navlinks"><a href="/">Beranda</a><a href="/?page=dashboard">Ruang Kerja</a><a class="selected" href="/super-admin.php">Pusat Super Admin</a><a href="/direktori-akun.php">Direktori Akun</a><a href="/kelola-pengguna.php">Manajemen Pengguna</a><a href="/operasional.php">Pusat Operasional</a><a href="/verifikasi-layanan.php">Verifikasi & Laporan</a><a href="/pengajuan-warga.php">Pengajuan Masyarakat</a><a href="/tindak-lanjut.php">Tindak Lanjut</a><a href="/?page=profil">Profil</a></div>
</div></header>
<main>
<section class="hero"><div class="wrap hero-inner"><div>
  <span class="kicker">PUSAT PENGENDALIAN TERPADU</span><h1>Super Administrator</h1>
  <p>Satu ruang kendali untuk administrasi pegawai, pelayanan masyarakat, dan progres pengembangan SmartCikoko.</p>
</div><div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr($actor['name'],0,1)))?></span><span><small>AKSES TEROTORISASI</small><strong><?=h($actor['name'])?></strong><em>Super Administrator · <?=h(strtoupper($actor['source']))?></em></span></div></div></section>
<div class="wrap content">
<?php if($errors):?><p class="alert error"><?=h($errors)?></p><?php endif;?>
<?php if($notice):?><p class="alert success"><?=h($notice)?></p><?php endif;?>
<section aria-labelledby="overview-title">
<div class="section-head"><div><span class="eyebrow">STATUS OPERASIONAL</span><h2 id="overview-title">Ringkasan Sistem</h2><p>Data aktual berasal dari dua database yang tetap terpisah.</p></div><span class="updated">Diperiksa: <?=h(date('d/m/Y H:i'))?> WIB</span></div>
<div class="connections">
<?php foreach(['pegawai'=>'Database Pegawai','smartapp'=>'Database SmartApp'] as $key=>$label):?>
<div class="connection"><span class="dot <?=$summary['connections'][$key]?'green':'red'?>"></span><div><strong><?=h($label)?></strong><small><?=$summary['connections'][$key]?'Terhubung dan merespons':'Tidak dapat dihubungi'?></small></div><span class="pill <?=$summary['connections'][$key]?'connected':'interrupted'?>"><?=$summary['connections'][$key]?'Aktif':'Gangguan'?></span></div>
<?php endforeach;?>
</div>
<div class="metric-grid">
<?php foreach($summary['metrics'] as $metric):?>
<div class="metric"><small><?=h($metric['title'])?></small><strong><?=fmt($metric['value'])?></strong><span><?=h($metric['source']==='pegawai'?'Database Kepegawaian':'Database Pelayanan')?></span></div>
<?php endforeach;?>
</div>
<div class="accounts-shortcut"><div><strong>Manajemen Pengguna Terpadu</strong><p>Periksa akun, atur akses login, dan tentukan izin modul sesuai kewenangan. Perubahan tercatat dalam audit sistem.</p></div><a class="btn outline" href="/kelola-pengguna.php">Kelola Pengguna →</a></div>
<div class="accounts-shortcut"><div><strong>Pusat Operasional Pegawai dan Pelayanan</strong><p>Tinjau laporan PPPK/PJLP, pantau data pegawai, dan tindak lanjuti pengajuan ahli waris dari satu halaman.</p></div><a class="btn outline" href="/operasional.php">Buka Pusat Operasional →</a></div>
<div class="accounts-shortcut"><div><strong>Verifikasi Berkas dan Rekap Administrasi</strong><p>Periksa kelengkapan dokumen ahli waris secara privat dan lihat laporan agregat bulanan tanpa mengungkap data pribadi.</p></div><a class="btn outline" href="/verifikasi-layanan.php">Buka Verifikasi & Laporan →</a></div>
</section>
<section class="progress-section" aria-labelledby="progress-title">
<div class="section-head"><div><span class="eyebrow">PEMANTAUAN PEKERJAAN</span><h2 id="progress-title">Progres Seluruh Modul</h2><p>Persentase merupakan laporan pekerjaan yang diisi Super Admin, bukan penilaian otomatis.</p></div></div>
<div class="overview-progress">
 <div class="overview-total"><small>PROGRES RATA-RATA</small><strong><?= $overall===null?'—':$overall.'%' ?></strong><span><?=fmt($completed)?> dari <?=count($modules)?> modul ditandai selesai</span></div>
 <div class="overall-track"><progress value="<?=max(0,$overall??0)?>" max="100" aria-label="Progres keseluruhan"></progress><div class="overall-label"><span><?=fmt($ongoing)?> modul dalam pengerjaan</span><span>Target 100%</span></div></div>
</div>
<?php if(count($progress)!==count($modules)):?><div class="alert warn"><strong>Tabel progres belum lengkap.</strong> Impor <code>portal_private/schema-superadmin.sql</code> melalui phpMyAdmin ke database SmartApp, kemudian muat ulang halaman. Grafik yang belum disiapkan tidak akan dihitung sebagai pekerjaan selesai.</div><?php endif;?>
<div class="module-grid">
<?php foreach($modules as $key=>$module): $row=$progress[$key]??null; $pct=(int)($row['progress_percent']??0);$status=(string)($row['status']??''); ?>
<article class="module">
<div class="module-top"><span class="source"><?=h($module['source']==='pegawai'?'KEPEGAWAIAN':'PORTAL & LAYANAN')?></span><span class="status <?=h($status)?>"><?=h(status_label($status))?></span></div>
<h3><?=h($module['label'])?></h3>
<div class="progress-head"><strong><?= $row? $pct.'%':'—' ?></strong><span>Progres terlapor</span></div>
<progress value="<?=$pct?>" max="100" aria-label="Progres <?=h($module['label'])?>"></progress>
<?php if($row && trim((string)$row['notes'])!==''):?><p class="note"><?=h($row['notes'])?></p><?php else:?><p class="note muted">Belum terdapat catatan pelaksanaan.</p><?php endif;?>
<div class="module-actions">
 <a class="btn outline" href="<?=h($module['url'])?>">Buka Modul ↗</a>
 <?php if($row):?><a class="text-link" href="#edit-<?=h($key)?>">Perbarui progres ↓</a><?php endif;?>
</div>
<?php if($row):?>
<details id="edit-<?=h($key)?>" class="edit"><summary>Kelola status pekerjaan</summary>
<form method="post" action="/super-admin.php">
 <input type="hidden" name="csrf" value="<?=h(csrf())?>">
 <input type="hidden" name="module_key" value="<?=h($key)?>">
 <label>Status
 <select name="status" required>
 <?php foreach(['belum','berjalan','tertunda','selesai'] as $option):?>
 <option value="<?=h($option)?>" <?=$status===$option?'selected':''?>><?=h(status_label($option))?></option>
 <?php endforeach;?>
 </select></label>
 <label>Progres (%)<input type="number" min="0" max="100" step="1" name="progress_percent" required value="<?=$pct?>"></label>
 <label>Catatan tindak lanjut<textarea name="notes" maxlength="500" rows="3" placeholder="Contoh: verifikasi akun PPPK belum selesai"><?=h($row['notes'])?></textarea></label>
 <button class="btn primary" type="submit">Simpan Progres</button>
</form></details>
<?php endif;?>
</article>
<?php endforeach;?>
</div>
</section>
<div class="accounts-shortcut"><div><strong>Pengajuan Pelayanan dan Persetujuan Berjenjang</strong><p>Pantau pengajuan baru yang dibuat warga, pemeriksaan PTSP, serta keputusan Lurah, tanpa mengubah arsip pelayanan lama.</p></div><a class="btn outline" href="/pengajuan-warga.php">Buka Pengajuan Baru →</a></div>
<div class="accounts-shortcut"><div><strong>Lampiran dan Pemberitahuan Pengajuan</strong><p>Tinjau lampiran privat, status permohonan, pemberitahuan warga, dan bukti informasi yang bukan surat resmi.</p></div><a class="btn outline" href="/tindak-lanjut.php">Buka Tindak Lanjut →</a></div>
<section class="guide" aria-label="Catatan pelaksanaan">
<div><span class="eyebrow">PENGENDALIAN DAN AKUNTABILITAS</span><h2>Setiap modul, satu pusat kendali.</h2><p>Super Admin dapat melihat ringkasan lintas database dan menuju masing-masing modul sesuai kewenangan. Pembaruan progres dicatat dalam audit sistem. Data pegawai serta data warga tidak dipublikasikan pada panel ringkasan ini.</p></div>
<a href="/pusat-kendali.php" class="btn outline">Lihat Ringkasan Administrator ↗</a>
</section>
</div></main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Super Administrator <span>Akses terbatas dan tercatat</span></div></footer>
</body></html>
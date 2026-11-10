<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/unified_reports.php';
    $admin=require_super_admin(); // Retains the full Stage-13 MFA gate.
}catch(Throwable $e){http_response_code(503);exit('Pusat Super Administrator belum tersedia.');}
$alert=(string)($_SESSION['sa15_notice']??'');unset($_SESSION['sa15_notice']);
$error='';
$cat=(string)($_GET['kategori']??'');
if($cat!==''&&!isset(lr_categories()[$cat]))$cat='';
$ready=lr_ready();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['action']??'');
        if($action==='report_review'){
            if(!$ready)throw new RuntimeException('Pusat Laporan belum dipasang.');
            lr_review($admin,(int)($_POST['report_id']??0),(string)($_POST['status']??''),trim((string)($_POST['notes']??'')));
            $_SESSION['sa15_notice']='Tindak lanjut laporan tercatat di pusat laporan.';
            header('Location: /super-admin-satu.php?kategori='.rawurlencode($cat).'#laporan',true,303);exit;
        }elseif($action==='module_progress'){
            $percent=filter_var($_POST['progress_percent']??null,FILTER_VALIDATE_INT);
            if($percent===false)throw new InvalidArgumentException('Progres 0–100 diperlukan.');
            super_admin_set_progress($admin,(string)($_POST['module_key']??''),(string)($_POST['status']??''),$percent,trim((string)($_POST['notes']??'')));
            $_SESSION['sa15_notice']='Kemajuan pekerjaan sudah diperbarui.';
            header('Location: /super-admin-satu.php#kemajuan',true,303);exit;
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
    }catch(Throwable $e){error_log('One-page Super Admin action failed: '.get_class($e));$error='Tindakan belum dapat disimpan. Periksa kewenangan, status dan tabel sistem.';}
}
$summary=super_admin_metrics();
$catalog=lr_categories();
$native=lr_native_counts();
$counts=$ready?lr_new_counts():[];
$list=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
if($ready)try{$list=lr_query($admin,$cat,1,15);}
catch(Throwable $e){error_log('Compact Super Admin report list failed.');$error='Daftar laporan belum dapat dibaca.';}
$progress=super_admin_progress();$modules=super_admin_modules();
$pct=$progress?(int)round(array_sum(array_column($progress,'progress_percent'))/count($progress)):0;
function sa15_count(?int $n):string {return $n===null?'—':number_format($n,0,',','.');}
function sa15_progress_status(string $s):string{return ['belum'=>'Belum','berjalan'=>'Berjalan','tertunda'=>'Tertunda','selesai'=>'Selesai'][$s]??'Belum Dikonfigurasi';}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b304c">
<title>Super Admin Terpadu | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin-satu.css"></head><body>
<div class="govtop"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="bar"><div class="wrap barinner"><a class="brand" href="/"><span>KC</span><b>SMARTCIKOKO<small>Pusat Super Administrator</small></b></a>
<nav><a href="#ringkasan">Ringkasan</a><a href="#laporan">Laporan</a><a href="#modul">Modul</a><a href="#kemajuan">Progres</a><a href="#pengaturan">Keamanan</a><a href="/pusat-laporan.php">Input Laporan</a></nav>
</div></header>
<main class="wrap">
<div class="heading">
<div><small>RUANG KENDALI TERPADU</small><h1>Super Admin <em>SmartCikoko</em></h1><p>Satu layar untuk memantau dua database, laporan, pengguna, pelayanan, dan keamanan.</p></div>
<div class="actor"><span><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><div><b><?=h($admin['name'])?></b><small>MFA terverifikasi · <?=h(strtoupper($admin['source']))?></small></div></div>
</div>
<?php if($alert):?><div class="alert good"><?=h($alert)?></div><?php endif;?>
<?php if($error):?><div class="alert bad"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="alert warn">Pusat Input Laporan belum dipasang. Jalankan installer Tahap 15 untuk mengaktifkan input dan tinjauan laporan baru. Data lama tetap dapat ditampilkan.</div><?php endif;?>
<section id="ringkasan" class="section">
<div class="title"><h2>Ringkasan Operasional</h2><small>Data aktual, tanpa membuka identitas pribadi</small></div>
<div class="signals">
<?php foreach(['pegawai'=>'Database Pegawai','smartapp'=>'Database SmartApp'] as $key=>$name):?>
<div class="signal"><i class="<?=!empty($summary['connections'][$key])?'online':'offline'?>"></i><span><?=h($name)?></span><b><?=!empty($summary['connections'][$key])?'Aktif':'Gangguan'?></b></div>
<?php endforeach;?>
<div class="signal"><i class="<?=$ready?'online':'offline'?>"></i><span>Input Laporan Terpadu</span><b><?=$ready?'Aktif':'Belum Dipasang'?></b></div>
</div>
<div class="quick-metrics">
<?php foreach($summary['metrics'] as $metric):?>
<div><small><?=h($metric['title'])?></small><strong><?=sa15_count($metric['value'])?></strong></div>
<?php endforeach;?>
</div></section>
<section id="laporan" class="section">
<div class="title"><h2>Pusat Seluruh Laporan</h2><a class="link" href="/pusat-laporan.php">Buka Form Input →</a></div>
<p class="hint">Kolom “Lama” membaca rekap dari database asli, sedangkan “Baru” berasal dari formulir laporan ringkas. Tidak ada migrasi atau penggabungan data pribadi.</p>
<div class="report-cards">
<?php foreach($catalog as $key=>$mod):?>
<a class="report-card <?=($cat===$key)?'chosen':''?>" href="/super-admin-satu.php?kategori=<?=h(rawurlencode($key))?>#laporan">
<strong><?=h($mod['label'])?></strong>
<span>Lama <b><?=sa15_count($native[$key]??null)?></b></span>
<span>Baru <b><?=isset($counts[$key])?(int)$counts[$key]:'—'?></b></span>
</a>
<?php endforeach;?>
</div>
<div class="records">
<div class="records-heading"><div><h3>Antrean Input Terbaru</h3><small><?=(int)$list['total']?> laporan<?php if($cat!==''):?> · <?=h($catalog[$cat]['label'])?><?php endif;?></small></div>
<form method="get" action="/super-admin-satu.php" class="filter">
<label for="kategori">Filter</label><select name="kategori" id="kategori">
<option value="">Semua Kategori</option>
<?php foreach($catalog as $key=>$mod):?><option value="<?=h($key)?>" <?=$cat===$key?'selected':''?>><?=h($mod['label'])?></option><?php endforeach;?></select>
<button type="submit">Tampilkan</button></form></div>
<div class="records-list">
<?php if(!$list['rows']):?><p class="empty">Belum ada input laporan baru. Rekap lama tetap ada pada kartu di atas.</p><?php endif;?>
<?php foreach($list['rows'] as $r):?>
<details class="report-item">
<summary><span><b><?=h($r['title'])?></b><small><?=h($catalog[$r['category']]['label']??$r['category'])?> · <?=h($r['activity_date'])?></small></span>
<span class="state"><?=h(lr_stages()[$r['status']]??$r['status'])?></span></summary>
<div class="report-detail"><p><?=nl2br(h($r['body']))?></p><small>Lokasi: <?=h($r['location']?:'—')?> · Selesai: <?=h($r['end_date']?:'—')?></small>
<form class="inline-review" method="post" action="/super-admin-satu.php?kategori=<?=h(rawurlencode($cat))?>#laporan">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="report_review">
<input type="hidden" name="report_id" value="<?=(int)$r['id']?>">
<label>Status<select name="status"><?php foreach(lr_stages() as $s=>$label):?><option value="<?=h($s)?>" <?=$r['status']===$s?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
<label>Catatan<input type="text" name="notes" maxlength="500" placeholder="Alasan dan tindak lanjut"></label>
<button type="submit">Simpan</button></form>
</div></details>
<?php endforeach;?></div>
<?php if($list['pages']>1):?><p class="smallnote">Menampilkan 15 laporan terbaru. Gunakan <a href="/pusat-laporan.php?kategori=<?=h(rawurlencode($cat))?>">halaman laporan lengkap</a> untuk melihat daftar berikutnya.</p><?php endif;?>
</div></section>
<section id="modul" class="section">
<div class="title"><h2>Akses Cepat Seluruh Modul</h2><small>Pengaturan lanjutan tidak memenuhi halaman utama</small></div>
<div class="actions">
<a href="/pusat-laporan.php"><b>Input Laporan</b><small>Form dan riwayat sesuai peran</small></a>
<a href="/kelola-pengguna.php"><b>Pengguna & Izin</b><small>Hak akses dan status akun</small></a>
<a href="/operasional.php"><b>PPPK / PJLP / Pegawai</b><small>Data operasional asli</small></a>
<a href="/pengajuan-warga.php"><b>Ahli Waris</b><small>Permohonan dan pemeriksaan</small></a>
<a href="/verifikasi-layanan.php"><b>Verifikasi Layanan</b><small>Dokumen dan rekap bulanan</small></a>
<a href="/tindak-lanjut.php"><b>Tindak Lanjut</b><small>Lampiran dan pemberitahuan</small></a>
</div></section>
<section id="kemajuan" class="section">
<div class="title"><h2>Progres Pengembangan</h2><span class="progress-meta">Rata-rata <?=$pct?>%</span></div>
<progress max="100" value="<?=$pct?>"></progress>
<details class="advanced"><summary>Ubah progres <?=count($modules)?> modul</summary>
<div class="progress-list">
<?php foreach($modules as $key=>$mod):$r=$progress[$key]??null;$status=(string)($r['status']??'belum');?>
<details class="progress-item"><summary><span><?=h($mod['label'])?></span><b><?=(int)($r['progress_percent']??0)?>% · <?=h(sa15_progress_status($status))?></b></summary>
<?php if($r):?><form method="post" action="/super-admin-satu.php#kemajuan">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="module_progress"><input type="hidden" name="module_key" value="<?=h($key)?>">
<select name="status"><?php foreach(['belum','berjalan','tertunda','selesai'] as $choice):?><option value="<?=h($choice)?>" <?=$status===$choice?'selected':''?>><?=h(sa15_progress_status($choice))?></option><?php endforeach;?></select>
<input type="number" min="0" max="100" name="progress_percent" value="<?=(int)$r['progress_percent']?>" required>
<input type="text" name="notes" maxlength="500" value="<?=h($r['notes'])?>" placeholder="Catatan">
<button type="submit">Simpan</button></form>
<?php else:?><p class="smallnote">Tabel progres belum siap. Data tidak ditulis otomatis.</p><?php endif;?></details>
<?php endforeach;?></div></details></section>
<section id="pengaturan" class="section">
<div class="title"><h2>Keamanan dan Pengaturan</h2><small>Terhubung dengan MFA dan modul yang sudah ada</small></div>
<details class="advanced"><summary>Kelola keamanan, arsip, sistem dan aktivitas lanjutan</summary>
<div class="advanced-links">
<a href="/keamanan-superadmin.php">MFA & Audit Login</a>
<a href="/uji-keamanan14.php">Uji Keamanan Dokumen</a>
<a href="/arsip-keamanan.php">Arsip Digital</a>
<a href="/kesiapan-produksi.php">Kesiapan Produksi</a>
<a href="/direktori-akun.php">Direktori Akun</a>
<a href="/draf-administrasi.php">Draf Administrasi</a>
<a href="/?page=berita">Informasi Publik</a>
<a href="/?page=agenda">Agenda</a>
<a href="/super-admin.php">Dashboard Versi Lama (Cadangan)</a>
</div></details>
</section>
<p class="notice">**Pusat Super Admin ini tidak memindahkan data atau memberi kewenangan persetujuan resmi kepada administrator.** Rekap lama dan input baru ditampilkan terpisah; akses setiap formulir mengikuti peran akun. Seluruh transaksi tetap melewati login, MFA, CSRF, dan kontrol akses.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Kendali Terpadu</div></footer>
</body></html>
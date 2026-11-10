<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/monitor20.php';
    $admin=require_super_admin();
    m20_super_guard($admin);
}catch(Throwable $e){http_response_code(503);exit('Pusat monitoring Super Admin belum tersedia.');}
$month=(string)($_GET['bulan']??date('Y-m'));
try{m20_month($month);}catch(Throwable $e){$month=date('Y-m');}
$ready=m20_ready();$error='';$notice=(string)($_SESSION['m20_notice']??'');unset($_SESSION['m20_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Modul monitoring belum diinstal.');
        $action=(string)($_POST['action']??'');
        if($action==='rule'){
            $days=filter_var($_POST['followup_days']??null,FILTER_VALIDATE_INT);
            if($days===false)throw new InvalidArgumentException('Ambang hari harus berupa angka.');
            m20_rule_change($admin,(string)($_POST['source_kind']??''),(int)$days,($_POST['enabled']??'')==='1');
            $_SESSION['m20_notice']='Aturan monitoring berhasil diperbarui. Tidak ada pengingat dikirim otomatis.';
        }elseif($action==='reminder'){
            m20_remind($admin,(string)($_POST['source_kind']??''),(int)($_POST['report_id']??0),
                (int)($_POST['version']??0),(string)($_POST['phase']??''));
            $_SESSION['m20_notice']='Pengingat internal berhasil dicatat, tanpa mengirim WhatsApp atau email.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        header('Location: /monitor-laporan20.php?bulan='.rawurlencode($month),true,303);exit;
    }catch(Throwable $e){
        error_log('Stage20 Super Admin action failed: '.get_class($e));
        $error=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)
            ?$e->getMessage():'Tindakan tidak berhasil. Periksa status dan izin akun.';
    }
}
$rules=$ready?m20_rules():[];
$monthly=['modules'=>[],'incomplete'=>[],'month'=>$month];
$queue=['rows'=>[],'count_in_sample'=>0,'incomplete'=>false,'enabled_rules'=>0];
if($ready){
    try{$monthly=m20_monthly($admin,$month);$queue=m20_queue($admin,60);}
    catch(Throwable $e){error_log('Stage20 dashboard unavailable');$error='Monitoring belum dapat dimuat dari database.';}
}
$labels=m20_phase_labels();$sources=m20_sources();
$sum=0;foreach($monthly['modules'] as $row)$sum+=(int)$row['submitted'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b3556">
<title>Monitoring Bulanan dan Pengingat | SmartCikoko</title>
<link rel="stylesheet" href="/assets/monitor20.css"></head><body>
<div class="gov"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a class="brand" href="/"><span>KC</span><b>SMARTCIKOKO<small>Monitoring Administrasi Terpadu</small></b></a>
<nav><a href="/super-admin-satu.php#monitor-bulanan">Super Admin Ringkas</a><a href="/rekap-laporan18.php">Rekap Tahap 18</a><a href="/pengingat-saya20.php">Pengingat Saya</a></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 20 · MONITORING KINERJA LAPORAN</small><h1>Monitoring Bulanan dan Tindak Lanjut</h1>
<p>Ringkasan jumlah laporan, status administrasi, dan antrean yang melampaui batas hari internal. Ini bukan penilaian kinerja ASN/PJLP atau penetapan keterlambatan disiplin kerja.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="flash success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="flash warning">Instalasi Tahap 20 belum diselesaikan. Minta Super Admin menjalankan installer satu tombol setelah Tahap 19 siap.</div><?php endif;?>
<section class="panel">
<div class="panel-head"><div><h2>Aktivitas Laporan Bulanan</h2><p class="sub">Periode tanggal input awal. Tidak mencerminkan kewajiban yang belum dikirim atau hasil penilaian resmi.</p></div>
<form method="get" action="/monitor-laporan20.php" class="month"><label for="bulan">Pilih bulan</label><input id="bulan" name="bulan" type="month" value="<?=h($month)?>" required><button class="btn" type="submit">Tampilkan</button></form></div>
<div class="top-stats"><div><small>Total laporan diajukan pada bulan tersebut</small><strong><?=$sum?></strong></div>
<div><small>Modul sumber siap dibaca</small><strong><?=count($sources)-count($monthly['incomplete'])?> / <?=count($sources)?></strong></div>
<div><small>Aturan pengingat aktif</small><strong><?=(int)$queue['enabled_rules']?> / <?=count($sources)?></strong></div></div>
<?php if($monthly['incomplete']):?><div class="flash warning">Rekap belum lengkap: <?=h(implode(', ',$monthly['incomplete']))?>. Periksa tabel modul sebelumnya.</div><?php endif;?>
<div class="cards">
<?php foreach($sources as $source=>$meta):$m=$monthly['modules'][$source]??['submitted'=>0,'correction'=>0,'closed'=>0,'categories'=>[]];?>
<div class="module-card"><h3><?=h($meta['label'])?></h3>
<div class="module-numbers"><span><b><?=(int)$m['submitted']?></b><small>Diajukan*</small></span>
<span><b><?=(int)$m['correction']?></b><small>Perlu perbaikan**</small></span>
<span><b><?=(int)$m['closed']?></b><small>Ditutup**</small></span></div>
<details><summary>Lihat jenis laporan</summary>
<?php if(!$m['categories']):?><p class="sub">Tidak ada laporan untuk bulan ini.</p><?php endif;?>
<?php foreach($m['categories'] as $kind=>$n):?><div class="category"><span><?=h($kind)?></span><b><?=(int)$n?></b></div><?php endforeach;?>
</details>
</div>
<?php endforeach;?>
</div>
<p class="smallnote">* Berdasarkan laporan yang pertama kali dibuat dalam bulan terpilih dan sudah dikirim. ** Status pada saat halaman dibuka; bukan jumlah tindakan sepanjang bulan dan bukan persetujuan resmi.</p>
</section>
<section class="panel">
<div class="panel-head"><div><h2>Aturan Pengingat Internal</h2><p class="sub">Tidak ada batas waktu yang diasumsikan sebagai kebijakan instansi. Super Admin menentukan ambang pemantauan (1–60 hari kalender).</p></div></div>
<div class="flash warning">Semua aturan <b>nonaktif saat pemasangan awal</b>. Setelah diaktifkan, daftar laporan perlu tindak lanjut muncul. Pengingat hanya dibuat jika Super Admin menekan tombol; tidak ada SMS, email, WhatsApp, ataupun pengiriman otomatis.</div>
<div class="rules">
<?php foreach($sources as $source=>$meta):$rule=$rules[$source]??['followup_days'=>3,'enabled'=>0];?>
<form class="rule" method="post" action="/monitor-laporan20.php?bulan=<?=h(rawurlencode($month))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="action" value="rule">
<input type="hidden" name="source_kind" value="<?=h($source)?>">
<b><?=h($meta['label'])?></b>
<label>Ambang hari tanpa pembaruan<input type="number" name="followup_days" min="1" max="60" required value="<?=(int)$rule['followup_days']?>"></label>
<label class="toggle"><input type="checkbox" name="enabled" value="1" <?=!empty($rule['enabled'])?'checked':''?>> Aktifkan pemantauan</label>
<button type="submit" class="btn secondary" <?=$ready?'':'disabled'?>>Simpan Aturan</button>
</form><?php endforeach;?>
</div>
</section>
<section class="panel" id="antrean"><div class="panel-head"><div><h2>Antrean Perlu Tindak Lanjut</h2>
<p class="sub">Dihitung dari tanggal perubahan status terakhir. Antrean ini bukan daftar pegawai yang terlambat menyerahkan laporan.</p></div>
<span class="chip"><?=count($queue['rows'])?> ditampilkan</span></div>
<?php if($queue['incomplete']):?><div class="flash warning">Antrean pratinjau terbatas pada 300 laporan aktif per sumber dan maksimal 60 hasil. Data mungkin tidak lengkap; jangan gunakan untuk menghukum atau menilai petugas.</div><?php endif;?>
<?php if(!$queue['rows']):?><div class="empty">Tidak ada laporan yang melewati ambang aktif, atau aturan pengingat belum diaktifkan.</div><?php endif;?>
<?php foreach($queue['rows'] as $r):?>
<div class="overdue">
<div><b><?=h($sources[$r['source_kind']]['label']??$r['source_kind'])?> · #<?=(int)$r['id']?></b>
<small><?=h($r['category'])?> · <?=h($labels[$r['phase']]??$r['phase'])?></small>
<span class="age"><?=number_format((int)$r['days_unchanged'],0,',','.')?> hari tanpa pembaruan · ambang <?=(int)$r['threshold_days']?> hari</span></div>
<form method="post" action="/monitor-laporan20.php?bulan=<?=h(rawurlencode($month))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="reminder">
<input type="hidden" name="source_kind" value="<?=h($r['source_kind'])?>">
<input type="hidden" name="report_id" value="<?=(int)$r['id']?>">
<input type="hidden" name="version" value="<?=(int)$r['version']?>">
<input type="hidden" name="phase" value="<?=h($r['phase'])?>">
<button class="btn" type="submit">Buat Pengingat Internal</button>
</form></div>
<?php endforeach;?>
<p class="smallnote">Pengingat perbaikan dikirim ke kotak pemberitahuan pelapor. Laporan yang menunggu pemeriksa atau telaah Lurah dicatat di kotak pengawasan Super Admin karena sistem belum memiliki pemetaan penugasan pemeriksa individual. Satu pengingat per versi dan tahap; pengingat ganda ditolak.</p>
</section>
<p class="footnote">Seluruh angka diambil dari tabel laporan tambahan SmartApp Tahap 15–19 dan tidak mengubah tabel Pegawai, absensi, saldo cuti, surat RT/RW, penilaian PPPK resmi, atau dokumen warga.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Monitoring Administratif Bulanan</div></footer></body></html>
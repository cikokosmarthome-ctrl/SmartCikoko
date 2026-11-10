<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/review22.php';
    $actor=requires_login();
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($actor)){http_response_code(403);exit('Akun tidak berwenang.');}
    $list=r22_deputy_tasks($actor,40);
}catch(Throwable $e){http_response_code(503);exit('Daftar pengganti belum tersedia.');}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b3555">
<title>Tugas Pengganti Sementara | SmartCikoko</title><link rel="stylesheet" href="/assets/review22.css"></head><body>
<div class="gov"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a href="/" class="brand"><span>KC</span><b>SMARTCIKOKO<small>Pemeriksaan Pengganti Sementara</small></b></a>
<nav><a href="/verifikasi-laporan19.php">Verifikasi Laporan</a><a href="/penugasan-saya21.php">Tugas Utama Saya</a></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 22 · PENUGASAN TERBATAS</small><h1>Tugas Cadangan Saya</h1>
<p>Daftar laporan yang dapat Anda periksa sebagai pengganti sementara berdasarkan kategori, peran, status, dan tanggal penugasan yang masih berlaku.</p></div></section>
<main class="wrap content"><section class="panel">
<div class="panel-head"><div><h2>Laporan dalam Penugasan Cadangan</h2>
<p class="hint">Laporan selesai, draf, pengganti dicabut atau masa berlaku kedaluwarsa tidak ditampilkan.</p></div><span class="tag"><?=count($list['rows'])?> tugas</span></div>
<?php if(!$list['rows']):?><div class="empty">Belum ada laporan yang memerlukan tindakan Anda sebagai pengganti sementara.</div><?php endif;?>
<?php foreach($list['rows'] as $task):?>
<div class="task"><div><b><?=h(m20_sources()[$task['source']]['label']??$task['source'])?> · Laporan #<?=(int)$task['id']?></b>
<small><?=h($task['category'])?> · <?=h(t21_steps()[$task['step']]??$task['step'])?></small>
<span class="<?=$task['overdue']?'late':'normal'?>"><?=(int)$task['age']?> hari menunggu · ambang internal <?=(int)$task['due_days']?> hari</span></div>
<a class="btn secondary" href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($task['source']))?>&amp;id=<?=(int)$task['id']?>">Buka Pemeriksaan →</a></div>
<?php endforeach;?>
<?php if($list['incomplete']):?><div class="notice warning">Daftar terbatas pada 100 laporan aktif per kategori dan 40 hasil terbaru. Pemeriksa dapat melihat laporan lain melalui modul verifikasi sesuai haknya.</div><?php endif;?>
</section>
<p class="footnote">Sebagai cadangan, Anda tetap melalui pemeriksaan kewenangan pada Tahap 19 dan pengaman Tahap 22 ketika menyimpan tindakan. Daftar tugas tidak memberikan persetujuan atau tanda tangan resmi.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Penugasan Pengganti</div></footer></body></html>
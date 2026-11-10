<?php
declare(strict_types=1);
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    $viewer=actor();
    if(!$viewer){header('Location: /?page=login',true,303);exit;}
    require_once dirname(__DIR__).'/portal_private/workflow_feed.php';
    $feed=sc_workflow_feed($viewer);
}catch(RuntimeException $e){http_response_code(403);exit('Akun tidak dapat mengakses pusat aktivitas.');}
catch(Throwable $e){http_response_code(503);exit('Pusat aktivitas belum tersedia. Hubungi administrator.');}
$items=$feed['items'];$notifications=$feed['notifications'];$summary=$feed['summary'];
header('Cache-Control: private,no-store');
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Pusat Aktivitas | SmartCikoko</title>
<link rel="stylesheet" href="/assets/aktivitas-terpadu.css">
</head><body class="sc-body sc-page-aktivitas-terpadu">
<header class="activity-head"><div class="activity-wrap"><a href="/" aria-label="Beranda Kelurahan Cikoko">← Kelurahan Cikoko</a><nav aria-label="Navigasi pusat aktivitas"><a href="/?page=dashboard">Ruang Kerja</a><a href="/pengajuan-warga.php">Pengajuan Surat</a><a href="/formulir-pegawai16.php">Formulir Pegawai</a><a href="/laporan-wilayah17.php">Laporan Wilayah</a></nav></div></header>
<main class="activity-wrap activity-main">
<div class="activity-intro"><span class="activity-kicker">PELAYANAN DIGITAL TERPADU</span><h1>Progres dan Notifikasi Akun</h1><p>Halo, <?=h((string)$viewer['name'])?>. Laporan dan pengajuan yang Anda miliki tampil dari data yang sama dengan aplikasi SmartCikoko.</p></div>
<div class="activity-metrics" aria-label="Ringkasan aktivitas">
<div><strong><?=(int)$summary['total']?></strong><span>Aktivitas Terbaru</span></div>
<div><strong><?=(int)$summary['need_action']?></strong><span>Perlu Tindakan</span></div>
<div><strong><?=(int)$summary['under_review']?></strong><span>Dalam Alur Kerja</span></div>
<div><strong><?=(int)$summary['completed']?></strong><span>Ditutup / Selesai</span></div>
</div>
<div class="activity-cols"><section class="activity-panel"><div class="activity-heading"><h2>Daftar Pengajuan dan Laporan</h2><a href="/aktivitas-terpadu.php" aria-label="Muat ulang aktivitas">↻ Muat Ulang</a></div>
<?php if(!$items):?><p class="activity-empty">Belum ada aktivitas milik akun ini pada modul yang tersedia.</p><?php endif;?>
<?php foreach($items as $item):?><article class="activity-case">
<div class="activity-case-top"><div><strong><?=h($item['reference'])?></strong><small><?=h($item['kind'])?> · <?=h($item['updated_at'])?> WIB</small></div><b><?=h($item['status_label'])?></b></div>
<div class="activity-percent"><span>Progres administratif</span><strong><?=(int)$item['progress_percent']?>%</strong></div>
<progress max="100" value="<?=(int)$item['progress_percent']?>"><?=(int)$item['progress_percent']?>%</progress>
<p><?=h($item['next_step'])?></p>
<a href="<?=h($item['web_url'])?>">Buka Rincian / Formulir →</a>
</article><?php endforeach;?>
</section>
<section class="activity-panel activity-notices"><h2>Notifikasi Semua Proses</h2><p class="activity-muted">Notifikasi dalam akun, berdasarkan riwayat tindakan server. Memuat ulang halaman mengambil pembaruan terbaru.</p>
<?php if(!$notifications):?><p class="activity-empty">Belum ada riwayat tindakan yang tercatat untuk akun ini.</p><?php endif;?>
<?php foreach($notifications as $notice):?><article class="activity-notice"><strong><?=h($notice['title'])?></strong><p><?=h($notice['message'])?></p><small><?=h($notice['created_at'])?> WIB · <?=(int)$notice['progress_percent']?>%</small></article><?php endforeach;?>
</section></div>
<?php if($feed['unavailable_modules']):?><p class="activity-warning">Beberapa modul belum siap di server: <?=h(implode(', ',$feed['unavailable_modules']))?>. Data modul tersebut tidak dihitung.</p><?php endif;?>
<p class="activity-footnote"><?=h($feed['progress_notice'])?> <?=h($feed['notice'])?></p>
</main><footer class="activity-footer"><div class="activity-wrap">© <?=date('Y')?> Kelurahan Cikoko · Akses berdasarkan kewenangan akun</div></footer></body></html>

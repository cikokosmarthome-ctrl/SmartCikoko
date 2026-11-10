<?php
declare(strict_types=1);
/** Embedded into the existing MFA-protected single Super Admin dashboard. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){http_response_code(403);return;}
require_once __DIR__.'/evidence18.php';
require_once __DIR__.'/recap18.php';
$e18Installed=e18_ready();$e18Scanner=e18_scan_ready();
$e18Counts=$e18Installed?e18_counts():['pegawai16'=>0,'wilayah17'=>0];
$e18Recent=e18_recent_reports($admin);
?>
<section class="section" id="bukti-dan-rekap">
<div class="title"><h2>Bukti Kegiatan &amp; Rekap Terpadu</h2>
<a class="link" href="/rekap-laporan18.php">Lihat Rekap Bulanan →</a></div>
<p class="hint">Bukti foto tahap 16–17 tersimpan privat dan hanya dapat diunduh dengan izin. Ekspor CSV berisi metadata laporan Tahap 15–17 tanpa identitas pribadi.</p>
<div class="e18-cards">
<div><small>Penyimpanan bukti</small><strong><?=$e18Installed?'Siap':'Belum aktif'?></strong></div>
<div><small>ClamAV wajib + pemrosesan foto</small><strong><?=$e18Scanner?'Terkonfigurasi':'Belum siap'?></strong></div>
<div><small>Bukti Pegawai 16</small><strong><?=(int)($e18Counts['pegawai16']??0)?></strong></div>
<div><small>Bukti Wilayah 17</small><strong><?=(int)($e18Counts['wilayah17']??0)?></strong></div>
</div>
<div class="e18-actions"><a href="/rekap-laporan18.php">Rekap dan Ekspor CSV</a>
<a href="/bukti-laporan18.php">Pemeriksaan Bukti Foto</a></div>
<details class="e18-recent">
<summary>Laporan terbaru untuk pemeriksaan bukti (<?=(int)count($e18Recent)?>)</summary>
<?php foreach(array_slice($e18Recent,0,8) as $r):?>
<a href="/bukti-laporan18.php?source=<?=h(rawurlencode($r['source']))?>&amp;id=<?=(int)$r['id']?>">
<span><?=h($r['source']==='pegawai16'?'Pegawai':'Wilayah')?> · #<?=(int)$r['id']?> · <?=h($r['kind'])?></span>
<small><?=h($r['status'])?></small>
</a>
<?php endforeach;?>
<?php if(!$e18Recent):?><p class="hint">Belum ada laporan yang dapat diperiksa.</p><?php endif;?>
</details>
</section>
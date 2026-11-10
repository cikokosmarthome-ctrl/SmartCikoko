<?php
declare(strict_types=1);
/** Embedded in the existing MFA-protected Super Admin Ringkas. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){http_response_code(403);return;}
require_once __DIR__.'/monitor20.php';
$m20Installed=m20_ready();
$m20Month=['modules'=>[],'incomplete'=>[]];
$m20Queue=['rows'=>[],'incomplete'=>false,'enabled_rules'=>0];
if($m20Installed){
    try{
        $m20Month=m20_monthly($admin,date('Y-m'));
        $m20Queue=m20_queue($admin,12);
    }catch(Throwable $e){error_log('Stage20 compact monitoring panel unavailable.');}
}
$m20Total=0;foreach($m20Month['modules'] as $metric)$m20Total+=(int)$metric['submitted'];
?>
<section class="section" id="monitor-bulanan">
<div class="title"><h2>Monitoring Laporan Bulanan</h2>
<a class="link" href="/monitor-laporan20.php">Buka Monitoring &amp; Pengingat →</a></div>
<p class="hint">Jumlah laporan yang masuk bulan ini dan tindak lanjut administrasi menurut aturan yang Anda aktifkan. Bukan skor kinerja resmi atau catatan disiplin pegawai.</p>
<div class="m20-metrics">
<div><small>Laporan bulan ini</small><strong><?=$m20Installed?$m20Total:'—'?></strong></div>
<div><small>Modul siap terbaca</small><strong><?=$m20Installed?(3-count($m20Month['incomplete'])):'—'?> / 3</strong></div>
<div><small>Aturan pemantauan aktif</small><strong><?=(int)$m20Queue['enabled_rules']?> / 3</strong></div>
<div><small>Antrean perhatian (pratinjau)</small><strong><?=count($m20Queue['rows'])?></strong></div>
</div>
<?php if(!$m20Installed):?><p class="m20-warning">Tahap 20 belum dipasang. Jalankan installer melalui akun Super Admin.</p>
<?php else:?>
<div class="m20-links"><a href="/monitor-laporan20.php">Pengaturan Ambang Hari dan Rekap Bulanan</a>
<a href="/pengingat-saya20.php">Kotak Pengingat Saya</a></div>
<details class="m20-preview">
<summary>Lihat daftar perhatian terbaru (maksimal 12 laporan)</summary>
<?php if(!$m20Queue['rows']):?><p class="hint">Belum ada pengingat aktif atau laporan melebihi ambang pemantauan.</p><?php endif;?>
<?php foreach($m20Queue['rows'] as $item):?>
<div><span><b><?=h($item['source_kind'])?> · #<?=(int)$item['id']?></b>
<small><?=h(m20_phase_labels()[$item['phase']]??$item['phase'])?> · <?=(int)$item['days_unchanged']?> hari</small></span>
<a href="/monitor-laporan20.php#antrean">Lihat</a></div>
<?php endforeach;?>
<?php if($m20Queue['incomplete']):?><p class="hint">Antrean merupakan pratinjau terbatas; buka Monitoring untuk rincian.</p><?php endif;?>
</details>
<?php endif;?>
</section>
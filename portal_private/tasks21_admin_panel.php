<?php
declare(strict_types=1);
/** Embedded in existing Stage15 one-page Super Admin; no extra admin home. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){http_response_code(403);return;}
require_once __DIR__.'/tasks21.php';
$t21Ready=t21_ready();
$t21Rules=[];$t21Preview=['rows'=>[],'active'=>0,'incomplete'=>false];
$t21Schedule=['enabled'=>0,'last_run'=>null,'last_count'=>0,'last_incomplete'=>0];
if($t21Ready){
    try{
        $t21Rules=t21_assignments();
        $t21Preview=t21_due_candidates(8);
        $t21Schedule=t21_schedule();
    }catch(Throwable $e){error_log('Stage21 one-page panel read unavailable.');}
}
?>
<section class="section" id="penugasan-pemeriksa">
<div class="title"><h2>Penugasan Pemeriksa &amp; Tenggat</h2>
<a class="link" href="/tugas-pemeriksa21.php">Kelola Penugasan →</a></div>
<p class="hint">Pemeriksa per kategori dan akun Lurah dapat ditentukan dengan tenggat internal yang punya referensi tertulis. Tugas dan notifikasi tidak mengubah kewenangan verifikasi resmi.</p>
<div class="t21-cards">
<div><small>Modul terpasang</small><strong><?=$t21Ready?'Siap':'Belum'?></strong></div>
<div><small>Aturan tersimpan</small><strong><?=count($t21Rules)?></strong></div>
<div><small>Aturan aktif dan valid</small><strong><?=(int)$t21Preview['active']?></strong></div>
<div><small>Notifikasi CLI terjadwal</small><strong><?=!empty($t21Schedule['enabled'])?'Diizinkan':'Nonaktif'?></strong></div>
</div>
<?php if(!$t21Ready):?><div class="t21-warning">Aktifkan Tahap 21 dari installer satu tombol terlebih dahulu.</div>
<?php else:?>
<div class="t21-actions"><a href="/tugas-pemeriksa21.php">Atur Pemeriksa dan Tenggat</a>
<a href="/penugasan-saya21.php">Kotak Tugas Saya</a></div>
<details class="t21-preview"><summary>Pratinjau laporan perlu tindak lanjut (<?=count($t21Preview['rows'])?>)</summary>
<?php if(!$t21Preview['rows']):?><p class="hint">Belum ada penugasan aktif yang melewati tenggat.</p><?php endif;?>
<?php foreach($t21Preview['rows'] as $r):?>
<a href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($r['source']))?>&amp;id=<?=(int)$r['id']?>">
<b><?=h($r['source'])?> #<?=(int)$r['id']?> · <?=h($r['category'])?></b>
<span><?=(int)$r['days']?> hari · <?=h($r['recipient_name'])?></span>
</a><?php endforeach;?>
<?php if(!empty($t21Preview['incomplete'])):?><p class="hint">Pratinjau terbatas; periksa halaman penugasan untuk rincian.</p><?php endif;?>
</details>
<?php endif;?>
</section>
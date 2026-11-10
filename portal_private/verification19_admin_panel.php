<?php
declare(strict_types=1);
/** Inserted into existing MFA-protected Super Admin Ringkas, NOT another dashboard. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){http_response_code(403);return;}
require_once __DIR__.'/verification19.php';
$v19Ready=v19_ready();
$v19Latest=[];
if($v19Ready){
    try{$v19Latest=v19_queue($admin,'',12);}
    catch(Throwable $e){error_log('Stage19 compact queue not available.');}
}
$v19Open=0;$v19Checked=0;$v19Returned=0;
foreach($v19Latest as $item){
    if($item['effective_state']==='menunggu')++$v19Open;
    elseif($item['effective_state']==='lolos_periksa')++$v19Checked;
    elseif($item['effective_state']==='perlu_perbaikan')++$v19Returned;
}
?>
<section class="section" id="verifikasi-berjenjang">
<div class="title"><h2>Verifikasi Berjenjang</h2>
<a class="link" href="/verifikasi-laporan19.php">Buka Pemeriksaan Laporan →</a></div>
<p class="hint">Pemeriksaan kelengkapan petugas dan telaah Lurah, tanpa menyetujui cuti, menilai PPPK secara resmi, atau menerbitkan surat. Laporan yang dikembalikan dapat diperbaiki pemiliknya.</p>
<div class="v19-metrics">
<div><small>Modul Verifikasi</small><strong><?=$v19Ready?'Aktif':'Belum dipasang'?></strong></div>
<div><small>Menunggu diperiksa*</small><strong><?=$v19Open?></strong></div>
<div><small>Lolos pemeriksaan*</small><strong><?=$v19Checked?></strong></div>
<div><small>Perlu perbaikan*</small><strong><?=$v19Returned?></strong></div>
</div>
<details class="v19-box">
<summary>Lihat antrean pemeriksaan terbaru <small>(maksimal 12 laporan; angka bertanda * hanya dari pratinjau)</small></summary>
<?php if(!$v19Ready):?><p class="hint">Pasang Tahap 19 melalui installer Super Admin.</p>
<?php elseif(!$v19Latest):?><p class="hint">Belum ada laporan aktif untuk diperiksa.</p>
<?php else:?>
<div class="v19-items">
<?php foreach($v19Latest as $item):?>
<a href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($item['source_kind']))?>&amp;id=<?=(int)$item['id']?>">
<span><strong><?=h($item['source_kind'])?> · #<?=(int)$item['id']?></strong>
<small><?=h($item['category'])?> · <?=h($item['created_at'])?></small></span>
<b><?=h(v19_states()[$item['effective_state']]??$item['effective_state'])?></b>
</a>
<?php endforeach;?>
</div>
<?php endif;?>
</details>
</section>
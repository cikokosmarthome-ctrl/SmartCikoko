<?php
declare(strict_types=1);
/* Included by the existing MFA-protected super-admin-satu.php, not a route. */
if(!isset($admin)||!is_array($admin)||!function_exists('is_super_admin')||!is_super_admin($admin)){
    http_response_code(403);return;
}
require_once __DIR__.'/field_reports17.php';
$w17Ok=w17_ready();
$w17Counts=$w17Ok?w17_summary():[];
$w17Queue=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
if($w17Ok){
    try{$w17Queue=w17_list($admin,'',1,8);}
    catch(Throwable $e){error_log('Stage 17 compact admin queue unavailable.');}
}
$w17Notice=(string)($_SESSION['w17_admin_notice']??'');
unset($_SESSION['w17_admin_notice']);
$w17Names=w17_catalog();
?>
<section id="laporan-wilayah" class="section w17-admin">
<div class="title">
<h2>Pelaporan Wilayah dan Jumantik</h2>
<a class="link" href="/laporan-wilayah17.php">Buka Input / Riwayat →</a>
</div>
<p class="hint">Laporan terstruktur Tahap 17 terpisah dari surat RT/RW dan laporan mobile lama. Menutup laporan berarti menutup administrasi peninjauan, bukan menerbitkan surat atau mengesahkan bukti foto.</p>
<?php if($w17Notice):?><div class="w17-notice"><?=h($w17Notice)?></div><?php endif;?>
<?php if(!$w17Ok):?><p class="w17-warning">Modul laporan wilayah belum disiapkan. Jalankan installer Tahap 17 sebelum menggunakan antrean ini.</p><?php else:?>
<div class="w17-stats">
<?php foreach($w17Names as $kind=>$meta):$value=$w17Counts[$kind]??['total'=>0,'pending'=>0,'revision'=>0];?>
<a href="/laporan-wilayah17.php?jenis=<?=h(rawurlencode($kind))?>" class="w17-stat">
<strong><?=h($meta['name'])?></strong>
<span>Masuk/ditinjau <b><?=(int)$value['pending']?></b></span>
<span>Perbaikan <b><?=(int)$value['revision']?></b></span>
<span>Total <b><?=(int)$value['total']?></b></span>
</a>
<?php endforeach;?></div>
<details class="w17-list" open>
<summary><strong>Antrean Peninjauan Laporan</strong><small><?=(int)$w17Queue['total']?> laporan yang telah dikirim · 8 terbaru</small></summary>
<?php if(!$w17Queue['rows']):?><p class="w17-empty">Belum ada laporan masuk yang siap ditinjau. Draf petugas tidak ditampilkan.</p><?php endif;?>
<?php foreach($w17Queue['rows'] as $r):
    $payload=json_decode((string)$r['payload_json'],true);if(!is_array($payload))$payload=[];
?>
<details class="w17-entry">
<summary><span><b>#<?=(int)$r['id']?> · <?=h($w17Names[$r['kind']]['name']??$r['kind'])?></b>
<small><?=h($r['created_at'])?> WIB · <?=h($r['actor_source'])?></small></span>
<i><?=h(w17_statuses()[$r['status']]??$r['status'])?></i></summary>
<div class="w17-content">
<dl><?php foreach($w17Names[$r['kind']]['fields']??[] as $key=>$meta):
    if(($payload[$key]??'')==='')continue;?>
<dt><?=h($meta['label'])?></dt><dd><?=nl2br(h((string)$payload[$key]))?></dd>
<?php endforeach;?></dl>
<?php if(!empty($r['review_note'])):?><p class="w17-note">Catatan terakhir: <?=h($r['review_note'])?></p><?php endif;?>
<?php if(in_array($r['status'],['diajukan','ditinjau'],true)):?>
<form method="post" class="w17-review" action="/tinjau-wilayah17.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="report_id" value="<?=(int)$r['id']?>">
<label>Status<select name="status" required>
<option value="ditinjau">Dalam Peninjauan</option><option value="perbaikan">Minta Perbaikan</option>
<option value="ditutup">Tutup Administratif (bukan persetujuan resmi)</option>
</select></label>
<label>Catatan<input type="text" name="note" maxlength="500" placeholder="Alasan permintaan perbaikan"></label>
<button type="submit">Simpan Tindak Lanjut</button>
</form>
<?php endif;?></div></details>
<?php endforeach;?>
</details>
<?php if($w17Queue['pages']>1):?><p class="smallnote">Untuk riwayat lebih lengkap gunakan <a href="/laporan-wilayah17.php">Pusat Laporan Wilayah</a>.</p><?php endif;?>
<?php endif;?>
</section>
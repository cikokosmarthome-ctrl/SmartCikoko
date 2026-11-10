<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/uat26.php';
    $admin=require_super_admin();
    u26_guard($admin);
}catch(Throwable $e){
    error_log('Stage26 UAT access unavailable: '.get_class($e));
    http_response_code(503);exit('Pencatatan Uji Tahap 26 belum siap. Login Super Admin dengan MFA dan jalankan installer.');
}
$error='';$notice=(string)($_SESSION['uat26_notice']??'');
unset($_SESSION['uat26_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $saved=u26_save($admin,
            (string)($_POST['case_key']??''),(string)($_POST['state']??''),
            (string)($_POST['note']??''),($_POST['tested']??'')==='1',
            (string)($_POST['submission_token']??''));
        $_SESSION['uat26_notice']=$saved
            ?'Hasil uji manual berhasil dicatat beserta riwayatnya.'
            :'Hasil yang sama sudah tercatat; kiriman duplikat diabaikan.';
        header('Location: /uji-operasional26.php',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage26 manual UAT write denied: '.get_class($e));
        $error=($e instanceof InvalidArgumentException)
            ?$e->getMessage():'Hasil uji tidak tersimpan. Pastikan status, hak akun dan tabel benar.';
    }
}
try{$snapshot=u26_snapshot($admin);$history=u26_history($admin,25);}
catch(Throwable $e){
    error_log('Stage26 manual UAT read unavailable: '.get_class($e));
    http_response_code(503);exit('Catatan uji belum dapat dibaca.');
}
$states=u26_states();$blockers=u26_blockers($snapshot);
$grouped=[];
foreach($snapshot['rows'] as $r)$grouped[$r['area']][]=$r;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0e3453">
<title>Uji Operasional Terpandu Tahap 26 | SmartCikoko</title>
<link rel="stylesheet" href="/assets/uji-operasional26.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="top"><div class="wrap nav">
<a href="/" class="brand"><span class="crest">KC</span><b>SMARTCIKOKO<small>Pencatatan Uji Operasional</small></b></a>
<div class="navlinks"><a href="/super-admin-satu.php#uji-operasional26">Super Admin Ringkas</a>
<a href="/cek-operasional25.php">Pemeriksaan Tahap 25</a>
<a href="/pusat-operasional23.php">Pusat Operasional</a></div></div></header>
<main class="wrap main">
<div class="heading"><div><small>TAHAP 26 · UJI PENERIMAAN PENGGUNA (UAT)</small>
<h1>Uji Operasional <em>Terpandu</em></h1>
<p>Catat hasil pemeriksaan nyata untuk setiap modul, kendala, dan tindak lanjut. Pengujian hanya dilakukan pada akun/laporan dummy sesuai kewenangan.</p></div>
<div class="user"><span><?=h(mb_strtoupper(mb_substr((string)($admin['name']??'A'),0,1)))?></span>
<div><b>Super Administrator</b><small>MFA terverifikasi</small></div></div></div>
<div class="notice">Status awal semua uji adalah <strong>Belum diuji</strong>. Menekan “Lulus” hanya mencatat pernyataan penguji setelah uji nyata, bukan melakukan tes otomatis. Catatan tidak boleh mengandung data warga, NIK, identitas pegawai, rahasia akses, atau berkas asli.</div>
<?php if($notice):?><div class="flash good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<section class="stats">
<div><small>Seluruh skenario</small><strong><?=$snapshot['total_cases']?></strong></div>
<div><small>Lulus manual</small><strong><?=$snapshot['totals']['pass']?></strong></div>
<div><small>Gagal / Terhambat</small><strong><?=$snapshot['totals']['fail']+$snapshot['totals']['blocked']?></strong></div>
<div><small>Belum diuji</small><strong><?=$snapshot['totals']['pending']?></strong></div>
</section>
<?php if($snapshot['all_passed']):?>
<div class="flash good">Semua skenario telah ditandai lulus oleh penguji. Ini adalah rekap pengujian manual, <strong>bukan sertifikat kesiapan produksi</strong>; bukti dan persetujuan organisasi harus diverifikasi terpisah.</div>
<?php endif;?>
<?php if($blockers):?>
<section class="panel" id="temuan"><div class="section-head"><h2>Temuan yang Perlu Tindak Lanjut</h2><span class="pill"><?=count($blockers)?> skenario</span></div>
<div class="blockers">
<?php foreach($blockers as $b):?>
<a href="#case-<?=h($b['key'])?>"><b><?=h($b['title'])?></b><span class="state state-<?=h($b['result'])?>"><?=h($states[$b['result']]??$b['result'])?></span>
<small><?=h($b['note'])?></small></a>
<?php endforeach;?></div>
<p class="hint">Catat perbaikan secara administratif lalu ulangi tes. Saat skenario berhasil diuji ulang, simpan status baru; riwayat lama tetap disimpan.</p>
</section>
<?php endif;?>
<section class="panel">
<div class="section-head"><h2>Daftar Skenario Pengujian</h2><span class="pill">16 skenario · hasil manual</span></div>
<p class="hint">Klik salah satu skenario untuk melihat prosedur uji dan mencatat hasil. Tidak ada perubahan terhadap laporan warga, izin pegawai, ataupun keputusan Lurah.</p>
<?php foreach($grouped as $area=>$cases):?>
<div class="area"><h3><?=h($area)?></h3>
<?php foreach($cases as $case):?>
<details class="scenario" id="case-<?=h($case['key'])?>" <?=$error!==''&&(($_POST['case_key']??'')===$case['key'])?'open':''?>>
<summary><span class="scenario-title"><?=h($case['title'])?></span>
<span class="state state-<?=h($case['result'])?>"><?=h($states[$case['result']])?></span>
<span class="chevron" aria-hidden="true">⌄</span></summary>
<div class="scenario-body">
<div class="instructions"><b>Langkah uji</b><p><?=h($case['steps'])?></p>
<b>Kriteria keberhasilan</b><p><?=h($case['criterion'])?></p></div>
<?php if($case['recorded_at']):?><p class="previous">Hasil terakhir <?=h($case['recorded_at'])?> WIB ·
<?=h($states[$case['result']])?>
<?php if($case['note']!==''):?> · <?=h($case['note'])?><?php endif;?></p><?php endif;?>
<form method="post" action="/uji-operasional26.php" class="form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="case_key" value="<?=h($case['key'])?>">
<input type="hidden" name="submission_token" value="<?=h(bin2hex(random_bytes(16)))?>">
<label>Status hasil uji<select name="state" required>
<option value="pending" <?=$case['result']==='pending'?'selected':''?>>Belum diuji (kosongkan catatan)</option>
<option value="pass" <?=$case['result']==='pass'?'selected':''?>>Lulus — saya telah menguji dengan akun dummy</option>
<option value="fail" <?=$case['result']==='fail'?'selected':''?>>Gagal — perlu perbaikan</option>
<option value="blocked" <?=$case['result']==='blocked'?'selected':''?>>Terhambat — belum dapat diuji</option>
</select></label>
<label>Catatan uji atau tindak lanjut (15–240 karakter jika bukan “Belum diuji”)
<textarea name="note" rows="2" maxlength="240" placeholder="Contoh: akun uji RT tidak dapat membuka formulir karena hak akses belum disesuaikan. Jangan tulis identitas."><?=h($error!==''&&(($_POST['case_key']??'')===$case['key'])?(string)($_POST['note']??''):$case['result']==='pending'?'':$case['note'])?></textarea></label>
<label class="check"><input type="checkbox" name="tested" value="1"> Saya menyatakan hasil Lulus hanya setelah pengujian nyata dengan akun/laporan dummy.</label>
<button class="button" type="submit">Simpan Hasil dan Riwayat</button>
</form></div></details>
<?php endforeach;?></div>
<?php endforeach;?></section>
<section class="panel" id="riwayat"><div class="section-head"><h2>Riwayat Perubahan Hasil Uji</h2><span class="pill">25 catatan terakhir</span></div>
<?php if(!$history):?><p class="empty">Belum ada hasil uji yang dicatat. Semua skenario tetap berstatus Belum diuji.</p>
<?php else:?><div class="scroll"><table><thead><tr><th>Waktu (WIB)</th><th>Skenario</th><th>Status</th><th>Catatan teknis</th></tr></thead><tbody>
<?php foreach($history as $item):?>
<tr><td><?=h($item['recorded_at'])?></td><td><?=h(u26_catalog()[$item['case_key']]['title'])?></td>
<td><span class="state state-<?=h($item['result'])?>"><?=h($states[$item['result']]??$item['result'])?></span></td>
<td><?=h($item['note']!==''?$item['note']:'Pencatatan direset ke Belum diuji')?></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<p class="footnote">Catatan hanya ringkasan hasil uji administratif, bukan bukti otomatis. Hasil disimpan dalam jurnal append-only di SmartApp dan disertai jejak audit; tidak ada lampiran atau data sensitif yang harus dikumpulkan.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Catatan Uji Operasional Internal</div></footer></body></html>
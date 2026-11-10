<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/review22.php';
    $admin=require_super_admin();
    r22_super($admin);
}catch(Throwable $e){http_response_code(503);exit('Pengendalian Tahap 22 belum siap. Login Super Admin dengan MFA dan jalankan installer.');}
$notice=(string)($_SESSION['r22_notice']??'');unset($_SESSION['r22_notice']);
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['action']??'');
        [$source,$category]=array_pad(explode(':',(string)($_POST['category_key']??''),2),2,'');
        if($action==='policy'){
            r22_set_policy($admin,$source,$category,($_POST['enforced']??'')==='1');
            $_SESSION['r22_notice']='Kebijakan penugasan ketat berhasil diperbarui. Status tindakan sekarang mengikuti penugasan aktif.';
        }elseif($action==='deputy'){
            [$deputySource,$deputyId]=array_pad(explode('|',(string)($_POST['deputy_key']??''),2),2,'');
            r22_delegate($admin,$source,$category,(string)($_POST['step']??''),$deputySource,$deputyId,
                (string)($_POST['starts_on']??''),(string)($_POST['ends_on']??''),(string)($_POST['reason']??''));
            $_SESSION['r22_notice']='Pengganti sementara tercatat. Haknya hanya berlaku untuk kategori, tahap, dan tanggal yang ditetapkan.';
        }elseif($action==='revoke'){
            r22_revoke($admin,$source,$category,(string)($_POST['step']??''));
            $_SESSION['r22_notice']='Penugasan pengganti dibatalkan.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        header('Location: /kendali-verifikasi22.php',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage22 Super Admin policy denied: '.get_class($e));
        $error=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)
            ?$e->getMessage():'Perubahan tidak dapat disimpan. Periksa izin, versi dan status penugasan.';
    }
}
$catalog=t21_catalog();$candidates=t21_candidates();$assignments=t21_assignments();
$policies=r22_policies();$replacements=r22_delegations();
$month=(string)($_GET['bulan']??date('Y-m'));try{m20_month($month);}catch(Throwable $e){$month=date('Y-m');}
$metrics=['total_actions'=>0,'modules'=>[],'month'=>$month];
try{$metrics=r22_monthly($admin,$month);}catch(Throwable $e){error_log('Stage22 monthly action summary unavailable.');$error='Sebagian ringkasan belum dapat dibaca.';}
$guardSource=@file_get_contents(dirname(__DIR__).'/portal_private/verification19.php');
$guardReady=is_string($guardSource)&&str_contains($guardSource,'SMARTCIKOKO_TAHAP22_STRICT_GUARD');
$names=[];foreach($candidates as $p)$names[$p['source'].'|'.$p['id']]=$p['name'].' · '.$p['role'];
$activeCount=count(array_filter($policies,static fn($r)=>!empty($r['enforced'])));
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0e3451">
<title>Kendali Verifikasi dan Pengganti | SmartCikoko</title>
<link rel="stylesheet" href="/assets/review22.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a href="/" class="brand"><span>KC</span><b>SMARTCIKOKO<small>Pengendalian Penugasan Terpadu</small></b></a><nav>
<a href="/super-admin-satu.php#kendali-verifikasi">Super Admin Ringkas</a><a href="/tugas-pemeriksa21.php">Penugasan Tahap 21</a><a href="/verifikasi-laporan19.php">Verifikasi Laporan</a></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 22 · OTORISASI PENUGASAN TERPADU</small><h1>Kendali Pemeriksa dan Pengganti</h1>
<p>Aktifkan pemeriksaan hanya oleh petugas yang ditugaskan, tentukan pemeriksa cadangan untuk periode terbatas, dan pantau jumlah tindakan administratif.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="notice good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="notice bad"><?=h($error)?></div><?php endif;?>
<div class="stats">
<div><small>Pengaman Verifikasi 19</small><strong><?=$guardReady?'Terpasang':'Belum tersambung'?></strong></div>
<div><small>Kategori dengan kebijakan ketat</small><strong><?=$activeCount?></strong></div>
<div><small>Penugasan pengganti tercatat</small><strong><?=count($replacements)?></strong></div>
<div><small>Tindakan administratif <?=$month?></small><strong><?=(int)$metrics['total_actions']?></strong></div>
</div>
<div class="notice warning">Pengaturan ketat **nonaktif secara default**. Sebelum mengaktifkan kategori, pastikan pemeriksa dan Lurah sudah ditugaskan serta aktif pada Tahap 21. Super Admin masih dapat memantau laporan, tetapi tidak otomatis memperoleh hak memeriksa kategori yang dikunci. Ini bukan mekanisme persetujuan resmi surat atau cuti.</div>
<div class="split">
<section class="panel"><h2>1. Batasi Verifikasi per Kategori</h2>
<p class="hint">Jika diaktifkan, tombol pemeriksaan Tahap 19 hanya dapat dijalankan akun yang ditunjuk pada Tahap 21 atau pengganti sementara yang sah.</p>
<form method="post" action="/kendali-verifikasi22.php" class="form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="policy">
<label>Kategori<select name="category_key" required><option value="">Pilih jenis laporan</option>
<?php foreach($catalog as $src=>$items):?>
<optgroup label="<?=h(m20_sources()[$src]['label']??$src)?>">
<?php foreach($items as $key=>$label):?><option value="<?=h($src.':'.$key)?>"><?=h($label)?></option><?php endforeach;?></optgroup>
<?php endforeach;?></select></label>
<label class="check"><input type="checkbox" name="enforced" value="1"> Aktifkan hanya pemeriksa dan Lurah yang telah ditugaskan</label>
<button class="btn" type="submit" <?=$guardReady?'':'disabled'?>>Simpan Kebijakan Kategori</button></form>
<p class="hint">Untuk mematikan kebijakan ketat kategori tertentu, pilih kategori yang sama lalu simpan tanpa mencentang opsi aktif. Ini mengembalikan pemeriksaan berbasis peran pada Tahap 19.</p></section>
<section class="panel"><h2>2. Pengganti Sementara</h2>
<p class="hint">Pengganti adalah **pemeriksa cadangan tambahan**, bukan pemindahan kepemilikan laporan. Petugas utama tetap berwenang, sedangkan cadangan wajib mempunyai peran yang sama-sama sah.</p>
<form method="post" action="/kendali-verifikasi22.php" class="form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="deputy">
<label>Kategori<select name="category_key" required><option value="">Pilih jenis laporan</option>
<?php foreach($catalog as $src=>$items):?><optgroup label="<?=h(m20_sources()[$src]['label']??$src)?>">
<?php foreach($items as $key=>$label):?><option value="<?=h($src.':'.$key)?>"><?=h($label)?></option><?php endforeach;?></optgroup><?php endforeach;?></select></label>
<label>Tahap<select name="step" required><option value="pemeriksa">Pemeriksa kelengkapan</option><option value="lurah">Telaah Lurah (harus akun Lurah asli)</option></select></label>
<label>Akun pengganti<select name="deputy_key" required><option value="">Pilih akun aktif sesuai peran</option>
<?php foreach($candidates as $p):?><option value="<?=h($p['source'].'|'.$p['id'])?>"><?=h($p['name'])?> · <?=h($p['role'])?> (<?=h($p['source'])?>)</option><?php endforeach;?></select></label>
<div class="date-grid"><label>Mulai berlaku<input name="starts_on" type="date" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required></label>
<label>Berlaku sampai<input name="ends_on" type="date" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d',strtotime('+6 days'))?>" required></label></div>
<label>Alasan penunjukan<input name="reason" minlength="12" maxlength="220" required placeholder="Petugas utama cuti / tugas luar, sesuai penetapan internal"></label>
<button class="btn" type="submit">Tetapkan Cadangan Maksimal 90 Hari</button></form>
<p class="hint">Bila aturan kategori belum diaktifkan dalam mode ketat, penetapan pengganti hanya tersimpan sebagai persiapan dan tidak memberi akses khusus.</p></section>
</div>
<section class="panel"><div class="panel-head"><h2>Kebijakan Pemeriksaan Tersimpan</h2><span class="tag"><?=count($policies)?> kategori</span></div>
<?php if(!$policies):?><div class="empty">Belum ada kebijakan khusus. Tahap 19 masih berjalan menggunakan peran yang sudah ada.</div>
<?php else:?><div class="scroll"><table><thead><tr><th>Modul</th><th>Kategori</th><th>Mode</th><th>Perubahan</th></tr></thead><tbody>
<?php foreach($policies as $p):?><tr><td><?=h($p['source_kind'])?></td><td><?=h($catalog[$p['source_kind']][$p['category']]??$p['category'])?></td>
<td><span class="<?=!empty($p['enforced'])?'state-on':'state-off'?>"><?=!empty($p['enforced'])?'Khusus yang ditugaskan':'Berbasis peran Tahap 19'?></span></td>
<td><?=h($p['updated_at'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<section class="panel"><div class="panel-head"><h2>Cadangan Sementara</h2><span class="tag"><?=count($replacements)?> tercatat</span></div>
<?php if(!$replacements):?><div class="empty">Belum ada pengganti sementara yang ditunjuk.</div>
<?php else:?><div class="scroll"><table><thead><tr><th>Kategori</th><th>Tahap</th><th>Pengganti</th><th>Masa berlaku</th><th>Status dan pencabutan</th></tr></thead><tbody>
<?php foreach($replacements as $d):
$active=empty($d['revoked_at'])&&$d['starts_on']<=date('Y-m-d')&&$d['ends_on']>=date('Y-m-d');
?>
<tr><td><?=h($d['source_kind'])?> · <?=h($catalog[$d['source_kind']][$d['category']]??$d['category'])?></td>
<td><?=h(t21_steps()[$d['step']]??$d['step'])?></td>
<td><?=h($names[$d['deputy_source'].'|'.$d['deputy_id']]??$d['deputy_source'].' · ID '.$d['deputy_id'])?></td>
<td><?=h($d['starts_on'])?> sampai <?=h($d['ends_on'])?><small><?=h($d['reason'])?></small></td>
<td><span class="<?=$active?'state-on':'state-off'?>"><?=$active?'Berlaku':'Tidak berlaku'?></span>
<?php if(empty($d['revoked_at'])):?><form method="post" action="/kendali-verifikasi22.php" class="inline-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="revoke">
<input type="hidden" name="category_key" value="<?=h($d['source_kind'].':'.$d['category'])?>">
<input type="hidden" name="step" value="<?=h($d['step'])?>">
<button class="btn secondary" type="submit">Cabut pengganti</button></form><?php endif;?></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<section class="panel"><div class="panel-head"><h2>Rekap Tindakan Verifikasi</h2>
<form method="get" action="/kendali-verifikasi22.php" class="month"><input name="bulan" type="month" value="<?=h($month)?>" required><button class="btn secondary" type="submit">Tampilkan</button></form></div>
<p class="hint">Jumlah tindakan tercatat berdasarkan tanggal tindakan: pemeriksaan kelengkapan, pengembalian, telaah Lurah. Tidak menghitung nilai kinerja individu atau durasi kerja resmi.</p>
<div class="metric-grid">
<?php foreach(v19_sources() as $src=>$spec):$m=$metrics['modules'][$src]??[];?>
<div class="metric"><b><?=h(m20_sources()[$src]['label']??$src)?></b>
<span><strong><?=(int)($m['periksa']??0)?></strong><small>Diperiksa</small></span>
<span><strong><?=(int)($m['kembalikan']??0)+(int)($m['lurah_kembalikan']??0)?></strong><small>Dikembalikan</small></span>
<span><strong><?=(int)($m['lurah_telaah']??0)?></strong><small>Telaah Lurah</small></span></div>
<?php endforeach;?></div>
</section>
<p class="footnote">Sistem melindungi tindakan verifikasi hanya setelah pengaman pada Tahap 19 terpasang dan kategori diaktifkan. Penugasan tidak mengganti prosedur resmi persetujuan cuti, surat pengantar, atau penilaian ASN.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Kendali Verifikasi Administratif</div></footer></body></html>
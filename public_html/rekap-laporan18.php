<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/evidence18.php';
    require_once dirname(__DIR__).'/portal_private/recap18.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Rekap Super Admin belum tersedia.');}
$start=(string)($_GET['mulai']??date('Y-m-01'));
$end=(string)($_GET['sampai']??date('Y-m-d'));
$source=(string)($_GET['modul']??'');
$error='';$data=['rows'=>[],'incomplete'=>[],'start'=>$start,'end'=>$end,'source'=>$source];
try{$data=e18_recap_rows($admin,$start,$end,$source);}
catch(Throwable $e){
    $error=($e instanceof InvalidArgumentException)?$e->getMessage():'Rekap tidak dapat dimuat. Periksa database dan hak akses.';
}
$summary=e18_recap_counts($data['rows']);
if(($_GET['csv']??'')==='1'){
    if($error!==''){http_response_code(400);exit($error);}
    e18_audit_insert(db('smartapp'),$admin,'evidence18_report_exported',$start.'_'.$end.'_'.$source);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rekap-smartcikoko-'.date('Ymd').'.csv"');
    header('X-Content-Type-Options: nosniff');
    $out=fopen('php://output','wb');
    fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Jenis Modul','ID Laporan','Kategori','Status Administratif','Tanggal Input','Terakhir Diperbarui'],',','"','');
    foreach($data['rows'] as $row){
        fputcsv($out,[
            e18_csv_safe($row['modul']),$row['id'],e18_csv_safe($row['kategori']),
            e18_csv_safe($row['status']),$row['dibuat'],$row['diperbarui']
        ],',','"','');
    }
    if($data['incomplete']){
        fputcsv($out,['CATATAN','Rekap mungkin tidak lengkap; modul tidak tersedia atau melebihi batas 1500 catatan per modul.','','','',''],',','"','');
    }
    fclose($out);exit;
}
$params=['mulai'=>$start,'sampai'=>$end,'modul'=>$source,'csv'=>1];
$csv='/rekap-laporan18.php?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#123956">
<title>Rekap Laporan SmartCikoko | Super Administrator</title>
<link rel="stylesheet" href="/assets/bukti18.css"></head><body>
<div class="gov"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a class="brand" href="/"><span>KC</span><b>SMARTCIKOKO<small>Rekap Laporan Administratif</small></b></a>
<nav><a href="/super-admin-satu.php#bukti-dan-rekap">Super Admin Ringkas</a><a href="/bukti-laporan18.php">Bukti Foto</a></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 18 · REKAP BERDASARKAN DATA LAPORAN</small>
<h1>Rekap Laporan Terpadu</h1>
<p>Laporan ringkas Tahap 15, formulir pegawai Tahap 16, dan laporan wilayah Tahap 17. Hanya metadata tanpa isi laporan dan data warga.</p></div></section>
<main class="wrap content">
<div class="panel">
<h2>Filter Periode Laporan</h2>
<form class="filters" method="get" action="/rekap-laporan18.php">
<label>Mulai<input type="date" name="mulai" value="<?=h($start)?>" required></label>
<label>Sampai<input type="date" name="sampai" value="<?=h($end)?>" required></label>
<label>Jenis Modul<select name="modul">
<option value="">Semua Modul</option>
<?php foreach(['ringkas15'=>'Laporan Ringkas Tahap 15','pegawai16'=>'Formulir Pegawai Tahap 16','wilayah17'=>'Laporan Wilayah Tahap 17'] as $key=>$label):?>
<option value="<?=h($key)?>" <?=$source===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
<button class="btn" type="submit">Tampilkan Rekap</button></form>
<?php if($error):?><div class="alert bad"><?=h($error)?></div><?php else:?>
<div class="signals"><span>Jumlah baris: <b><?=number_format(count($data['rows']),0,',','.')?></b></span>
<span>Rentang: <b><?=h($data['start'])?> sampai <?=h($data['end'])?></b></span></div>
<?php if($data['incomplete']):?><div class="alert bad">Rekap belum lengkap: <?=h(implode(', ',$data['incomplete']))?>. Modul tersebut belum tersedia atau melebihi batas 1.500 catatan per modul. Persempit tanggal sebelum mengunduh.</div><?php endif;?>
<a class="btn" href="<?=h($csv)?>">Unduh CSV tanpa Data Pribadi</a>
<div class="recap-cards">
<?php foreach($summary as $key=>$value):?>
<div class="recap-card"><small><?=h(str_replace(':',' · ',$key))?></small><strong><?=(int)$value?></strong></div>
<?php endforeach;?>
</div>
<?php if(!$data['rows']):?><p class="empty">Belum ada data laporan pada periode yang dipilih.</p><?php else:?>
<div class="table-scroll"><table><thead><tr><th>Modul</th><th>ID</th><th>Kategori</th><th>Status administratif</th><th>Dibuat</th></tr></thead><tbody>
<?php foreach(array_slice($data['rows'],0,80) as $row):?>
<tr><td><?=h($row['modul'])?></td><td><?=(int)$row['id']?></td><td><?=h($row['kategori'])?></td><td><?=h($row['status'])?></td><td><?=h($row['dibuat'])?></td></tr>
<?php endforeach;?></tbody></table></div>
<?php if(count($data['rows'])>80):?><p class="footnote">Tampilan menampilkan 80 baris terbaru. Unduh CSV untuk data tambahan pada rentang ini.</p><?php endif;?>
<?php endif;?>
<?php endif;?>
</div>
<p class="footnote">Rekap ini bukan penilaian resmi, laporan statistik kesehatan yang sudah diverifikasi, atau persetujuan surat/cuti. Keputusan formal tetap mengikuti kewenangan instansi dan sistem asal.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Rekap Administratif Terpadu</div></footer>
</body></html>
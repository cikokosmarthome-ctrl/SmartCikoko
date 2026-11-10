<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/hub23.php';
    $user=requires_login();
    if(!account_control_allowed($user)){http_response_code(403);exit('Akun tidak memiliki izin membuka Pusat Operasional.');}
    $data=h23_overview($user);
}catch(Throwable $e){
    error_log('Stage23 portal not available: '.get_class($e));
    http_response_code(503);exit('Pusat Operasional belum siap. Periksa pemasangan modul.');
}
$query=trim((string)($_GET['cari']??''));
if(mb_strlen($query)>80)$query=mb_substr($query,0,80);
$group=(string)($_GET['bagian']??'');
if(!in_array($group,['','umum','laporan','pelayanan','tugas','pengendalian'],true))$group='';
$cards=h23_filter_cards($data['cards'],$query,$group);
$groups=[
    'umum'=>['title'=>'Ruang Kerja Utama','subtitle'=>'Akses awal akun dan fungsi dasar'],
    'laporan'=>['title'=>'Input dan Perbaikan Laporan','subtitle'=>'Formulir sesuai peran, tanpa mengubah proses resmi'],
    'pelayanan'=>['title'=>'Pelayanan Masyarakat','subtitle'=>'Akses hanya melalui kewenangan yang sudah ada'],
    'tugas'=>['title'=>'Tugas, Bukti dan Pengingat','subtitle'=>'Pemeriksaan, tindak lanjut dan pesan internal'],
    'pengendalian'=>['title'=>'Pengendalian Super Admin','subtitle'=>'Akses pengaturan sistem yang sudah tersedia'],
];
$first=(string)($user['name']??'Pengguna');
$stageLabels=['15'=>'Ringkasan','16'=>'Pegawai','17'=>'Wilayah','18'=>'Bukti & Rekap','19'=>'Verifikasi','20'=>'Monitoring','21'=>'Penugasan','22'=>'Kendali'];
$metrics=$data['counts']['totals'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d3555">
<title>Pusat Operasional | SmartCikoko</title><link rel="stylesheet" href="/assets/pusat-operasional23.css"></head>
<body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="top"><div class="wrap bar">
<a href="/" class="brand"><span class="crest">KC</span><span><b>SMARTCIKOKO</b><small>Pusat Operasional Terpadu</small></span></a>
<nav><a class="active" href="/pusat-operasional23.php">Pusat Operasional</a><a href="/?page=dashboard">Ruang Kerja</a>
<?php if($data['admin']):?><a href="/super-admin-satu.php">Super Admin Ringkas</a><?php endif;?>
<a href="/pusat-laporan.php">Pusat Laporan</a></nav></div></header>
<main class="wrap">
<section class="heading"><div><small>TAHAP 23 · SATU TITIK AKSES OPERASIONAL</small>
<h1>Pusat Operasional <em>SmartCikoko</em></h1><p>Semua pekerjaan yang tersedia untuk akun Anda, dalam satu tampilan yang ringkas. Pilih modul, lanjutkan tugas, atau lihat status laporan tanpa berpindah-pindah menu terlebih dahulu.</p></div>
<div class="actor"><span><?=h(mb_strtoupper(mb_substr($first,0,1)))?></span><div><b><?=h($first)?></b><small><?=h(role_label((string)$user['role']))?> · <?=h(strtoupper((string)$user['source']))?></small></div></div></section>
<?php if(!empty($data['warnings'])):?><div class="alert warning">
<strong>Informasi sebagian belum termuat:</strong> <?=h(implode(', ',$data['warnings']))?>.
Menu yang tersedia tetap dapat dibuka sesuai hak akun. Silakan laporkan bagian ini kepada pengelola; sistem tidak menampilkan data privat atau pesan kesalahan teknis.
</div><?php endif;?>
<div class="alert subtle">Akun: <strong><?=h($data['counts']['scope'])?></strong> · Angka berikut adalah status administrasi pada formulir tambahan Tahap 15–17, bukan persetujuan cuti, penerbitan surat, atau penilaian resmi.</div>
<section class="metrics" aria-label="Ringkasan administratif">
<div class="metric"><small>Draf</small><strong><?=$data['counts']['available']?(int)$metrics['draf']:'—'?></strong><span>Belum dikirim</span></div>
<div class="metric"><small>Dalam Proses</small><strong><?=$data['counts']['available']?(int)$metrics['aktif']:'—'?></strong><span>Diajukan / ditinjau</span></div>
<div class="metric"><small>Perlu Perbaikan</small><strong><?=$data['counts']['available']?(int)$metrics['perbaikan']:'—'?></strong><span>Menunggu koreksi</span></div>
<div class="metric"><small>Selesai Administrasi</small><strong><?=$data['counts']['available']?(int)$metrics['selesai']:'—'?></strong><span>Ditutup</span></div>
</section>
<div class="alert subtle">
<strong>Rincian sumber laporan:</strong>
<?php foreach(['15'=>'Ringkas','16'=>'Pegawai','17'=>'Wilayah'] as $st=>$label):
$info=$data['counts']['by_stage'][$st]??['ready'=>false,'total'=>null];?>
<span style="margin-left:14px;display:inline-block">Tahap <?=h($st)?> · <?=h($label)?>:
<strong><?=$info['ready']?number_format((int)$info['total'],0,',','.'): 'belum terbaca'?></strong></span>
<?php endforeach;?>
</div>
<?php if($data['counts']['missing']):?>
<div class="alert warning">Data status belum lengkap dari Tahap <?=h(implode(', ',array_unique($data['counts']['missing'])))?>. Modul lain tetap dapat dibuka sesuai izin; tanda “0” tidak boleh dianggap sebagai tidak ada laporan ketika sumber belum tersedia.</div>
<?php endif;?>
<section class="focus">
<div class="focus-card"><div class="focus-head"><h2>Tugas Perlu Tindakan</h2><span class="pill"><?=count($data['tasks']['rows'])?> terlihat</span></div>
<?php if($data['tasks']['rows']):?>
<div class="task-list">
<?php foreach($data['tasks']['rows'] as $task):?>
<a href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($task['source']))?>&amp;id=<?=(int)$task['id']?>">
<span><b><?=h($stageLabels[substr($task['source'],-2)]??$task['source'])?> #<?=(int)$task['id']?> · <?=h($task['category'])?></b>
<small><?=h($task['type']==='cadangan'?'Tugas pengganti':'Tugas utama')?> · <?=h(t21_steps()[$task['stage']]??$task['stage'])?></small></span>
<span class="<?=$task['overdue']?'late':'neutral'?>"><?=(int)$task['days']?> hari <?=$task['overdue']?'· Perlu perhatian':''?></span>
</a><?php endforeach;?>
</div>
<?php else:?><p class="empty"><?=$data['admin']?'Pantau tindak lanjut melalui menu Monitoring Bulanan.':'Tidak ada tugas pemeriksaan yang saat ini ditugaskan kepada akun Anda.'?></p><?php endif;?>
<?php if($data['tasks']['incomplete']):?><p class="tiny">Pratinjau dibatasi. Buka halaman tugas untuk daftar yang lebih lengkap.</p><?php endif;?>
</div>
<div class="focus-card side"><div class="focus-head"><h2>Pengingat Internal</h2><span class="pill"><?=$data['notifications']['unread']?> belum dibaca<?=$data['notifications']['incomplete']?'+':''?></span></div>
<p class="tiny">Hanya pengingat untuk akun Anda. Status pesan dibaca tidak berarti laporan telah diselesaikan.</p>
<a class="action-link" href="/pengingat-saya20.php">Pengingat Tahap 20 <span>Lihat pesan →</span></a>
<?php if(h23_role_reviewer($user)):?>
<a class="action-link" href="/penugasan-saya21.php">Kotak Penugasan <span>Lihat tugas →</span></a>
<a class="action-link" href="/pengganti-saya22.php">Pengganti Sementara <span>Lihat tugas →</span></a>
<?php endif;?>
<?php if($data['admin']):?><a class="action-link" href="/monitor-laporan20.php">Monitoring Semua Laporan <span>Buka kontrol →</span></a><?php endif;?>
</div></section>
<section class="modules" id="modul">
<div class="section-title"><div><small>NAVIGASI BERDASARKAN PERAN</small><h2>Menu Kerja dan Layanan</h2></div><span class="pill"><?=count($cards)?> menu cocok</span></div>
<form class="filters" method="get" action="/pusat-operasional23.php">
<label>Temukan modul<input type="search" name="cari" maxlength="80" value="<?=h($query)?>" placeholder="Cari: PPPK, Cuti, RT/RW, Penugasan..."></label>
<label>Kelompok<select name="bagian"><option value="">Semua kelompok</option>
<?php foreach($groups as $key=>$g):?><option value="<?=h($key)?>" <?=$group===$key?'selected':''?>><?=h($g['title'])?></option><?php endforeach;?></select></label>
<button class="btn" type="submit">Cari Menu</button>
<?php if($query!==''||$group!==''):?><a class="btn secondary" href="/pusat-operasional23.php#modul">Reset</a><?php endif;?></form>
<?php if(!$cards):?><div class="empty wide">Tidak ada menu yang sesuai pencarian dan hak akses akun. Coba kata lain atau pilih semua kelompok.</div><?php endif;?>
<?php foreach($groups as $key=>$g):
$items=array_values(array_filter($cards,static fn($c)=>$c['group']===$key));
if(!$items)continue;
?>
<div class="module-group" id="<?=h($key)?>"><div class="group-title"><h3><?=h($g['title'])?></h3><p><?=h($g['subtitle'])?></p></div>
<div class="module-grid">
<?php foreach($items as $item):?>
<a class="module-card <?=$item['ready']?'':'not-ready'?>" href="<?=h($item['href'])?>">
<div class="module-head"><span class="stage"><?=h($item['stage']==='Portal'?'Portal':'T'.$item['stage'])?></span>
<span class="readiness"><?=$item['ready']?'Tersedia':'Perlu instalasi / pemeriksaan'?></span></div>
<b><?=h($item['label'])?></b><p><?=h($item['description'])?></p><span class="open-link">Buka modul →</span>
</a><?php endforeach;?>
</div></div>
<?php endforeach;?>
</section>
<section class="hint-panel"><div><h2>Alur kerja tetap terjaga</h2><p>Pusat Operasional tidak membuat tabel, salinan laporan, atau proses persetujuan baru. Tautan membuka modul asli; setiap tindakan tetap melalui login, pemeriksaan status akun, pembatasan peran, dan verifikasi berjenjang sesuai pengaturan yang berlaku.</p></div>
<?php if($data['admin']):?><a class="btn" href="/super-admin-satu.php">Buka Super Admin Ringkas</a><?php else:?><a class="btn" href="/?page=dashboard">Kembali ke Ruang Kerja</a><?php endif;?></section>
</main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Operasional Administratif Terpadu</div></footer>
</body></html>
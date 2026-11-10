<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    $viewer=actor();
    if(!$viewer){header('Location: /?page=login',true,303);exit;}
    require_once dirname(__DIR__).'/portal_private/cikoko_smart_catalog.php';
    require_once dirname(__DIR__).'/portal_private/workflow_feed.php';
    $modules=sc_smart_catalog($viewer);
    $summary=sc_smart_catalog_summary($modules);
    $activity=sc_workflow_feed($viewer);
    $admin=is_super_admin($viewer);
    $legacy=$admin?lr_native_counts():[];
    $new=$admin?lr_new_counts():[];
} catch(RuntimeException $e){http_response_code(403);exit('Akses Cikoko Smart ditolak.');}
catch(Throwable $e){
    error_log('Cikoko Smart hub unavailable: '.get_class($e));
    http_response_code(503);exit('Pusat Cikoko Smart belum tersedia di server.');
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d4b88">
<title>Cikoko Smart | Pusat Laporan dan Surat Terpadu</title>
<link rel="stylesheet" href="/assets/cikoko-smart.css?v=20261110">
</head><body>
<header class="top"><div class="wrap heading">
 <a class="brand" href="/"><span class="mark" aria-hidden="true">CS</span><span><b>CIKOKO SMART</b><small>Kelurahan Cikoko · Kecamatan Pancoran</small></span></a>
 <nav aria-label="Navigasi"><a href="/">Beranda</a><a href="/aktivitas-terpadu.php">Notifikasi</a><?php if($admin):?><a href="/super-admin-satu.php">Super Admin</a><?php endif;?></nav>
</div></header>
<main class="wrap">
 <section class="intro"><div><span class="eyebrow">LAYANAN DIGITAL KELURAHAN CIKOKO</span>
 <h1>Satu Pintu Laporan dan Pengajuan Surat</h1>
 <p>Selamat datang, <strong><?=h((string)$viewer['name'])?></strong>. Formulir ditampilkan sesuai hak akses. Website dan aplikasi menggunakan sistem penyimpanan yang sama; seluruh keputusan harus melalui petugas berwenang.</p></div>
 <a class="button" href="/aktivitas-terpadu.php">Lihat Progres dan Notifikasi →</a></section>
 <section class="totals" aria-label="Ringkasan modul">
 <div><b><?=(int)$summary['total']?></b><span>Modul Tersedia / Terpantau</span></div>
 <div><b><?=(int)$summary['can_create']?></b><span>Hak Penginputan</span></div>
 <div><b><?=(int)$summary['can_review']?></b><span>Hak Pemeriksaan</span></div>
 <div><b><?=(int)$activity['summary']['total']?></b><span>Aktivitas Akun Terbaru</span></div>
 </section>
 <?php if($admin):?><section class="admin-banner">
 <div><strong>Panel Super Administrator</strong><p>Pantau lintas modul, tinjau dan tindak lanjuti melalui halaman asli, tanpa mengambil identitas pelapor atau menerbitkan surat secara otomatis.</p></div>
 <a class="button light" href="/super-admin-satu.php">Buka Kendali Pusat</a></section><?php endif;?>
 <section class="section"><div class="section-top"><div><span class="eyebrow">DAFTAR MODUL RESMI</span>
 <h2>Penginputan dan Pemeriksaan</h2><p>Mempertahankan pemisahan formulir asli, kategori ringkasan tambahan, dan hak akses setiap akun.</p></div></div>
 <div class="filters" aria-label="Saring modul">
 <button type="button" class="active" data-filter="semua">Semua</button><button type="button" data-filter="pegawai">Pegawai</button><button type="button" data-filter="wilayah">Wilayah</button>
 <button type="button" data-filter="laporan">Ringkasan</button><button type="button" data-filter="warga">Pelayanan Surat</button></div>
 <div class="catalog" id="catalog">
 <?php foreach($modules as $m):?>
 <article class="module" data-group="<?=h($m['group'])?>">
   <div class="category"><?=h($m['section'])?></div>
   <h3><?=h($m['title'])?></h3>
   <p><?=h($m['description'])?></p>
   <div class="meta"><span><?=h($m['source'])?></span><span><?=(int)$m['fields_total']?> bidang dasar</span></div>
   <div class="roles"><?php if($m['can_create']):?><span>Input diizinkan</span><?php endif;?>
   <?php if($m['can_review']):?><span>Pemeriksaan berwenang</span><?php endif;?>
   <?php if($m['has_legacy_attachments']):?><span>Checklist lampiran</span><?php endif;?></div>
   <a class="button" href="<?=h($m['web_url'])?>"><?=$m['can_create']?'Buka Formulir':'Buka Pemeriksaan'?> →</a>
 </article>
 <?php endforeach;?>
 <?php if(!$modules):?><p class="empty">Tidak ada modul yang saat ini diizinkan untuk akun Anda. Hubungi administrator bila hak akses perlu diperiksa.</p><?php endif;?>
 </div></section>
 <section class="section progress"><div><h2>Progres dan Notifikasi Terpadu</h2>
 <p>Persentase 0–100% menunjukkan tahapan administratif dari database server, bukan hitungan waktu. Perbaikan/penolakan tidak otomatis berarti pelayanan sukses.</p></div>
 <div class="steps"><span>0% <small>Draf</small></span><span>25% <small>Diajukan</small></span><span>50–75% <small>Diperiksa</small></span><span>90% <small>Keputusan</small></span><span>100% <small>Selesai / Ditutup</small></span></div>
 <a href="/aktivitas-terpadu.php">Baca Semua Riwayat →</a>
 </section>
 <?php if($admin):?><section class="section"><h2>Monitoring Data Lama dan Data Terpadu</h2>
 <p class="muted">Angka berasal dari tabel sumber yang terpasang di hosting. Simbol “—” berarti tabel sumber belum bisa dibaca, bukan nol data. Data lama tidak dipindahkan atau diubah otomatis.</p>
 <div class="table-wrap"><table><thead><tr><th>Kategori</th><th>Arsip Asli</th><th>Ringkasan Smart</th><th>Akses</th></tr></thead><tbody>
 <?php foreach(lr_categories() as $name=>$info):?><tr>
 <td><?=h($info['label'])?></td><td><?=array_key_exists($name,$legacy)&&$legacy[$name]!==null?number_format((int)$legacy[$name],0,',','.'):'—'?></td>
 <td><?=array_key_exists($name,$new)?number_format((int)$new[$name],0,',','.'):'—'?></td>
 <td><a href="/pusat-laporan.php?kategori=<?=rawurlencode($name)?>">Periksa →</a></td>
 </tr><?php endforeach;?></tbody></table></div></section><?php endif;?>
 <?php if($activity['unavailable_modules']):?><p class="warning">Modul server belum aktif: <?=h(implode(', ',$activity['unavailable_modules']))?>. Data modul tersebut tidak termasuk dalam ringkasan.</p><?php endif;?>
 <p class="note">Keterangan: Formulir Tahap 16/17 dan ringkasan Tahap 15 adalah alur terstruktur baru yang didasarkan pada pemetaan arsip; belum dipastikan identik 100% dengan setiap file lampiran dan cetakan pada ZIP lama. Kewenangan dan arsip lama tetap dijaga.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Portal Cikoko Smart · Akun dan laporan dilindungi sesuai izin pengguna</div></footer>
<script defer src="/assets/cikoko-smart.js?v=20261110"></script>
</body></html>

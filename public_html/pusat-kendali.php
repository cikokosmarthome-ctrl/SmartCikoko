<?php
declare(strict_types=1);

/**
 * Pusat Kendali Administrator — read-only operational overview.
 * All privileged data lives server side; no personal identifiers in charts.
 * Requires existing authenticated SmartCikoko session.
 */
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(503);
    exit('Portal belum siap. Hubungi administrator.');
}
$me=actor();
if (!$me) {
    header('Location: /?page=login', true, 303);
    exit;
}
if (!is_admin($me)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman hanya untuk administrator yang berwenang.');
}

/** Query agregat tidak mengembalikan nama, NIK, email, maupun nomor telepon. */
function summary_number(string $source, string $sql): ?int {
    try {
        return (int)db($source)->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('[SmartCikoko/Admin] Ringkasan gagal: '.$source.' / '.$e->getMessage());
        return null;
    }
}
function source_ready(string $source): bool {
    try { return (int)db($source)->query('SELECT 1')->fetchColumn()===1; }
    catch(Throwable $e) { error_log('[SmartCikoko/Admin] Koneksi '.$source.' gagal: '.$e->getMessage());return false; }
}
function role_breakdown(string $source,string $sql): array {
    try {
        $stmt=db($source)->query($sql);
        return array_map(static function(array $r):array {
            return ['role'=>(string)$r['kategori'],'total'=>(int)$r['jumlah']];
        },$stmt->fetchAll());
    } catch(Throwable $e) {
        error_log('[SmartCikoko/Admin] Kelompok peran gagal: '.$source.' / '.$e->getMessage());
        return [];
    }
}
function val(?int $n):string {return $n===null?'—':number_format($n,0,',','.');}
function ratio(?int $n,?int $total):int {
    return $n!==null&&$total!==null&&$total>0?(int)min(100,max(0,round(100*$n/$total))):0;
}
$ready=['pegawai'=>source_ready('pegawai'),'smartapp'=>source_ready('smartapp')];
$tiles=[
    ['label'=>'Akun Pegawai','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM users','mark'=>'01','href'=>'/?page=pegawai'],
    ['label'=>'Akun PPPK Pancoran','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM ck_pancoran_users','mark'=>'02','href'=>'/?page=pppk'],
    ['label'=>'Akun Portal Cikoko','source'=>'smartapp','sql'=>'SELECT COUNT(*) FROM unified_portal_users','mark'=>'03','href'=>'/?page=dashboard'],
    ['label'=>'Pengajuan Ahli Waris','source'=>'smartapp','sql'=>'SELECT COUNT(*) FROM aw_applications','mark'=>'04','href'=>'/?page=waris'],
    ['label'=>'Permohonan Dalam Proses','source'=>'smartapp','sql'=>"SELECT COUNT(*) FROM aw_applications WHERE status IN ('masuk','proses','revisi')",'mark'=>'05','href'=>'/?page=waris'],
    ['label'=>'Permohonan Selesai','source'=>'smartapp','sql'=>"SELECT COUNT(*) FROM aw_applications WHERE status='selesai'",'mark'=>'06','href'=>'/?page=waris'],
    ['label'=>'Laporan Kinerja PPPK','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM ck_pppk_kinerja','mark'=>'07','href'=>'/?page=pppk'],
    ['label'=>'Laporan PJLP / PPSU','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM laporan','mark'=>'08','href'=>'/?page=pjlp'],
];
foreach($tiles as &$tile) $tile['value']=$ready[$tile['source']]?summary_number($tile['source'],$tile['sql']):null;
unset($tile);
$warisTotals=['masuk','proses','revisi','selesai','ditolak'];
$statusValues=[];
foreach($warisTotals as $status) {
    // Static allowlisted status, never use an arbitrary request parameter here.
    $statusValues[$status]=$ready['smartapp']?summary_number('smartapp',"SELECT COUNT(*) FROM aw_applications WHERE status='$status'"):null;
}
$allWaris=$tiles[3]['value'];
$rolesPegawai=$ready['pegawai']?role_breakdown('pegawai','SELECT role AS kategori,COUNT(*) AS jumlah FROM users GROUP BY role ORDER BY jumlah DESC LIMIT 12'):[];
$rolesSmart=$ready['smartapp']?role_breakdown('smartapp','SELECT role AS kategori,COUNT(*) AS jumlah FROM unified_portal_users GROUP BY role ORDER BY jumlah DESC LIMIT 12'):[];
$auditLast7Days=$ready['smartapp']?summary_number('smartapp','SELECT COUNT(*) FROM unified_audit WHERE created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY)'):null;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b2a48">
<meta name="robots" content="noindex,nofollow">
<title>Pusat Kendali Administrator | Kelurahan Cikoko</title>
<link rel="icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/pusat-kendali.css">
</head>
<body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="mainbar"><div class="wrap navigation">
  <a class="brand" href="/"><span class="brandmark">KC</span><span><b>KELURAHAN CIKOKO</b><small>Portal Pelayanan dan Informasi Terpadu</small></span></a>
  <nav aria-label="Navigasi administrator">
    <a href="/">Beranda</a>
    <a href="/?page=dashboard">Ruang Kerja</a>
    <a href="/?page=pegawai">Pegawai</a>
    <a href="/?page=pppk">PPPK</a>
    <a href="/?page=pjlp">PJLP</a>
    <a href="/?page=waris">Ahli Waris</a>
    <a href="/?page=profil">Akun</a>
  </nav>
</div></header>
<main>
<section class="hero"><div class="wrap hero-inner">
  <div><span class="eyebrow">RUANG KERJA TERPADU / ADMINISTRATOR</span>
    <h1>Pusat Kendali <span>Administrator</span></h1>
    <p>Ringkasan terpadu layanan masyarakat, kepegawaian, dan kegiatan administrasi Kelurahan Cikoko.</p>
    <p class="hero-foot">Akses terbatas · Data diperbarui dari dua database secara langsung</p>
  </div>
  <div class="identity">
    <div class="circle"><?=h(mb_strtoupper(mb_substr((string)$me['name'],0,1)))?></div>
    <div><span class="sub">AKUN AKTIF</span><strong><?=h($me['name'])?></strong>
    <small><?=h(role_label((string)$me['role']))?></small></div>
  </div>
</div></section>
<div class="wrap content">
  <div class="subhead"><div><span class="section-label">STATUS INTEGRASI</span><h2>Jaringan database terhubung</h2><p>Konektivitas dua database diperiksa terpisah. Tidak ada data yang dipindahkan.</p></div>
  <a class="link" href="/?page=dashboard">Kembali ke Ruang Kerja ↗</a></div>
  <div class="health-grid">
    <div class="health"><span class="light <?=$ready['pegawai']?'ok':'off'?>"></span>
      <div><small>DATABASE KEPEGAWAIAN</small><strong>datd1747_Pegawai</strong><p><?=$ready['pegawai']?'Koneksi berhasil':'Tidak dapat terhubung — periksa koneksi'?></p></div>
      <span class="status <?=$ready['pegawai']?'live':'down'?>"><?=$ready['pegawai']?'Terhubung':'Gangguan'?></span>
    </div>
    <div class="health"><span class="light <?=$ready['smartapp']?'ok':'off'?>"></span>
      <div><small>DATABASE PELAYANAN</small><strong>datd1747_smartapp</strong><p><?=$ready['smartapp']?'Koneksi berhasil':'Tidak dapat terhubung — periksa koneksi'?></p></div>
      <span class="status <?=$ready['smartapp']?'live':'down'?>"><?=$ready['smartapp']?'Terhubung':'Gangguan'?></span>
    </div>
  </div>

  <div class="subhead spaced"><div><span class="section-label">REKAPITULASI</span><h2>Ringkasan Data dan Pelayanan</h2></div></div>
  <div class="kpis">
    <?php foreach($tiles as $tile):?>
      <a href="<?=h($tile['href'])?>" class="kpi">
        <span class="ordinal"><?=h($tile['mark'])?></span>
        <span class="kpi-label"><?=h($tile['label'])?></span>
        <strong><?=val($tile['value'])?></strong>
        <small><?=h($tile['source']==='pegawai'?'Database Kepegawaian':'Database Pelayanan')?> <span>↗</span></small>
      </a>
    <?php endforeach;?>
  </div>
  <div class="columns">
    <section class="pane">
      <div class="pane-head"><div><span class="section-label">PELAYANAN MASYARAKAT</span><h2>Status Pengajuan Ahli Waris</h2></div>
      <a href="/?page=waris" class="link">Kelola pengajuan ↗</a></div>
      <?php foreach($statusValues as $key=>$count):?>
        <div class="bar-row">
          <div class="bar-top"><span><?=h(ucfirst($key))?></span><strong><?=val($count)?></strong></div>
          <div class="track"><progress value="<?=max(0,(int)($count??0))?>" max="<?=max(1,(int)($allWaris??0))?>" aria-label="Proporsi status <?=h($key)?>"></progress></div>
        </div>
      <?php endforeach;?>
      <p class="explain">Grafik berdasarkan jumlah permohonan pada tabel pengajuan ahli waris. Tidak menampilkan identitas pemohon.</p>
    </section>
    <section class="pane">
      <div class="pane-head"><div><span class="section-label">AKUN DAN KEWENANGAN</span><h2>Komposisi Akun per Sumber</h2></div></div>
      <h3>Database Pegawai</h3>
      <?php if(!$rolesPegawai):?><p class="empty">Komposisi akun belum tersedia.</p><?php endif;?>
      <div class="roles"><?php foreach($rolesPegawai as $r):?>
        <div><span><?=h(role_label($r['role']))?></span><strong><?=val($r['total'])?></strong></div>
      <?php endforeach;?></div>
      <h3>Database Portal Cikoko</h3>
      <?php if(!$rolesSmart):?><p class="empty">Komposisi akun belum tersedia.</p><?php endif;?>
      <div class="roles"><?php foreach($rolesSmart as $r):?>
        <div><span><?=h(role_label($r['role']))?></span><strong><?=val($r['total'])?></strong></div>
      <?php endforeach;?></div>
    </section>
  </div>
  <section class="pane activity">
    <div><span class="section-label">PENGAWASAN SISTEM</span><h2>Catatan Aktivitas Terpadu</h2>
      <p>Jumlah aktivitas tercatat selama tujuh hari terakhir. Rincian akun dan data sensitif tidak ditampilkan.</p></div>
    <div class="activity-num"><strong><?=val($auditLast7Days)?></strong><span>Aktivitas / 7 hari</span></div>
  </section>
  <div class="subhead spaced"><div><span class="section-label">AKSES CEPAT</span><h2>Modul Pengelolaan</h2></div></div>
  <div class="modules">
   <?php foreach([
      ['Administrasi Kepegawaian','Data dan identitas pegawai','/?page=pegawai'],
      ['Pelaporan Kinerja PPPK','Rekapitulasi aktivitas PPPK','/?page=pppk'],
      ['Pelaksanaan Tugas PJLP / PPSU','Kegiatan dan laporan petugas','/?page=pjlp'],
      ['Pelayanan Ahli Waris','Verifikasi dan status permohonan','/?page=waris'],
      ['Informasi Publik','Berita dan publikasi kelurahan','/?page=berita'],
      ['Agenda Kelurahan','Kegiatan dan agenda pelayanan','/?page=agenda']
   ] as $m):?>
   <a class="module" href="<?=h($m[2])?>"><span class="module-symbol">◈</span><strong><?=h($m[0])?></strong><small><?=h($m[1])?></small><span class="link">Buka modul ↗</span></a>
   <?php endforeach;?>
  </div>
  <div class="notice"><strong>Pengendalian Akses</strong><p>Halaman ini hanya menampilkan ringkasan agregat bagi administrator terautentikasi. Pengubahan data dilakukan melalui modul masing-masing dengan kewenangan serta pencatatan aktivitas.</p></div>
</div>
</main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Kendali Administrator <a href="/?page=dashboard">Ruang Kerja Terpadu</a></div></footer>
</body>
</html>
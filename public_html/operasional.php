<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/operational_center.php';
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);exit('Pusat operasional belum tersedia.');
}
$modules=operational_modules();
$key=(string)($_GET['modul']??'waris');
if(!isset($modules[$key]))$key='waris';
$search=mb_substr(trim((string)($_GET['cari']??'')),0,80);
$page=max(1,min(10000,filter_var($_GET['hal']??1,FILTER_VALIDATE_INT)?:1));
function op_href(string $module,string $search='',int $page=1):string {
    return '/operasional.php?'.http_build_query(['modul'=>$module,'cari'=>$search,'hal'=>$page],'','&',PHP_QUERY_RFC3986);
}
function op_status(string $s):string {
    return [
        'masuk'=>'Masuk','proses'=>'Dalam Proses','revisi'=>'Perlu Perbaikan',
        'selesai'=>'Selesai','ditolak'=>'Ditolak','active'=>'Aktif',
        'inactive'=>'Tidak Aktif','draft'=>'Draf','pending'=>'Menunggu'
    ][$s]??ucwords(str_replace('_',' ',$s));
}
$message=(string)($_SESSION['operational_notice']??'');unset($_SESSION['operational_notice']);
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    $formKey=(string)($_POST['module_key']??'');
    $id=(string)($_POST['record_id']??'');
    $action=(string)($_POST['action']??'');
    if(!isset($modules[$formKey]) || $formKey==='pegawai' || $id===''){
        http_response_code(400);$error='Permintaan tidak valid.';
    } else {
        try {
            if(empty($_POST['confirmed'])) throw new RuntimeException('Persetujuan tindakan diperlukan.');
            $notes=trim((string)($_POST['notes']??''));
            if($action==='review' && in_array($formKey,['pppk','pjlp'],true)){
                operational_update_review($admin,$formKey,$id,(string)($_POST['status']??''),$notes);
                $_SESSION['operational_notice']='Hasil peninjauan telah disimpan dan tercatat pada audit.';
            }elseif($action==='waris_status' && $formKey==='waris'){
                if(!ctype_digit($id))throw new RuntimeException('Nomor permohonan tidak valid.');
                waris_change_status($admin,(int)$id,(string)($_POST['status']??''),$notes);
                $_SESSION['operational_notice']='Status permohonan ahli waris berhasil diperbarui dan dicatat dalam riwayat.';
            }else throw new RuntimeException('Tindakan tidak diizinkan.');
            header('Location: '.op_href($formKey),true,303);exit;
        }catch(Throwable $e){
            error_log('SmartCikoko Stage 7 operational action failure: '.$e->getMessage());
            $error='Perubahan tidak berhasil. Pastikan status, catatan, dan izin database sesuai.';
            $key=$formKey;
        }
    }
}
$counts=operational_summary();
$results=['total'=>0,'page'=>1,'max_page'=>1,'rows'=>[]];
$history=[];
try {
    $results=operational_list($key,$search,$page);
    $history=operational_recent(8);
}catch(Throwable $e){
    error_log('SmartCikoko Stage 7 operational list error: '.$e->getMessage());
    $error='Data modul belum dapat ditampilkan. Periksa pemasangan dan koneksi database.';
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b2a48"><meta name="robots" content="noindex,nofollow">
<title>Pusat Operasional Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/operasional.css">
</head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a href="/" class="brand"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks" aria-label="Navigasi operasional">
<a href="/">Beranda</a><a href="/super-admin.php">Pusat Super Admin</a><a href="/kelola-pengguna.php">Manajemen Pengguna</a><a class="selected" href="/operasional.php">Operasional</a><a href="/verifikasi-layanan.php">Verifikasi & Laporan</a>
</nav></div></header>
<main>
<section class="hero"><div class="wrap hero-inner"><div><span class="kicker">PENGELOLAAN LAYANAN DAN ADMINISTRASI</span><h1>Pusat Operasional</h1>
<p>Pemantauan data kepegawaian, tindak lanjut laporan PPPK dan PJLP, serta penanganan permohonan ahli waris dari satu ruang kerja.</p>
</div><div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr($admin['name'],0,1)))?></span><span><small>SUPER ADMIN TEROTORISASI</small><strong><?=h($admin['name'])?></strong><em>Pengawasan lintas database</em></span></div></div>
</section>
<div class="wrap content">
<?php if($message):?><div class="op-alert success"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="op-alert error"><?=h($error)?></div><?php endif;?>
<div class="section-head"><div><span class="eyebrow">PEMANTAUAN TERPADU</span><h2>Ringkasan Modul</h2><p>Jumlah data berasal langsung dari masing-masing database. Angka ini bukan persentase pekerjaan selesai.</p></div></div>
<div class="op-summary">
<?php foreach($modules as $code=>$module):?>
<a class="op-summary-card <?=$key===$code?'active':''?>" href="<?=h(op_href($code))?>">
<small><?=h($module['name'])?></small><strong><?= $counts[$code]===null?'—':number_format($counts[$code],0,',','.') ?></strong>
<span><?=h($module['db']==='pegawai'?'Database Pegawai':'Database SmartApp')?> ↗</span>
</a>
<?php endforeach;?>
</div>
<section class="op-worklist">
<div class="work-head">
<div><span class="eyebrow">DAFTAR OPERASIONAL</span><h2><?=h($modules[$key]['name'])?></h2>
<p><?=h(match($key){
'pegawai'=>'Pemeriksaan identitas, peran, dan status akun kepegawaian (baca-saja).',
'pppk'=>'Peninjauan laporan kinerja PPPK tanpa mengubah laporan aslinya.',
'pjlp'=>'Peninjauan sesi tugas PJLP / PPSU tanpa mengubah laporan aslinya.',
'waris'=>'Verifikasi dan pembaruan status pengajuan ahli waris dengan riwayat tindakan.',
})?></p></div>
</div>
<nav class="op-tabs" aria-label="Pilih modul">
<?php foreach($modules as $code=>$m):?><a class="<?=$code===$key?'active':''?>" href="<?=h(op_href($code))?>"><?=h($m['name'])?></a><?php endforeach;?>
</nav>
<form method="get" action="/operasional.php" class="op-search">
<input type="hidden" name="modul" value="<?=h($key)?>">
<label for="search">Cari data <?=h($modules[$key]['name'])?></label>
<div><input id="search" name="cari" maxlength="80" value="<?=h($search)?>" placeholder="<?=h($key==='pegawai'?'Nama atau username':($key==='waris'?'Nomor permohonan atau nama pemohon':($key==='pppk'?'Periode atau nama pegawai':'Tanggal atau nama petugas')))?>">
<button class="btn primary" type="submit">Cari</button><a href="<?=h(op_href($key))?>" class="btn outline">Bersihkan</a></div>
</form>
<div class="work-count"><?=number_format($results['total'],0,',','.')?> data ditemukan · Halaman <?=$results['page']?> dari <?=$results['max_page']?></div>
<div class="op-cards">
<?php if(!$results['rows']):?><p class="empty">Belum terdapat data untuk modul atau pencarian ini.</p><?php endif;?>
<?php foreach($results['rows'] as $record):?>
<article class="op-card">
<div class="op-record-title">
<div><small><?=h($key==='waris'?'PEMOHON':($key==='pegawai'?'PEGAWAI':'PENANGGUNG JAWAB'))?></small>
<h3><?=h($record['title'])?></h3><span><?=h($record['description'])?></span></div>
<span class="op-badge"><?=h($key==='pppk'||$key==='pjlp'?(operational_review_statuses()[$record['review_status']]??'Belum Ditinjau'):op_status((string)$record['state']))?></span>
</div>
<div class="op-meta"><span><?=h((string)$record['kind'])?></span><span><?=h($key==='pjlp'?'Mulai: '.(string)$record['state']:'Status sumber: '.op_status((string)$record['state']))?></span></div>
<?php if($key==='pegawai'):?>
<div class="op-note">Informasi kepegawaian ditampilkan untuk pemeriksaan saja. Tidak ada perubahan identitas atau data sumber melalui halaman ini.</div>
<?php elseif($key==='pppk'||$key==='pjlp'):?>
<details class="op-details"><summary>Kelola Peninjauan Laporan</summary>
<form method="post" action="<?=h(op_href($key))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="review">
<input type="hidden" name="module_key" value="<?=h($key)?>"><input type="hidden" name="record_id" value="<?=h($record['id'])?>">
<label>Status Peninjauan<select name="status" required>
<?php foreach(operational_review_statuses() as $value=>$label):?>
<option value="<?=h($value)?>" <?=$record['review_status']===$value?'selected':''?>><?=h($label)?></option>
<?php endforeach;?></select></label>
<label>Catatan Tindak Lanjut<textarea name="notes" maxlength="500" rows="3" placeholder="Tuliskan pemeriksaan atau tindak lanjut yang perlu dilakukan."><?=h($record['review_notes'])?></textarea></label>
<label class="op-confirm"><input type="checkbox" name="confirmed" value="1" required> Saya telah memeriksa data dan menyetujui pencatatan tindak lanjut ini.</label>
<button class="btn primary" type="submit">Simpan Hasil Peninjauan</button>
</form></details>
<?php elseif($key==='waris'):?>
<details class="op-details"><summary>Kelola Status Permohonan</summary>
<form method="post" action="<?=h(op_href($key))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="waris_status">
<input type="hidden" name="module_key" value="waris"><input type="hidden" name="record_id" value="<?=h($record['id'])?>">
<label>Status Pelayanan<select name="status" required>
<?php foreach(['masuk','proses','revisi','selesai','ditolak'] as $status):?>
<option value="<?=h($status)?>" <?=$record['state']===$status?'selected':''?>><?=h(op_status($status))?></option>
<?php endforeach;?></select></label>
<label>Catatan Pemeriksaan Petugas<textarea name="notes" maxlength="1000" rows="3" placeholder="Isikan catatan profesional pelayanan yang relevan."></textarea></label>
<label class="op-confirm"><input type="checkbox" name="confirmed" value="1" required> Saya menyetujui perubahan status resmi permohonan ini dan memahami bahwa perubahan tercatat dalam riwayat.</label>
<button class="btn primary" type="submit">Perbarui Status Permohonan</button>
</form></details>
<?php endif;?>
</article>
<?php endforeach;?>
</div>
<div class="op-pages">
<?php if($results['page']>1):?><a class="btn outline" href="<?=h(op_href($key,$search,$results['page']-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($results['page']<$results['max_page']):?><a class="btn outline" href="<?=h(op_href($key,$search,$results['page']+1))?>">Selanjutnya →</a><?php endif;?>
</div>
</section>
<section class="op-history"><span class="eyebrow">AUDIT PENINJAUAN</span><h2>Riwayat Tindak Lanjut</h2>
<p>Perubahan status peninjauan PPPK dan PJLP tersimpan tanpa mengubah laporan sumber. Riwayat perubahan status ahli waris tetap tercatat pada modul layanan ahli waris.</p>
<div class="op-table-wrap"><table><thead><tr><th>Waktu</th><th>Modul</th><th>ID Data</th><th>Dari</th><th>Menjadi</th></tr></thead><tbody>
<?php if(!$history):?><tr><td colspan="5">Belum terdapat perubahan peninjauan yang dicatat.</td></tr><?php endif;?>
<?php foreach($history as $item):?><tr><td><?=h($item['created_at'])?> WIB</td><td><?=h($modules[$item['module_key']]['name']??$item['module_key'])?></td><td><?=h($item['record_id'])?></td><td><?=h(operational_review_statuses()[$item['old_status']]??$item['old_status'])?></td><td><?=h(operational_review_statuses()[$item['new_status']]??$item['new_status'])?></td></tr><?php endforeach;?>
</tbody></table></div>
</section>
<div class="op-notice"><strong>Pengendalian dan Perlindungan Data</strong><p>Fungsi ini hanya tersedia bagi Super Administrator yang sudah terautentikasi. Peninjauan PPPK/PJLP tidak mengubah status laporan aslinya, sedangkan perubahan status ahli waris memakai mekanisme dan riwayat pelayanan yang telah tersedia. Tidak ada penghapusan data atau perubahan kata sandi.</p></div>
</div></main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pusat Operasional <a href="/super-admin.php">Pusat Super Admin</a></div></footer>
</body></html>
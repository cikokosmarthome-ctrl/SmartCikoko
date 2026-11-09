<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/archive_security.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Manajemen arsip belum tersedia.');}
$source=(string)($_GET['s']??'pengajuan');
if(!isset(t11_sources()[$source]))$source='pengajuan';
$page=max(1,min(10000,filter_var($_GET['p']??1,FILTER_VALIDATE_INT)?:1));
$id=max(0,(int)($_GET['id']??0));
$notice=(string)($_SESSION['t11_notice']??'');unset($_SESSION['t11_notice']);
$error='';
function t11_url(string $source,int $page=1,int $id=0):string{
    $q=['s'=>$source,'p'=>$page];
    if($id>0)$q['id']=$id;
    return '/arsip-keamanan.php?'.http_build_query($q,'','&',PHP_QUERY_RFC3986);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try {
        $action=(string)($_POST['action']??'');
        $src=(string)($_POST['source']??'');
        $fid=filter_var($_POST['file_id']??0,FILTER_VALIDATE_INT);
        if(!$fid || $fid<1)throw new InvalidArgumentException('Berkas tidak valid.');
        if($action==='verify'){
            $result=t11_verify($admin,$src,$fid);
            $_SESSION['t11_notice']='Hasil pemeriksaan integritas: '.$result.'. Pemeriksaan integritas bukan pemindaian malware.';
        }elseif($action==='review'){
            if(empty($_POST['confirmed']))throw new InvalidArgumentException('Konfirmasi pemeriksaan diperlukan.');
            t11_set_review($admin,$src,$fid,(string)($_POST['review_status']??''),!empty($_POST['preserve_flag']),trim((string)($_POST['retention_note']??'')));
            $_SESSION['t11_notice']='Klasifikasi kerja dan instruksi pertahanan berkas tercatat dalam audit.';
        }else throw new InvalidArgumentException('Tindakan tidak diizinkan.');
        header('Location: '.t11_url($src,1,(int)$fid),true,303);exit;
    } catch(Throwable $e) {
        error_log('SmartCikoko Stage 11 archive action rejected: '.$e->getMessage());
        $error='Tindakan belum berhasil. Pastikan arsip ditemukan, data valid, dan tabel Tahap 11 telah terpasang.';
    }
}
$overview=[];$list=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];$registry=null;$file=null;$events=[];
try {
    $overview=t11_total();
    $list=t11_inventory($source,$page);
    if($id>0){
        $file=t11_file_record($source,$id);
        if($file)$registry=t11_registry($source,$id);
    }
    $events=t11_events(15);
}catch(Throwable $e){
    error_log('SmartCikoko Stage 11 archive dashboard failed: '.$e->getMessage());
    $error='Daftar arsip belum dapat dibuka. Pastikan installer Tahap 11 sudah dijalankan dan Tahap 8/10 terpasang.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0a3152">
<title>Arsip dan Keamanan Berkas | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/arsip-keamanan.css"></head>
<body><div class="topline"><div class="wrap">PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA · KECAMATAN PANCORAN</div></div>
<header class="nav"><div class="wrap inner"><a class="brand" href="/"><span>KC</span><b>KELURAHAN CIKOKO<small>Portal Pelayanan dan Administrasi Terpadu</small></b></a>
<nav><a href="/super-admin.php">Super Admin</a><a href="/tindak-lanjut.php">Tindak Lanjut</a><a class="active" href="/arsip-keamanan.php">Arsip Keamanan</a><a href="/draf-administrasi.php">Draf Administrasi</a></nav></div></header>
<section class="hero"><div class="wrap hero-inner"><div><span class="kicker">TAHAP 11 / PENGAWASAN DOKUMEN</span><h1>Arsip dan Keamanan Berkas</h1><p>Inventaris, pencatatan tinjauan, pemeriksaan integritas, dan jejak audit pada berkas pelayanan yang tersimpan secara privat.</p></div><div class="identity"><b><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></b><span><?=h($admin['name'])?><small>Super Administrator</small></span></div></div></section>
<main class="wrap content">
<?php if($notice):?><p class="alert good"><?=h($notice)?></p><?php endif;?>
<?php if($error):?><p class="alert bad"><?=h($error)?></p><?php endif;?>
<div class="intro"><span class="eyebrow">JUMLAH BERKAS</span><h2>Inventaris Dua Kelompok Arsip</h2><p>Arsip Tahap 8 dan Tahap 10 tetap berada pada tabel dan folder penyimpanan masing-masing.</p></div>
<div class="source-cards">
<?php foreach(t11_sources() as $key=>$cfg):?>
<a href="<?=h(t11_url($key))?>" class="<?=$source===$key?'selected':''?>">
<small><?=h($cfg['label'])?></small>
<strong><?=isset($overview[$key])&&$overview[$key]!==null?number_format($overview[$key],0,',','.'):'—'?></strong>
<span>Periksa Arsip ↗</span></a>
<?php endforeach;?>
</div>
<div class="columns"><section class="panel">
<h2>Daftar Berkas</h2><p class="muted">Tanpa memaparkan nama berkas atau identitas warga pada daftar utama.</p>
<div class="rows">
<?php if(!$list['rows']):?><p class="muted">Belum terdapat data arsip.</p><?php endif;?>
<?php foreach($list['rows'] as $entry):?>
<a href="<?=h(t11_url($source,$list['page'],(int)$entry['id']))?>" class="row-item <?=$id===(int)$entry['id']?'selected':''?>">
<span><strong>Dokumen #<?=(int)$entry['id']?></strong><small><?=h(ucwords((string)$entry['doc_type']))?> · Permohonan #<?=(int)$entry['parent_id']?></small>
<small><?=h((string)$entry['created_at'])?> WIB</small></span>
<span class="right"><b><?=h((string)$entry['integrity_status'])?></b><small><?=h(t11_review_labels()[$entry['review_status']]??'Belum Diklasifikasi')?></small></span></a>
<?php endforeach;?></div>
<div class="pagination">
<?php if($list['page']>1):?><a href="<?=h(t11_url($source,$list['page']-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($list['page']<$list['pages']):?><a href="<?=h(t11_url($source,$list['page']+1))?>">Berikutnya →</a><?php endif;?>
</div>
</section><section class="panel">
<?php if(!$file):?><div class="empty"><span>▤</span><h2>Pilih Berkas</h2><p>Klik satu berkas untuk meninjau klasifikasi, status integritas, dan pencatatan audit.</p></div>
<?php else:?>
<span class="eyebrow">PENGENDALIAN AKSES DAN ARSIP</span><h2>Arsip #<?=(int)$file['id']?></h2>
<div class="facts"><div><small>SUMBER</small><strong><?=h(t11_sources()[$source]['label'])?></strong></div><div><small>PERMOHONAN</small><strong>#<?=(int)$file['parent_id']?></strong></div>
<div><small>UKURAN</small><strong><?=number_format((int)$file['file_size']/1024,1,',','.')?> KB</strong></div>
<div><small>STATUS INTEGRITAS</small><strong><?=h($registry['integrity_status']??'belum')?></strong></div></div>
<form method="post" action="<?=h(t11_url($source,$list['page'],$id))?>" class="verify-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="verify">
<input type="hidden" name="source" value="<?=h($source)?>"><input type="hidden" name="file_id" value="<?=$id?>">
<button class="button primary" type="submit">Periksa Integritas SHA-256</button></form>
<p class="muted">Hasil “sesuai” berarti ukuran dan hash cocok dengan saat berkas disimpan; tidak membuktikan dokumen asli/sah atau bebas malware.</p>
<div class="divider"></div><h3>Catatan Pengelolaan Arsip</h3>
<form method="post" action="<?=h(t11_url($source,$list['page'],$id))?>" class="review-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="review">
<input type="hidden" name="source" value="<?=h($source)?>"><input type="hidden" name="file_id" value="<?=$id?>">
<label>Status Tinjauan Arsip<select name="review_status" required><?php foreach(t11_review_labels() as $state=>$label):?><option value="<?=h($state)?>" <?=($registry['review_status']??'belum')===$state?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
<label>Catatan Internal (maksimal 300 karakter)<textarea name="retention_note" maxlength="300" rows="3" placeholder="Catatan penanganan; jangan memasukkan NIK atau password."><?=h($registry['retention_note']??'')?></textarea></label>
<label class="confirm"><input type="checkbox" name="preserve_flag" value="1" <?=!empty($registry['preserve_flag'])?'checked':''?>> Tandai “Pertahankan berkas” (catatan administrasi, tidak mengatur jadwal retensi otomatis)</label>
<label class="confirm"><input type="checkbox" name="confirmed" value="1" required> Saya menyetujui pencatatan perubahan metadata dan audit.</label>
<button class="button outline" type="submit">Simpan Catatan Arsip</button>
</form>
<?php endif;?>
</section></div>
<section class="panel audit"><span class="eyebrow">JEJAK PEMERIKSAAN</span><h2>Riwayat Arsip Terbaru</h2>
<p class="muted">Riwayat tidak menampilkan nama pemohon, hash dokumen, atau isi berkas.</p>
<div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Sumber</th><th>ID</th><th>Aktivitas</th><th>Catatan Ringkas</th></tr></thead><tbody>
<?php if(!$events):?><tr><td colspan="5">Belum ada aktivitas pemeriksaan.</td></tr><?php endif;?>
<?php foreach($events as $ev):?><tr><td><?=h($ev['created_at'])?> WIB</td><td><?=h(t11_sources()[$ev['source_key']]['label']??$ev['source_key'])?></td><td><?=(int)$ev['file_id']?></td>
<td><?=h($ev['event_type'])?></td><td><?=h($ev['event_summary'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<div class="safety"><strong>Batas Keamanan Tahap 11</strong><p>Modul ini memeriksa kesesuaian hash dan ukuran berkas, serta mencatat perubahan metadata tanpa menghapus data. Belum ada pemindaian virus, enkripsi arsip terkelola, MFA yang dipaksakan, penetapan retensi resmi, atau sistem surat dinas bertanda tangan elektronik. Jangan menggunakan unggahan untuk dokumen warga nyata sebelum pengamanan dan prosedur tersebut disiapkan.</p></div>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Arsip Internal Super Administrator</div></footer>
</body></html>
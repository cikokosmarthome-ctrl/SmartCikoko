<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/document_workflow.php';
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);exit('Modul pelayanan belum tersedia.');
}
$tab=(string)($_GET['tab']??'dokumen');
if(!in_array($tab,['dokumen','laporan'],true))$tab='dokumen';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT,['options'=>['default'=>0,'min_range'=>1]])?:0;
$query=mb_substr(trim((string)($_GET['cari']??'')),0,80);
$page=filter_var($_GET['hal']??1,FILTER_VALIDATE_INT,['options'=>['default'=>1,'min_range'=>1]])?:1;
$month=(string)($_GET['bulan']??date('Y-m'));
$error='';$notice=(string)($_SESSION['t8_notice']??'');unset($_SESSION['t8_notice']);
try{$month=t8_month($month);}catch(Throwable $e){$month=date('Y-m');$error='Periode tidak valid, digunakan bulan berjalan.';}
function t8_link(string $tab='dokumen',int $id=0,string $search='',int $page=1,string $month=''):string {
    $params=['tab'=>$tab];
    if($id>0)$params['id']=$id;
    if($search!=='')$params['cari']=$search;
    if($page>1)$params['hal']=$page;
    if($month!=='')$params['bulan']=$month;
    return '/verifikasi-layanan.php?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    $caseId=filter_var($_POST['application_id']??null,FILTER_VALIDATE_INT);
    $action=(string)($_POST['action']??'');
    try{
        if(!$caseId||$caseId<1)throw new InvalidArgumentException('Nomor permohonan tidak valid.');
        if(empty($_POST['confirmed']))throw new RuntimeException('Konfirmasi tindakan wajib diberikan.');
        $docType=(string)($_POST['doc_type']??'');
        if($action==='check'){
            t8_record_review($admin,$caseId,$docType,(string)($_POST['review_status']??''),trim((string)($_POST['note']??'')));
            $_SESSION['t8_notice']='Hasil pemeriksaan dokumen berhasil disimpan.';
        }elseif($action==='upload'){
            t8_upload($admin,$caseId,$docType,$_FILES['berkas']??[]);
            $_SESSION['t8_notice']='Berkas berhasil disimpan di direktori privat. Status pemeriksaan tetap perlu ditentukan oleh petugas.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        header('Location: '.t8_link('dokumen',$caseId),true,303);exit;
    }catch(Throwable $e){
        error_log('SmartCikoko stage 8 form error: '.$e->getMessage());
        $error='Tindakan belum berhasil: '.($action==='upload'?'periksa jenis berkas (PDF/JPG/PNG), batas 5 MB dan izin penyimpanan privat.':'periksa formulir, status, dan koneksi database.');
        $id=(int)($caseId?:0);$tab='dokumen';
    }
}
$case=null;$checks=[];$files=[];$events=[];
$cases=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
$report=[];
try{
    if($tab==='dokumen'){
        $cases=t8_cases($query,$page);
        if($id>0){
            $case=t8_case($id);
            if($case){
                $checks=t8_case_checks($id);
                $files=t8_case_files($id);
                $events=t8_events($id);
            }
        }
    }else {
        $report=t8_report($month);
        if(($_GET['export']??'')==='csv'){
            $filename='rekap-smartcikoko-'.$month.'.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: private, no-store');
            $fp=fopen('php://output','wb');
            fwrite($fp,"\xEF\xBB\xBF");
            fputcsv($fp,['Periode','Indikator','Jumlah'],';');
            foreach($report as $row)fputcsv($fp,[$month,$row['label'],(string)$row['count']],';');
            fclose($fp);
            log_event('aggregate_report_downloaded','monthly_report',$month);
            exit;
        }
    }
}catch(Throwable $e){
    error_log('SmartCikoko stage 8 screen error: '.$e->getMessage());
    $error='Data belum dapat dibuka. Pastikan pemasangan Tahap 8 selesai dan kedua database dapat diakses.';
}
$docTypes=t8_document_types();$statuses=t8_check_statuses();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Verifikasi Pelayanan dan Laporan | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/verifikasi-layanan.css">
</head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a href="/" class="brand"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks" aria-label="Navigasi"><a href="/">Beranda</a><a href="/super-admin.php">Pusat Super Admin</a><a href="/operasional.php">Operasional</a><a class="selected" href="/verifikasi-layanan.php">Verifikasi & Laporan</a></nav>
</div></header>
<main><section class="hero"><div class="wrap hero-inner"><div><span class="kicker">PELAYANAN YANG TERTIB DAN AKUNTABEL</span><h1>Verifikasi Pelayanan & Laporan</h1>
<p>Pemeriksaan dokumen ahli waris dan rekapitulasi administrasi bulanan dalam satu ruang kerja yang aman.</p></div>
<div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr($admin['name'],0,1)))?></span><span><small>SUPER ADMIN AKTIF</small><strong><?=h($admin['name'])?></strong><em>Pengelolaan dokumen terotorisasi</em></span></div></div></section>
<div class="wrap content">
<?php if($error):?><div class="form-alert error"><?=h($error)?></div><?php endif;?>
<?php if($notice):?><div class="form-alert success"><?=h($notice)?></div><?php endif;?>
<nav class="stage-tabs" aria-label="Layanan Tahap 8">
<a class="<?=$tab==='dokumen'?'active':''?>" href="<?=h(t8_link('dokumen'))?>">Verifikasi Dokumen Ahli Waris</a>
<a class="<?=$tab==='laporan'?'active':''?>" href="<?=h(t8_link('laporan',0,'',1,$month))?>">Laporan Administrasi</a>
</nav>
<?php if($tab==='dokumen'):?>
<div class="section-head"><div><span class="eyebrow">FORMULIR PELAYANAN</span><h2>Verifikasi Dokumen Permohonan</h2>
<p>Checklist administrasi yang membantu peninjauan; bukan penetapan sah atau tidaknya dokumen secara hukum.</p></div></div>
<div class="two-column">
<section class="white-panel"><h2>Daftar Permohonan</h2>
<form class="case-search" method="get" action="/verifikasi-layanan.php">
<input type="hidden" name="tab" value="dokumen">
<label for="search">Cari nomor atau nama pemohon</label><div><input id="search" name="cari" maxlength="80" value="<?=h($query)?>" placeholder="Nomor permohonan / nama"><button class="btn primary" type="submit">Cari</button></div></form>
<p class="muted-xs"><?=number_format((int)$cases['total'],0,',','.')?> permohonan · Halaman <?=$cases['page']?> dari <?=$cases['pages']?></p>
<div class="case-list">
<?php if(!$cases['rows']):?><p class="empty-msg">Belum terdapat permohonan.</p><?php endif;?>
<?php foreach($cases['rows'] as $item):?>
<a class="case-item <?=$id===(int)$item['id']?'selected':''?>" href="<?=h(t8_link('dokumen',(int)$item['id'],$query,$cases['page']))?>">
<span><strong><?=h($item['reference_no'])?></strong><small><?=h($item['applicant_name'])?></small></span>
<span class="case-status"><?=h(ucfirst((string)$item['status']))?></span></a>
<?php endforeach;?>
</div><div class="page-nav">
<?php if($cases['page']>1):?><a href="<?=h(t8_link('dokumen',0,$query,$cases['page']-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($cases['page']<$cases['pages']):?><a href="<?=h(t8_link('dokumen',0,$query,$cases['page']+1))?>">Selanjutnya →</a><?php endif;?>
</div></section>
<section class="white-panel">
<?php if(!$case):?>
<div class="choose-case"><div>▤</div><h2>Pilih Permohonan</h2><p>Klik salah satu nomor permohonan untuk meninjau kelengkapan dokumen dan menyimpan tindak lanjutnya.</p></div>
<?php else:?>
<span class="eyebrow">DATA PERMOHONAN TERPILIH</span>
<h2><?=h($case['reference_no'])?></h2>
<div class="case-facts"><div><small>PEMOHON</small><strong><?=h($case['applicant_name'])?></strong></div>
<div><small>STATUS LAYANAN</small><strong><?=h(ucfirst((string)$case['status']))?></strong></div></div>
<p class="helper">Status pelayanan resmi diperbarui melalui <a href="/operasional.php?modul=waris">Pusat Operasional</a>. Pemeriksaan berkas di sini tidak otomatis mengubah status permohonan.</p>
<h3 class="subsection">Checklist Pemeriksaan</h3>
<div class="checklist">
<?php foreach($docTypes as $code=>$label):$check=$checks[$code]??null;?>
<div class="check-line"><span><strong><?=h($label)?></strong><small><?=h($check['review_notes']??'Belum terdapat catatan pemeriksaan.')?></small></span><b class="<?=($check['review_status']??'')==='sesuai'?'ok':'neutral'?>"><?=h($statuses[$check['review_status']??'belum'])?></b></div>
<?php endforeach;?>
</div>
<details class="edit-form"><summary>Catat Hasil Pemeriksaan Dokumen</summary>
<form method="post" action="<?=h(t8_link('dokumen',$id))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="check"><input type="hidden" name="application_id" value="<?=$id?>">
<label>Jenis Dokumen<select name="doc_type" required><?php foreach($docTypes as $code=>$label):?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<label>Hasil Pemeriksaan<select name="review_status" required><?php foreach($statuses as $code=>$label):?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<label>Catatan Pemeriksaan<textarea name="note" rows="3" maxlength="500" placeholder="Catatan kelengkapan dokumen berdasarkan pemeriksaan petugas"></textarea></label>
<label class="confirm"><input type="checkbox" name="confirmed" value="1" required> Saya telah meninjau dokumen dan menyetujui pencatatan hasil pemeriksaan ini.</label>
<button type="submit" class="btn primary">Simpan Hasil Pemeriksaan</button></form>
</details>
<h3 class="subsection">Berkas Pendukung Privat</h3>
<p class="helper">PDF, JPG atau PNG, maksimum 5 MB per berkas. Berkas hanya dapat diakses Super Admin melalui pemeriksaan sesi login.</p>
<div class="files-list">
<?php if(!$files):?><p class="empty-msg">Belum terdapat berkas tambahan pada modul Tahap 8. Lampiran dari website lama tidak otomatis dipindahkan.</p><?php endif;?>
<?php foreach($files as $f):?>
<div class="file-line"><span><strong><?=h($docTypes[$f['doc_type']]??$f['doc_type'])?></strong><small><?=h($f['original_name'])?> · <?=number_format((int)$f['file_size']/1024,1,',','.')?> KB</small></span>
<a href="/unduh-berkas.php?id=<?=(int)$f['id']?>">Unduh ↗</a></div>
<?php endforeach;?></div>
<details class="edit-form"><summary>Unggah Berkas Pendukung</summary>
<form method="post" action="<?=h(t8_link('dokumen',$id))?>" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="application_id" value="<?=$id?>">
<label>Jenis Dokumen<select name="doc_type" required><?php foreach($docTypes as $code=>$label):?><option value="<?=h($code)?>"><?=h($label)?></option><?php endforeach;?></select></label>
<label>Pilih Berkas<input type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label>
<label class="confirm"><input type="checkbox" name="confirmed" value="1" required> Saya berwenang memproses dan menyimpan dokumen ini untuk keperluan pelayanan.</label>
<button class="btn primary" type="submit">Simpan Berkas Secara Privat</button></form>
</details>
<h3 class="subsection">Riwayat Pemeriksaan</h3>
<div class="timeline"><?php if(!$events):?><p class="empty-msg">Belum terdapat aktivitas pemeriksaan.</p><?php endif;?>
<?php foreach($events as $ev):?><div><small><?=h($ev['created_at'])?> WIB · <?=h($ev['event_type']==='file_uploaded'?'Unggah berkas':'Pemeriksaan berkas')?></small><strong><?=h($docTypes[$ev['doc_type']]??$ev['doc_type'])?></strong><p><?=h($ev['event_note'])?></p></div><?php endforeach;?></div>
<?php endif;?>
</section></div>
<?php else:?>
<div class="section-head"><div><span class="eyebrow">REKAPITULASI ADMINISTRASI</span><h2>Laporan Pelayanan dan Kegiatan</h2>
<p>Rekap agregat per bulan, tanpa mengekspor identitas pribadi pegawai ataupun warga.</p></div></div>
<section class="white-panel report-panel">
<form method="get" action="/verifikasi-layanan.php" class="report-filter"><input type="hidden" name="tab" value="laporan">
<label for="bulan">Periode Bulan</label><input id="bulan" type="month" name="bulan" value="<?=h($month)?>" required>
<button class="btn primary" type="submit">Tampilkan Rekap</button>
<a class="btn outline" href="<?=h(t8_link('laporan',0,'',1,$month).'&export=csv')?>">Unduh CSV Agregat ↗</a>
</form>
<div class="report-grid"><?php foreach($report as $row):?><div class="report-card"><small><?=h($row['label'])?></small><strong><?=number_format((int)$row['count'],0,',','.')?></strong></div><?php endforeach;?></div>
<div class="report-note"><strong>Keterangan Laporan</strong><p>Permohonan ahli waris dihitung berdasarkan tanggal dibuat dan status saat ini. Laporan PPPK berasal dari periode laporan; tugas PJLP berdasarkan tanggal pelaksanaan. Riwayat verifikasi dihitung berdasarkan tanggal aktivitas. Karena definisi masing-masing berbeda, angka tidak dijumlahkan sebagai satu total pegawai atau satu total layanan.</p></div>
</section>
<?php endif;?>
<div class="privacy-note"><strong>Pengamanan Informasi Pelayanan</strong><p>Akses dokumen dan rekap dibatasi untuk Super Administrator. File baru disimpan di folder privat di luar <code>public_html</code>, bukan di GitHub. Sebelum digunakan untuk dokumen warga sungguhan, pengelola perlu memastikan kebijakan retensi, persetujuan/landasan pemrosesan, pemeriksaan malware, backup, dan pengujian akses telah diberlakukan.</p></div>
</div></main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Verifikasi dan Laporan Administrasi <a href="/super-admin.php">Pusat Super Admin</a></div></footer>
</body></html>
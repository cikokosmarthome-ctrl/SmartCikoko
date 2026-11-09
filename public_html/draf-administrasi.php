<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    $admin=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Akses administratif belum tersedia.');}
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT,['options'=>['default'=>0,'min_range'=>1]])?:0;
$approved=[];$selected=null;$error='';
try{
    $q=db('smartapp')->query("SELECT id,reference_no,applicant_name,status,updated_at FROM unified_citizen_requests WHERE status='disetujui' ORDER BY id DESC LIMIT 50");
    $approved=$q->fetchAll(PDO::FETCH_ASSOC);
    if($id>0){
        $q=db('smartapp')->prepare('SELECT reference_no,applicant_name,deceased_name,relationship,status,created_at,updated_at FROM unified_citizen_requests WHERE id=? AND status=? LIMIT 1');
        $q->execute([$id,'disetujui']);
        $selected=$q->fetch(PDO::FETCH_ASSOC)?:null;
        if(!$selected)$error='Permohonan tidak ditemukan atau belum mencapai tahapan persetujuan internal.';
    }
}catch(Throwable $e){
    error_log('SmartCikoko Stage 11 draft list unavailable: '.$e->getMessage());
    $error='Daftar draf belum dapat dibuka. Periksa instalasi Tahap 9.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Draf Administrasi Internal | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/draf-administrasi.css"></head>
<body>
<div class="tools"><a href="/super-admin.php">← Pusat Super Admin</a><a href="/arsip-keamanan.php">Arsip Digital</a><b>DRAF ADMINISTRASI INTERNAL — TIDAK SAH</b></div>
<div class="screen">
<header class="head"><div class="crest">KC</div><div><small>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</small><strong>KELURAHAN CIKOKO</strong><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></header>
<h1>Draf Ringkasan Administrasi Pengajuan</h1>
<p class="disclaimer">Halaman ini hanya menyiapkan bahan pemeriksaan internal. Format surat resmi, nomor naskah dinas, kewenangan, paraf, stempel, dan tanda tangan elektronik <b>belum ditetapkan</b> melalui sistem ini.</p>
<?php if($error):?><p class="warning"><?=h($error)?></p><?php endif;?>
<form method="get" class="choice" action="/draf-administrasi.php">
<label for="id">Pilih pengajuan Tahap 9 yang mencapai status Disetujui</label>
<select name="id" id="id" required>
<option value="">Pilih pengajuan...</option>
<?php foreach($approved as $row):?><option value="<?=(int)$row['id']?>" <?=$id===(int)$row['id']?'selected':''?>><?=h($row['reference_no'])?> — <?=h($row['applicant_name'])?></option><?php endforeach;?>
</select><button type="submit">Tampilkan Draf Internal</button></form>
<?php if($selected):?>
<article class="draft"><div class="watermark">DRAF · TIDAK SAH</div>
<div class="draft-heading">DRAF RINGKASAN HASIL ALUR KERJA</div>
<div class="draft-subtitle">DOKUMEN INTERNAL — TIDAK UNTUK DISERAHKAN SEBAGAI SURAT RESMI</div>
<dl>
<dt>Nomor Referensi Portal</dt><dd><?=h($selected['reference_no'])?></dd>
<dt>Nama Pemohon</dt><dd><?=h($selected['applicant_name'])?></dd>
<dt>Nama Almarhum/Almarhumah</dt><dd><?=h($selected['deceased_name'])?></dd>
<dt>Hubungan yang Dinyatakan Pemohon</dt><dd><?=h(ucwords(str_replace('_',' ',(string)$selected['relationship'])))?></dd>
<dt>Status Alur Kerja Internal</dt><dd>Disetujui — <b>Bukan Persetujuan Dokumen Resmi</b></dd>
<dt>Waktu Pembaruan Status</dt><dd><?=h($selected['updated_at'])?> WIB</dd>
</dl>
<div class="notice"><strong>Informasi Pelayanan</strong><p>Catatan ini dihasilkan otomatis berdasarkan status internal yang tersimpan dalam Portal SmartCikoko. Isi belum diverifikasi sebagai surat keterangan resmi. Dokumen ini tidak boleh dipakai menggantikan dokumen yang ditandatangani pejabat berwenang.</p></div>
<div class="no-signature">Tidak tersedia ruang tanda tangan/stempel karena halaman ini bukan naskah dinas resmi.</div>
</article>
<button class="print-btn" type="button" onclick="window.print()">Cetak Draf Bertanda Tidak Sah</button>
<?php endif;?>
</div></body></html>
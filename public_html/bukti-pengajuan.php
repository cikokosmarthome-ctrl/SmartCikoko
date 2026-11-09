<?php
declare(strict_types=1);
header('Cache-Control: no-store,private,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/citizen_requests.php';
    require_once dirname(__DIR__).'/portal_private/citizen_followup.php';
    $actor=t9_actor(actor());
    if(!$actor){header('Location: /?page=login',true,303);exit;}
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
    if(!$id || $id<1){http_response_code(400);exit('Nomor permohonan tidak valid.');}
    $case=t10_actor_case($actor,$id);
}catch(Throwable $e){http_response_code(404);exit('Bukti informasi tidak tersedia.');}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Bukti Informasi Pengajuan | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/bukti-pengajuan.css"></head>
<body><main class="sheet">
<div class="banner">DOKUMEN INFORMASI · BUKAN SURAT RESMI</div>
<header><span class="seal">KC</span><div><strong>KELURAHAN CIKOKO</strong><small>KECAMATAN PANCORAN · JAKARTA SELATAN</small></div></header>
<h1>Ringkasan Informasi Pengajuan</h1>
<p class="desc">Cetakan informasi dari Portal Pelayanan Terpadu SmartCikoko. Tidak berlaku sebagai keputusan atau surat keterangan resmi.</p>
<dl>
<dt>Nomor Referensi</dt><dd><?=h($case['reference_no'])?></dd>
<dt>Nama Pemohon</dt><dd><?=h($case['applicant_name'])?></dd>
<dt>Status Saat Dicetak</dt><dd><?=h(t9_stages()[$case['status']]??$case['status'])?></dd>
<dt>Tanggal Pengajuan Dibuat</dt><dd><?=h($case['created_at'])?> WIB</dd>
<dt>Waktu Pencetakan</dt><dd><?=h(date('d/m/Y H:i'))?> WIB</dd>
</dl>
<div class="warning"><strong>BUKAN BUKTI PERSETUJUAN RESMI</strong><p>Dokumen ini tidak memiliki nomor surat dinas, tanda tangan pejabat, stempel, atau tanda tangan elektronik. Status “disetujui” di sistem hanya menunjukkan tahapan internal sampai prosedur penerbitan dokumen resmi ditetapkan.</p></div>
<p class="foot">Informasi dapat berubah sesuai hasil pemeriksaan dan keputusan pejabat yang berwenang.</p>
</main></body></html>
<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/monitor20.php';
    $user=requires_login();
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($user)){http_response_code(403);exit('Akun tidak dapat membuka pengingat.');}
}catch(Throwable $e){http_response_code(503);exit('Kotak pengingat belum tersedia.');}
$notice=(string)($_SESSION['m20_inbox_notice']??'');unset($_SESSION['m20_inbox_notice']);
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!m20_ready())throw new RuntimeException('Pengingat belum diaktifkan.');
        m20_read($user,(int)($_POST['reminder_id']??0));
        $_SESSION['m20_inbox_notice']='Pengingat telah ditandai sebagai dibaca.';
        header('Location: /pengingat-saya20.php',true,303);exit;
    }catch(Throwable $e){error_log('Stage20 inbox action refused: '.get_class($e));$error='Pengingat tidak ditemukan atau akses ditolak.';}
}
$items=[];
try{$items=m20_inbox($user,40);}
catch(Throwable $e){error_log('Stage20 inbox unavailable');$error='Daftar pengingat belum dapat dimuat.';}
$unread=count(array_filter($items,static fn($x)=>$x['read_at']===null));
$destinations=['ringkas15'=>'/pusat-laporan.php','pegawai16'=>'/formulir-pegawai16.php','wilayah17'=>'/laporan-wilayah17.php'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0a304b"><title>Pengingat Saya | SmartCikoko</title>
<link rel="stylesheet" href="/assets/monitor20.css"></head><body>
<div class="gov"><div class="wrap">PEMPROV DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a href="/" class="brand"><span>KC</span><b>SMARTCIKOKO<small>Pengingat Internal Akun</small></b></a>
<nav><a href="/pusat-laporan.php">Pusat Input Laporan</a><a href="/?page=dashboard">Ruang Kerja</a>
<?php if(is_super_admin($user)):?><a href="/super-admin-satu.php#monitor-bulanan">Super Admin Ringkas</a><?php endif;?></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 20 · KOTAK PENGINGAT INTERNAL</small>
<h1>Pengingat dan Tindak Lanjut Saya</h1><p>Hanya pemberitahuan yang ditujukan kepada akun yang sedang masuk. Tidak mengirim pesan WhatsApp, email, atau notifikasi ponsel.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="flash success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<div class="panel"><div class="panel-head"><h2>Daftar Pengingat</h2><span class="chip"><?=$unread?> belum dibaca</span></div>
<?php if(!$items):?><div class="empty">Belum ada pengingat internal yang ditujukan kepada akun Anda.</div><?php endif;?>
<?php foreach($items as $item):?>
<div class="inbox-item <?=$item['read_at']===null?'unread':''?>">
<div><b><?=h($item['message'])?></b>
<small><?=h($item['created_at'])?> WIB · <?=h(m20_phase_labels()[$item['phase']]??$item['phase'])?></small>
<a class="inbox-link" href="<?=h($destinations[$item['source_kind']]??'/pusat-laporan.php')?>">Buka Modul Laporan →</a></div>
<?php if($item['read_at']===null):?>
<form method="post" action="/pengingat-saya20.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="reminder_id" value="<?=(int)$item['id']?>">
<button type="submit" class="btn secondary">Tandai Dibaca</button>
</form>
<?php else:?><span class="read">Dibaca <?=h($item['read_at'])?></span><?php endif;?></div>
<?php endforeach;?>
<p class="smallnote">Menampilkan hingga 40 pengingat terbaru. Status telah dibaca tidak berarti laporan selesai atau telah disetujui.</p>
</div>
<p class="footnote">Pelapor dapat memperbaiki laporan hanya bila formulir asli berstatus Perlu Perbaikan. Pengingat tidak menambah kewenangan mengakses laporan milik orang lain.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pengingat Internal SmartCikoko</div></footer></body></html>
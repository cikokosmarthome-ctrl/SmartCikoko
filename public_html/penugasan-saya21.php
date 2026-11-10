<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/tasks21.php';
    $user=requires_login();
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($user)){http_response_code(403);exit('Akses akun tidak diizinkan.');}
}catch(Throwable $e){http_response_code(503);exit('Penugasan belum tersedia.');}
$error='';$notice=(string)($_SESSION['t21_inbox_notice']??'');unset($_SESSION['t21_inbox_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        t21_read($user,(int)($_POST['alert_id']??0));
        $_SESSION['t21_inbox_notice']='Pengingat ditandai dibaca; tugas tetap perlu ditindaklanjuti.';
        header('Location: /penugasan-saya21.php',true,303);exit;
    }catch(Throwable $e){error_log('Stage21 inbox read blocked: '.get_class($e));$error='Pengingat tidak ditemukan, penugasan berubah, atau akun tidak berwenang.';}
}
$messages=[];
try{$messages=t21_inbox($user,40);}
catch(Throwable $e){error_log('Stage21 inbox not available.');$error='Kotak tugas belum dapat ditampilkan.';}
$unread=count(array_filter($messages,static fn($r)=>$r['read_at']===null));
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b3454">
<title>Penugasan Saya | SmartCikoko</title><link rel="stylesheet" href="/assets/penugasan21.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a href="/" class="brand"><span>KC</span><b>SMARTCIKOKO<small>Penugasan Pemeriksa dan Lurah</small></b></a><nav><a href="/verifikasi-laporan19.php">Verifikasi Laporan</a><a href="/pengingat-saya20.php">Pengingat Tahap 20</a>
<?php if(is_super_admin($user)):?><a href="/super-admin-satu.php#penugasan-pemeriksa">Super Admin Ringkas</a><?php endif;?></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 21 · KOTAK TUGAS ADMINISTRATIF</small><h1>Penugasan dan Notifikasi Saya</h1><p>Hanya pengingat yang masih sesuai penugasan dan status laporan akun Anda. Tidak ada WhatsApp, email atau pesan publik.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="notice good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="notice bad"><?=h($error)?></div><?php endif;?>
<section class="panel"><div class="section-head"><div><h2>Tugas Aktif Saya</h2><p class="hint">Penugasan yang telah selesai, dialihkan atau tidak sesuai versi terkini tidak ditampilkan.</p></div><span class="pill"><?=$unread?> belum dibaca</span></div>
<?php if(!$messages):?><div class="empty">Belum ada pengingat aktif untuk akun ini. Penugasan mungkin belum diaktifkan atau tenggat belum tercapai.</div><?php endif;?>
<div class="inbox">
<?php foreach($messages as $item):?>
<div class="inbox-row <?=empty($item['read_at'])?'unread':''?>">
<div><b><?=h($item['message'])?></b>
<small><?=h($item['created_at'])?> WIB · <?=h(m20_phase_labels()[$item['phase']]??$item['phase'])?></small>
<a class="link" href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($item['source_kind']))?>&amp;id=<?=(int)$item['source_id']?>">Buka laporan untuk pemeriksaan →</a></div>
<?php if($item['read_at']===null):?>
<form method="post" action="/penugasan-saya21.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="alert_id" value="<?=(int)$item['id']?>">
<button class="btn secondary" type="submit">Tandai Dibaca</button></form>
<?php else:?><span class="read">Dibaca <?=h($item['read_at'])?></span><?php endif;?>
</div>
<?php endforeach;?></div>
<p class="hint">Status dibaca hanya berarti pesan sudah dilihat, bukan persetujuan atau penyelesaian pemeriksaan.</p>
</section>
<p class="footnote">Hak untuk melihat dan memproses laporan tetap diperiksa oleh modul verifikasi Tahap 19. Pengingat tidak dapat mengubah laporan, surat, saldo cuti atau penilaian resmi.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Penugasan Internal</div></footer>
</body></html>
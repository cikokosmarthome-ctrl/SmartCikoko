<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    require_once dirname(__DIR__).'/portal_private/release_readiness.php';
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);exit('Pusat kesiapan produksi belum dapat dibuka.');
}
$error='';$notice=(string)($_SESSION['r12_notice']??'');unset($_SESSION['r12_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try {
        if((string)($_POST['action']??'')!=='record')throw new InvalidArgumentException('Tindakan tidak didukung.');
        r12_set_manual(
            $admin,(string)($_POST['key']??''),(string)($_POST['status']??''),
            trim((string)($_POST['evidence']??''))
        );
        $_SESSION['r12_notice']='Hasil pemeriksaan manual tercatat dalam riwayat pengawasan.';
        header('Location: /kesiapan-produksi.php',true,303);exit;
    }catch(Throwable $e){
        error_log('SmartCikoko Stage 12 evidence update failed: '.$e->getMessage());
        $error='Pencatatan belum berhasil. Pastikan checklist dan catatan singkat valid, serta installer Tahap 12 sudah dipasang.';
    }
}
$automatic=r12_auto_results();$manual=[];$recent=[];$dbReady=true;
try {
    $manual=r12_manual_list();
    $recent=r12_recent(12);
}catch(Throwable $e){
    $dbReady=false;
    error_log('SmartCikoko Stage 12 checklist schema unavailable.');
    $error='Checklist tersimpan belum dapat dimuat. Jalankan installer Tahap 12 dahulu.';
}
$stats=r12_stats($automatic,$manual);
$autoComplete=$stats['passed']===$stats['auto_total'];
$manualComplete=$stats['verified']===$stats['manual_total'];
$percent=(int)floor(100*($stats['passed']+$stats['verified'])/max(1,($stats['auto_total']+$stats['manual_total'])));
function r12_status_label(string $status):string{
    return match($status){'verified'=>'Sudah Diverifikasi','blocked'=>'Ada Kendala',default=>'Belum Diverifikasi'};
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2d4a">
<title>Kesiapan Produksi | SmartCikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/kesiapan-produksi.css">
</head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a class="brand" href="/"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks" aria-label="Navigasi utama">
<a href="/">Beranda</a><a href="/super-admin.php">Super Administrator</a>
<a href="/arsip-keamanan.php">Arsip Keamanan</a><a class="selected" href="/kesiapan-produksi.php">Kesiapan Produksi</a></nav>
</div></header>
<main>
<section class="hero"><div class="wrap hero-inner"><div>
<span class="kicker">TAHAP 12 / PEMERIKSAAN PRA-PRODUKSI</span>
<h1>Pusat Kesiapan Produksi</h1>
<p>Pengawasan teknis, bukti pengujian dan prasyarat layanan publik dari satu halaman. Pemeriksaan tidak memodifikasi akun atau data warga.</p>
</div><div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><span><small>AKSES TERBATAS</small><strong><?=h($admin['name'])?></strong><em>Super Administrator</em></span></div></div></section>
<div class="wrap content">
<?php if($error):?><p class="readiness-alert bad"><?=h($error)?></p><?php endif;?>
<?php if($notice):?><p class="readiness-alert good"><?=h($notice)?></p><?php endif;?>
<div class="release-banner"><div><small>STATUS SISTEM</small><h2>Masih Tahap Pengujian / Pra-Produksi</h2>
<p><?=($autoComplete&&$manualComplete)?'Seluruh item terdata sebagai lulus/terverifikasi, tetapi aktivasi pelayanan publik tetap membutuhkan persetujuan resmi dan uji keamanan independen.':'Masih terdapat pemeriksaan otomatis atau bukti pengujian manual yang belum selesai. Tidak ada aktivasi pelayanan publik otomatis.'?></p></div>
<span class="release-status">BELUM DINYATAKAN SIAP PRODUKSI</span></div>
<div class="summary-grid">
<div class="summary-tile"><small>PEMERIKSAAN OTOMATIS</small><strong><?=$stats['passed']?> / <?=$stats['auto_total']?></strong><span>Persyaratan teknis yang terdeteksi memenuhi pemeriksaan</span></div>
<div class="summary-tile"><small>VERIFIKASI MANUAL</small><strong><?=$stats['verified']?> / <?=$stats['manual_total']?></strong><span>Telah dicatat Super Administrator dengan bukti ringkas</span></div>
<div class="summary-tile"><small>KEMAJUAN PENGUJIAN</small><strong><?=$percent?>%</strong><span>Persentase checklist, bukan bukti kelayakan produksi</span></div>
</div>
<section class="white-section">
<div class="heading"><span class="eyebrow">PEMERIKSAAN SERVER DAN DATABASE</span><h2>Diagnostik Otomatis</h2>
<p>Hasil diambil saat halaman ini dibuka. Tidak memaparkan nama pengguna atau password database.</p></div>
<div class="test-grid">
<?php foreach($automatic as $key=>$item):?>
<div class="test-card">
<div class="test-head"><strong><?=h($item['title'])?></strong><span class="check-pill <?=$item['passed']?'passed':'failed'?>"><?=$item['passed']?'Memenuhi':'Perlu Tindakan'?></span></div>
<p><?=h($item['hint'])?></p></div>
<?php endforeach;?>
</div>
</section>
<section class="white-section">
<div class="heading"><span class="eyebrow">PEMERIKSAAN KEBIJAKAN DAN PENGUJIAN</span><h2>Bukti Verifikasi Manual</h2>
<p>Ubah status hanya setelah benar-benar dilakukan. Catatan merupakan bukti ringkas, bukan tempat menyimpan password, NIK, atau data warga.</p></div>
<?php foreach(r12_manual_requirements() as $key=>$item):
  $record=$manual[$key]??null;
  $status=(string)($record['status']??'pending');
?>
<details class="manual-item" <?=($status!=='verified')?'':' '?>>
<summary>
<span class="manual-title"><strong><?=h($item['title'])?></strong><small><?=h($item['why'])?></small></span>
<span class="check-pill <?=($status==='verified')?'passed':(($status==='blocked')?'failed':'pending')?>"><?=h(r12_status_label($status))?></span>
</summary>
<form method="post" action="/kesiapan-produksi.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="action" value="record">
<input type="hidden" name="key" value="<?=h($key)?>">
<label>Status Pemeriksaan
<select name="status" required>
<option value="pending" <?=$status==='pending'?'selected':''?>>Belum Diverifikasi</option>
<option value="verified" <?=$status==='verified'?'selected':''?>>Sudah Diverifikasi — bukti wajib</option>
<option value="blocked" <?=$status==='blocked'?'selected':''?>>Ada Kendala</option>
</select></label>
<label>Catatan Bukti / Tindak Lanjut (maksimal 250 karakter)
<textarea name="evidence" maxlength="250" rows="2" placeholder="Contoh: pengujian akses oleh tim internal pada tanggal tertentu, tanpa menyebut data pribadi."><?=h($record['evidence_note']??'')?></textarea></label>
<div class="manual-actions"><button class="btn primary" type="submit" <?=$dbReady?'':'disabled'?>>Simpan Pemeriksaan</button>
<small><?=isset($record['updated_at'])?'Terakhir dicatat '.h($record['updated_at']).' WIB':'Belum pernah dicatat'?></small></div>
</form></details>
<?php endforeach;?>
</section>
<section class="white-section audit-section"><div class="heading"><span class="eyebrow">AUDIT INTERNAL</span><h2>12 Perubahan Checklist Terakhir</h2><p>Daftar ini tidak menampilkan catatan rinci atau data pribadi.</p></div>
<div class="audit-scroll"><table><thead><tr><th>Waktu</th><th>Pemeriksaan</th><th>Sebelumnya</th><th>Sesudahnya</th></tr></thead><tbody>
<?php if(!$recent):?><tr><td colspan="4">Belum ada perubahan yang tercatat.</td></tr><?php endif;?>
<?php foreach($recent as $r):?>
<tr><td><?=h($r['created_at'])?> WIB</td><td><?=h(r12_manual_requirements()[$r['requirement_key']]['title']??$r['requirement_key'])?></td>
<td><?=h(r12_status_label($r['old_status']))?></td><td><?=h(r12_status_label($r['new_status']))?></td></tr>
<?php endforeach;?></tbody></table></div></section>
<div class="readiness-notice"><strong>Batas Pengujian Tahap 12</strong><p>Status checklist ini bukan sertifikasi keamanan ataupun izin resmi peluncuran layanan. Sistem belum mempunyai integrasi tanda tangan elektronik resmi, validasi naskah dinas, pemindaian malware bawaan, atau MFA bawaan yang telah diuji di hosting. Jangan menandai item tersebut “Sudah Diverifikasi” tanpa bukti pelaksanaan nyata melalui mekanisme yang sah. Gunakan akun dan berkas dummy hingga proses pengamanan selesai.</p></div>
</div></main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Pengawasan Kesiapan Produksi <a href="/super-admin.php">Pusat Super Admin</a></div></footer>
</body></html>
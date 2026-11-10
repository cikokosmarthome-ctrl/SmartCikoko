<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/tasks21.php';
    $admin=require_super_admin();
    t21_super($admin);
}catch(Throwable $e){http_response_code(503);exit('Penugasan Super Admin belum siap. Pastikan installer selesai dan MFA berhasil.');}
$error='';$notice=(string)($_SESSION['t21_notice']??'');unset($_SESSION['t21_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['action']??'');
        if($action==='save'){
            [$source,$category]=array_pad(explode(':',(string)($_POST['category_key']??''),2),2,'');
            [$whoSource,$whoId]=array_pad(explode('|',(string)($_POST['reviewer_key']??''),2),2,'');
            $days=filter_var($_POST['days']??null,FILTER_VALIDATE_INT);
            if($days===false)throw new InvalidArgumentException('Ambang hari harus angka 1–60.');
            t21_save($admin,$source,$category,(string)($_POST['step']??''),$whoSource,$whoId,(int)$days,
                trim((string)($_POST['reference']??'')),(string)($_POST['effective_from']??''),($_POST['enabled']??'')==='1');
            $_SESSION['t21_notice']='Penugasan dan referensi tenggat telah disimpan.';
        }elseif($action==='schedule'){
            t21_toggle_schedule($admin,($_POST['enabled']??'')==='1');
            $_SESSION['t21_notice']='Status penjadwal internal diperbarui. Cron cPanel harus diatur terpisah dan tidak dijalankan otomatis oleh installer.';
        }elseif($action==='manual_run'){
            $result=t21_run(true,$admin);
            $_SESSION['t21_notice']='Pemeriksaan manual selesai: '.$result['created'].' pengingat internal baru dibuat dari '.$result['checked'].' kandidat.'
                .(!empty($result['incomplete'])?' Daftar kandidat dibatasi; periksa log.':'');
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        header('Location: /tugas-pemeriksa21.php',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage21 Super Admin action failed: '.get_class($e));
        $error=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)
            ?$e->getMessage():'Pengaturan gagal disimpan. Periksa status akun dan tabel.';
    }
}
$catalog=t21_catalog();$candidates=t21_candidates();$assignments=t21_assignments();$schedule=t21_schedule();
$preview=['rows'=>[],'active'=>0,'incomplete'=>false];
try{$preview=t21_due_candidates(40);}
catch(Throwable $e){error_log('Stage21 due preview failed.');$error='Pratinjau penugasan tidak tersedia.';}
$names=[];foreach($candidates as $p)$names[$p['source'].'|'.$p['id']]=$p['name'].' · '.$p['role'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d3757">
<title>Penugasan Pemeriksa dan SLA Internal | SmartCikoko</title>
<link rel="stylesheet" href="/assets/penugasan21.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap bar"><a class="brand" href="/"><span>KC</span><b>SMARTCIKOKO<small>Penugasan Pemeriksa Laporan</small></b></a>
<nav><a href="/super-admin-satu.php#penugasan-pemeriksa">Super Admin Ringkas</a><a href="/verifikasi-laporan19.php">Verifikasi Tahap 19</a><a href="/penugasan-saya21.php">Tugas Saya</a></nav></div></header>
<section class="hero"><div class="wrap"><small>TAHAP 21 · PENGENDALIAN PENUGASAN INTERNAL</small>
<h1>Pemeriksa, Tenggat &amp; Notifikasi Terjadwal</h1>
<p>Setiap kategori laporan dapat dipasangkan dengan satu pemeriksa dan satu akun Lurah untuk telaah administrasi. Aturan ini tidak mengubah hak akses resmi pada Tahap 19.</p></div></section>
<main class="wrap content">
<?php if($notice):?><div class="notice good"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="notice bad"><?=h($error)?></div><?php endif;?>
<div class="stats">
<div><small>Konfigurasi penugasan</small><strong><?=count($assignments)?></strong></div>
<div><small>Penugasan aktif dan valid</small><strong><?=(int)$preview['active']?></strong></div>
<div><small>Perlu tindak lanjut (pratinjau)</small><strong><?=count($preview['rows'])?></strong></div>
<div><small>Notifikasi terjadwal</small><strong><?=!empty($schedule['enabled'])?'Diizinkan':'Nonaktif'?></strong></div>
</div>
<div class="split">
<section class="panel">
<h2>Tetapkan Penugasan Kategori</h2>
<p class="hint">Pilih pemeriksa dari akun aktif. Penugasan disimpan berdasarkan sumber laporan, kategori, dan tahap. Tidak membuat akun atau meningkatkan hak akses pegawai.</p>
<form class="form" method="post" action="/tugas-pemeriksa21.php">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save">
<label>Kategori laporan<select name="category_key" required><option value="">Pilih kategori</option>
<?php foreach($catalog as $source=>$items):?>
<optgroup label="<?=h(m20_sources()[$source]['label']??$source)?>">
<?php foreach($items as $cat=>$label):?><option value="<?=h($source.':'.$cat)?>"><?=h($label)?></option><?php endforeach;?>
</optgroup><?php endforeach;?></select></label>
<label>Tahap pemeriksaan<select name="step" required>
<option value="pemeriksa">Pemeriksaan kelengkapan</option><option value="lurah">Telaah administratif Lurah</option>
</select></label>
<label>Akun yang ditugaskan<select name="reviewer_key" required><option value="">Pilih petugas aktif sesuai tahap</option>
<?php foreach($candidates as $p):?><option value="<?=h($p['source'].'|'.$p['id'])?>"><?=h($p['name'])?> · <?=h($p['role'])?> (<?=h($p['source'])?>)</option><?php endforeach;?></select></label>
<label>Tenggat tindak lanjut (hari kalender)<input type="number" name="days" min="1" max="60" value="3" required></label>
<label>Referensi SOP / dasar tenggat internal<input name="reference" maxlength="180" minlength="8" placeholder="Isi nomor dan nama SOP atau keputusan internal yang berlaku" required></label>
<label>Tanggal aturan mulai berlaku<input name="effective_from" type="date" value="<?=date('Y-m-d')?>" required></label>
<label class="check"><input type="checkbox" name="enabled" value="1"> Aktifkan penugasan ini setelah diverifikasi</label>
<button class="btn" type="submit">Simpan Penugasan dan Tenggat</button>
</form>
<p class="subnote">Untuk mengubah suatu konfigurasi, pilih kembali kategori dan tahap yang sama. Sistem mengganti pengaturan lama dan mempertahankan jejak audit. Referensi yang diketik tidak otomatis memverifikasi keabsahan SOP.</p>
</section>
<section class="panel">
<h2>Jadwal Notifikasi Internal</h2>
<p class="hint">Secara default nonaktif. Pengingat tidak dikirim ke WhatsApp, email, atau SMS. Semua pemberitahuan hanya muncul dalam akun SmartCikoko.</p>
<div class="schedule"><small>Status izin penjadwal</small><strong><?=!empty($schedule['enabled'])?'Diizinkan, perlu cron cPanel':'Nonaktif'?></strong>
<small>Eksekusi terakhir: <?=h($schedule['last_run']??'Belum pernah')?> · Dibuat: <?=(int)$schedule['last_count']?> · <?=!empty($schedule['last_incomplete'])?'Perlu cek keterbatasan':'Tanpa peringatan kelengkapan'?></small></div>
<form method="post" action="/tugas-pemeriksa21.php" class="schedule-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="schedule">
<label class="check"><input type="checkbox" name="enabled" value="1" <?=!empty($schedule['enabled'])?'checked':''?>> Izinkan penjadwal bekerja hanya setelah Cron Jobs cPanel dipasang dan diuji</label>
<button class="btn secondary" type="submit">Simpan Izin Penjadwal</button></form>
<form method="post" action="/tugas-pemeriksa21.php" class="schedule-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="manual_run">
<button class="btn" type="submit">Jalankan Pemeriksaan Manual Sekali</button></form>
<div class="notice warning">Tombol pemeriksaan manual benar-benar membuat pengingat bila laporan memenuhi aturan aktif; gunakan akun dan laporan dummy dahulu. Pemasangan ZIP tidak membuat cron otomatis.</div>
<h3>Konfigurasi cPanel Cron Jobs (opsional)</h3>
<p class="hint">Setelah uji berhasil, gunakan Cron Jobs dari hosting untuk menjalankan file PHP CLI privat. Pastikan jalur PHP CLI sesuai versi PHP domain.</p>
<code class="cron">php /home/datd1747/portal_private/cron_tugas21.php --dispatch</code>
<p class="subnote">Contoh dijalankan sekali sehari melalui cron cPanel setelah administrator mengaktifkan jadwal. Jangan membuat HTTP cron URL untuk file ini.</p>
</section>
</div>
<section class="panel"><div class="section-head"><h2>Daftar Penugasan Tersimpan</h2><span class="pill"><?=count($assignments)?> aturan</span></div>
<?php if(!$assignments):?><div class="empty">Belum ada penugasan. Tambahkan aturan pertama dengan status nonaktif sampai diverifikasi.</div><?php else:?>
<div class="scroll"><table><thead><tr><th>Modul</th><th>Kategori</th><th>Tahap</th><th>Petugas</th><th>Ambang</th><th>Status</th><th>Referensi</th></tr></thead><tbody>
<?php foreach($assignments as $r):?>
<tr><td><?=h(m20_sources()[$r['source_kind']]['label']??$r['source_kind'])?></td>
<td><?=h($catalog[$r['source_kind']][$r['category']]??$r['category'])?></td>
<td><?=h(t21_steps()[$r['step']]??$r['step'])?></td>
<td><?=h($names[$r['reviewer_source'].'|'.$r['reviewer_id']]??$r['reviewer_source'].' · ID '.$r['reviewer_id'])?></td>
<td><?=(int)$r['due_days']?> hari</td>
<td><span class="<?=!empty($r['enabled'])?'state-on':'state-off'?>"><?=!empty($r['enabled'])?'Aktif':'Nonaktif'?></span></td>
<td><?=h($r['reference_note'])?><small><?=h($r['effective_from'])?></small></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<section class="panel" id="antrean"><div class="section-head"><div><h2>Pratinjau Tugas Melewati Ambang</h2><p class="hint">Data aktif yang menunggu pemeriksa atau Lurah dan telah melewati ambang internal; bukan penilaian disiplin kerja.</p></div><span class="pill"><?=count($preview['rows'])?> ditampilkan</span></div>
<?php if($preview['incomplete']):?><div class="notice warning">Pratinjau dibatasi pada 120 laporan per kategori dan 40 baris akhir. Daftar mungkin belum lengkap, sehingga jangan digunakan untuk evaluasi disiplin.</div><?php endif;?>
<?php if(!$preview['rows']):?><div class="empty">Belum ada penugasan aktif yang melewati ambang, atau seluruh laporan sudah ditindaklanjuti.</div><?php else:?>
<div class="scroll"><table><thead><tr><th>Laporan</th><th>Fase</th><th>Penanggung jawab</th><th>Usia</th><th>Tindakan</th></tr></thead><tbody>
<?php foreach($preview['rows'] as $r):?>
<tr><td><?=h($r['source'])?> #<?=(int)$r['id']?><small><?=h($r['category'])?></small></td>
<td><?=h(m20_phase_labels()[$r['phase']]??$r['phase'])?></td>
<td><?=h($r['recipient_name'])?></td>
<td><?=(int)$r['days']?> hari (ambang <?=(int)$r['due_days']?>)</td>
<td><a class="link" href="/verifikasi-laporan19.php?modul=<?=h(rawurlencode($r['source']))?>&amp;id=<?=(int)$r['id']?>">Buka Verifikasi</a></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<p class="footnote">Penugasan ini hanya mengarahkan pemeriksaan administratif. Otorisasi Tahap 19 tetap berlaku; penetapan nama di sini tidak memberikan hak persetujuan cuti, penandatanganan surat, atau kewenangan di database Pegawai lama.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Penugasan Internal Terpadu</div></footer>
</body></html>
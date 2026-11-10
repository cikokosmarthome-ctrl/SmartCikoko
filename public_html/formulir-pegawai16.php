<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/detailed_forms16.php';
    $user=requires_login();
    if(!function_exists('account_control_allowed'))require_once dirname(__DIR__).'/portal_private/account_control.php';
    if(!account_control_allowed($user)){http_response_code(403);exit('Akun belum memiliki izin mengakses formulir.');}
}catch(Throwable $e){http_response_code(503);exit('Pusat formulir belum tersedia.');}
$kinds=f16_kinds();
$manager=is_super_admin($user);
$kind=(string)($_GET['jenis']??'');
if(!isset($kinds[$kind]))$kind='pppk';
$ready=f16_ready();
$notice=(string)($_SESSION['f16_notice']??'');unset($_SESSION['f16_notice']);
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Tabel Tahap 16 belum diaktifkan melalui installer.');
        $action=(string)($_POST['action']??'');
        if($action==='create'){
            $newId=f16_create($user,(string)($_POST['kind']??''),$_POST,(string)($_POST['request_key']??''),!empty($_POST['submit_now']));
            $_SESSION['f16_notice']='Formulir #'.$newId.(!empty($_POST['submit_now'])?' berhasil diajukan untuk peninjauan.':' tersimpan sebagai draf.');
        }elseif($action==='update'){
            f16_update($user,(int)($_POST['form_id']??0),(int)($_POST['version']??0),$_POST,!empty($_POST['submit_now']));
            $_SESSION['f16_notice']=!empty($_POST['submit_now'])?'Perubahan formulir berhasil diajukan kembali.':'Perubahan draf tersimpan.';
        }elseif($action==='review'&&$manager){
            f16_review($user,(int)($_POST['form_id']??0),(string)($_POST['status']??''),trim((string)($_POST['review_note']??'')));
            $_SESSION['f16_notice']='Hasil peninjauan administratif tersimpan.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        $destKind=(string)($_POST['kind']??$kind);
        if(!isset($kinds[$destKind]))$destKind=$kind;
        header('Location: /formulir-pegawai16.php?jenis='.rawurlencode($destKind).'#daftar',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage16 form action blocked: '.get_class($e));
        $error=($e instanceof RuntimeException||$e instanceof InvalidArgumentException)
            ?$e->getMessage():'Formulir gagal disimpan. Periksa pengaturan.';
    }
}
$allowed=f16_authorized($user,$kind);
$oneId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT);
$one=$ready&&$oneId?f16_read_one($user,(int)$oneId):null;
if($one && $one['kind']!==$kind)$one=null;
if($one && !($one['actor_source']===$user['source'] && hash_equals((string)$one['actor_id'],(string)$user['id'])
    && in_array($one['status'],['draf','perbaikan'],true)))$one=null;
$editData=$one?json_decode((string)$one['payload_json'],true):[];
if(!is_array($editData))$editData=[];
$rows=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
$page=max(1,filter_var($_GET['hal']??1,FILTER_VALIDATE_INT)?:1);
if($ready)try{$rows=f16_list($user,$kind,$page,12);}
catch(Throwable $e){error_log('Stage16 form list unavailable');$error='Daftar formulir tidak dapat dibuka saat ini.';}
$native=$allowed?f16_native_for_actor($user,$kind):[];
$nonce=bin2hex(random_bytes(16));
function f16_href(string $kind,int $page=1):string{
    return '/formulir-pegawai16.php?'.http_build_query(['jenis'=>$kind,'hal'=>$page],'','&',PHP_QUERY_RFC3986);
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#103657">
<title>Formulir Kepegawaian Terstruktur | SmartCikoko</title>
<link rel="stylesheet" href="/assets/formulir-pegawai16.css?v=compact1"></head>
<body>
<div class="govbar"><div class="wrap">PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="header"><div class="wrap head"><a class="brand" href="/"><span>KC</span><div><strong>KELURAHAN CIKOKO</strong><small>Administrasi Digital Terpadu</small></div></a>
<nav><a href="/?page=dashboard">Ruang Kerja</a><a href="/pusat-laporan.php">Pusat Laporan</a><a href="/aktivitas-terpadu.php">Notifikasi &amp; Progres</a>
<?php if($manager):?><a href="/super-admin-satu.php">Super Admin Ringkas</a><?php endif;?></nav></div></header>
<section class="hero"><div class="wrap hero-content"><div><small>TAHAP 16 · FORMULIR BERDASARKAN ARSIP LAMA</small>
<h1>Administrasi PPPK, PJLP dan Cuti</h1>
<p>Formulir dengan bidang yang dipetakan dari aplikasi lama. Data pengajuan baru dan catatan peninjauan berada di database terpisah; laporan dan persetujuan resmi lama tetap tidak berubah.</p>
</div><div class="id-badge"><span><?=h(mb_strtoupper(mb_substr((string)$user['name'],0,1)))?></span><div><strong><?=h($user['name'])?></strong><small><?=h(role_label((string)$user['role']))?></small></div></div></div></section>
<main class="wrap content">
<?php if($notice):?><div class="notice success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="notice danger"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="notice danger">Formulir Tahap 16 belum terpasang. Minta Super Admin menjalankan installer satu tombol; tidak perlu impor ulang SQL.</div><?php endif;?>
<div class="tabs">
<?php foreach($kinds as $key=>$meta): if(!$manager&&!f16_authorized($user,$key))continue;?>
<a class="<?=$kind===$key?'current':''?>" href="<?=h(f16_href($key))?>"><?=h($meta['name'])?></a>
<?php endforeach;?></div>
<div class="layout <?=$manager?'manager-mode':''?>">
<section class="panel" id="pengajuan">
<div class="panel-top"><div><h1 class="form-main-title"><?=h($kinds[$kind]['name'])?></h1></div><span class="chip">Draf Administrasi</span></div>
<details class="scope"><summary>Informasi formulir</summary><p>Disesuaikan dengan sumber <code><?=h($kinds[$kind]['native'])?></code>. Pengajuan ini tidak otomatis menjadi laporan resmi, persetujuan cuti, atau unggahan bukti.</p></details>
<?php if($manager):?><div class="empty">Super Admin bertugas meninjau; pembuatan formulir atas nama pegawai lain tidak diizinkan. Gunakan akun petugas untuk pengajuan baru.</div>
<?php elseif(!$allowed):?><div class="empty">Kategori ini hanya tersedia untuk akun dan peran yang sesuai. Silakan pilih formulir yang menjadi kewenangan Anda.</div>
<?php elseif(!$ready):?><div class="empty">Installer belum dijalankan.</div>
<?php else:?>
<?php if($one):?><div class="notice info">Mengubah formulir #<?=(int)$one['id']?> · Versi <?=(int)$one['version']?> · Status <?=h(f16_stages()[$one['status']]??$one['status'])?></div><?php endif;?>
<form class="form" method="post" action="<?=h(f16_href($kind))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="action" value="<?=$one?'update':'create'?>">
<input type="hidden" name="kind" value="<?=h($kind)?>">
<input type="hidden" name="request_key" value="<?=h($nonce)?>">
<?php if($one):?><input type="hidden" name="form_id" value="<?=(int)$one['id']?>"><input type="hidden" name="version" value="<?=(int)$one['version']?>"><?php endif;?>
<?php foreach($kinds[$kind]['fields'] as $key=>$spec):
 $value=(string)($editData[$key]??($key==='tanggal_surat'?date('Y-m-d'):'')); ?>
<label class="<?=$spec['type']==='textarea'?'wide-field':''?>">
<span><?=h($spec['label'])?><?=!empty($spec['required'])?' *':''?></span>
<?php if($spec['type']==='textarea'):?>
<textarea name="<?=h($key)?>" rows="2" maxlength="<?=(int)($spec['max']??2000)?>" <?=!empty($spec['required'])?'required':''?> placeholder="Isi sesuai kegiatan sebenarnya; jangan cantumkan NIK atau password."><?=h($value)?></textarea>
<?php elseif($spec['type']==='select'):?>
<select name="<?=h($key)?>" required>
<option value="">Pilih jenis laporan</option>
<?php foreach($spec['options'] as $opt=>$label):?><option value="<?=h($opt)?>" <?=$opt===$value?'selected':''?>><?=h($label)?></option><?php endforeach;?></select>
<?php else:?>
<input type="<?=h($spec['type'])?>" name="<?=h($key)?>" value="<?=h($value)?>" <?=!empty($spec['required'])?'required':''?> maxlength="<?=(int)($spec['max']??255)?>" <?=($spec['type']==='number')?'min="1" step="1"':''?>>
<?php endif;?>
</label>
<?php endforeach;?>
<p class="form-help">Belum mendukung unggah bukti atau pengesahan resmi.</p>
<div class="buttons"><button type="submit" name="submit_now" value="0" class="button secondary">Simpan Draf</button>
<button type="submit" name="submit_now" value="1" class="button primary">Ajukan Laporan</button></div>
</form>
<?php endif;?>
</section>
<section class="panel" id="daftar">
<div class="panel-top"><div><span class="eyebrow">RIWAYAT DAN TINDAK LANJUT</span><h2><?=$manager?'Antrean Semua Petugas':'Formulir Saya'?></h2></div><span class="chip"><?=number_format($rows['total'],0,',','.')?> formulir</span></div>
<?php if(!$rows['rows']):?><div class="empty">Belum ada formulir terstruktur untuk kategori ini.</div><?php endif;?>
<?php foreach($rows['rows'] as $row):
  $data=json_decode((string)$row['payload_json'],true);if(!is_array($data))$data=[];
  $canEdit=$row['actor_source']===$user['source']&&hash_equals((string)$row['actor_id'],(string)$user['id'])&&in_array($row['status'],['draf','perbaikan'],true);
?>
<details class="record"><summary><span><b>#<?=(int)$row['id']?> · <?=h($kinds[$row['kind']]['name']??$row['kind'])?></b>
<small><?=h($row['created_at'])?> WIB</small></span><i><?=h(f16_stages()[$row['status']]??$row['status'])?></i></summary>
<div class="record-body">
<dl><?php foreach($kinds[$row['kind']]['fields']??[] as $field=>$meta):?>
<dt><?=h($meta['label'])?></dt><dd><?=nl2br(h((string)($data[$field]??'—')))?></dd>
<?php endforeach;?></dl>
<?php if((string)($row['review_note']??'')!==''):?><div class="review-note"><b>Catatan peninjauan:</b> <?=h($row['review_note'])?></div><?php endif;?>
<?php if($canEdit):?><a class="button secondary" href="/formulir-pegawai16.php?jenis=<?=h($kind)?>&edit=<?=(int)$row['id']?>#pengajuan">Perbaiki / Kirim Ulang →</a><?php endif;?>
<?php if($manager&&in_array($row['status'],['diajukan','ditinjau'],true)):?>
<form class="review" method="post" action="<?=h(f16_href($kind))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="review"><input type="hidden" name="kind" value="<?=h($kind)?>">
<input type="hidden" name="form_id" value="<?=(int)$row['id']?>">
<label>Status administrasi<select name="status" required>
<option value="ditinjau">Sedang ditinjau</option>
<option value="perbaikan">Kembalikan untuk diperbaiki</option>
<option value="ditutup">Ditutup administratif (bukan persetujuan resmi)</option>
</select></label>
<label>Catatan peninjauan<textarea name="review_note" rows="2" maxlength="500" placeholder="Alasan saat meminta perbaikan"></textarea></label>
<button type="submit" class="button primary">Simpan Tinjauan</button></form>
<?php endif;?>
</div></details>
<?php endforeach;?>
<div class="pagination"><?php if($rows['page']>1):?><a href="<?=h(f16_href($kind,$rows['page']-1))?>#daftar">← Sebelumnya</a><?php endif;?>
<span>Halaman <?=$rows['page']?> / <?=$rows['pages']?></span>
<?php if($rows['page']<$rows['pages']):?><a href="<?=h(f16_href($kind,$rows['page']+1))?>#daftar">Selanjutnya →</a><?php endif;?></div>
</section>
</div>
<?php if($allowed):?>
<details class="panel native"><summary>Riwayat dari website lama <small>Opsional · hanya baca</small></summary><div class="native-content"><p>Hanya data yang berhak dilihat oleh akun Pegawai ini; identitas akun sumber tidak digabung otomatis.</p>
<?php if(!$native):?><div class="empty">Belum ditemukan catatan sumber asli untuk identitas ini, atau modul sumber tidak dapat dibaca.</div>
<?php else:?><div class="native-table"><table><thead><tr>
<?php foreach(array_keys($native[0]) as $col):?><th><?=h(str_replace('_',' ',ucwords($col,'_')))?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($native as $r):?><tr><?php foreach($r as $val):?><td><?=h($val??'—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div></details><?php endif;?>
<p class="disclaimer">Catatan: formulir terstruktur Tahap 16 adalah pengajuan administratif untuk verifikasi. Status “Ditutup” tidak berarti cuti disetujui, kinerja PPPK dinilai, atau laporan PJLP resmi terkirim. Pengesahan tetap mengikuti sistem serta kewenangan instansi.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Formulir Terstruktur Internal</div></footer>
</body></html>
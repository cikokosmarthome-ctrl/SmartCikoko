<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/field_reports17.php';
    require_once dirname(__DIR__).'/portal_private/account_control.php';
    $user=requires_login();
    if(!account_control_allowed($user)){http_response_code(403);exit('Akun belum diizinkan membuka formulir.');}
}catch(Throwable $e){http_response_code(503);exit('Pusat laporan belum dapat dibuka.');}
$catalog=w17_catalog();$manager=is_super_admin($user);
$kind=(string)($_GET['jenis']??'');
if(!isset($catalog[$kind])){
    $kind='rt_rw';
    if(!$manager)foreach($catalog as $key=>$_){if(w17_actor_can($user,$key)){$kind=$key;break;}}
}
$allowed=w17_actor_can($user,$kind);
$ready=w17_ready();
$error='';$notice=(string)($_SESSION['w17_notice']??'');unset($_SESSION['w17_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Formulir Tahap 17 belum diaktifkan.');
        $action=(string)($_POST['action']??'');
        if($action==='create'){
            $id=w17_submit($user,(string)($_POST['kind']??''),$_POST,(string)($_POST['request_key']??''),($_POST['send']??'')==='1');
            $_SESSION['w17_notice']='Laporan #'.$id.' berhasil dicatat. Statusnya administratif, bukan pengesahan resmi.';
        }elseif($action==='edit'){
            w17_edit($user,(int)($_POST['report_id']??0),(int)($_POST['version']??0),$_POST,($_POST['send']??'')==='1');
            $_SESSION['w17_notice']='Draf / perbaikan berhasil disimpan dalam riwayat.';
        }else throw new RuntimeException('Tindakan tidak diizinkan.');
        $dest=(string)($_POST['kind']??$kind);
        if(!isset($catalog[$dest]))$dest=$kind;
        header('Location: /laporan-wilayah17.php?jenis='.rawurlencode($dest).'#riwayat',true,303);exit;
    }catch(Throwable $e){
        error_log('Stage 17 form submission denied: '.get_class($e));
        $error=($e instanceof RuntimeException||$e instanceof InvalidArgumentException)
            ?$e->getMessage():'Pengiriman gagal. Coba lagi setelah memeriksa isian.';
    }
}
$editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT);
$edit=$ready&&$editId?w17_get_own($user,(int)$editId):null;
if($edit&&($edit['kind']!==$kind||!in_array($edit['status'],['draf','perbaikan'],true)))$edit=null;
$values=$edit?json_decode((string)$edit['payload_json'],true):[];
if(!is_array($values))$values=[];
$page=max(1,filter_var($_GET['hal']??1,FILTER_VALIDATE_INT)?:1);
$list=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];
if($ready)try{$list=w17_list($user,$kind,$page,12);}
catch(Throwable $e){error_log('Stage 17 report listing error.');$error='Daftar laporan belum dapat ditampilkan.';}
$old=$allowed?w17_native_own($user,$kind):[];
$nonce=bin2hex(random_bytes(16));
function w17_href(string $kind,int $page=1):string {
    return '/laporan-wilayah17.php?'.http_build_query(['jenis'=>$kind,'hal'=>$page],'','&',PHP_QUERY_RFC3986);
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2f4d">
<title>Pusat Laporan Kewilayahan | SmartCikoko</title>
<link rel="stylesheet" href="/assets/laporan-wilayah17.css"></head><body>
<div class="top-strip"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a class="brand" href="/"><span class="brand-mark">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Pusat Pelayanan dan Laporan Terpadu</small></span></a>
<nav><a href="/?page=dashboard">Ruang Kerja</a><a href="/pusat-laporan.php">Pusat Laporan</a>
<?php if($manager):?><a href="/super-admin-satu.php#laporan-wilayah">Super Admin Ringkas</a><?php endif;?></nav></div></header>
<div class="hero"><div class="wrap hero-inner">
<div><small>TAHAP 17 · KEWILAYAHAN DAN KESEHATAN</small><h1>Input Laporan Lapangan Terstruktur</h1>
<p>RT/RW, LMK, Jumantik, FKDM dan PPSU dalam satu formulir sesuai kewenangan. Foto, identitas warga, koordinat pribadi dan dokumen resmi tidak dikumpulkan pada tahap ini.</p></div>
<div class="account"><span><?=h(mb_strtoupper(mb_substr((string)$user['name'],0,1)))?></span><div><b><?=h($user['name'])?></b><small><?=h(role_label((string)$user['role']))?></small></div></div>
</div></div>
<main class="wrap content">
<?php if($notice):?><div class="flash success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="flash error">Tahap 17 belum diaktifkan. Super Admin perlu menjalankan installer sekali. Tidak perlu SQL manual.</div><?php endif;?>
<div class="intro"><div><small class="eyebrow">PILIH BIDANG</small><h2>Modul Pelaporan</h2>
<p>Setiap modul memiliki format berbeda. Hanya akun dengan peran yang sesuai yang dapat menginput laporan.</p></div></div>
<div class="module-tabs">
<?php foreach($catalog as $key=>$meta):?>
<a class="tab <?=$kind===$key?'current':''?>" href="<?=h(w17_href($key))?>">
<strong><?=h($meta['name'])?></strong>
<small><?=w17_actor_can($user,$key)?'Akses penginputan':'Akses terbatas'?></small></a>
<?php endforeach;?></div>
<div class="columns">
<section class="panel" id="formulir"><div class="panel-title"><div><span class="eyebrow">FORMULIR BERDASARKAN JENIS KEGIATAN</span><h2><?=h($catalog[$kind]['name'])?></h2></div><span class="tag">Draf & Peninjauan</span></div>
<p class="panel-desc"><?=h($catalog[$kind]['note'])?></p>
<?php if($manager):?><div class="empty">Super Admin memantau dan meninjau laporan yang dikirim, tetapi tidak mengisi laporan atas nama petugas.</div>
<?php elseif(!$allowed):?><div class="empty">Akun ini tidak memiliki hak mengirim kategori tersebut. Silakan pilih kategori sesuai peran.</div>
<?php elseif(!$ready):?><div class="empty">Installer Tahap 17 belum diselesaikan.</div>
<?php else:?>
<?php if($edit):?><div class="flash neutral">Mengedit laporan #<?=(int)$edit['id']?> (versi <?=(int)$edit['version']?>). Status: <?=h(w17_statuses()[$edit['status']]??$edit['status'])?></div><?php endif;?>
<form class="form" action="<?=h(w17_href($kind))?>" method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="kind" value="<?=h($kind)?>">
<input type="hidden" name="action" value="<?=$edit?'edit':'create'?>">
<?php if($edit):?><input type="hidden" name="report_id" value="<?=(int)$edit['id']?>"><input type="hidden" name="version" value="<?=(int)$edit['version']?>"><?php endif;?>
<input type="hidden" name="request_key" value="<?=h($nonce)?>">
<?php foreach($catalog[$kind]['fields'] as $key=>$field):
 $type=(string)$field['type'];$v=(string)($values[$key]??'');
 if($type==='date'&&$v==='')$v=date('Y-m-d');
 $required=!empty($field['required']);
?>
<label><span><?=h($field['label'])?><?=$required?' *':''?></span>
<?php if($type==='textarea'):?>
<textarea name="<?=h($key)?>" maxlength="<?=(int)($field['max']??2000)?>" rows="3" <?=$required?'required':''?> placeholder="Gunakan informasi kegiatan umum, tanpa NIK atau alamat warga."><?=h($v)?></textarea>
<?php elseif($type==='select'):?>
<select name="<?=h($key)?>" <?=$required?'required':''?>><option value="">Pilih jenis kegiatan</option>
<?php foreach($field['options'] as $opt=>$label):?><option value="<?=h($opt)?>" <?=$v===$opt?'selected':''?>><?=h($label)?></option><?php endforeach;?></select>
<?php else:?>
<input type="<?=in_array($type,['count','rt'],true)?'number':h($type)?>" name="<?=h($key)?>" value="<?=h($v)?>" <?=$required?'required':''?>
<?php if(in_array($type,['count','rt'],true)):?>min="<?=$type==='rt'?'1':'0'?>" max="<?=(int)($field['max']??999)?>" step="1"
<?php else:?>maxlength="<?=(int)($field['max']??255)?>"<?php endif;?>>
<?php endif;?></label>
<?php endforeach;?>
<div class="flash neutral">
Laporan hanya berupa teks dan angka. **Dokumen dan foto tidak diunggah** sampai perlindungan malware dan tata kelola data diuji. Status “Ditutup Administratif” bukan pengesahan laporan resmi.
</div>
<div class="form-buttons"><button class="btn ghost" type="submit" name="send" value="0">Simpan Draf</button><button class="btn primary" type="submit" name="send" value="1">Ajukan untuk Ditinjau →</button></div>
</form>
<?php endif;?></section>
<section class="panel" id="riwayat"><div class="panel-title"><div><span class="eyebrow">MONITORING DAN PERBAIKAN</span><h2><?=$manager?'Antrean Laporan Kewilayahan':'Riwayat Laporan Saya'?></h2></div><span class="tag"><?=(int)$list['total']?> laporan</span></div>
<?php if(!$list['rows']):?><div class="empty">Belum ada laporan pada kategori ini.</div><?php endif;?>
<?php foreach($list['rows'] as $record):
 $payload=json_decode((string)$record['payload_json'],true);if(!is_array($payload))$payload=[];
 $own=w17_own($user,$record);
 $canEdit=$own&&in_array($record['status'],['draf','perbaikan'],true);
?>
<details class="report"><summary><span><b>#<?=(int)$record['id']?> · <?=h($catalog[$record['kind']]['name']??$record['kind'])?></b><small><?=h($record['created_at'])?> WIB</small></span><i><?=h(w17_statuses()[$record['status']]??$record['status'])?></i></summary>
<div class="report-body"><dl><?php foreach($catalog[$record['kind']]['fields']??[] as $fieldKey=>$field):?>
<dt><?=h($field['label'])?></dt><dd><?=nl2br(h((string)($payload[$fieldKey]??'—')))?></dd><?php endforeach;?></dl>
<?php if((string)($record['review_note']??'')!==''):?><p class="review-note"><b>Catatan peninjauan:</b> <?=h($record['review_note'])?></p><?php endif;?>
<?php if($canEdit):?><a class="btn ghost" href="/laporan-wilayah17.php?jenis=<?=h($kind)?>&edit=<?=(int)$record['id']?>#formulir">Perbaiki / Kirim Ulang →</a><?php endif;?>
<?php if($manager&&in_array($record['status'],['diajukan','ditinjau'],true)):?>
<a class="btn ghost" href="/super-admin-satu.php#laporan-wilayah">Tinjau dari Super Admin Ringkas →</a>
<?php endif;?></div></details>
<?php endforeach;?>
<div class="pagination"><?php if($list['page']>1):?><a href="<?=h(w17_href($kind,$list['page']-1))?>#riwayat">← Sebelumnya</a><?php endif;?>
<span>Halaman <?=$list['page']?> / <?=$list['pages']?></span>
<?php if($list['page']<$list['pages']):?><a href="<?=h(w17_href($kind,$list['page']+1))?>#riwayat">Berikutnya →</a><?php endif;?></div></section>
</div>
<?php if($allowed):?>
<section class="panel legacy"><div class="panel-title"><div><span class="eyebrow">REFERENSI SISTEM SEBELUMNYA</span><h2>Riwayat Sumber Asli Saya (Baca Saja)</h2></div></div>
<p>Hanya metadata laporan yang cocok dengan identitas akun. Data pemohon surat RT/RW, identitas rumah tangga, dan bukti foto tidak ditampilkan.</p>
<?php if(!$old):?><div class="empty">Belum ada laporan lama untuk kategori dan akun ini, atau sistem sumber tidak dapat dibaca.</div>
<?php else:?><div class="table-scroll"><table><thead><tr><?php foreach(array_keys($old[0]) as $col):?><th><?=h(str_replace('_',' ',$col))?></th><?php endforeach;?></tr></thead>
<tbody><?php foreach($old as $row):?><tr><?php foreach($row as $cell):?><td><?=h($cell??'—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php endif;?>
<p class="disclaimer">Pelaporan ini terpisah dari surat RT/RW, laporan mobile berfoto, tindakan PSN resmi, verifikasi kegiatan, dan keputusan perangkat daerah. Gunakan sistem dan prosedur asli apabila diperlukan bukti atau pengesahan resmi.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Laporan Kewilayahan dan Kesehatan</div></footer>
</body></html>
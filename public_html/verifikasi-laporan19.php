<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_once dirname(__DIR__).'/portal_private/verification19.php';
    $u=requires_login();
    if(!v19_actor_allowed($u)){http_response_code(403);exit('Akun tidak berwenang memeriksa laporan.');}
}catch(Throwable $e){http_response_code(503);exit('Verifikasi berjenjang belum tersedia.');}
$notice=(string)($_SESSION['v19_notice']??'');unset($_SESSION['v19_notice']);
$error='';$ready=v19_ready();
$source=(string)($_GET['modul']??'');
if($source!==''&&!isset(v19_sources()[$source]))$source='';
$selectedId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        if(!$ready)throw new RuntimeException('Installer Tahap 19 belum dijalankan.');
        $sourcePost=(string)($_POST['source_kind']??'');
        $id=(int)($_POST['report_id']??0);
        $version=(int)($_POST['version']??0);
        $action=(string)($_POST['action']??'');
        $note=trim((string)($_POST['note']??''));
        v19_act($u,$sourcePost,$id,$version,$action,$note);
        $_SESSION['v19_notice']='Catatan pemeriksaan berhasil disimpan pada riwayat administratif.';
        header('Location: /verifikasi-laporan19.php?'.http_build_query(['modul'=>$sourcePost]),true,303);exit;
    }catch(Throwable $e){
        error_log('Stage 19 verification blocked: '.get_class($e));
        $error=($e instanceof InvalidArgumentException||$e instanceof RuntimeException)
            ?$e->getMessage():'Verifikasi tidak dapat disimpan; periksa kewenangan dan status.';
    }
}
$queue=[];$selected=null;
if($ready){
    try{$queue=v19_queue($u,$source,45);}
    catch(Throwable $e){error_log('Stage 19 queue failed.');$error='Daftar pemeriksaan belum dapat dimuat.';}
    if($selectedId&&$source!==''){
        try{$selected=v19_detail($u,$source,(int)$selectedId);}
        catch(Throwable $e){error_log('Stage 19 detail denied.');}
    }
}
function v19_link(string $src,int $id=0):string{
    return '/verifikasi-laporan19.php?'.http_build_query(array_filter(['modul'=>$src,'id'=>$id],static fn($v)=>$v!==''&&$v!==0),'','&',PHP_QUERY_RFC3986);
}
$labels=['ringkas15'=>'Laporan Ringkas','pegawai16'=>'Formulir Pegawai','wilayah17'=>'Formulir Wilayah'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0a304f">
<title>Pemeriksaan Berjenjang Laporan | SmartCikoko</title>
<link rel="stylesheet" href="/assets/verifikasi19.css"></head><body>
<div class="gov"><div class="wrap">PEMERINTAH PROVINSI DKI JAKARTA <span>KECAMATAN PANCORAN · KELURAHAN CIKOKO</span></div></div>
<header class="nav"><div class="wrap nav-inner"><a href="/" class="brand"><span class="crest">KC</span><span><strong>SMARTCIKOKO</strong><small>Pusat Pemeriksaan Administratif</small></span></a>
<nav><a href="/pusat-laporan.php">Input Laporan</a><?php if(is_super_admin($u)):?><a href="/super-admin-satu.php#verifikasi-berjenjang">Super Admin Ringkas</a><?php endif;?></nav></div></header>
<section class="hero"><div class="wrap hero-grid"><div><small>TAHAP 19 · PEMERIKSAAN BERJENJANG</small><h1>Verifikasi Laporan Administratif</h1>
<p>Pemeriksa meneliti kelengkapan, kemudian Lurah dapat mencatat hasil telaah. Keputusan ini tidak otomatis menerbitkan surat, menyetujui cuti atau menilai kinerja secara resmi.</p></div>
<div class="role"><span><?=h(mb_strtoupper(mb_substr((string)$u['name'],0,1)))?></span><div><strong><?=h($u['name'])?></strong><small><?=v19_lurah($u)?'Telaah Lurah':'Pemeriksa administrasi'?></small></div></div></div></section>
<main class="wrap content">
<?php if($notice):?><div class="flash success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="flash error">Tabel Tahap 19 belum siap. Super Admin perlu menjalankan installer satu tombol terlebih dahulu.</div><?php endif;?>
<div class="intro"><h2>Antrean dan Status Pemeriksaan</h2><p>Daftar hanya memuat laporan yang sudah diajukan dan relevan dengan peran. Draf petugas tidak ditampilkan.</p></div>
<div class="status-grid">
<div><span>Langkah 1</span><b>Periksa Kelengkapan</b><small>Petugas sesuai bidang / Super Admin</small></div>
<div><span>Langkah 2</span><b>Perbaikan Bila Perlu</b><small>Pelapor memperbaiki formulir miliknya</small></div>
<div><span>Langkah 3</span><b>Telaah Lurah</b><small>Dicatat terpisah, bukan pengesahan resmi</small></div>
</div>
<div class="split">
<section class="panel" id="antrean">
<div class="panel-head"><h2>Laporan untuk Diperiksa</h2><span class="chip"><?=count($queue)?> terbaru</span></div>
<div class="filters">
<a class="<?=$source===''?'active':''?>" href="<?=h(v19_link(''))?>">Semua</a>
<?php foreach($labels as $key=>$label):?><a class="<?=$source===$key?'active':''?>" href="<?=h(v19_link($key))?>"><?=h($label)?></a><?php endforeach;?>
</div>
<?php if(!$queue):?><div class="empty">Belum ada laporan yang tersedia untuk akun Anda.</div><?php endif;?>
<div class="items">
<?php foreach($queue as $r):?>
<a class="item <?=($selectedId===(int)$r['id']&&$source===$r['source_kind'])?'selected':''?>" href="<?=h(v19_link((string)$r['source_kind'],(int)$r['id']))?>">
<div><b><?=h($labels[$r['source_kind']]??$r['source_kind'])?> · #<?=(int)$r['id']?></b>
<small><?=h($r['category'])?> · <?=h($r['created_at'])?> WIB</small></div>
<span><?=h(v19_states()[$r['effective_state']]??$r['effective_state'])?></span></a>
<?php endforeach;?>
</div>
<p class="subnote">Menampilkan maksimal 45 laporan terbaru. Untuk laporan lama, gunakan rekap pada Super Admin dan filter modul.</p>
</section>
<section class="panel" id="detail">
<h2>Rincian Laporan</h2>
<?php if(!$selected):?><div class="empty">Pilih laporan dari antrean untuk meninjau data dan mencatat tindakan.</div>
<?php else:
$eligible=null;foreach($queue as $r)if($r['source_kind']===$source&&(int)$r['id']===(int)$selectedId){$eligible=$r;break;}
?>
<p class="subnote"><?=h($labels[$source]??$source)?> · #<?=(int)$selected['id']?> · Jenis <?=h($selected['category'])?> · Status <?=h($selected['status'])?></p>
<div class="details">
<?php if($source==='ringkas15'):?>
<?php foreach(['title'=>'Judul','body'=>'Uraian Laporan','location'=>'Wilayah','activity_date'=>'Tanggal Kegiatan','end_date'=>'Tanggal Selesai'] as $key=>$label):?>
<div><b><?=h($label)?></b><p><?=nl2br(h($selected[$key]??'—'))?></p></div>
<?php endforeach;?>
<?php else:?>
<?php foreach(($selected['payload']??[]) as $key=>$val):?>
<div><b><?=h(str_replace('_',' ',ucwords((string)$key,'_')))?></b><p><?=nl2br(h(is_scalar($val)?$val:'—'))?></p></div>
<?php endforeach;?>
<?php endif;?>
</div>
<?php if($eligible):?>
<div class="review-state"><b><?=h(v19_states()[$eligible['effective_state']]??$eligible['effective_state'])?></b>
<?php if(!empty($eligible['review_note'])):?><p>Catatan pemeriksa: <?=h($eligible['review_note'])?></p><?php endif;?>
<?php if(!empty($eligible['lurah_note'])):?><p>Catatan Lurah: <?=h($eligible['lurah_note'])?></p><?php endif;?></div>
<?php if($eligible['can_precheck']||$eligible['can_lurah']):?>
<form class="review" method="post" action="<?=h(v19_link($source,(int)$selected['id']))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="source_kind" value="<?=h($source)?>">
<input type="hidden" name="report_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="version" value="<?=(int)$selected['version']?>">
<label>Tindakan Administratif<select name="action" required>
<?php if($eligible['can_precheck']):?>
<option value="periksa">Kelengkapan diperiksa — kirim untuk telaah Lurah</option>
<option value="kembalikan">Kembalikan kepada pelapor untuk perbaikan</option>
<?php elseif($eligible['can_lurah']):?>
<option value="lurah_telaah">Catat telaah Lurah (BUKAN pengesahan surat/cuti)</option>
<option value="lurah_kembalikan">Lurah meminta perbaikan laporan</option>
<?php endif;?>
</select></label>
<label>Catatan Pemeriksaan<textarea name="note" rows="3" maxlength="500" placeholder="Hasil pemeriksaan, alasan atau arahan perbaikan"></textarea></label>
<p class="subnote">Untuk meminta perbaikan, alasan minimal 12 karakter wajib diisi. Pelapor hanya dapat mengedit ulang setelah status sumber dikembalikan.</p>
<button type="submit" class="btn">Simpan Tindakan Berjenjang →</button>
</form>
<?php else:?><div class="flash neutral">Tidak ada tindakan yang tersedia pada tahap ini. Laporan mungkin menunggu peran lain atau sudah selesai secara administratif.</div><?php endif;?>
<?php else:?><div class="flash neutral">Formulir hanya dapat diproses melalui antrean aktif yang tampil pada akun.</div><?php endif;?>
<?php endif;?>
</section></div>
<p class="footnote">Hasil Tahap 19 hanya pemeriksaan kelengkapan dan telaah administratif. Persetujuan cuti, penilaian kerja, penerbitan surat, serta tanda tangan sah memerlukan prosedur dan kewenangan tersendiri.</p>
</main><footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Verifikasi Administratif Berjenjang</div></footer></body></html>
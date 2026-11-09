<?php
declare(strict_types=1);
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if(!function_exists('require_super_admin'))require_once dirname(__DIR__).'/portal_private/super_admin.php';
    if(!function_exists('control_target'))require_once dirname(__DIR__).'/portal_private/account_control.php';
    $super=require_super_admin();
}catch(Throwable $e){http_response_code(503);exit('Layanan pengelolaan pengguna belum tersedia.');}
$names=['pegawai'=>'Pegawai','pancoran'=>'PPPK Pancoran','smartapp'=>'Portal Cikoko'];
$source=(string)($_GET['s']??'pegawai');
if(!isset($names[$source]))$source='pegawai';
$query=mb_substr(trim((string)($_GET['q']??'')),0,80);
$id=mb_substr(trim((string)($_GET['id']??'')),0,100);
$notice=(string)($_SESSION['user_management_notice']??'');
unset($_SESSION['user_management_notice']);
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $postSource=(string)($_POST['source']??'');
        $postId=(string)($_POST['id']??'');
        $action=(string)($_POST['action']??'');
        $value=(string)($_POST['value']??'');
        $ability=(string)($_POST['ability']??'');
        control_set($super,$postSource,$postId,$action,$value,$ability);
        $_SESSION['user_management_notice']='Pengaturan akses berhasil diperbarui dan dicatat dalam audit.';
        header('Location: /kelola-pengguna.php?'.http_build_query(['s'=>$postSource,'id'=>$postId],'','&',PHP_QUERY_RFC3986),true,303);exit;
    }catch(Throwable $e){
        error_log('SmartCikoko user management failed: '.$e->getMessage());
        $error='Perubahan tidak dapat disimpan. Pastikan akun bukan administrator yang dilindungi, pilihan modul sesuai sumber, dan tabel Tahap 6 sudah terpasang.';
    }
}
function ulink(string $source,string $q='',string $id='',int $page=1):string{
    return '/kelola-pengguna.php?'.http_build_query(['s'=>$source,'q'=>$q,'id'=>$id,'p'=>$page],'','&',PHP_QUERY_RFC3986);
}
function control_badge(string $val):string{
    return match($val){'active'=>'Aktif','blocked'=>'Diblokir','grant'=>'Diizinkan','deny'=>'Ditolak','default'=>'Mengikuti Peran',default=>'Tidak Diketahui'};
}
function sql_source(string $source):array{
    return match($source){
        'pegawai'=>['db'=>'pegawai','table'=>'users','column'=>"id,username,nama_lengkap AS name,role,status",'search'=>"username LIKE :user OR nama_lengkap LIKE :name"],
        'pancoran'=>['db'=>'pegawai','table'=>'ck_pancoran_users','column'=>"id,email AS username,name,'pppk_pancoran' AS role,CASE WHEN is_active=1 THEN 'active' ELSE 'inactive' END AS status",'search'=>"email LIKE :user OR name LIKE :name"],
        default=>['db'=>'smartapp','table'=>'unified_portal_users','column'=>"id,username,name,role,CASE WHEN active=1 THEN 'active' ELSE 'inactive' END AS status",'search'=>"username LIKE :user OR name LIKE :name"]
    };
}
$limit=20;$page=max(1,min(10000,filter_var($_GET['p']??1,FILTER_VALIDATE_INT)?:1));
$rows=[];$count=0;
try{
    $config=sql_source($source);
    $pdo=db($config['db']);
    $search=$query!==''?' WHERE ('.$config['search'].')':'';
    $filter=$query!=='' ? '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$query).'%' : '';
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM '.$config['table'].$search);
    if($query!==''){$stmt->bindValue(':user',$filter);$stmt->bindValue(':name',$filter);}
    $stmt->execute();$count=(int)$stmt->fetchColumn();
    $page=min($page,max(1,(int)ceil($count/$limit)));
    $stmt=$pdo->prepare('SELECT '.$config['column'].' FROM '.$config['table'].$search.' ORDER BY id DESC LIMIT :lim OFFSET :off');
    if($query!==''){$stmt->bindValue(':user',$filter);$stmt->bindValue(':name',$filter);}
    $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);$stmt->bindValue(':off',($page-1)*$limit,PDO::PARAM_INT);
    $stmt->execute();$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){error_log('Account management listing unavailable.');$error='Daftar akun belum dapat dimuat. Periksa koneksi database.';}
$target=null;$current=[];$grants=[];
if($id!==''){
    try {
        $target=control_target($source,$id);
        if($target){
            $current=control_current($source,$id);
            $grants=control_decisions($source,$id);
        }
    }catch(Throwable $e){error_log('Account management detail unavailable.');$error='Rincian pengaturan akun belum dapat dibuka.';}
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b2a48"><meta name="robots" content="noindex,nofollow">
<title>Manajemen Pengguna | SmartCikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/kelola-pengguna.css">
</head><body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner"><a class="brand" href="/"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks" aria-label="Menu"><a href="/">Beranda</a><a href="/super-admin.php">Pusat Super Admin</a><a href="/direktori-akun.php">Direktori Akun</a><a class="selected" href="/kelola-pengguna.php">Manajemen Pengguna</a></nav>
</div></header>
<main><section class="hero"><div class="wrap hero-inner"><div><span class="kicker">TAHAP 6 / AKSES TERBATAS</span><h1>Manajemen Pengguna</h1><p>Kelola izin akses portal dan kewenangan modul dari dua database dalam satu halaman. Data akun sumber tetap terpelihara.</p></div><div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr($super['name'],0,1)))?></span><span><small>SUPER ADMIN AKTIF</small><strong><?=h($super['name'])?></strong><em>Pengendali akses terotorisasi</em></span></div></div></section>
<div class="wrap content">
<?php if($notice):?><div class="message success"><?=h($notice)?></div><?php endif;?>
<?php if($error):?><div class="message error"><?=h($error)?></div><?php endif;?>
<div class="section-head"><div><span class="eyebrow">AKUN TERPADU</span><h2>Pilih Sumber Pengguna</h2><p>Akun tetap berada di database masing-masing; perubahan izin hanya berlaku pada Portal SmartCikoko.</p></div></div>
<div class="source-pills"><?php foreach($names as $key=>$label):?><a class="<?=$key===$source?'active':''?>" href="<?=h(ulink($key))?>"><?=h($label)?></a><?php endforeach;?></div>
<div class="manage-layout"><section class="panel-users">
<h2>Daftar Akun <?=h($names[$source])?></h2>
<form method="get" action="/kelola-pengguna.php" class="filter-form">
<input type="hidden" name="s" value="<?=h($source)?>">
<label for="q">Cari nama atau username</label><div class="filter-controls"><input id="q" name="q" maxlength="80" value="<?=h($query)?>" placeholder="Nama atau username"><button class="btn primary" type="submit">Cari</button></div></form>
<p class="tiny">Ditemukan <?=number_format($count,0,',','.')?> akun. Halaman <?=$page?>.</p>
<div class="users-list">
<?php if(!$rows):?><p class="empty">Tidak terdapat akun pada daftar ini.</p><?php endif;?>
<?php foreach($rows as $row):?>
<a class="user-row <?=($id!=='')&&(string)$row['id']===$id?'selected':''?>" href="<?=h(ulink($source,$query,(string)$row['id'],$page))?>">
<span class="avatar-small"><?=h(mb_strtoupper(mb_substr((string)$row['name'],0,1)))?></span>
<span class="user-info"><strong><?=h($row['name'])?></strong><small><?=h($row['username'])?> · <?=h(role_label((string)$row['role']))?></small></span>
<span class="user-end"><em class="<?=$row['status']==='active'?'green':'red'?>"><?=h($row['status']==='active'?'Aktif':'Nonaktif')?></em><span>›</span></span>
</a>
<?php endforeach;?></div>
<div class="pages"><?php if($page>1):?><a href="<?=h(ulink($source,$query,'',$page-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($page*$limit<$count):?><a href="<?=h(ulink($source,$query,'',$page+1))?>">Berikutnya →</a><?php endif;?></div>
</section>
<section class="panel-detail">
<?php if(!$target):?><div class="placeholder"><span class="symbol">◎</span><h2>Pilih Akun Pengguna</h2><p>Klik salah satu pengguna pada daftar untuk memeriksa status serta kewenangan modulnya.</p></div>
<?php else:?>
<span class="eyebrow">PENGATURAN IDENTITAS</span><h2><?=h($target['name'])?></h2>
<p class="identity-line"><?=h($target['username'])?> · <?=h(role_label($target['role']))?></p>
<div class="facts"><div><small>SUMBER AKUN</small><strong><?=h($names[$source])?></strong></div><div><small>STATUS SUMBER</small><strong><?=h($target['status']==='active'?'Aktif':'Tidak Aktif')?></strong></div></div>
<?php if($target['protected'] || ($source===$super['source'] && $id===(string)$super['id'])):?>
<div class="message warn"><strong>Akun Administrator dilindungi.</strong><p>Perubahan akun Administrator dan Super Administrator tidak dapat dilakukan melalui halaman ini, untuk mencegah pengambilalihan kewenangan.</p></div>
<?php else:?>
<div class="control-section"><h3>Akses Login Portal</h3><p>Memblokir akun di portal ini tidak menghapus akun atau data aslinya. Sesi portal aktif akan ditolak setelah pengaturan diterapkan.</p>
<div class="current-state">Status saat ini <strong><?=h($current['allowed']?'Diizinkan':'Diblokir')?></strong></div>
<form method="post" action="<?=h(ulink($source,'',$id))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="source" value="<?=h($source)?>">
<input type="hidden" name="id" value="<?=h($id)?>">
<input type="hidden" name="action" value="login">
<label for="access-mode">Atur akses</label>
<select id="access-mode" name="value" required>
<option value="active" <?=$current['allowed']?'selected':''?>>Izinkan Login</option>
<option value="blocked" <?=!$current['allowed']?'selected':''?>>Blokir Login</option>
</select><button class="btn primary" type="submit">Simpan Status Akses</button></form></div>
<div class="control-section"><h3>Hak Akses Modul</h3><p>Pengaturan <b>Mengikuti Peran</b> menggunakan kewenangan bawaan akun. Izin khusus hanya berlaku pada modul yang kompatibel dengan sumber akun.</p>
<?php foreach(control_available_abilities($source) as $ability):
$label=match($ability){'pppk'=>'Pelaporan PPPK','pjlp'=>'Kegiatan PJLP / PPSU','waris'=>'Pelayanan Ahli Waris',default=>$ability};
$mode=$grants[$ability]??'default';?>
<form class="permission-form" method="post" action="<?=h(ulink($source,'',$id))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="source" value="<?=h($source)?>">
<input type="hidden" name="id" value="<?=h($id)?>">
<input type="hidden" name="action" value="permission">
<input type="hidden" name="ability" value="<?=h($ability)?>">
<div class="permission-title"><strong><?=h($label)?></strong><small><?=h(control_badge($mode))?></small></div>
<div class="permission-controls"><label for="mode-<?=h($ability)?>" class="visually-hidden">Izin <?=h($label)?></label>
<select id="mode-<?=h($ability)?>" name="value">
<option value="default" <?=$mode==='default'?'selected':''?>>Mengikuti Peran</option>
<option value="grant" <?=$mode==='grant'?'selected':''?>>Diizinkan</option>
<option value="deny" <?=$mode==='deny'?'selected':''?>>Ditolak</option>
</select><button type="submit" class="btn outline">Simpan</button></div>
</form>
<?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>
</section></div>
<div class="message info"><strong>Pengelolaan Aman dan Akuntabel</strong><p>Kata sandi, NIK, dan data pribadi sensitif tidak ditampilkan. Perubahan dicatat pada audit. Hak Super Admin tidak dapat diberikan dari halaman ini. Perubahan role sumber dan reset kata sandi memerlukan mekanisme verifikasi tambahan.</p></div>
</div></main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Manajemen Pengguna Terpadu <a href="/super-admin.php">Pusat Super Admin</a></div></footer>
</body></html>
<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    if (!function_exists('require_super_admin')) {
        require_once dirname(__DIR__).'/portal_private/super_admin.php';
    }
    $admin=require_super_admin();
} catch(Throwable $e) {
    http_response_code(503);
    exit('Layanan administrasi belum dapat dibuka.');
}
$labels=['pegawai'=>'Pegawai','pancoran'=>'PPPK Pancoran','smartapp'=>'Portal Cikoko'];
$source=(string)($_GET['s']??'pegawai');
if (!isset($labels[$source])) $source='pegawai';
$term=trim((string)($_GET['q']??''));
$term=mb_substr($term,0,80);
$page=filter_var($_GET['p']??1,FILTER_VALIDATE_INT, ['options'=>['default'=>1,'min_range'=>1,'max_range'=>100000]]);
if (!is_int($page)) $page=1;
$limit=30;
$offset=($page-1)*$limit;
$tables=[
  'pegawai'=>[
    'db'=>'pegawai',
    'from'=>'users',
    'filter'=>'(username LIKE :username OR nama_lengkap LIKE :name)',
    'columns'=>"id,username,nama_lengkap AS name,role,status",
    'order'=>'id DESC'
  ],
  'pancoran'=>[
    'db'=>'pegawai',
    'from'=>'ck_pancoran_users',
    'filter'=>'(email LIKE :username OR name LIKE :name)',
    'columns'=>"id,email AS username,name,'pppk_pancoran' AS role,CASE WHEN is_active=1 THEN 'active' ELSE 'inactive' END AS status",
    'order'=>'id DESC'
  ],
  'smartapp'=>[
    'db'=>'smartapp',
    'from'=>'unified_portal_users',
    'filter'=>'(username LIKE :username OR name LIKE :name)',
    'columns'=>"id,username,name,role,CASE WHEN active=1 THEN 'active' ELSE 'inactive' END AS status",
    'order'=>'id DESC'
  ],
];
function directory_count(PDO $db,string $table):?int {
    try{return (int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();}
    catch(Throwable $e){error_log('Directory count failed');return null;}
}
function as_count(?int $n):string{return $n===null?'—':number_format($n,0,',','.');}
function directory_url(string $s,string $q='',int $p=1):string {
   return '/direktori-akun.php?'.http_build_query(['s'=>$s,'q'=>$q,'p'=>$p],'','&',PHP_QUERY_RFC3986);
}
$counts=[];
try {
  $counts=[
     'pegawai'=>directory_count(db('pegawai'),'users'),
     'pancoran'=>directory_count(db('pegawai'),'ck_pancoran_users'),
     'smartapp'=>directory_count(db('smartapp'),'unified_portal_users')
  ];
} catch(Throwable $e) {
   $counts=['pegawai'=>null,'pancoran'=>null,'smartapp'=>null];
}
$rows=[];$total=null;$error='';
try {
  $selected=$tables[$source];
  $pdo=db($selected['db']);
  $where=$term!==''?' WHERE '.$selected['filter']:'';
  $countSql='SELECT COUNT(*) FROM '.$selected['from'].$where;
  $sum=$pdo->prepare($countSql);
  if ($term!=='') {
    // Escape LIKE wildcards, keeping searches literal.
    $search='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$term).'%';
    $sum->bindValue(':username',$search,PDO::PARAM_STR);
    $sum->bindValue(':name',$search,PDO::PARAM_STR);
  }
  $sum->execute();
  $total=(int)$sum->fetchColumn();
  $maxPage=max(1,(int)ceil($total/$limit));
  $page=min($page,$maxPage);
  $offset=($page-1)*$limit;
  $sql='SELECT '.$selected['columns'].' FROM '.$selected['from'].$where.' ORDER BY '.$selected['order'].' LIMIT :limit OFFSET :offset';
  $stmt=$pdo->prepare($sql);
  if ($term!=='') {
    $stmt->bindValue(':username',$search,PDO::PARAM_STR);
    $stmt->bindValue(':name',$search,PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
  $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
  $stmt->execute();
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
  error_log('SmartCikoko account directory unavailable: '.$e->getMessage());
  $error='Direktori akun sementara tidak tersedia. Periksa koneksi kedua database.';
}
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b2a48">
<title>Direktori Akun Terpadu | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/super-admin.css">
<link rel="stylesheet" href="/assets/direktori-akun.css">
</head>
<body>
<div class="topline"><div class="wrap"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="nav"><div class="wrap nav-inner">
<a class="brand" href="/"><span class="monogram">KC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav class="navlinks" aria-label="Navigasi utama"><a href="/">Beranda</a><a href="/?page=dashboard">Ruang Kerja</a><a href="/super-admin.php">Pusat Super Admin</a><a class="selected" href="/direktori-akun.php">Direktori Akun</a><a href="/kelola-pengguna.php">Manajemen Pengguna</a></nav>
</div></header>
<main>
<section class="hero"><div class="wrap hero-inner"><div>
<span class="kicker">PENGAWASAN AKUN TERPADU</span><h1>Direktori Akun</h1>
<p>Meninjau akun dari kedua database secara terpusat, tanpa memindahkan atau mengubah data asli pengguna.</p>
</div><div class="person"><span class="avatar"><?=h(mb_strtoupper(mb_substr((string)$admin['name'],0,1)))?></span><span><small>AKSES TERBATAS</small><strong><?=h($admin['name'])?></strong><em>Super Administrator</em></span></div></div></section>
<div class="wrap content">
<div class="section-head"><div><span class="eyebrow">RINGKASAN IDENTITAS</span><h2>Akun Berdasarkan Sumber</h2><p>Angka merupakan jumlah akun, bukan jumlah orang unik. Satu pengguna mungkin tercatat di beberapa sumber.</p></div></div>
<div class="account-summary">
<?php foreach($labels as $key=>$label):?>
<a class="account-count <?=$key===$source?'active':''?>" href="<?=h(directory_url($key))?>"><small><?=h($label)?></small><strong><?=as_count($counts[$key])?></strong><span><?=h($key==='pegawai'?'Database Pegawai':($key==='pancoran'?'Database Pegawai':'Database SmartApp'))?></span></a>
<?php endforeach;?>
</div>
<section class="directory">
<div class="directory-head"><div><span class="eyebrow">DAFTAR AKUN</span><h2><?=h($labels[$source])?></h2><p>Informasi terbatas untuk kebutuhan administrasi dan verifikasi akun.</p></div></div>
<div class="source-tabs" role="navigation" aria-label="Sumber akun">
<?php foreach($labels as $key=>$label):?><a class="<?=$key===$source?'active':''?>" href="<?=h(directory_url($key))?>"><?=h($label)?></a><?php endforeach;?>
</div>
<form class="search" method="get" action="/direktori-akun.php">
<input type="hidden" name="s" value="<?=h($source)?>">
<label for="search">Cari akun berdasarkan nama atau username</label>
<div class="search-controls"><input id="search" name="q" maxlength="80" value="<?=h($term)?>" placeholder="Masukkan nama atau username"><button class="btn primary" type="submit">Cari Akun</button><a class="btn outline" href="<?=h(directory_url($source))?>">Atur Ulang</a></div>
</form>
<?php if($error):?><p class="alert error"><?=h($error)?></p><?php else:?>
<p class="result-count">Ditemukan <b><?=as_count($total)?></b> akun. Halaman <?=h((string)$page)?> dari <?=h((string)max(1,(int)ceil(($total??0)/$limit)))?>.</p>
<div class="account-table-wrap"><table class="account-table"><thead><tr><th>Nama</th><th>Username / Email Akun</th><th>Jenis Akun</th><th>Status</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="4" class="empty-row">Tidak terdapat akun yang sesuai dengan pencarian.</td></tr><?php endif;?>
<?php foreach($rows as $row):?>
<tr><td><strong><?=h($row['name'])?></strong></td><td><?=h($row['username'])?></td><td><?=h(role_label((string)$row['role']))?></td><td><span class="account-status <?=($row['status']==='active')?'active':'inactive'?>"><?=($row['status']==='active')?'Aktif':'Tidak Aktif'?></span></td></tr>
<?php endforeach;?>
</tbody></table></div>
<nav class="pages" aria-label="Navigasi halaman daftar akun">
<?php if($page>1):?><a class="btn outline" href="<?=h(directory_url($source,$term,$page-1))?>">← Sebelumnya</a><?php endif;?>
<?php if($total!==null && $page*$limit<$total):?><a class="btn outline" href="<?=h(directory_url($source,$term,$page+1))?>">Berikutnya →</a><?php endif;?>
</nav>
<?php endif;?>
</section>
<div class="notice"><strong>Perlindungan Data Pengguna</strong><p>Halaman ini hanya dapat dibuka Super Administrator, tidak menampilkan password, hash password, NIK, atau token. Pengaturan peran dan kata sandi belum dapat diubah melalui direktori ini. Data asli tetap berada pada database masing-masing.</p></div>
</div></main>
<footer><div class="wrap">© <?=date('Y')?> Kelurahan Cikoko · Direktori Akun Terpadu <a href="/super-admin.php">Pusat Super Admin</a></div></footer>
</body></html>
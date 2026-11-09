<?php
declare(strict_types=1);
try { require dirname(__DIR__).'/portal_private/bootstrap.php'; }
catch(Throwable $e) {http_response_code(503);exit('Portal belum dikonfigurasi. Hubungi administrator sistem.');}
$page=(string)($_GET['page']??'beranda');
$pages=['beranda','login','dashboard','pegawai','pppk','pjlp','waris','detail-waris','berita','agenda','profil','logout'];
if(!in_array($page,$pages,true))$page='beranda';
$err='';$notice=(string)($_SESSION['notice']??'');unset($_SESSION['notice']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try {
  if($page==='login'){
   if(isset($_POST['selection'],$_SESSION['pending_matches'])){
    $idx=filter_var($_POST['selection'],FILTER_VALIDATE_INT);
    $choices=$_SESSION['pending_matches'];
    if($idx===false||!isset($choices[$idx]))throw new RuntimeException('Pilihan akun tidak tersedia.');
    start_actor_session($choices[$idx]);redirect('dashboard');
   }
   $identity=trim((string)($_POST['username']??''));
   throttle_login($identity);
   $matches=login_matches($identity,(string)($_POST['password']??''));
   if(!$matches)throw new RuntimeException('Identitas akun atau kata sandi tidak sesuai.');
   clear_login_limit($identity);
   if(count($matches)===1){start_actor_session($matches[0]);redirect('dashboard');}
   $_SESSION['pending_matches']=$matches;
  }elseif($page==='logout'){end_actor_session();redirect('beranda');}
  elseif($page==='detail-waris'){
   $u=require_ability('waris_manage');
   waris_change_status($u,(int)($_GET['id']??0),(string)($_POST['status']??''),trim((string)($_POST['notes']??'')));
   $_SESSION['notice']='Status permohonan berhasil diperbarui.';
   header('Location: /?page=detail-waris&id='.(int)($_GET['id']??0),true,303);exit;
  }else throw new RuntimeException('Tindakan tidak diizinkan.');
 }catch(Throwable $e){$err=$e->getMessage();}
}
$u=actor();
if(in_array($page,['dashboard','pegawai','pppk','pjlp','waris','detail-waris','profil'],true)&&!$u)redirect('login');
$allowed = !($page==='pegawai'&&!is_admin()) &&
 !($page==='pppk'&&!permitted('pppk')) &&
 !($page==='pjlp'&&!permitted('pjlp')) &&
 !(in_array($page,['waris','detail-waris'],true)&&!permitted('waris'));
if(!$allowed){http_response_code(403);$page='forbidden';}
function a(string $target,string $title,string $current):void {
 echo '<a class="'.($target===$current?'active':'').'" href="'.h(url($target)).'">'.h($title).'</a>';
}
function show_table(array $rows,array $cols):void {
 if(!$rows){echo '<p class="empty">Belum terdapat data yang dapat ditampilkan.</p>';return;}
 echo '<div class="scroll"><table><thead><tr>';
 foreach($cols as $v)echo '<th>'.h($v).'</th>';
 echo '</tr></thead><tbody>';
 foreach($rows as $r){echo '<tr>';foreach($cols as $k=>$v)echo '<td>'.h($r[$k]??'—').'</td>';echo '</tr>';}
 echo '</tbody></table></div>';
}
function date_text(?string $s):string{return $s&&strtotime($s)?date('d M Y',strtotime($s)):'—';}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0a2949">
<meta name="description" content="Portal informasi, pelayanan masyarakat dan administrasi terpadu Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.">
<title><?=h(ucwords(str_replace('-',' ',$page)))?> | Kelurahan Cikoko</title>
<link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="ribbon"><div class="container"><span>PEMERINTAH PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA</span><span>KECAMATAN PANCORAN · JAKARTA SELATAN</span></div></div>
<header class="header"><div class="container headinner"><a class="brand" href="/"><span class="seal">JC</span><span><strong>KELURAHAN CIKOKO</strong><small>Portal Pelayanan dan Administrasi Terpadu</small></span></a>
<nav><?php a('beranda','Beranda',$page);a('berita','Informasi',$page);a('agenda','Agenda',$page);
if($u){a('dashboard','Ruang Kerja',$page);if(is_admin($u))a('pegawai','Pegawai',$page);
if(permitted('pppk'))a('pppk','PPPK',$page);if(permitted('pjlp'))a('pjlp','PJLP',$page);
if(permitted('waris'))a('waris','Ahli Waris',$page);a('profil','Akun',$page);}
else a('login','Masuk Portal',$page);?></nav></div></header>
<?php if($notice):?><div class="container alert good"><?=h($notice)?></div><?php endif;?>
<?php if($err):?><div class="container alert bad"><?=h($err)?></div><?php endif;?>
<?php if($page==='beranda'):
try{$posts=public_posts(3);$events=public_events(3);}catch(Throwable $e){$posts=[];$events=[];}
?>
<section class="hero"><div class="container hero-grid"><div><span class="eyebrow">LAYANAN DIGITAL KELURAHAN CIKOKO</span>
<h1>Pelayanan publik <em>terintegrasi</em> untuk masyarakat yang lebih mudah terlayani.</h1>
<p>Portal resmi informasi dan administrasi Kelurahan Cikoko. Pelayanan yang profesional, transparan, akuntabel, dan berorientasi pada kebutuhan masyarakat.</p>
<div class="buttons"><a class="btn primary" href="<?=h(url($u?'dashboard':'login'))?>"><?= $u?'Buka Ruang Kerja':'Akses Portal Layanan' ?> ↗</a><a class="btn secondary" href="#layanan">Jelajahi Layanan ↓</a></div>
<div class="hero-meta">Informasi Terpadu <b>•</b> Pelayanan Profesional <b>•</b> Hak Akses Terverifikasi</div></div>
<div class="hero-visual"><div class="outline">CIKOKO</div><div class="hero-graphic">◈</div><div class="hero-plate"><small>PORTAL DIGITAL RESMI</small><strong>Kelurahan Cikoko</strong><span>Kecamatan Pancoran, Jakarta Selatan</span></div></div></div></section>
<section id="layanan" class="container section"><div class="section-top"><span class="eyebrow dark">LAYANAN UNGGULAN</span><h2>Satu portal, berbagai kemudahan pelayanan</h2><p>Informasi bagi masyarakat dan ruang kerja digital bagi aparatur kelurahan.</p></div>
<div class="cards">
<?php foreach([
 ['◈','Pelayanan Ahli Waris','Penelusuran dan pemantauan permohonan ahli waris.','waris'],
 ['▤','Administrasi Kepegawaian','Pengelolaan pegawai dan administrasi internal.','dashboard'],
 ['◎','Pelaporan PPPK','Laporan kinerja PPPK sesuai peran dan kewenangan.','pppk'],
 ['▣','Kegiatan PJLP / PPSU','Informasi tugas dan rekap kegiatan petugas lapangan.','pjlp'],
 ['◇','Informasi Publik','Berita dan informasi resmi Kelurahan Cikoko.','berita'],
 ['◷','Agenda Kegiatan','Agenda layanan dan kegiatan kelurahan.','agenda']
] as $service):?><a class="service" href="<?=h(url($service[3]))?>"><div class="service-icon"><?=h($service[0])?></div><strong><?=h($service[1])?></strong><p><?=h($service[2])?></p><span>Lihat Layanan ↗</span></a><?php endforeach;?></div></section>
<section class="soft"><div class="container section"><div class="section-top"><span class="eyebrow dark">PUBLIKASI</span><h2>Informasi dan Agenda Terkini</h2></div>
<div class="cards"><?php foreach($posts as $p):?><a class="service" href="<?=h(url('berita'))?>"><span class="tag">BERITA</span><strong><?=h($p['title'])?></strong><p><?=h($p['summary'])?></p><span><?=h(date_text($p['published_at']))?></span></a><?php endforeach;?>
<?php foreach($events as $e):?><a class="service" href="<?=h(url('agenda'))?>"><span class="tag">AGENDA</span><strong><?=h($e['title'])?></strong><p><?=h($e['location'])?></p><span><?=h(date_text($e['starts_at']))?></span></a><?php endforeach;?>
<?php if(!$posts&&!$events):?><p>Belum terdapat publikasi atau agenda terbaru.</p><?php endif;?></div></div></section>
<?php else:?>
<section class="banner"><div class="container"><span class="eyebrow">PORTAL TERPADU KELURAHAN CIKOKO</span><h1><?=h(match($page){
 'login'=>'Akses Portal Terpadu','dashboard'=>'Ruang Kerja Terpadu','pegawai'=>'Administrasi Pegawai',
 'pppk'=>'Pelaporan Kinerja PPPK','pjlp'=>'Kegiatan PJLP / PPSU','waris'=>'Pelayanan Ahli Waris',
 'detail-waris'=>'Rincian Permohonan Ahli Waris','berita'=>'Informasi Publik','agenda'=>'Agenda Kelurahan',
 'profil'=>'Profil Pengguna',default=>'Akses Terbatas'
})?></h1><p>Pelayanan dan administrasi yang tertib, transparan, aman dan berorientasi pada pengguna.</p></div></section>
<section class="container section">
<?php if($page==='login'):?>
<div class="panel narrow"><h2>Masuk ke Sistem</h2><p>Gunakan akun dan kata sandi yang sudah terdaftar pada sistem sebelumnya.</p>
<?php if(isset($_SESSION['pending_matches'])&&count($_SESSION['pending_matches'])>1):?>
<div class="alert">Ditemukan lebih dari satu ruang kerja. Pilih identitas yang akan digunakan.</div>
<?php foreach($_SESSION['pending_matches'] as $i=>$choice):?><form method="post" action="<?=h(url('login'))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="selection" value="<?=h((string)$i)?>">
<button class="account-choice" type="submit"><strong><?=h($choice['name'])?></strong><span><?=h(role_label($choice['role']))?> · <?=h($choice['source'])?> →</span></button></form><?php endforeach;?>
<?php else:?>
<form method="post" action="<?=h(url('login'))?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label>Username atau Email<input required maxlength="190" name="username" autocomplete="username" placeholder="Masukkan identitas akun"></label>
<label>Kata Sandi<input required type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi"></label>
<button class="btn primary wide" type="submit">Masuk ke Ruang Kerja ↗</button></form>
<?php endif;?></div>
<?php elseif($page==='dashboard'):
$d=dashboard_data($u);?><div class="profile"><span class="avatar"><?=h(mb_strtoupper(mb_substr($u['name'],0,1)))?></span><div><small>IDENTITAS AKTIF</small><h2>Selamat datang, <?=h($u['name'])?></h2><p><?=h(role_label($u['role']))?> · <?=h($u['source'])?></p></div></div>
<?php if($d['metrics']):?><div class="metrics"><?php foreach($d['metrics'] as $m):?><div class="metric"><small><?=h($m['label'])?></small><strong><?=number_format((int)$m['value'],0,',','.')?></strong></div><?php endforeach;?></div><?php endif;?>
<h2 class="subtitle">Modul yang Dapat Diakses</h2><div class="cards">
<?php $modules=[['berita','Informasi Publik'],['agenda','Agenda Kelurahan']];
if(is_admin($u))$modules[]=['pegawai','Administrasi Pegawai'];
if(permitted('pppk',$u))$modules[]=['pppk','Pelaporan PPPK'];
if(permitted('pjlp',$u))$modules[]=['pjlp','Kegiatan PJLP / PPSU'];
if(permitted('waris',$u))$modules[]=['waris','Pelayanan Ahli Waris'];
foreach($modules as $m):?><a class="service" href="<?=h(url($m[0]))?>"><div class="service-icon">◈</div><strong><?=h($m[1])?></strong><span>Masuk Modul ↗</span></a><?php endforeach;?></div>
<?php elseif($page==='pegawai'):?><div class="panel"><h2>Daftar Akun Pegawai</h2><p>Data internal. Hanya dapat diakses oleh administrator yang berwenang.</p><?php show_table(employee_list($u),['id'=>'ID','nama_lengkap'=>'Nama Pegawai','username'=>'Username','role'=>'Kategori','status'=>'Status']);?></div>
<?php elseif($page==='pppk'):?><div class="panel"><h2>Data Laporan Kinerja PPPK</h2>
<?php if($u['source']==='pancoran'&&!is_admin($u)):?><p class="alert">Akun PPPK Pancoran sudah terintegrasi untuk login. Pemetaan laporan khusus Pancoran perlu validasi relasi akun.</p><?php endif;?>
<?php show_table(pppk_list($u),['id'=>'ID','month_key'=>'Periode','report_type'=>'Jenis','status'=>'Status']);?></div>
<?php elseif($page==='pjlp'):?><div class="panel"><h2>Data Pelaksanaan Tugas PJLP / PPSU</h2><?php show_table(pjlp_list($u),['session_id'=>'ID','work_date'=>'Tanggal','route_no'=>'Jalur','planned_start'=>'Mulai','planned_end'=>'Selesai']);?></div>
<?php elseif($page==='waris'):?><div class="panel"><h2>Permohonan Ahli Waris</h2><p>Informasi hanya ditampilkan sesuai kewenangan akun.</p>
<?php $rows=waris_list($u);if(!$rows):?><p class="empty">Belum ada permohonan yang dapat ditampilkan.</p><?php else:?>
<div class="scroll"><table><thead><tr><th>Nomor</th><th>Nama Pemohon</th><th>Status</th><th>Detail</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['reference_no'])?></td><td><?=h($r['applicant_name'])?></td><td><?=h($r['status'])?></td><td><a href="/?page=detail-waris&id=<?=(int)$r['id']?>">Lihat ↗</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php elseif($page==='detail-waris'):$w=waris_details($u,(int)($_GET['id']??0));?><div class="panel">
<?php if(!$w):?><p class="empty">Data tidak ditemukan atau akun tidak memiliki kewenangan.</p><?php else:?>
<h2><?=h($w['reference_no'])?></h2><dl class="details"><dt>Nama Pemohon</dt><dd><?=h($w['applicant_name'])?></dd><dt>Hubungan</dt><dd><?=h($w['relationship'])?></dd><dt>Almarhum/Almarhumah</dt><dd><?=h($w['deceased_name'])?></dd><dt>Status</dt><dd><?=h($w['status'])?></dd><dt>Catatan Petugas</dt><dd><?=h($w['staff_notes']?:'Belum terdapat catatan.')?></dd></dl>
<?php if(permitted('waris_manage',$u)):?><h3>Perbarui Status</h3><form method="post" action="/?page=detail-waris&id=<?=(int)$w['id']?>">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label>Status<select name="status"><?php foreach(['masuk','proses','revisi','selesai','ditolak'] as $status):?><option value="<?=h($status)?>" <?=$w['status']===$status?'selected':''?>><?=h(ucfirst($status))?></option><?php endforeach;?></select></label>
<label>Catatan<textarea name="notes" maxlength="1000" rows="4"></textarea></label><button class="btn primary" type="submit">Simpan Pembaruan</button></form><?php endif;endif;?></div>
<?php elseif($page==='berita'):?><div class="cards"><?php foreach(public_posts(18) as $p):?><div class="service"><span class="tag">BERITA</span><strong><?=h($p['title'])?></strong><p><?=h($p['summary'])?></p><small><?=h(date_text($p['published_at']))?></small></div><?php endforeach;?></div>
<?php elseif($page==='agenda'):?><div class="cards"><?php foreach(public_events(18) as $e):?><div class="service"><span class="tag">AGENDA</span><strong><?=h($e['title'])?></strong><p><?=h($e['description'])?></p><small><?=h($e['location'])?> · <?=h(date_text($e['starts_at']))?></small></div><?php endforeach;?></div>
<?php elseif($page==='profil'):?><div class="panel narrow"><h2><?=h($u['name'])?></h2><dl class="details"><dt>Username</dt><dd><?=h($u['username'])?></dd><dt>Kategori Akun</dt><dd><?=h(role_label($u['role']))?></dd><dt>Sumber Akun</dt><dd><?=h($u['source'])?></dd></dl><form method="post" action="<?=h(url('logout'))?>"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><button class="btn secondary" type="submit">Keluar dari Portal</button></form></div>
<?php else:?><div class="panel"><h2>Akses Dibatasi</h2><p>Akun Anda tidak memiliki kewenangan untuk membuka modul ini.</p><a class="btn primary" href="<?=h(url('dashboard'))?>">Kembali ke Ruang Kerja</a></div><?php endif;?>
</section>
<?php endif;?>
<footer class="footer"><div class="container footgrid"><div><h3>KELURAHAN CIKOKO</h3><p>Kecamatan Pancoran, Kota Administrasi Jakarta Selatan</p><p>Pelayanan profesional, transparan, dan akuntabel.</p></div><div><h3>Navigasi</h3><a href="/">Beranda</a><a href="/?page=berita">Informasi Publik</a><a href="/?page=agenda">Agenda</a></div><div><h3>Layanan Digital</h3><a href="/?page=login">Portal Pengguna</a><a href="/?page=dashboard">Ruang Kerja Terpadu</a><a href="/kebijakan-privasi.html">Kebijakan Privasi</a></div></div><div class="container copyright">© <?=date('Y')?> Portal Terpadu Kelurahan Cikoko</div></footer>
</body></html>
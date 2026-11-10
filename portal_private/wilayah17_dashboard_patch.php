<?php
declare(strict_types=1);
/**
 * Add a Stage-17 section to the existing compact Super Admin WITHOUT
 * replacing index.php, bootstrap, auth, MFA, or the whole dashboard.
 */
const W17_PATCH_MARKER='<!-- SMARTCIKOKO_TAHAP17_PANEL -->';
function w17_dashboard_ready(string $src):bool {
    return str_contains($src,W17_PATCH_MARKER)
        && str_contains($src,'/assets/wilayah17-admin.css')
        && str_contains($src,'/portal_private/wilayah17_admin_panel.php');
}
function w17_dashboard_patch(string $src):string {
    if(w17_dashboard_ready($src))return $src;
    if(strlen($src)<3000 || !str_contains($src,'super_admin_metrics()')
        || substr_count($src,'<section id="kemajuan"')!==1
        || substr_count(strtolower($src),'</head>')!==1
        || !str_contains($src,'</main>')
        || str_contains($src,W17_PATCH_MARKER))
        throw new RuntimeException('Struktur dashboard aktif berbeda dari yang dapat diperbarui secara aman.');
    $stylesheet='<link rel="stylesheet" href="/assets/wilayah17-admin.css">';
    $panel=W17_PATCH_MARKER."\n"
        ."<?php\n"
        ."if(is_file(dirname(__DIR__).'/portal_private/wilayah17_admin_panel.php')) {\n"
        ."    require dirname(__DIR__).'/portal_private/wilayah17_admin_panel.php';\n"
        ."}\n"
        ."?>\n";
    $src=preg_replace('~</head>~i',$stylesheet."\n".'</head>',$src,1,$count);
    if(!is_string($src)||$count!==1)throw new RuntimeException('Head dashboard tidak dapat diperbarui.');
    $src=str_replace('<section id="kemajuan"',$panel.'<section id="kemajuan"',$src,$n);
    if($n!==1 || !w17_dashboard_ready($src))
        throw new RuntimeException('Integrasi panel ditolak karena struktur file berubah.');
    return $src;
}
function w17_dashboard_apply(string $path,string $private,string $expectedHash):string {
    if(!preg_match('/^[0-9a-f]{64}$/D',$expectedHash))
        throw new InvalidArgumentException('Pemeriksaan versi dashboard tidak tersedia.');
    if(!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('Dashboard tidak dapat ditulis secara aman.');
    $base=realpath($private);
    $web=realpath(dirname($path));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup tidak privat.');
    $original=@file_get_contents($path);
    if(!is_string($original)||!hash_equals(hash('sha256',$original),$expectedHash))
        throw new RuntimeException('File Super Admin berubah sejak halaman dibuka. Muat ulang.');
    $patched=w17_dashboard_patch($original);
    if($patched===$original)return '';
    $backupdir=$base.'/wilayah17_backups';
    if(is_link($backupdir))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($backupdir)&&!@mkdir($backupdir,0700))
        throw new RuntimeException('Folder backup tidak dapat dibuat.');
    @chmod($backupdir,0700);
    if(realpath($backupdir)!==$backupdir)throw new RuntimeException('Lokasi backup tidak sesuai.');
    $backup=$backupdir.'/super-admin-satu-before-t17-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Backup tidak lengkap; dashboard tidak diubah.');
    @chmod($backup,0600);
    $temp=@tempnam($backupdir,'stage17_');
    if(!$temp)throw new RuntimeException('File kerja privat tidak tersedia.');
    try {
        if(@file_put_contents($temp,$patched,LOCK_EX)!==strlen($patched))
            throw new RuntimeException('Penulisan perubahan dashboard belum lengkap.');
        $mode=@fileperms($path);
        @chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$path))throw new RuntimeException('Tidak dapat memasang panel; dashboard asli tidak diganti.');
    }finally {if(is_file($temp))@unlink($temp);}
    return basename($backup);
}


/** Optional Stage-15 entry link so staff can find detailed field forms from one input center. */
function w17_center_ready(string $src):bool {
    return str_contains($src,'SMARTCIKOKO_TAHAP17_INPUT_LINK')
        && str_contains($src,'/laporan-wilayah17.php');
}
function w17_center_patch(string $src):string {
    if(w17_center_ready($src))return $src;
    if(strlen($src)<5000||!str_contains($src,'<div class="category-grid">')
       ||!str_contains($src,'/assets/pusat-laporan.css')
       ||str_contains($src,'SMARTCIKOKO_TAHAP17_INPUT_LINK'))
        throw new RuntimeException('Struktur pusat laporan tidak cocok.');
    $snippet=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP17_INPUT_LINK -->
<?php if(is_file(dirname(__DIR__).'/portal_private/field_reports17.php')): ?>
<div class="alert neutral">
  <strong>Formulir terstruktur RT/RW, LMK, Jumantik, FKDM dan PPSU tersedia.</strong>
  Formulir ini memakai hak akses sesuai peran dan terpisah dari laporan ringkas.
  <a class="btn primary" href="/laporan-wilayah17.php">Buka Formulir Kewilayahan →</a>
</div>
<?php endif;?>
HTML;
    $new=str_replace('<div class="category-grid">',$snippet."\n".'<div class="category-grid">',$src,$count);
    if($count!==1||!w17_center_ready($new))throw new RuntimeException('Tautan tidak dapat dipasang.');
    return $new;
}
function w17_center_apply(string $path,string $private,string $expectedHash):string {
    if(!preg_match('/^[0-9a-f]{64}$/D',$expectedHash)||!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('File input tidak tersedia atau versi tidak valid.');
    $base=realpath($private);$web=realpath(dirname($path));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup input tidak aman.');
    $src=@file_get_contents($path);
    if(!is_string($src)||!hash_equals(hash('sha256',$src),$expectedHash))
        throw new RuntimeException('Pusat laporan berubah. Segarkan halaman pemasangan.');
    $modified=w17_center_patch($src);
    if($src===$modified)return '';
    $dir=$base.'/wilayah17_backups';
    if(is_link($dir))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($dir)&&!@mkdir($dir,0700))throw new RuntimeException('Folder backup gagal dibuat.');
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Folder backup salah.');
    $name=$dir.'/pusat-laporan-before-t17-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($name,$src,LOCK_EX)!==strlen($src))
        throw new RuntimeException('Pencadangan pusat laporan gagal.');
    @chmod($name,0600);
    $tmp=@tempnam($dir,'w17_');
    if(!$tmp)throw new RuntimeException('File sementara tidak tersedia.');
    try{
        if(@file_put_contents($tmp,$modified,LOCK_EX)!==strlen($modified))
            throw new RuntimeException('Penulisan file kerja gagal.');
        $mode=@fileperms($path);@chmod($tmp,$mode!==false?($mode&0777):0644);
        if(!@rename($tmp,$path))throw new RuntimeException('Pusat laporan tidak dapat diperbarui.');
    }finally{if(is_file($tmp))@unlink($tmp);}
    return basename($name);
}

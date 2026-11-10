<?php
declare(strict_types=1);
/** Only scoped, SHA-confirmed edits to existing UI, with private backup. */
function t21_patch(string $src,string $type):string {
    if($type==='dashboard'){
        $marker='<!-- SMARTCIKOKO_TAHAP21_ASSIGNMENTS -->';
        if(str_contains($src,$marker)&&str_contains($src,'tasks21_admin_panel.php')
            &&str_contains($src,'/assets/tasks21-admin.css'))return $src;
        if(strlen($src)<8000||!str_contains($src,'super_admin_metrics()')
            ||substr_count($src,'<section id="pengaturan"')!==1
            ||str_contains($src,$marker))
            throw new RuntimeException('Struktur dashboard berbeda: perubahan otomatis ditolak.');
        $fragment=$marker."\n"
            .'<?php if(is_file(dirname(__DIR__).\'/portal_private/tasks21_admin_panel.php\'))'
            .' require dirname(__DIR__).\'/portal_private/tasks21_admin_panel.php\'; ?>'."\n";
        $out=str_replace('<section id="pengaturan"',$fragment.'<section id="pengaturan"',$src,$n);
        if($n!==1)throw new RuntimeException('Tempat panel belum tersedia.');
        $out=preg_replace('~</head>~i','<link rel="stylesheet" href="/assets/tasks21-admin.css">'."\n".'</head>',$out,1,$m);
        if(!is_string($out)||$m!==1)throw new RuntimeException('Stylesheet tidak dapat disisipkan.');
        return $out;
    }
    if($type==='center'){
        $marker='<!-- SMARTCIKOKO_TAHAP21_INBOX -->';
        if(str_contains($src,$marker)&&str_contains($src,'/penugasan-saya21.php'))return $src;
        if(strlen($src)<7000||!str_contains($src,'<div class="category-grid">')
            ||!str_contains($src,'/assets/pusat-laporan.css')
            ||str_contains($src,$marker))
            throw new RuntimeException('Versi pusat laporan tidak cocok.');
        $fragment=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP21_INBOX -->
<div class="alert neutral">
  <strong>Penugasan Pemeriksa dan Lurah.</strong>
  Lihat tugas internal yang ditetapkan berdasarkan kategori laporan.
  <a class="btn outline" href="/penugasan-saya21.php">Buka Kotak Penugasan Saya →</a>
</div>
HTML;
        $out=str_replace('<div class="category-grid">',$fragment."\n".'<div class="category-grid">',$src,$n);
        if($n!==1)throw new RuntimeException('Tautan pusat laporan tidak dapat ditambahkan.');
        return $out;
    }
    throw new InvalidArgumentException('Jenis pemasangan tidak tersedia.');
}
function t21_apply(string $target,string $private,string $sha,string $type):string{
    if(!in_array($type,['dashboard','center'],true)||!preg_match('/^[a-f0-9]{64}$/D',$sha))
        throw new InvalidArgumentException('Versi pemasangan tidak valid.');
    if(!is_file($target)||is_link($target)||!is_writable($target))
        throw new RuntimeException('File dashboard tidak dapat diperbarui secara aman.');
    $base=realpath($private);$web=realpath(dirname($target));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup harus di luar direktori web.');
    $original=@file_get_contents($target);
    if(!is_string($original)||!hash_equals(hash('sha256',$original),$sha))
        throw new RuntimeException('Versi halaman telah berubah. Muat ulang installer.');
    $modified=t21_patch($original,$type);
    if($modified===$original)return '';
    $dir=$base.'/tasks21_backups';
    if(is_link($dir))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($dir)&&!@mkdir($dir,0700))
        throw new RuntimeException('Folder backup tidak dapat dibuat.');
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Lokasi backup bukan direktori privat.');
    $backup=$dir.'/'.$type.'-before-stage21-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Backup tidak lengkap. Halaman belum diubah.');
    @chmod($backup,0600);
    $tmp=@tempnam($dir,'task21_');
    if(!$tmp)throw new RuntimeException('File sementara privat tidak dapat dibuat.');
    try{
        if(@file_put_contents($tmp,$modified,LOCK_EX)!==strlen($modified))
            throw new RuntimeException('Perubahan file tidak lengkap.');
        $mode=@fileperms($target);@chmod($tmp,$mode!==false?($mode&0777):0644);
        if(!@rename($tmp,$target))throw new RuntimeException('Tidak dapat menerapkan panel secara aman.');
    }finally{if(is_file($tmp))@unlink($tmp);}
    return basename($backup);
}

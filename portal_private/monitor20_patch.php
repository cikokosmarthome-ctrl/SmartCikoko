<?php
declare(strict_types=1);
/** Small idempotent UI additions with private backup and file SHA guard. */
function m20_patch(string $src,string $type):string {
    if($type==='dashboard'){
        $marker='<!-- SMARTCIKOKO_TAHAP20_MONITOR -->';
        if(str_contains($src,$marker)&&str_contains($src,'/assets/monitor20-admin.css')
           &&str_contains($src,'monitor20_admin_panel.php'))return $src;
        if(strlen($src)<8000||!str_contains($src,'super_admin_metrics()')
            ||substr_count($src,'<section id="pengaturan"')!==1
            ||str_contains($src,$marker))
            throw new RuntimeException('Dashboard berbeda dari struktur yang aman untuk ditambah.');
        $segment=$marker."\n"
            .'<?php if(is_file(dirname(__DIR__).\'/portal_private/monitor20_admin_panel.php\'))'
            .' require dirname(__DIR__).\'/portal_private/monitor20_admin_panel.php\'; ?>'."\n";
        $patched=str_replace('<section id="pengaturan"',$segment.'<section id="pengaturan"',$src,$number);
        if($number!==1)throw new RuntimeException('Lokasi panel tidak ditemukan.');
        $patched=preg_replace('~</head>~i','<link rel="stylesheet" href="/assets/monitor20-admin.css">'."\n".'</head>',$patched,1,$head);
        if(!is_string($patched)||$head!==1)throw new RuntimeException('Head dashboard tidak sesuai.');
        return $patched;
    }
    if($type==='center'){
        $marker='<!-- SMARTCIKOKO_TAHAP20_INBOX -->';
        if(str_contains($src,$marker)&&str_contains($src,'/pengingat-saya20.php'))return $src;
        if(strlen($src)<7000||!str_contains($src,'<div class="category-grid">')
            ||!str_contains($src,'/assets/pusat-laporan.css')
            ||str_contains($src,$marker))
            throw new RuntimeException('Versi Pusat Laporan belum dikenali.');
        $segment=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP20_INBOX -->
<div class="alert neutral">
  <strong>Pengingat Tindak Lanjut</strong> — periksa pemberitahuan internal Anda.
  <a class="btn outline" href="/pengingat-saya20.php">Lihat Pengingat Saya →</a>
</div>
HTML;
        $patched=str_replace('<div class="category-grid">',$segment."\n".'<div class="category-grid">',$src,$number);
        if($number!==1)throw new RuntimeException('Lokasi tautan tidak ditemukan.');
        return $patched;
    }
    throw new InvalidArgumentException('Jenis penyisipan tidak dikenal.');
}
function m20_apply(string $path,string $private,string $expectedHash,string $type):string {
    if(!in_array($type,['dashboard','center'],true)
       ||!preg_match('/^[0-9a-f]{64}$/D',$expectedHash))
        throw new InvalidArgumentException('Permintaan perubahan file tidak valid.');
    if(!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('Berkas halaman tidak dapat ditulis atau tidak tersedia.');
    $base=realpath($private);$web=realpath(dirname($path));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Backup harus berada di luar folder website.');
    $src=@file_get_contents($path);
    if(!is_string($src)||!hash_equals(hash('sha256',$src),$expectedHash))
        throw new RuntimeException('File berubah; muat ulang installer tanpa memaksakan perubahan.');
    $patched=m20_patch($src,$type);
    if($patched===$src)return '';
    $folder=$base.'/monitor20_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))
        throw new RuntimeException('Gagal membuat folder backup privat.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)throw new RuntimeException('Lokasi backup tidak sesuai.');
    $backup=$folder.'/'.$type.'-before-stage20-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$src,LOCK_EX)!==strlen($src))
        throw new RuntimeException('Backup tidak lengkap; file belum diubah.');
    @chmod($backup,0600);
    $temp=@tempnam($folder,'m20_');
    if(!$temp)throw new RuntimeException('File kerja privat tidak tersedia.');
    try{
        if(@file_put_contents($temp,$patched,LOCK_EX)!==strlen($patched))
            throw new RuntimeException('Penulisan perubahan tidak lengkap.');
        $mode=@fileperms($path);@chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$path))
            throw new RuntimeException('Tidak dapat memasang panel monitoring.');
    }finally {if(is_file($temp))@unlink($temp);}
    return basename($backup);
}

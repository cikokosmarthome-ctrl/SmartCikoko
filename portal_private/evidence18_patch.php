<?php
declare(strict_types=1);
/** Non-destructive, version-checked integration, backed up outside webroot. */
function e18_patch(string $original,string $type):string {
    if($type==='dashboard'){
        $marker='<!-- SMARTCIKOKO_TAHAP18_ADMIN -->';
        $existing=str_contains($original,$marker)
            && str_contains($original,'/assets/evidence18-admin.css')
            && str_contains($original,'/portal_private/evidence18_admin_panel.php');
        if($existing)return $original;
        if(strlen($original)<8000||!str_contains($original,'super_admin_metrics()')
            ||substr_count($original,'<section id="pengaturan"')!==1
            ||str_contains($original,$marker))
            throw new RuntimeException('Dashboard tidak cocok untuk pemasangan otomatis.');
        $snippet=$marker."\n".'<?php if(is_file(dirname(__DIR__).\'/portal_private/evidence18_admin_panel.php\'))'
            .' require dirname(__DIR__).\'/portal_private/evidence18_admin_panel.php\'; ?>'."\n";
        $updated=str_replace('<section id="pengaturan"',$snippet.'<section id="pengaturan"',$original,$num);
        if($num!==1)throw new RuntimeException('Gagal menambahkan bagian dashboard.');
        $updated=preg_replace('~</head>~i','<link rel="stylesheet" href="/assets/evidence18-admin.css">'."\n".'</head>',$updated,1,$n);
        if(!is_string($updated)||$n!==1)throw new RuntimeException('Gagal menyisipkan stylesheet.');
        return $updated;
    }
    if($type==='center'){
        $marker='<!-- SMARTCIKOKO_TAHAP18_CENTER -->';
        if(str_contains($original,$marker)&&str_contains($original,'/bukti-laporan18.php'))return $original;
        if(strlen($original)<7000||!str_contains($original,'<div class="category-grid">')
            ||!str_contains($original,'/assets/pusat-laporan.css')
            ||str_contains($original,$marker))
            throw new RuntimeException('Pusat laporan tidak cocok untuk pemasangan otomatis.');
        $snippet=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP18_CENTER -->
<div class="alert neutral">
  <strong>Bukti foto kegiatan dan rekap laporan tersedia.</strong>
  Lampiran hanya dibuka bila ClamAV aktif dan akun mempunyai izin.
  <a class="btn outline" href="/bukti-laporan18.php">Lihat / Unggah Bukti Foto →</a>
</div>
HTML;
        $updated=str_replace('<div class="category-grid">',$snippet."\n".'<div class="category-grid">',$original,$num);
        if($num!==1)throw new RuntimeException('Gagal menyisipkan tautan bukti.');
        return $updated;
    }
    throw new InvalidArgumentException('Jenis integrasi tidak dikenal.');
}
function e18_apply(string $target,string $private,string $expected,string $type):string {
    if(!in_array($type,['dashboard','center'],true)||!preg_match('/^[a-f0-9]{64}$/D',$expected))
        throw new InvalidArgumentException('Versi instalasi tidak valid.');
    if(!is_file($target)||is_link($target)||!is_writable($target))
        throw new RuntimeException('File halaman tidak dapat ditulis.');
    $base=realpath($private);$web=realpath(dirname($target));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Backup harus di luar folder website.');
    $src=@file_get_contents($target);
    if(!is_string($src)||!hash_equals(hash('sha256',$src),$expected))
        throw new RuntimeException('File berubah sejak dimuat. Silakan muat ulang installer.');
    $new=e18_patch($src,$type);
    if($new===$src)return '';
    $folder=$base.'/evidence18_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))
        throw new RuntimeException('Folder backup tidak dapat dibuat.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)throw new RuntimeException('Folder backup tidak sesuai.');
    $back=$folder.'/'.$type.'-before-t18-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.php.bak';
    if(@file_put_contents($back,$src,LOCK_EX)!==strlen($src))
        throw new RuntimeException('Gagal membuat backup. Perubahan dibatalkan.');
    @chmod($back,0600);
    $temp=@tempnam($folder,'e18_');
    if(!$temp)throw new RuntimeException('Gagal membuat file sementara.');
    try{
        if(@file_put_contents($temp,$new,LOCK_EX)!==strlen($new))
            throw new RuntimeException('Penulisan file baru tidak lengkap.');
        $mode=@fileperms($target);@chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$target))
            throw new RuntimeException('File tidak dapat diperbarui secara aman.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($back);
}

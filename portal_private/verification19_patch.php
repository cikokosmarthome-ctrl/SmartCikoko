<?php
declare(strict_types=1);
/** Backed-up, idempotent scoped insertion. No bulk replacement of live PHP. */
function v19_patch(string $src,string $type):string{
    if($type==='dashboard'){
        $marker='<!-- SMARTCIKOKO_TAHAP19_VERIFICATION -->';
        if(str_contains($src,$marker)&&str_contains($src,'/assets/verifikasi19-admin.css')
           &&str_contains($src,'verification19_admin_panel.php'))return $src;
        if(strlen($src)<8000||!str_contains($src,'super_admin_metrics()')
            ||substr_count($src,'<section id="pengaturan"')!==1
            ||str_contains($src,$marker))
            throw new RuntimeException('Versi Super Admin Ringkas berbeda; integrasi otomatis ditolak.');
        $part=$marker."\n".'<?php if(is_file(dirname(__DIR__).\'/portal_private/verification19_admin_panel.php\'))'
            .' require dirname(__DIR__).\'/portal_private/verification19_admin_panel.php\'; ?>'."\n";
        $patched=str_replace('<section id="pengaturan"',$part.'<section id="pengaturan"',$src,$n);
        if($n!==1)throw new RuntimeException('Penempatan panel tidak sesuai.');
        $patched=preg_replace('~</head>~i','<link rel="stylesheet" href="/assets/verifikasi19-admin.css">'."\n".'</head>',$patched,1,$m);
        if(!is_string($patched)||$m!==1)throw new RuntimeException('Stylesheet dashboard tidak bisa ditambahkan.');
        return $patched;
    }
    if($type==='center'){
        $marker='<!-- SMARTCIKOKO_TAHAP19_REVIEWER_LINK -->';
        if(str_contains($src,$marker)&&str_contains($src,'/verifikasi-laporan19.php'))return $src;
        if(strlen($src)<7000||!str_contains($src,'<div class="category-grid">')
            ||!str_contains($src,'/assets/pusat-laporan.css')
            ||str_contains($src,$marker))
            throw new RuntimeException('Pusat Laporan tidak cocok untuk penambahan tautan.');
        $entry=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP19_REVIEWER_LINK -->
<?php if(is_file(dirname(__DIR__).'/portal_private/verification19.php')):
require_once dirname(__DIR__).'/portal_private/verification19.php';
if(isset($u) && is_array($u) && v19_actor_allowed($u)): ?>
<div class="alert neutral"><strong>Pemeriksaan Berjenjang Tahap 19</strong>
Petugas pemeriksa dan Lurah menggunakan antrean yang sama sesuai kewenangan.
<a class="btn outline" href="/verifikasi-laporan19.php">Buka Verifikasi Laporan →</a>
</div>
<?php endif; endif;?>
HTML;
        $patched=str_replace('<div class="category-grid">',$entry."\n".'<div class="category-grid">',$src,$n);
        if($n!==1)throw new RuntimeException('Penempatan menu pemeriksa tidak sesuai.');
        return $patched;
    }
    throw new InvalidArgumentException('Jenis perubahan tidak dikenal.');
}
function v19_apply(string $path,string $private,string $hash,string $type):string{
    if(!in_array($type,['dashboard','center'],true)||!preg_match('/^[a-f0-9]{64}$/D',$hash))
        throw new InvalidArgumentException('Versi pemasangan salah.');
    if(!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('File halaman tidak tersedia atau tidak dapat ditulis.');
    $base=realpath($private);$web=realpath(dirname($path));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup wajib terpisah dari webroot.');
    $src=@file_get_contents($path);
    if(!is_string($src)||!hash_equals(hash('sha256',$src),$hash))
        throw new RuntimeException('File berubah sejak halaman installer dibuka. Muat ulang.');
    $patched=v19_patch($src,$type);
    if($src===$patched)return '';
    $dir=$base.'/verification19_backups';
    if(is_link($dir))throw new RuntimeException('Folder backup bertipe symlink ditolak.');
    if(!is_dir($dir)&&!@mkdir($dir,0700))throw new RuntimeException('Tidak dapat membuat backup privat.');
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Lokasi backup salah.');
    $name=$dir.'/'.$type.'-before-stage19-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.php.bak';
    if(@file_put_contents($name,$src,LOCK_EX)!==strlen($src))
        throw new RuntimeException('Backup gagal. Berkas tidak diubah.');
    @chmod($name,0600);
    $tmp=@tempnam($dir,'v19_');
    if(!$tmp)throw new RuntimeException('Tidak dapat membuat file sementara.');
    try{
        if(@file_put_contents($tmp,$patched,LOCK_EX)!==strlen($patched))
            throw new RuntimeException('File perubahan belum lengkap.');
        $mode=@fileperms($path);@chmod($tmp,$mode!==false?($mode&0777):0644);
        if(!@rename($tmp,$path))
            throw new RuntimeException('Gagal menerapkan perubahan aman.');
    }finally{if(is_file($tmp))@unlink($tmp);}
    return basename($name);
}

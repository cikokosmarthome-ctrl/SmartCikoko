<?php
declare(strict_types=1);
/**
 * Restore a versioned CSS link without rewriting original routes/auth.
 * All helpers are safe for isolated CLI tests. No database writes.
 */
function b14_home_css_ok(string $css):bool {
    return strlen($css)>8000
        && str_contains($css,'SMARTCIKOKO_BERANDA_FIX_V2_20261110')
        && str_contains($css,'.header nav')
        && str_contains($css,'.hero .hero-grid')
        && str_contains($css,'.hero .hero-visual')
        && substr_count($css,'{')===substr_count($css,'}');
}
function b14_home_target_ok(string $html):bool {
    return strlen($html)>3000
        && stripos($html,'</head>')!==false
        && str_contains($html,'hero-grid')
        && str_contains($html,'class="header"')
        && str_contains($html,'hero-visual')
        && str_contains($html,'/assets/style.css');
}
function b14_home_inject(string $html,string $hash):string {
    if(!b14_home_target_ok($html))
        throw new RuntimeException('Struktur index.php berbeda dari versi yang dapat dipasang otomatis.');
    if(!preg_match('/^[a-f0-9]{12}$/D',$hash))
        throw new InvalidArgumentException('Versi stylesheet tidak valid.');
    $marker='<!-- SMARTCIKOKO_HOMEPAGE_V2 -->';
    $tag=$marker."\n".'<link rel="stylesheet" href="/assets/beranda-fix.css?v='.$hash.'" media="all">'."\n";
    if(str_contains($html,$marker)){
        $changed=preg_replace(
            '~<!-- SMARTCIKOKO_HOMEPAGE_V2 -->\s*<link\b[^>]*href="/assets/beranda-fix\.css[^"]*"[^>]*>\s*~i',
            $tag,$html,1,$count
        );
        if(!is_string($changed)||$count!==1)
            throw new RuntimeException('Penanda V2 tidak dapat diperbarui. Tidak ada file yang diubah.');
        return $changed;
    }
    $updated=preg_replace('~</head\s*>~i', $tag.'</head>', $html, 1, $count);
    if(!is_string($updated) || $count!==1)
        throw new RuntimeException('Tag penutup head tidak dapat ditemukan.');
    return $updated;
}
function b14_home_write_atomically(string $original,string $patched,string $target,string $private):string {
    if($original===$patched)return '';
    if(!is_file($target)||is_link($target)||!is_writable($target))
        throw new RuntimeException('File index.php tidak dapat diperbarui oleh PHP; gunakan File Manager untuk penambahan satu baris CSS.');
    $base=realpath($private);
    $web=realpath(dirname($target));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup harus berada di luar public_html.');
    $directory=$base.'/beranda_backups';
    if(is_link($directory))throw new RuntimeException('Folder backup symlink ditolak.');
    if(!is_dir($directory)&&!@mkdir($directory,0700))
        throw new RuntimeException('Folder backup tidak dapat dibuat.');
    @chmod($directory,0700);
    if(realpath($directory)!==$base.'/beranda_backups')
        throw new RuntimeException('Folder backup tidak sesuai.');
    $suffix=date('Ymd-His').'-'.bin2hex(random_bytes(4));
    $backup=$directory.'/index-before-v2-'.$suffix.'.php.bak';
    if(file_put_contents($backup,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Pencadangan index.php gagal; perubahan dibatalkan.');
    @chmod($backup,0600);
    $temp=@tempnam($directory,'stage14_');
    if($temp===false)throw new RuntimeException('File kerja privat tidak dapat dibuat.');
    try{
        if(file_put_contents($temp,$patched,LOCK_EX)!==strlen($patched))
            throw new RuntimeException('Penulisan file kerja tidak lengkap.');
        $mode=@fileperms($target);
        @chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$target))
            throw new RuntimeException('Tidak dapat mengganti index.php dengan aman. File asli tidak diubah.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($backup);
}

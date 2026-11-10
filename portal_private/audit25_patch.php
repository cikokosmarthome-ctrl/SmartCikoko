<?php
declare(strict_types=1);
/** Stage25: SHA-confirmed, backup-first addition to ONE existing dashboard. */
function a25_patch(string $original):string{
    $marker='<!-- SMARTCIKOKO_TAHAP25_AUDIT_PANEL -->';
    if(str_contains($original,$marker)
        &&str_contains($original,'audit25_admin_panel.php')
        &&str_contains($original,'/assets/audit25-admin.css'))
        return $original;
    $anchor='<section id="pengaturan" class="section">';
    if(strlen($original)<7000||substr_count($original,$anchor)!==1
        ||!str_contains($original,'super_admin_metrics()')||str_contains($original,$marker))
        throw new RuntimeException('Struktur Super Admin bukan versi yang didukung.');
    $segment=$marker."\n"
        .'<?php if(is_file(dirname(__DIR__).\'/portal_private/audit25_admin_panel.php\'))'
        .' require dirname(__DIR__).\'/portal_private/audit25_admin_panel.php\'; ?>'."\n";
    $output=str_replace($anchor,$segment.$anchor,$original,$count);
    if($count!==1)throw new RuntimeException('Lokasi panel tidak tersedia.');
    $output=preg_replace('~</head>~i',
        '<link rel="stylesheet" href="/assets/audit25-admin.css">'."\n".'</head>',
        $output,1,$headCount);
    if(!is_string($output)||$headCount!==1)
        throw new RuntimeException('Tautan stylesheet tidak dapat dipasang.');
    return $output;
}
function a25_apply(string $file,string $private,string $hash):string{
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))
        throw new InvalidArgumentException('Checksum file tidak valid.');
    if(!is_file($file)||is_link($file)||!is_writable($file))
        throw new RuntimeException('Super Admin tidak dapat diperbarui.');
    $privateRoot=realpath($private);$webRoot=realpath(dirname($file));
    if($privateRoot===false||$webRoot===false
        ||$privateRoot===$webRoot||str_starts_with($privateRoot,$webRoot.'/'))
        throw new RuntimeException('Backup harus berada di luar public_html.');
    $old=@file_get_contents($file);
    if(!is_string($old)||!hash_equals(hash('sha256',$old),$hash))
        throw new RuntimeException('File di hosting telah berubah; muat ulang installer.');
    $new=a25_patch($old);
    if($old===$new)return '';
    $folder=$privateRoot.'/audit25_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))
        throw new RuntimeException('Direktori backup tidak dapat dibuat.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)
        throw new RuntimeException('Folder backup bukan direktori privat yang diharapkan.');
    $backup=$folder.'/superadmin-before-audit25-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$old,LOCK_EX)!==strlen($old))
        throw new RuntimeException('Backup tidak lengkap; halaman lama tidak berubah.');
    @chmod($backup,0600);
    $temp=@tempnam(dirname($file),'a25_');
    if($temp===false)throw new RuntimeException('Berkas sementara gagal dibuat.');
    try{
        if(@file_put_contents($temp,$new,LOCK_EX)!==strlen($new))
            throw new RuntimeException('Kode penambahan panel tidak lengkap.');
        $permissions=@fileperms($file);
        @chmod($temp,$permissions!==false?($permissions&0777):0644);
        if(!@rename($temp,$file))
            throw new RuntimeException('Tidak dapat memasang panel baru secara atomik.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($backup);
}

<?php
declare(strict_types=1);
/** Stage26: private backup and exact SHA before one-panel insertion. */
function u26_patch(string $old):string{
    $marker='<!-- SMARTCIKOKO_STAGE26_UAT_PANEL -->';
    if(str_contains($old,$marker)
        &&str_contains($old,'uat26_admin_panel.php')
        &&str_contains($old,'/assets/uat26-admin.css'))return $old;
    $anchor='<section id="pengaturan" class="section">';
    if(strlen($old)<7000||substr_count($old,$anchor)!==1
        ||!str_contains($old,'super_admin_metrics()')||str_contains($old,$marker))
        throw new RuntimeException('Struktur Super Admin tidak cocok; instalasi panel ditolak.');
    $segment=$marker."\n"
        .'<?php if(is_file(dirname(__DIR__).\'/portal_private/uat26_admin_panel.php\'))'
        .' require dirname(__DIR__).\'/portal_private/uat26_admin_panel.php\'; ?>'."\n";
    $new=str_replace($anchor,$segment.$anchor,$old,$inserted);
    if($inserted!==1)throw new RuntimeException('Tempat panel tidak ditemukan.');
    $new=preg_replace('~</head>~i',
        '<link rel="stylesheet" href="/assets/uat26-admin.css">'."\n".'</head>',
        $new,1,$head);
    if(!is_string($new)||$head!==1)
        throw new RuntimeException('Style panel tidak dapat terhubung.');
    return $new;
}
function u26_apply(string $path,string $private,string $expectedSha):string{
    if(!preg_match('/^[a-f0-9]{64}$/D',$expectedSha))
        throw new InvalidArgumentException('Checksum pemasangan tidak valid.');
    if(!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('Super Admin tidak tersedia atau tidak dapat ditulis.');
    $priv=realpath($private);$web=realpath(dirname($path));
    if($priv===false||$web===false||$priv===$web||str_starts_with($priv,$web.'/'))
        throw new RuntimeException('Backup harus di luar folder publik.');
    $original=@file_get_contents($path);
    if(!is_string($original)||!hash_equals(hash('sha256',$original),$expectedSha))
        throw new RuntimeException('Versi halaman berubah; muat ulang installer.');
    $updated=u26_patch($original);
    if($updated===$original)return '';
    $folder=$priv.'/uat26_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))
        throw new RuntimeException('Folder backup gagal dibuat.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)
        throw new RuntimeException('Lokasi backup tidak valid.');
    $name='superadmin-before-uat26-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($folder.'/'.$name,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Backup tidak lengkap, halaman tidak diubah.');
    @chmod($folder.'/'.$name,0600);
    $temp=@tempnam(dirname($path),'uat26_');
    if(!$temp)throw new RuntimeException('File sementara tidak tersedia.');
    try{
        if(@file_put_contents($temp,$updated,LOCK_EX)!==strlen($updated))
            throw new RuntimeException('Kode panel tidak lengkap.');
        $mode=@fileperms($path);@chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$path))
            throw new RuntimeException('Integrasi panel belum dapat diselesaikan.');
    }finally{if(is_file($temp))@unlink($temp);}
    return $name;
}

<?php
declare(strict_types=1);
/** Add ONE small navigation entry to both existing workspaces. No dashboard
 * rewrite, no modification to login, MFA, legacy table or report workflows.
 * Requires explicit SHA confirmation and stores an exact private backup.
 */
function h23_patch(string $original,string $type):string {
    if($type==='dashboard'){
        $tag='<!-- SMARTCIKOKO_TAHAP23_OPERATIONS_LINK -->';
        if(str_contains($original,$tag)&&str_contains($original,'/pusat-operasional23.php'))return $original;
        $needle='<nav><a href="#ringkasan">Ringkasan</a>';
        if(strlen($original)<7000||substr_count($original,$needle)!==1
            ||!str_contains($original,'super_admin_metrics()')||str_contains($original,$tag))
            throw new RuntimeException('Struktur Super Admin Ringkas tidak dikenali. Tautan tidak disisipkan.');
        return str_replace($needle,$tag."\n".'<nav><a href="/pusat-operasional23.php">Pusat Operasional</a><a href="#ringkasan">Ringkasan</a>',$original);
    }
    if($type==='center'){
        $tag='<!-- SMARTCIKOKO_TAHAP23_OPERATIONS_LINK -->';
        if(str_contains($original,$tag)&&str_contains($original,'/pusat-operasional23.php'))return $original;
        $needle='<nav><a href="/">Beranda</a>';
        if(strlen($original)<7000||substr_count($original,$needle)!==1
            ||!str_contains($original,'/assets/pusat-laporan.css')||str_contains($original,$tag))
            throw new RuntimeException('Struktur Pusat Laporan tidak dikenali. Tautan tidak disisipkan.');
        return str_replace($needle,$tag."\n".'<nav><a href="/">Beranda</a><a href="/pusat-operasional23.php">Pusat Operasional</a>',$original);
    }
    throw new InvalidArgumentException('Jenis integrasi tidak dikenal.');
}
function h23_apply(string $target,string $private,string $expectedHash,string $type):string {
    if(!in_array($type,['dashboard','center'],true)
       ||preg_match('/^[a-f0-9]{64}$/D',$expectedHash)!==1)
        throw new InvalidArgumentException('Berkas atau versi tidak valid.');
    if(!is_file($target)||is_link($target)||!is_writable($target))
        throw new RuntimeException('Halaman tidak tersedia atau tidak dapat diperbarui.');
    $privateRoot=realpath($private);$webRoot=realpath(dirname($target));
    if($privateRoot===false||$webRoot===false||$privateRoot===$webRoot
       ||str_starts_with($privateRoot,$webRoot.'/'))
        throw new RuntimeException('Backup harus disimpan di luar public_html.');
    $original=@file_get_contents($target);
    if(!is_string($original)||!hash_equals(hash('sha256',$original),$expectedHash))
        throw new RuntimeException('Halaman berubah. Muat ulang installer tanpa menimpa perubahan orang lain.');
    $updated=h23_patch($original,$type);
    if($updated===$original)return '';
    $folder=$privateRoot.'/hub23_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))
        throw new RuntimeException('Folder backup tidak dapat dibuat.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)throw new RuntimeException('Folder backup tidak valid.');
    $backup=$folder.'/'.$type.'-before-stage23-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Gagal mencadangkan file asli.');
    @chmod($backup,0600);
    $temp=@tempnam($folder,'h23_');
    if(!$temp)throw new RuntimeException('File kerja sementara tidak dapat dibuat.');
    try{
        if(@file_put_contents($temp,$updated,LOCK_EX)!==strlen($updated))
            throw new RuntimeException('Penulisan tidak lengkap. Halaman lama tidak diubah.');
        $perms=@fileperms($target);
        @chmod($temp,$perms!==false?($perms&0777):0644);
        if(!@rename($temp,$target))throw new RuntimeException('Tautan halaman gagal diperbarui.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($backup);
}

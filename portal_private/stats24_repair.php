<?php
declare(strict_types=1);
/**
 * Stage24 verified recovery from currently deployed Stage23 V2. Only the two
 * known Stage23 code files, NOT databases, login, MFA or other modules.
 */
function s24_files(string $private,string $web):array {
    return [
        'engine'=>[
            'target'=>$private.'/hub23.php',
            'candidate'=>$private.'/recovery24/hub23.php',
            'old'=>'f1a11681a969992a0c8e4fabaea327e3e27a50e3',
            'new'=>'ed723864bc281554e99ccc30cdd83e7a73e3301e',
        ],
        'page'=>[
            'target'=>$web.'/pusat-operasional23.php',
            'candidate'=>$private.'/recovery24/pusat-operasional23.php',
            'old'=>'a88d9ad688c798bd1c9a180fd281118017513c41',
            'new'=>'61fb69da867fc21983aeecaf27ca707287652ceb',
        ],
    ];
}
function s24_blob(string $value):string {
    return sha1('blob '.strlen($value)."\0".$value);
}
function s24_state(array $f):array {
    if(!is_file($f['target'])||is_link($f['target'])
        ||!is_file($f['candidate'])||is_link($f['candidate']))
        return ['status'=>'missing','sha'=>''];
    $candidate=@file_get_contents($f['candidate']);
    $current=@file_get_contents($f['target']);
    if(!is_string($candidate)||!is_string($current)
        ||!hash_equals($f['new'],s24_blob($candidate)))
        return ['status'=>'invalid_package','sha'=>''];
    $blob=s24_blob($current);
    if(hash_equals($f['new'],$blob))
        return ['status'=>'ready','sha'=>hash('sha256',$current)];
    if(hash_equals($f['old'],$blob))
        return ['status'=>'repairable','sha'=>hash('sha256',$current)];
    return ['status'=>'modified','sha'=>''];
}
function s24_apply(array $f,string $private,string $expectedSha,string $name):string {
    if(!in_array($name,['engine','page'],true)
        ||!preg_match('/^[a-f0-9]{64}$/D',$expectedSha))
        throw new InvalidArgumentException('Permintaan pemulihan tidak valid.');
    $st=s24_state($f);
    if($st['status']==='ready')return '';
    if($st['status']!=='repairable'||!hash_equals($st['sha'],$expectedSha))
        throw new RuntimeException('Versi berkas telah berubah atau tidak dikenal. Tidak diubah.');
    if(!is_writable($f['target']))
        throw new RuntimeException('File sumber tidak dapat ditulis.');
    $base=realpath($private);
    if($base===false||is_link($base))throw new RuntimeException('Direktori privat tidak aman.');
    $old=@file_get_contents($f['target']);$new=@file_get_contents($f['candidate']);
    if(!is_string($old)||!is_string($new)
        ||!hash_equals(hash('sha256',$old),$expectedSha)
        ||!hash_equals(s24_blob($new),$f['new']))
        throw new RuntimeException('Checksum sumber/kandidat tidak cocok.');
    $dir=$base.'/stats24_backups';
    if(is_link($dir))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($dir)&&!@mkdir($dir,0700))
        throw new RuntimeException('Backup privat tidak dapat disiapkan.');
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Folder backup tidak valid.');
    $backup=$dir.'/'.$name.'-before-stage24-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.bak';
    if(@file_put_contents($backup,$old,LOCK_EX)!==strlen($old))
        throw new RuntimeException('Pencadangan file asli gagal. Tidak diubah.');
    @chmod($backup,0600);
    // Same filesystem as destination: atomic rename preserves a working file.
    $tmp=@tempnam(dirname($f['target']),'s24_');
    if($tmp===false)throw new RuntimeException('File kerja tidak bisa dibuat.');
    try{
        if(@file_put_contents($tmp,$new,LOCK_EX)!==strlen($new))
            throw new RuntimeException('Penulisan kode baru tidak lengkap.');
        $mode=@fileperms($f['target']);
        @chmod($tmp,$mode!==false?($mode&0777):0644);
        if(!@rename($tmp,$f['target']))
            throw new RuntimeException('Penggantian atomik tidak dapat diselesaikan.');
    }finally{if(is_file($tmp))@unlink($tmp);}
    return basename($backup);
}
function s24_install(string $private,string $web,array $expected):array {
    $files=s24_files($private,$web);$checks=[];
    // Check both files before replacing either one. Refuse customization.
    foreach($files as $name=>$file){
        $st=s24_state($file);
        if(!in_array($st['status'],['ready','repairable'],true))
            throw new RuntimeException('Berkas '.$name.' tidak cocok dengan versi Tahap23 V2.');
        if($st['status']==='repairable'
           &&!hash_equals($st['sha'],(string)($expected[$name]??'')))
            throw new RuntimeException('Checksum '.$name.' berubah sejak halaman installer dibuka.');
        $checks[$name]=$st;
    }
    $backups=[];
    foreach($files as $name=>$file){
        if($checks[$name]['status']==='repairable')
            $backups[$name]=s24_apply($file,$private,$checks[$name]['sha'],$name);
    }
    return $backups;
}

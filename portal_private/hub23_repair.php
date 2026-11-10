<?php
declare(strict_types=1);
/**
 * Recover two known Stage23 files ONLY. User's Stage23 archive produced exact
 * initial Git blob hashes. Refuse unknown/hot-edited files.
 */
function recover23_files(string $private,string $public):array{
    $spec=[
        'hub'=>[
            'target'=>$private.'/hub23.php',
            'candidate'=>$private.'/recovery23/hub23.php',
            'original'=>'ab836ce385ff599f2b0e7785f62f3a1af5cb0dc7',
            'fixed'=>'f1a11681a969992a0c8e4fabaea327e3e27a50e3',
        ],
        'page'=>[
            'target'=>$public.'/pusat-operasional23.php',
            'candidate'=>$private.'/recovery23/pusat-operasional23.php',
            'original'=>'bff61805b7f31dbb88c8fae9e3a24cfa1ad7d9ff',
            'fixed'=>'a88d9ad688c798bd1c9a180fd281118017513c41',
        ],
    ];
    return $spec;
}
function recover23_blob(string $body):string{
    return sha1('blob '.strlen($body)."\0".$body);
}
function recover23_state(array $spec):array{
    if(!is_file($spec['target'])||is_link($spec['target'])
       ||!is_file($spec['candidate'])||is_link($spec['candidate']))
        return ['status'=>'missing','hash'=>''];
    $current=@file_get_contents($spec['target']);
    $candidate=@file_get_contents($spec['candidate']);
    if(!is_string($current)||!is_string($candidate)
        ||!hash_equals($spec['fixed'],recover23_blob($candidate)))
        return ['status'=>'invalid_candidate','hash'=>''];
    $blob=recover23_blob($current);
    if(hash_equals($spec['fixed'],$blob))
        return ['status'=>'ready','hash'=>hash('sha256',$current)];
    if(hash_equals($spec['original'],$blob))
        return ['status'=>'repairable','hash'=>hash('sha256',$current)];
    return ['status'=>'modified','hash'=>''];
}
/** Original files are protected by SHA256 form token, then privately backed up. */
function recover23_apply(array $spec,string $private,string $expectedHash,string $name):string{
    if(!in_array($name,['hub','page'],true)
       ||preg_match('/^[a-f0-9]{64}$/D',$expectedHash)!==1)
        throw new InvalidArgumentException('Permintaan pemulihan tidak valid.');
    $state=recover23_state($spec);
    if($state['status']==='ready')return '';
    if($state['status']!=='repairable'||!hash_equals($state['hash'],$expectedHash))
        throw new RuntimeException('Versi file tidak dikenal atau sudah berubah. Tidak ada file yang ditimpa.');
    $old=@file_get_contents($spec['target']);
    $new=@file_get_contents($spec['candidate']);
    if(!is_string($old)||!is_string($new)
       ||!hash_equals($expectedHash,hash('sha256',$old))
       ||!hash_equals($spec['fixed'],recover23_blob($new)))
        throw new RuntimeException('Versi file berubah sebelum penggantian.');
    $privatePath=realpath($private);
    if(!$privatePath||is_link($privatePath))throw new RuntimeException('Folder privat tidak valid.');
    $dir=$privatePath.'/hub23_repair_backups';
    if(is_link($dir))throw new RuntimeException('Lokasi backup tidak aman.');
    if(!is_dir($dir)&&!@mkdir($dir,0700))throw new RuntimeException('Gagal membuat folder backup.');
    @chmod($dir,0700);
    if(realpath($dir)!==$dir)throw new RuntimeException('Folder backup tidak valid.');
    $backup=$dir.'/'.$name.'-before-recovery23-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$old,LOCK_EX)!==strlen($old))
        throw new RuntimeException('Backup gagal; tidak ada kode diubah.');
    @chmod($backup,0600);
    $temp=@tempnam(dirname($spec['target']),'recovery23_');
    if(!$temp)throw new RuntimeException('File sementara tidak dapat dibuat.');
    try{
        if(@file_put_contents($temp,$new,LOCK_EX)!==strlen($new))
            throw new RuntimeException('File kerja pemulihan tidak lengkap.');
        $mode=@fileperms($spec['target']);
        @chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$spec['target']))
            throw new RuntimeException('Penggantian atomik tidak dapat dilakukan.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($backup);
}
function recover23_all(string $private,string $public,array $hashes):array{
    $files=recover23_files($private,$public);$status=[];
    // Make a preflight PASS for every file before mutating either.
    foreach($files as $name=>$spec){
        $s=recover23_state($spec);
        if(!in_array($s['status'],['ready','repairable'],true))
            throw new RuntimeException('File '.$name.' tidak cocok dengan versi Tahap 23 yang didukung. Proses ditahan.');
        if($s['status']==='repairable'&&!hash_equals($s['hash'],(string)($hashes[$name]??'')))
            throw new RuntimeException('File '.$name.' berubah; muat ulang halaman.');
        $status[$name]=$s;
    }
    $backups=[];
    foreach($files as $name=>$spec)
        if($status[$name]['status']==='repairable')
            $backups[$name]=recover23_apply($spec,$private,(string)$hashes[$name],$name);
    return $backups;
}

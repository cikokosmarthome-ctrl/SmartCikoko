<?php
declare(strict_types=1);
/**
 * Add mandatory Stage22 guard to old Stage19 state-changing actions, queue and
 * detail. Scope: 1 existing private PHP file + optional one-page dashboard
 * and staff intake; all backed up and patched ONLY by authenticated installer.
 */
function r22_patch(string $source,string $type):string{
    if($type==='verification'){
        $marker='/* SMARTCIKOKO_TAHAP22_STRICT_GUARD */';
        if(str_contains($source,$marker)
           &&str_contains($source,"r22_gate(\$actor,\$source,\$category,'pemeriksa')")
           &&str_contains($source,'r22_can_view('))return $source;
        $need=[
            "declare(strict_types=1);\n",
            "return v19_reviewer(\$actor,\$source,\$category)&&\$state==='menunggu';",
            "if(!v19_lurah(\$actor)||\$state!=='lolos_periksa'||!\$check)return false;",
            "if(!v19_reviewer(\$actor,\$key,(string)\$row['category'])&&!v19_lurah(\$actor))continue;",
            "if(!v19_reviewer(\$actor,\$source,(string)\$r['category'])&&!v19_lurah(\$actor))return null;",
        ];
        if(strlen($source)<9000||str_contains($source,$marker))throw new RuntimeException('File Stage19 berbeda. Pembaruan otomatis ditolak.');
        foreach($need as $anchor)if(substr_count($source,$anchor)!==1)
            throw new RuntimeException('Struktur verifikasi lama berbeda. Jangan memaksakan penggantian.');
        $out=$source;
        $out=str_replace("declare(strict_types=1);\n",
            "declare(strict_types=1);\n".$marker."\nrequire_once __DIR__.'/review22.php';\n",$out,$n);
        if($n!==1)throw new RuntimeException('Pengaman tidak dapat dimuat.');
        $out=str_replace(
            "return v19_reviewer(\$actor,\$source,\$category)&&\$state==='menunggu';",
            "return v19_reviewer(\$actor,\$source,\$category)&&\$state==='menunggu'\n            &&r22_gate(\$actor,\$source,\$category,'pemeriksa');",$out,$n);
        if($n!==1)throw new RuntimeException('Pengaman pemeriksa gagal ditambahkan.');
        $out=str_replace(
            "if(!v19_lurah(\$actor)||\$state!=='lolos_periksa'||!\$check)return false;",
            "if(!v19_lurah(\$actor)||\$state!=='lolos_periksa'||!\$check)return false;\n        if(!r22_gate(\$actor,\$source,\$category,'lurah'))return false;",$out,$n);
        if($n!==1)throw new RuntimeException('Pengaman Lurah gagal ditambahkan.');
        $out=str_replace(
            "if(!v19_reviewer(\$actor,\$key,(string)\$row['category'])&&!v19_lurah(\$actor))continue;",
            "if(!v19_reviewer(\$actor,\$key,(string)\$row['category'])&&!v19_lurah(\$actor))continue;\n                if(!r22_can_view(\$actor,\$key,(string)\$row['category']))continue;",$out,$n);
        if($n!==1)throw new RuntimeException('Filter antrean gagal ditambahkan.');
        $out=str_replace(
            "if(!v19_reviewer(\$actor,\$source,(string)\$r['category'])&&!v19_lurah(\$actor))return null;",
            "if(!v19_reviewer(\$actor,\$source,(string)\$r['category'])&&!v19_lurah(\$actor))return null;\n    if(!r22_can_view(\$actor,\$source,(string)\$r['category']))return null;",$out,$n);
        if($n!==1)throw new RuntimeException('Filter rincian laporan gagal ditambahkan.');
        // IMPORTANT: re-submitted versions must start at first-stage review
        // even if prior version was already checked.
        $old="if(\$status==='perlu_perbaikan'\n       &&(\$report['status']??'')==='diajukan'";
        $new="if(\$status!=='menunggu'\n       &&(\$report['status']??'')==='diajukan'";
        if(substr_count($out,$old)!==1)throw new RuntimeException('Versi logika perbaikan Tahap19 tidak dikenal.');
        $out=str_replace($old,$new,$out,$n);
        if($n!==1)throw new RuntimeException('Reset versi tidak dapat ditambahkan.');
        return $out;
    }
    if($type==='dashboard'){
        $marker='<!-- SMARTCIKOKO_TAHAP22_POLICY_PANEL -->';
        if(str_contains($source,$marker)&&str_contains($source,'review22_admin_panel.php')
           &&str_contains($source,'/assets/review22-admin.css'))return $source;
        if(strlen($source)<8000||str_contains($source,$marker)
            ||!str_contains($source,'super_admin_metrics()')
            ||substr_count($source,'<section id="pengaturan"')!==1)
            throw new RuntimeException('Super Admin Ringkas berbeda. Tidak akan ditimpa.');
        $include=$marker."\n"
            .'<?php if(is_file(dirname(__DIR__).\'/portal_private/review22_admin_panel.php\'))'
            .' require dirname(__DIR__).\'/portal_private/review22_admin_panel.php\'; ?>'."\n";
        $out=str_replace('<section id="pengaturan"',$include.'<section id="pengaturan"',$source,$n);
        if($n!==1)throw new RuntimeException('Panel tidak dapat disisipkan.');
        $out=preg_replace('~</head>~i','<link rel="stylesheet" href="/assets/review22-admin.css">'."\n".'</head>',$out,1,$m);
        if(!is_string($out)||$m!==1)throw new RuntimeException('Stylesheet tidak dapat disisipkan.');
        return $out;
    }
    if($type==='center'){
        $marker='<!-- SMARTCIKOKO_TAHAP22_DEPUTY_INBOX -->';
        if(str_contains($source,$marker)&&str_contains($source,'/pengganti-saya22.php'))return $source;
        if(strlen($source)<7000||!str_contains($source,'/assets/pusat-laporan.css')
            ||substr_count($source,'<div class="category-grid">')!==1||str_contains($source,$marker))
            throw new RuntimeException('Pusat Input Laporan berbeda.');
        $insert=<<<'HTML'
<!-- SMARTCIKOKO_TAHAP22_DEPUTY_INBOX -->
<div class="alert neutral">
  <strong>Pemeriksaan Pengganti Tahap 22</strong>
  Tugas pengganti sementara hanya mengikuti kewenangan akun Anda.
  <a class="btn outline" href="/pengganti-saya22.php">Lihat Tugas Pengganti →</a>
</div>
HTML;
        return str_replace('<div class="category-grid">',$insert."\n".'<div class="category-grid">',$source);
    }
    throw new InvalidArgumentException('Jenis halaman tidak dikenali.');
}
function r22_apply(string $path,string $private,string $hash,string $type):string{
    if(!in_array($type,['verification','dashboard','center'],true)
       ||!preg_match('/^[a-f0-9]{64}$/D',$hash))
        throw new InvalidArgumentException('Versi pemasangan tidak valid.');
    if(!is_file($path)||is_link($path)||!is_writable($path))
        throw new RuntimeException('Berkas tidak dapat dimodifikasi secara aman.');
    $base=realpath($private);$web=realpath(dirname($path));
    if($base===false||$web===false||$base===$web||str_starts_with($base,$web.'/'))
        throw new RuntimeException('Lokasi backup tidak boleh berada dalam public_html.');
    $original=@file_get_contents($path);
    if(!is_string($original)||!hash_equals(hash('sha256',$original),$hash))
        throw new RuntimeException('File berubah sejak installer dibuka. Muat ulang.');
    $patched=r22_patch($original,$type);
    if($patched===$original)return '';
    $folder=$base.'/review22_backups';
    if(is_link($folder))throw new RuntimeException('Folder backup tidak aman.');
    if(!is_dir($folder)&&!@mkdir($folder,0700))throw new RuntimeException('Gagal membuat folder backup.');
    @chmod($folder,0700);
    if(realpath($folder)!==$folder)throw new RuntimeException('Folder backup tidak sesuai.');
    $backup=$folder.'/'.$type.'-before-stage22-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.bak';
    if(@file_put_contents($backup,$original,LOCK_EX)!==strlen($original))
        throw new RuntimeException('Backup tidak lengkap, berkas belum diubah.');
    @chmod($backup,0600);
    $temp=@tempnam($folder,'r22_');
    if(!$temp)throw new RuntimeException('Gagal membuat file kerja privat.');
    try{
        if(@file_put_contents($temp,$patched,LOCK_EX)!==strlen($patched))
            throw new RuntimeException('File perubahan tidak lengkap.');
        $mode=@fileperms($path);@chmod($temp,$mode!==false?($mode&0777):0644);
        if(!@rename($temp,$path))throw new RuntimeException('Penambahan pengaman gagal.');
    }finally{if(is_file($temp))@unlink($temp);}
    return basename($backup);
}

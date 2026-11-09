<?php
declare(strict_types=1);
/**
 * Tahap 14 — pemeriksaan defensif yang hanya membaca konfigurasi dan metadata.
 * Tidak membuka berkas pribadi, tidak mengubah akun/DB, tidak melakukan scan malware palsu.
 */
function t14_php_ok(int $version):bool { return $version>=80300; }
function t14_upload_size_ok(int $size):bool { return $size>0 && $size<=5*1024*1024; }
function t14_safe_stored_filename(string $name):bool {
    return preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',$name)===1;
}
function t14_restricted_files(string $webroot):array {
    $targets=['.env','config.php','error_log','debug.log','backup.sql',
              'database.sql','site-backup.zip','data-backup.zip'];
    $found=[];
    foreach($targets as $target)if(is_file($webroot.'/'.$target))$found[]=$target;
    return $found;
}
function t14_private_dir(string $folder,string $base):array {
    $source=realpath($base);
    $path=$base.'/'.$folder;
    if(!in_array($folder,['private_documents','private_requests'],true))
        return ['ok'=>false,'detail'=>'Nama folder tidak disetujui.'];
    if($source===false || is_link($path))
        return ['ok'=>false,'detail'=>'Lokasi/symlink penyimpanan tidak aman.'];
    $real=realpath($path);
    if(!$real || !is_dir($real) || dirname($real)!==$source)
        return ['ok'=>false,'detail'=>'Folder privat belum tersedia pada lokasi yang diharapkan.'];
    $perms=@fileperms($real);
    if($perms===false || ($perms&0007)!==0)
        return ['ok'=>false,'detail'=>'Perizinan folder terlalu terbuka untuk pengguna lain.'];
    if(!is_readable($real)||!is_writable($real))
        return ['ok'=>false,'detail'=>'Folder tidak dapat diakses secara aman oleh aplikasi.'];
    return ['ok'=>true,'detail'=>'Folder privat berada pada lokasi yang diharapkan dan tidak world-accessible.'];
}
/**
 * A PING probe only: does not submit any file, and does not imply malware has been scanned.
 */
function t14_clamd_status(array $config):array {
    if(($config['enforce']??false)!==true)
        return ['ok'=>false,'detail'=>'Pemindaian malware belum diwajibkan; dokumen warga nyata jangan diunggah.'];
    $socket=(string)($config['clamd_socket']??'');
    if($socket==='' || str_contains($socket,"\0") || strlen($socket)>220 || !str_starts_with($socket,'/'))
        return ['ok'=>false,'detail'=>'Lokasi socket ClamAV belum dikonfirmasi oleh penyedia hosting.'];
    if(!function_exists('stream_socket_client'))
        return ['ok'=>false,'detail'=>'Koneksi lokal menuju pemindai tidak tersedia di PHP.'];
    $stream=@stream_socket_client('unix://'.$socket,$errno,$err,1,STREAM_CLIENT_CONNECT);
    if(!is_resource($stream))return ['ok'=>false,'detail'=>'Daemon ClamAV tidak dapat dihubungi melalui socket lokal.'];
    try{
        stream_set_timeout($stream,2);
        $sent=@fwrite($stream,"zPING\0");
        $answer=$sent===6?@stream_get_line($stream,128,"\0"):false;
        if($answer!=='PONG')return ['ok'=>false,'detail'=>'ClamAV belum memberikan jawaban PONG yang valid.'];
        return ['ok'=>true,'detail'=>'Daemon ClamAV menjawab PING; efektivitas pemindaian file tetap harus diuji.'];
    }finally{fclose($stream);}
}
function t14_access_simulation():array {
    if(!function_exists('t9_can_read'))require_once __DIR__.'/citizen_requests.php';
    $owner=['source'=>'smartapp','id'=>'fixture-warga-a','role'=>'warga','name'=>'Pemohon A'];
    $other=['source'=>'smartapp','id'=>'fixture-warga-b','role'=>'warga','name'=>'Pemohon B'];
    $staff=['source'=>'smartapp','id'=>'fixture-ptsp','role'=>'ptsp','name'=>'Petugas Dummy'];
    $official=['source'=>'smartapp','id'=>'fixture-lurah','role'=>'lurah','name'=>'Pejabat Dummy'];
    $case=['account_id'=>'fixture-warga-a'];
    return [
        'owner_read'=>t9_can_read($owner,$case),
        'other_denied'=>!t9_can_read($other,$case),
        'ptsp_read'=>t9_can_read($staff,$case),
        'lurah_read'=>t9_can_read($official,$case),
        'staff_cannot_submit'=>!t9_is_citizen($staff),
        'warga_not_officer'=>t9_staff_role($owner)==='',
    ];
}
function t14_status_report(array $actor):array {
    if(!function_exists('is_super_admin')||!is_super_admin($actor))
        throw new RuntimeException('Pemeriksaan keamanan hanya untuk Super Administrator.');
    $base=realpath(__DIR__);
    $web=realpath(dirname(__DIR__).'/public_html');
    if(!$base||!$web)throw new RuntimeException('Direktori aplikasi tidak tersedia.');
    $findings=[];
    $add=static function(string $key,string $title,bool $ok,string $detail)use(&$findings):void{
        $findings[$key]=['title'=>$title,'ok'=>$ok,'detail'=>$detail];
    };
    $https=isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!==''&&$_SERVER['HTTPS']!=='off';
    $add('https','HTTPS',$https,'Koneksi ke panel pengawasan harus terenkripsi melalui HTTPS.');
    $add('php','Versi PHP',t14_php_ok(PHP_VERSION_ID),'PHP 8.3/8.4 direkomendasikan agar menerima pembaruan keamanan.');
    $cookies=session_get_cookie_params();
    $cookieOk=!empty($cookies['secure'])&&!empty($cookies['httponly'])
        && ini_get('session.use_strict_mode')==='1';
    $add('cookie','Keamanan sesi',$cookieOk,'Atribut Secure, HttpOnly, dan strict mode harus diterapkan.');
    $mfa=false;
    try{
        if(is_file(__DIR__.'/security_stage13.php')){
            require_once __DIR__.'/security_stage13.php';
            $mfa=s13_installed()&&s13_enrolled($actor)&&s13_verified_session($actor);
        }
    }catch(Throwable $e){error_log('Tahap 14: pemeriksaan MFA gagal.');}
    $add('mfa','MFA Super Administrator',$mfa,'Status terdeteksi untuk sesi yang sedang aktif, bukan untuk seluruh petugas.');
    foreach(['private_documents'=>'folder_t8','private_requests'=>'folder_t10'] as $dir=>$key){
        $result=t14_private_dir($dir,$base);
        $add($key,'Penyimpanan '.($dir==='private_documents'?'Tahap 8':'Tahap 10'),$result['ok'],$result['detail']);
    }
    $risk=t14_restricted_files($web);
    $add('webroot','Berkas berisiko di webroot',count($risk)===0,
         $risk?'Terdeteksi nama berkas berisiko pada public_html; periksa dan pindahkan secara aman.':
             'Tidak ditemukan nama berkas berisiko yang umum di tingkat teratas public_html.');
    $antivirus=t14_clamd_status(cfg()['upload_scanning']??[]);
    $add('scanner','Antivirus untuk unggahan',$antivirus['ok'],$antivirus['detail']);
    $sim=[];
    try{$sim=t14_access_simulation();}catch(Throwable $e){error_log('Tahap 14: simulasi peran tidak tersedia.');}
    $add('simulation','Simulasi logika akses',count($sim)===6 && !in_array(false,$sim,true),
         'Menggunakan data dummy, BUKAN pengujian langsung atas akun atau database produksi.');
    $pending=['instal-keamanan13.php','instal-arsip.php','instal-kesiapan.php','instal-tindak-lanjut.php',
              'instal-layanan.php','instal-pengajuan.php'];
    $found=array_values(array_filter($pending,static fn($p)=>is_file($web.'/'.$p)));
    $add('installers','Pembersihan installer',count($found)===0,
         $found?'Masih ada file pemasang. Periksa dan hapus hanya setelah proses instalasi terverifikasi.':
             'Installer yang dikenal tidak ditemukan pada webroot.');
    return ['findings'=>$findings,'simulations'=>$sim,'passed'=>count(array_filter($findings,static fn($v)=>$v['ok'])),
            'total'=>count($findings),'checked_at'=>date('Y-m-d H:i:s')];
}

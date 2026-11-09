<?php
declare(strict_types=1);
/**
 * Tahap 13 — TOTP MFA for explicitly authorised Super Administrators.
 * Secrets encrypted at rest with a per-site key outside public_html.
 * No schema modification on legacy Pegawai or SmartApp user tables.
 */
function s13_b32encode(string $bytes):string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$buffer=0;$bits=0;$out='';
    foreach(unpack('C*',$bytes) as $v){
        $buffer=($buffer<<8)|$v;$bits+=8;
        while($bits>=5){$bits-=5;$out.=$alphabet[($buffer>>$bits)&31];$buffer&=(1<<$bits)-1;}
    }
    if($bits>0)$out.=$alphabet[($buffer<<(5-$bits))&31];
    return $out;
}
function s13_b32decode(string $text):string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$buffer=0;$bits=0;$out='';
    $text=strtoupper($text);
    if(!preg_match('/^[A-Z2-7]{32}$/D',$text))throw new InvalidArgumentException('Secret MFA tidak valid.');
    foreach(str_split($text) as $ch){
        $index=strpos($alphabet,$ch);
        if($index===false)throw new InvalidArgumentException('Secret MFA tidak valid.');
        $buffer=($buffer<<5)|$index;$bits+=5;
        if($bits>=8){$bits-=8;$out.=chr(($buffer>>$bits)&255);$buffer&=(1<<$bits)-1;}
    }
    return $out;
}
function s13_totp(string $secret,int $step):string {
    $binary=s13_b32decode($secret);
    $time=pack('N2',0,$step);
    $hmac=hash_hmac('sha1',$time,$binary,true);
    $offset=ord($hmac[19])&0xf;
    $n=((ord($hmac[$offset])&0x7f)<<24)
      |(ord($hmac[$offset+1])<<16)
      |(ord($hmac[$offset+2])<<8)
      |ord($hmac[$offset+3]);
    return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);
}
function s13_match_step(string $secret,string $code,int $time, int $last=-1):?int {
    if(!preg_match('/^\d{6}$/D',$code))return null;
    $current=intdiv($time,30);
    foreach([$current-1,$current,$current+1] as $step){
        if($step<0||$step<=$last)continue;
        if(hash_equals(s13_totp($secret,$step),$code))return $step;
    }
    return null;
}
function s13_key():string {
    $file=__DIR__.'/mfa_key.php';
    if(!is_file($file)||is_link($file))throw new RuntimeException('Kunci MFA server tidak tersedia.');
    $hex=require $file;
    if(!is_string($hex)||!preg_match('/^[0-9a-f]{64}$/D',$hex))
        throw new RuntimeException('Kunci MFA server tidak valid.');
    return hex2bin($hex);
}
function s13_encrypt(string $secret):string {
    if(!function_exists('openssl_encrypt'))throw new RuntimeException('Ekstensi OpenSSL belum tersedia.');
    $nonce=random_bytes(12);$tag='';
    $encrypted=openssl_encrypt($secret,'aes-256-gcm',s13_key(),OPENSSL_RAW_DATA,$nonce,$tag,'smartcikoko-mfa-v1',16);
    if($encrypted===false)throw new RuntimeException('Enkripsi MFA gagal.');
    return base64_encode($nonce.$tag.$encrypted);
}
function s13_decrypt(string $encoded):string {
    if(!function_exists('openssl_decrypt'))throw new RuntimeException('Ekstensi OpenSSL belum tersedia.');
    $data=base64_decode($encoded,true);
    if($data===false||strlen($data)<29)throw new RuntimeException('Data MFA terenkripsi tidak valid.');
    $secret=openssl_decrypt(substr($data,28),'aes-256-gcm',s13_key(),OPENSSL_RAW_DATA,substr($data,0,12),substr($data,12,16),'smartcikoko-mfa-v1');
    if($secret===false)throw new RuntimeException('Dekripsi MFA gagal.');
    return $secret;
}
function s13_installed():bool {
    try{
        foreach(['unified_super_mfa','unified_mfa_recovery','unified_mfa_api_tokens'] as $table)
            db('smartapp')->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        return is_file(__DIR__.'/mfa_key.php')&&!is_link(__DIR__.'/mfa_key.php');
    }catch(Throwable $e){return false;}
}
function s13_identity_key(array $actor):array {
    $src=(string)($actor['source']??'');$id=(string)($actor['id']??'');
    if(!in_array($src,['pegawai','smartapp'],true)||$id===''||strlen($id)>100)throw new InvalidArgumentException('Identitas tidak berlaku.');
    return [$src,$id];
}
function s13_enrolled(array $actor):bool {
    // Check enrollment independently of key availability: a missing key must never disable MFA.
    [$src,$id]=s13_identity_key($actor);
    $q=db('smartapp')->prepare('SELECT 1 FROM unified_super_mfa WHERE actor_source=? AND actor_id=? AND enrolled_at IS NOT NULL');
    $q->execute([$src,$id]);
    return (bool)$q->fetchColumn();
}
function s13_verified_session(array $actor):bool {
    $v=$_SESSION['s13_verified']??null;
    if(!is_array($v))return false;
    [$src,$id]=s13_identity_key($actor);
    return $v['source']===$src && hash_equals((string)$v['id'],$id)
        && is_int($v['time'])&&$v['time']<=time()
        && time()-$v['time']<=4*3600;
}
function s13_mark_verified(array $actor):void {
    session_regenerate_id(true);
    [$src,$id]=s13_identity_key($actor);
    $_SESSION['s13_verified']=['source'=>$src,'id'=>$id,'time'=>time()];
    unset($_SESSION['s13_pending']);
}
function s13_secret_new():string{return s13_b32encode(random_bytes(20));}
/** Initial enrolment only. Re-enrolment requires a controlled administrator reset. */
function s13_enroll(array $actor,string $secret,string $otp):array {
    if(!function_exists('is_super_admin_identity')||!is_super_admin_identity($actor))
        throw new RuntimeException('Hanya Super Administrator dapat mendaftar MFA.');
    if(!s13_installed())throw new RuntimeException('Installer Tahap 13 belum dijalankan.');
    $step=s13_match_step($secret,$otp,time());
    if($step===null)throw new InvalidArgumentException('Kode autentikator tidak cocok.');
    [$src,$id]=s13_identity_key($actor);
    $db=db('smartapp');$db->beginTransaction();
    try {
        $q=$db->prepare('SELECT 1 FROM unified_super_mfa WHERE actor_source=? AND actor_id=? FOR UPDATE');
        $q->execute([$src,$id]);
        if($q->fetchColumn())throw new RuntimeException('Akun sudah memiliki MFA. Hubungi pengelola untuk pemulihan.');
        $q=$db->prepare('INSERT INTO unified_super_mfa(actor_source,actor_id,secret_cipher,last_step,enrolled_at) VALUES(?,?,?,?,NOW())');
        $q->execute([$src,$id,s13_encrypt($secret),$step]);
        $codes=[];
        $insert=$db->prepare('INSERT INTO unified_mfa_recovery(actor_source,actor_id,code_hash,created_at) VALUES(?,?,?,NOW())');
        for($i=0;$i<8;$i++){
            $raw=strtoupper(bin2hex(random_bytes(8)));
            $codes[]=substr($raw,0,4).'-'.substr($raw,4,4).'-'.substr($raw,8,4).'-'.substr($raw,12,4);
            $insert->execute([$src,$id,hash('sha256',$raw)]);
        }
        $db->commit();
        s13_mark_verified($actor);
        log_event('mfa_enrolled','security','totp');
        return $codes;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function s13_verify_otp(array $actor,string $input):bool {
    if(!s13_installed())return false;
    [$src,$id]=s13_identity_key($actor);
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT secret_cipher,last_step FROM unified_super_mfa WHERE actor_source=? AND actor_id=? FOR UPDATE');
        $q->execute([$src,$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row){$db->rollBack();return false;}
        $step=s13_match_step(s13_decrypt((string)$row['secret_cipher']),$input,time(),(int)$row['last_step']);
        if($step===null){$db->rollBack();return false;}
        $q=$db->prepare('UPDATE unified_super_mfa SET last_step=? WHERE actor_source=? AND actor_id=?');
        $q->execute([$step,$src,$id]);
        $db->commit();
        return true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function s13_redeem_recovery(array $actor,string $code):bool {
    $raw=strtoupper(str_replace(['-',' '],'',$code));
    if(!preg_match('/^[0-9A-F]{16}$/D',$raw))return false;
    [$src,$id]=s13_identity_key($actor);
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('DELETE FROM unified_mfa_recovery WHERE actor_source=? AND actor_id=? AND code_hash=? LIMIT 1');
        $q->execute([$src,$id,hash('sha256',$raw)]);
        if($q->rowCount()!==1){$db->rollBack();return false;}
        $db->commit();log_event('mfa_recovery_used','security','recovery');
        return true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function s13_mfa_verified(array $actor,string $input):bool {
    if(s13_verify_otp($actor,trim($input)))return true;
    return s13_redeem_recovery($actor,trim($input));
}
function s13_store_api_attestation(array $actor,string $bearerToken):void {
    [$src,$id]=s13_identity_key($actor);
    $q=db('smartapp')->prepare('INSERT INTO unified_mfa_api_tokens(token_hash,actor_source,actor_id,expires_at,created_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW())');
    $q->execute([hash('sha256',$bearerToken),$src,$id]);
}
function s13_api_attested(array $actor,string $bearerToken):bool {
    if(!s13_enrolled($actor))return true;
    [$src,$id]=s13_identity_key($actor);
    $q=db('smartapp')->prepare('SELECT 1 FROM unified_mfa_api_tokens WHERE token_hash=? AND actor_source=? AND actor_id=? AND expires_at>NOW() LIMIT 1');
    $q->execute([hash('sha256',$bearerToken),$src,$id]);
    return (bool)$q->fetchColumn();
}
/** If set, scanner is mandatory; absence/failure makes uploads fail closed. */
function s13_scan_upload_if_configured(string $tmp):void {
    $settings=cfg()['upload_scanning']??[];
    if(empty($settings['enforce']))return;
    $socket=(string)($settings['clamd_socket']??'');
    if($socket===''||str_contains($socket,"\0")||strlen($socket)>230)
        throw new RuntimeException('Pemindaian unggahan diwajibkan, tetapi ClamAV belum dikonfigurasi.');
    if(!is_uploaded_file($tmp))throw new RuntimeException('Unggahan tidak valid.');
    // INSTREAM over a local daemon socket. No system shell, no external upload.
    $conn=@stream_socket_client('unix://'.$socket,$errno,$errstr,3,STREAM_CLIENT_CONNECT);
    if(!$conn)throw new RuntimeException('Antivirus tidak tersedia, unggahan ditolak sementara.');
    stream_set_timeout($conn,10);
    $in=@fopen($tmp,'rb');
    if(!$in){fclose($conn);throw new RuntimeException('Berkas tidak dapat dibaca pemindai.');}
    try{
        if(fwrite($conn,"zINSTREAM\0")!==10)throw new RuntimeException('Koneksi pemindai gagal.');
        while(!feof($in)){
            $chunk=fread($in,65536);
            if($chunk===false)throw new RuntimeException('Gagal membaca berkas.');
            if($chunk==='')break;
            $packet=pack('N',strlen($chunk)).$chunk;
            $sent=0;
            while($sent<strlen($packet)){
                $w=fwrite($conn,substr($packet,$sent));
                if($w===false||$w===0)throw new RuntimeException('Antivirus tidak merespons.');
                $sent+=$w;
            }
        }
        if(fwrite($conn,pack('N',0))!==4)throw new RuntimeException('Pemindaian gagal.');
        $response=stream_get_line($conn,2048,"\0");
        if(!is_string($response)||!str_ends_with($response,' OK'))
            throw new RuntimeException('Berkas tidak lolos pemeriksaan antivirus.');
    }finally{fclose($in);fclose($conn);}
}

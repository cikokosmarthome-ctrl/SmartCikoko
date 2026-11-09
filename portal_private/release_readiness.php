<?php
declare(strict_types=1);
/**
 * Stage 12 — non-destructive production readiness checks, evidence checklist.
 * Never exports secrets or source data. No official signature/certificate issuance.
 */
function r12_manual_requirements():array {
    return [
        'restore_test'=>['title'=>'Backup terenkripsi dan uji pemulihan','why'=>'Bukti backup SmartApp/Pegawai dan pengujian pemulihan dari salinan uji.'],
        'access_matrix'=>['title'=>'Uji hak akses lintas akun','why'=>'Uji Warga A/B, PTSP, Lurah, Super Admin; termasuk akun yang diblokir.'],
        'mfa_admin'=>['title'=>'MFA untuk akun berwenang','why'=>'Pastikan autentikasi multifaktor sudah diterapkan dan diuji pada akun petugas.'],
        'malware'=>['title'=>'Pemindaian malware pada unggahan','why'=>'Pastikan pemindaian antivirus benar-benar terintegrasi, bukan hanya validasi MIME/SHA-256.'],
        'privacy'=>['title'=>'Dasar pemrosesan dan kebijakan privasi','why'=>'Tinjau pemberitahuan privasi, hak subjek data, akses petugas, dan perlindungan informasi warga.'],
        'retention'=>['title'=>'Jadwal retensi dan penghapusan resmi','why'=>'Tetapkan jadwal retensi arsip, otorisasi pemusnahan, dan prosedur penahanan penghapusan.'],
        'penetration'=>['title'=>'Uji keamanan independen','why'=>'Lakukan penilaian kerentanan dan pengujian penetrasi pada website serta API Android.'],
        'approval'=>['title'=>'Persetujuan penanggung jawab layanan','why'=>'Dokumentasikan hasil UAT dan keputusan resmi mengenai aktivasi layanan publik.'],
        'letter'=>['title'=>'Kewenangan naskah dinas dan TTE','why'=>'Validasi format surat, pejabat penandatangan, dan penyedia tanda tangan elektronik berwenang; fitur surat resmi belum tersedia.'],
    ];
}
function r12_auto_catalog():array {
    return [
        'https'=>'Koneksi HTTPS',
        'php'=>'Versi PHP server',
        'errors'=>'Kesalahan tidak ditampilkan publik',
        'cookies'=>'Pengaturan keamanan sesi',
        'config'=>'Konfigurasi rahasia di luar webroot',
        'db_pegawai'=>'Database Pegawai',
        'db_smartapp'=>'Database SmartApp',
        'folder_pelayanan'=>'Folder lampiran Tahap 8',
        'folder_pengajuan'=>'Folder lampiran Tahap 10',
        'archive'=>'Pencatatan arsip Tahap 11',
        'installers'=>'Berkas installer publik dibersihkan'
    ];
}
function r12_is_https():bool {
    // Avoid trusting unvalidated X-Forwarded-Proto from public requests.
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' && $_SERVER['HTTPS']!=='';
}
function r12_private_folder_ok(string $name):bool {
    if(!in_array($name,['private_documents','private_requests'],true))return false;
    $base=realpath(__DIR__);
    $public=realpath(dirname(__DIR__).'/public_html');
    if(!$base || !$public)return false;
    $path=$base.'/'.$name;
    if(is_link($path))return false;
    $real=realpath($path);
    if(!$real||!is_dir($real)||dirname($real)!==$base)return false;
    if($real===$public || str_starts_with($real,$public.'/'))return false;
    $perms=@fileperms($real);
    if($perms===false || ((int)$perms & 0007)!==0)return false;
    return is_readable($real)&&is_writable($real);
}
function r12_installers_present():bool {
    $public=dirname(__DIR__).'/public_html';
    $names=[
        'aktivasi-super-admin.php','pasang-tahap6.php','instal-operasional.php',
        'instal-layanan.php','instal-pengajuan.php','instal-tindak-lanjut.php',
        'instal-arsip.php','instal-kesiapan.php',
    ];
    foreach($names as $name)if(is_file($public.'/'.$name))return true;
    return false;
}
/** One query for each source. Results are generic, never show credentials. */
function r12_db_up(string $source):bool {
    try{
        db($source)->query('SELECT 1')->fetchColumn();
        return true;
    }catch(Throwable $e){
        error_log('SmartCikoko release check: database unavailable for allowed source '.$source);
        return false;
    }
}
function r12_archive_up():bool {
    try{
        foreach(['unified_archive_registry','unified_archive_events'] as $table)
            db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function r12_auto_results():array {
    $v=PHP_VERSION_ID;
    $cookies=session_get_cookie_params();
    $private=realpath(__DIR__);
    $webroot=realpath(dirname(__DIR__).'/public_html');
    $config=realpath(__DIR__.'/config.php');
    $items=[
        'https'=>[r12_is_https(),'Akses dashboard harus menggunakan HTTPS.'],
        'php'=>[$v>=80300,'Rekomendasi minimal PHP 8.3 yang masih mendapat pembaruan keamanan.'],
        'errors'=>[ini_get('display_errors')==='0'||ini_get('display_errors')==='Off','Atur display_errors=Off agar informasi internal tidak terpapar.'],
        'cookies'=>[(bool)$cookies['secure'] && (bool)$cookies['httponly'] && (int)ini_get('session.use_strict_mode')===1,'Aktifkan secure, HttpOnly, dan session strict mode; periksa HTTPS.'],
        'config'=>[$private!==false && $webroot!==false && $config!==false && dirname($config)===$private && !is_link(__DIR__.'/config.php') && !str_starts_with($config,$webroot.'/'),'Simpan config.php dalam portal_private, tidak di public_html.'],
        'db_pegawai'=>[r12_db_up('pegawai'),'Periksa koneksi yang tersedia bagi aplikasi, tanpa membagikan password.'],
        'db_smartapp'=>[r12_db_up('smartapp'),'Periksa koneksi yang tersedia bagi aplikasi, tanpa membagikan password.'],
        'folder_pelayanan'=>[r12_private_folder_ok('private_documents'),'Pastikan folder privat Tahap 8 tersedia, tidak symlink, dan tidak dapat diakses publik.'],
        'folder_pengajuan'=>[r12_private_folder_ok('private_requests'),'Pastikan folder privat Tahap 10 tersedia, tidak symlink, dan tidak dapat diakses publik.'],
        'archive'=>[r12_archive_up(),'Pastikan tabel Tahap 11 tersedia di SmartApp.'],
        'installers'=>[!r12_installers_present(),'Hapus semua installer publik setelah sukses, termasuk instal-kesiapan.php.']
    ];
    $result=[];
    foreach(r12_auto_catalog() as $key=>$title){
        $result[$key]=['title'=>$title,'passed'=>(bool)$items[$key][0],'hint'=>$items[$key][1]];
    }
    return $result;
}
function r12_manual_list():array {
    $out=[];
    $q=db('smartapp')->query('SELECT requirement_key,status,evidence_note,updated_at FROM unified_release_checks');
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(isset(r12_manual_requirements()[$row['requirement_key']]))
            $out[$row['requirement_key']]=$row;
    }
    return $out;
}
function r12_set_manual(array $actor,string $key,string $status,string $note):void {
    if(!is_super_admin($actor))throw new RuntimeException('Tidak memiliki kewenangan.');
    if(!isset(r12_manual_requirements()[$key]))throw new InvalidArgumentException('Pemeriksaan tidak ditemukan.');
    if(!in_array($status,['pending','verified','blocked'],true))throw new InvalidArgumentException('Status tidak valid.');
    if(mb_strlen($note)>250)throw new InvalidArgumentException('Bukti ringkas maksimal 250 karakter.');
    if($status==='verified' && mb_strlen(trim($note))<10)
        throw new InvalidArgumentException('Cantumkan bukti pemeriksaan ringkas minimal 10 karakter.');
    $pdo=db('smartapp');$pdo->beginTransaction();
    try{
        $old=$pdo->prepare('SELECT status FROM unified_release_checks WHERE requirement_key=? FOR UPDATE');
        $old->execute([$key]);$prev=$old->fetchColumn();
        $q=$pdo->prepare('INSERT INTO unified_release_checks(requirement_key,status,evidence_note,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),evidence_note=VALUES(evidence_note),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
        $q->execute([$key,$status,$note,$actor['source'],(string)$actor['id']]);
        $event=$pdo->prepare('INSERT INTO unified_release_events(requirement_key,old_status,new_status,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,NOW())');
        $event->execute([$key,$prev===false?'pending':$prev,$status,$actor['source'],(string)$actor['id']]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function r12_recent(int $limit=12):array {
    $limit=max(1,min(30,$limit));
    $q=db('smartapp')->query('SELECT requirement_key,old_status,new_status,created_at FROM unified_release_events ORDER BY id DESC LIMIT '.$limit);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function r12_stats(array $auto,array $manual):array {
    $checks=count($auto);$passed=count(array_filter($auto,static fn(array $c):bool=>$c['passed']));
    $manualCount=count(r12_manual_requirements());
    $verified=0;$blocked=0;
    foreach(r12_manual_requirements() as $key=>$requirement){
        $status=(string)($manual[$key]['status']??'pending');
        if($status==='verified')$verified++;
        if($status==='blocked')$blocked++;
    }
    return ['passed'=>$passed,'auto_total'=>$checks,'verified'=>$verified,'manual_total'=>$manualCount,'blocked'=>$blocked];
}

<?php
declare(strict_types=1);

/**
 * Super Administrator: identitas ditetapkan secara privat melalui aktivasi
 * satu kali, tanpa mengubah tabel akun atau hak peran pengguna lain.
 * Prioritas: super_admin_access.php (wizard), fallback config.php (manual).
 */
function is_super_admin_identity(?array $candidate=null): bool {
    $candidate ??= actor();
    if (!$candidate || !is_admin($candidate)) return false;
    $source=(string)($candidate['source']??'');
    $id=(string)($candidate['id']??'');
    if (!in_array($source,['pegawai','smartapp'],true) || $id==='') return false;
    $accessFile=__DIR__.'/super_admin_access.php';
    $allowlist=is_file($accessFile) ? require $accessFile : (cfg()['super_admin_accounts']??[]);
    if (!is_array($allowlist)) return false;
    $allowed=false;
    foreach ($allowlist as $row) {
        if (!is_array($row)) continue;
        if ((string)($row['source']??'')!==$source) continue;
        if (hash_equals((string)($row['id']??''),$id)) {
            $allowed=true;
            break;
        }
    }
    if (!$allowed) return false;
    // Status dan kewenangan akun diperiksa kembali pada setiap permintaan.
    try {
        if ($source==='pegawai') {
            $q=db('pegawai')->prepare("SELECT role,status FROM users WHERE id=? LIMIT 1");
            $q->execute([$id]);$row=$q->fetch();
            return $row && $row['status']==='active'
                && in_array($row['role'],['admin','superadmin','admin_kelurahan'],true);
        }
        $q=db('smartapp')->prepare('SELECT role,active FROM unified_portal_users WHERE id=? LIMIT 1');
        $q->execute([$id]);$row=$q->fetch();
        return $row && (int)$row['active']===1
            && in_array($row['role'],['admin','superadmin','admin_kelurahan'],true);
    } catch(Throwable $e) {
        error_log('Super Admin identity verification failed.');
        return false;
    }
}

/** Actual Super Admin authorization includes the MFA challenge once enrolled. */
function is_super_admin(?array $candidate=null):bool {
    $candidate ??= actor();
    if(!$candidate || !is_super_admin_identity($candidate))return false;
    $f=__DIR__.'/security_stage13.php';
    if(!is_file($f))return true; // before Stage 13 is installed, preserve legacy access
    require_once $f;
    if(!s13_installed() || !s13_enrolled($candidate))return true;
    // REST API tokens need their own second-factor attestation, never web session state.
    if(str_contains((string)($_SERVER['SCRIPT_NAME']??''),'/api/')){
        $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(!preg_match('/^Bearer ([a-f0-9]{64})$/i',$auth,$m))return false;
        return s13_api_attested($candidate,strtolower($m[1]));
    }
    $session=actor();
    return $session!==null
        && $session['source']===$candidate['source']
        && hash_equals((string)$session['id'],(string)$candidate['id'])
        && s13_verified_session($candidate);
}

function require_super_admin(): array {
    $user=requires_login();
    if (!is_super_admin($user)) {
        $f=__DIR__.'/security_stage13.php';
        if(is_super_admin_identity($user) && is_file($f)){
            require_once $f;
            if(s13_installed() && s13_enrolled($user) && !s13_verified_session($user)){
                header('Location: /mfa.php',true,303);exit;
            }
        }
        http_response_code(403);
        header('Cache-Control: no-store');
        exit('Akses ditolak. Hanya Super Administrator terotorisasi.');
    }
    return $user;
}

/** Daftar modul ditetapkan oleh aplikasi, bukan parameter permintaan. */
function super_admin_modules(): array {
    return [
        'portal'=>['label'=>'Portal dan Login Terpadu','url'=>'/?page=dashboard','source'=>'smartapp'],
        'pegawai'=>['label'=>'Administrasi Pegawai','url'=>'/?page=pegawai','source'=>'pegawai'],
        'pppk'=>['label'=>'Kinerja PPPK','url'=>'/?page=pppk','source'=>'pegawai'],
        'pjlp'=>['label'=>'Kegiatan PJLP / PPSU','url'=>'/?page=pjlp','source'=>'pegawai'],
        'waris'=>['label'=>'Pelayanan Ahli Waris','url'=>'/?page=waris','source'=>'smartapp'],
        'informasi'=>['label'=>'Informasi dan Agenda','url'=>'/?page=berita','source'=>'smartapp'],
        'android'=>['label'=>'Integrasi Aplikasi Android','url'=>'/?page=dashboard','source'=>'smartapp'],
    ];
}

function super_admin_metrics(): array {
    $sourceStatuses=[];
    foreach(['pegawai','smartapp'] as $source) {
        try {$sourceStatuses[$source]=(int)db($source)->query('SELECT 1')->fetchColumn()===1;}
        catch(Throwable $e) {$sourceStatuses[$source]=false;error_log('Super Admin database health check failed: '.$source);}
    }
    $checks=[
        ['title'=>'Akun Pegawai','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM users'],
        ['title'=>'Akun PPPK Pancoran','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM ck_pancoran_users'],
        ['title'=>'Akun Portal Cikoko','source'=>'smartapp','sql'=>'SELECT COUNT(*) FROM unified_portal_users'],
        ['title'=>'Permohonan Ahli Waris','source'=>'smartapp','sql'=>'SELECT COUNT(*) FROM aw_applications'],
        ['title'=>'Permohonan Dalam Proses','source'=>'smartapp','sql'=>"SELECT COUNT(*) FROM aw_applications WHERE status IN ('masuk','proses','revisi')"],
        ['title'=>'Permohonan Selesai','source'=>'smartapp','sql'=>"SELECT COUNT(*) FROM aw_applications WHERE status='selesai'"],
        ['title'=>'Laporan PPPK','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM ck_pppk_kinerja'],
        ['title'=>'Laporan PJLP / PPSU','source'=>'pegawai','sql'=>'SELECT COUNT(*) FROM laporan'],
    ];
    foreach($checks as &$check) {
        $check['value']=null;
        if (!$sourceStatuses[$check['source']]) continue;
        try {$check['value']=(int)db($check['source'])->query($check['sql'])->fetchColumn();}
        catch(Throwable $e) {error_log('Super Admin aggregate unavailable: '.$check['title']);}
    }
    unset($check);
    return ['connections'=>$sourceStatuses,'metrics'=>$checks];
}

function super_admin_progress(): array {
    $modules=super_admin_modules();
    $data=[];
    try {
        $q=db('smartapp')->query('SELECT module_key,status,progress_percent,notes,updated_at FROM unified_module_progress');
        foreach($q->fetchAll() as $r) {
            if (isset($modules[$r['module_key']])) $data[$r['module_key']]=$r;
        }
    } catch(Throwable $e) {
        // Setelah upgrade kode namun sebelum migrasi SQL, tetap tampilkan
        // penanda "perlu instalasi tabel" tanpa membuat klaim kemajuan.
        error_log('Super Admin progress table unavailable.');
    }
    return $data;
}

function super_admin_set_progress(array $actor,string $module,string $status,int $percentage,string $notes): void {
    if (!is_super_admin($actor)) throw new RuntimeException('Tidak memiliki izin.');
    if (!isset(super_admin_modules()[$module])) throw new InvalidArgumentException('Modul tidak valid.');
    if (!in_array($status,['belum','berjalan','tertunda','selesai'],true)) throw new InvalidArgumentException('Status tidak valid.');
    if ($percentage<0 || $percentage>100 || mb_strlen($notes)>500) throw new InvalidArgumentException('Progres atau catatan tidak valid.');
    if ($status==='belum') $percentage=0;
    if ($status==='selesai') $percentage=100;
    $db=db('smartapp');
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('UPDATE unified_module_progress SET status=?,progress_percent=?,notes=?,updated_by_source=?,updated_by_id=?,updated_at=NOW() WHERE module_key=?');
        $stmt->execute([$status,$percentage,$notes,$actor['source'],$actor['id'],$module]);
        if ($stmt->rowCount()===0) {
            $exists=$db->prepare('SELECT 1 FROM unified_module_progress WHERE module_key=?');
            $exists->execute([$module]);
            if (!$exists->fetchColumn()) throw new RuntimeException('Modul belum diinisialisasi. Jalankan migrasi schema Super Admin.');
        }
        $log=$db->prepare('INSERT INTO unified_audit (actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES (?,?,?,?,?,NOW())');
        $log->execute([$actor['source'],(string)$actor['id'],'super_admin_progress_updated','unified_module_progress',hash('sha256',$module)]);
        $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

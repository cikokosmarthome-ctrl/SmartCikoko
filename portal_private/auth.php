<?php
declare(strict_types=1);
/** Login melalui 3 sumber identitas, tanpa mengubah tabel pengguna lama. */
function login_matches(string $identity, string $password): array {
    if ($identity === '' || mb_strlen($identity) > 190 || strlen($password) > 256 || $password === '') return [];
    $matches=[];
    // Database Pegawai: users berisi admin, ASN, PPPK dan PJLP.
    $q=db('pegawai')->prepare("SELECT id,username,nama_lengkap,role,password,status FROM users WHERE LOWER(username)=LOWER(?) AND status='active' LIMIT 5");
    $q->execute([$identity]);
    foreach ($q as $r) if (password_verify($password,(string)$r['password'])) {
        $matches[]=['source'=>'pegawai','id'=>(string)$r['id'],'username'=>$r['username'],'name'=>$r['nama_lengkap'],'role'=>$r['role']];
    }
    // Database Pegawai: akun PPPK Pancoran terpisah.
    $q=db('pegawai')->prepare("SELECT id,email,name,password_hash,is_active FROM ck_pancoran_users WHERE LOWER(email)=LOWER(?) AND is_active=1 LIMIT 5");
    $q->execute([$identity]);
    foreach ($q as $r) if (password_verify($password,(string)$r['password_hash'])) {
        $matches[]=['source'=>'pancoran','id'=>(string)$r['id'],'username'=>$r['email'],'name'=>$r['name'],'role'=>'pppk_pancoran'];
    }
    // Database SmartApp: akun lama portal_users.json/admin.json, hash tetap asli.
    $q=db('smartapp')->prepare('SELECT id,username,name,password_hash,role,active FROM unified_portal_users WHERE LOWER(username)=LOWER(?) AND active=1 LIMIT 5');
    $q->execute([$identity]);
    foreach ($q as $r) if (password_verify($password,(string)$r['password_hash'])) {
        $matches[]=['source'=>'smartapp','id'=>(string)$r['id'],'username'=>$r['username'],'name'=>$r['name'],'role'=>$r['role']];
    }
    return $matches;
}
function throttle_login(string $identity): void {
    $bucket=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.mb_strtolower($identity));
    $db=db();
    $db->prepare('DELETE FROM unified_login_limits WHERE expires_at < NOW()')->execute();
    $db->prepare('INSERT INTO unified_login_limits(bucket,attempts,expires_at) VALUES (?,1,DATE_ADD(NOW(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE attempts=attempts+1')->execute([$bucket]);
    $s=$db->prepare('SELECT attempts FROM unified_login_limits WHERE bucket=?');$s->execute([$bucket]);
    if ((int)$s->fetchColumn()>10) {http_response_code(429);throw new RuntimeException('Terlalu banyak percobaan. Coba kembali setelah 15 menit.');}
}
function clear_login_limit(string $identity): void {
    $bucket=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.mb_strtolower($identity));
    $s=db()->prepare('DELETE FROM unified_login_limits WHERE bucket=?');$s->execute([$bucket]);
}
function start_actor_session(array $user): void {
    session_regenerate_id(true);
    $_SESSION['actor'] = [
        'source'=>(string)$user['source'], 'id'=>(string)$user['id'], 'username'=>(string)$user['username'],
        'name'=>(string)$user['name'], 'role'=>(string)$user['role']
    ];
    unset($_SESSION['pending_matches']);
    log_event('login','identity',$user['source'].':'.$user['id']);
}
function end_actor_session(): void {
    log_event('logout');
    $_SESSION=[];
    if (ini_get('session.use_cookies')) { $p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
}
function issue_api_token(array $user): string {
    $token=bin2hex(random_bytes(32));
    $stmt=db()->prepare('INSERT INTO unified_api_tokens(token_hash,actor_source,actor_id,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))');
    $stmt->execute([hash('sha256',$token),$user['source'],$user['id']]);
    return $token;
}
function api_actor(): ?array {
    $h=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/i',$h,$m)) return null;
    $q=db()->prepare('SELECT actor_source,actor_id FROM unified_api_tokens WHERE token_hash=? AND expires_at>NOW() AND revoked_at IS NULL LIMIT 1');
    $q->execute([hash('sha256',strtolower($m[1]))]);$token=$q->fetch();
    if (!$token) return null;
    // Revalidasi status akun serta role setiap request.
    if ($token['actor_source']==='pegawai') { $q=db('pegawai')->prepare("SELECT id,username,nama_lengkap AS name,role FROM users WHERE id=? AND status='active'"); }
    elseif ($token['actor_source']==='pancoran') { $q=db('pegawai')->prepare('SELECT id,email AS username,name,\'pppk_pancoran\' AS role FROM ck_pancoran_users WHERE id=? AND is_active=1'); }
    else { $q=db()->prepare('SELECT id,username,name,role FROM unified_portal_users WHERE id=? AND active=1'); }
    $q->execute([$token['actor_id']]);$u=$q->fetch();
    if (!$u) return null;
    return ['source'=>$token['actor_source'],'id'=>(string)$u['id'],'username'=>$u['username'],'name'=>$u['name'],'role'=>$u['role']];
}
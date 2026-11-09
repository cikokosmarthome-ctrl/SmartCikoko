<?php
declare(strict_types=1);
/**
 * Tahap 6 — Portal-only account access overrides.
 * Two source databases stay unchanged. This layer does not grant the admin
 * or superadmin role, and does not modify the legacy password hashes.
 */
const SMARTCIKOKO_SOURCES = ['pegawai','pancoran','smartapp'];

function control_source(array $user): string {
    $source=(string)($user['source']??'');
    if(!in_array($source,SMARTCIKOKO_SOURCES,true)) throw new InvalidArgumentException('Sumber identitas tidak valid.');
    return $source;
}
function control_id(array $user): string {
    $id=(string)($user['id']??'');
    if($id==='' || strlen($id)>100 || str_contains($id,"\0")) throw new InvalidArgumentException('Identitas tidak valid.');
    return $id;
}
function account_control_allowed(array $user): bool {
    try {
        $source=control_source($user);
        $id=control_id($user);
        // Revalidate native account status and role; revoke stale web sessions.
        if($source==='pegawai'){
            $check=db('pegawai')->prepare('SELECT role,status FROM users WHERE id=? LIMIT 1');
            $check->execute([$id]);$native=$check->fetch(PDO::FETCH_ASSOC);
            if(!$native || $native['status']!=='active' || $native['role']!==(string)($user['role']??''))return false;
        }elseif($source==='pancoran'){
            $check=db('pegawai')->prepare('SELECT is_active FROM ck_pancoran_users WHERE id=? LIMIT 1');
            $check->execute([$id]);$native=$check->fetch(PDO::FETCH_ASSOC);
            if(!$native || (int)$native['is_active']!==1 || (string)($user['role']??'')!=='pppk_pancoran')return false;
        }else{
            $check=db('smartapp')->prepare('SELECT role,active FROM unified_portal_users WHERE id=? LIMIT 1');
            $check->execute([$id]);$native=$check->fetch(PDO::FETCH_ASSOC);
            if(!$native || (int)$native['active']!==1 || $native['role']!==(string)($user['role']??''))return false;
        }
        $q=db('smartapp')->prepare('SELECT login_allowed FROM unified_account_access WHERE actor_source=? AND actor_id=? LIMIT 1');
        $q->execute([$source,$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        return !$row || (int)$row['login_allowed']===1;
    } catch(Throwable $e) {
        error_log('SmartCikoko access validation failed.');
        // Fail closed: never silently bypass a saved access restriction.
        return false;
    }
}

/** Null = use role defaults; true/false = explicit module override. */
function account_control_permission_decision(array $user,string $ability): ?bool {
    if (!account_control_allowed($user)) return false;
    $source=control_source($user);
    $allowed=control_available_abilities($source);
    if(!in_array($ability,$allowed,true)) return null;
    $q=db('smartapp')->prepare('SELECT decision FROM unified_module_access WHERE actor_source=? AND actor_id=? AND ability=?');
    $q->execute([$source,control_id($user),$ability]);
    $decision=$q->fetchColumn();
    if ($decision===false) return null;
    return $decision==='grant';
}
function control_available_abilities(string $source):array {
    return match($source) {
        'pegawai'=>['pppk','pjlp'],
        'pancoran'=>['pppk'],
        'smartapp'=>['waris'],
        default=>[]
    };
}
/**
 * Only Super Admin can administer NON-admin accounts. Do not permit
 * changing the chosen Super Admin, other administrators, or oneself.
 */
function control_target(string $source,string $id):?array {
    if(!in_array($source,SMARTCIKOKO_SOURCES,true)||$id===''||strlen($id)>100) return null;
    $db=db($source==='smartapp'?'smartapp':'pegawai');
    switch($source){
        case 'pegawai':
            $q=$db->prepare('SELECT id,username,nama_lengkap AS name,role,status FROM users WHERE id=? LIMIT 1');
            break;
        case 'pancoran':
            $q=$db->prepare("SELECT id,email AS username,name,'pppk_pancoran' AS role,CASE WHEN is_active=1 THEN 'active' ELSE 'inactive' END AS status FROM ck_pancoran_users WHERE id=? LIMIT 1");
            break;
        default:
            $q=$db->prepare("SELECT id,username,name,role,CASE WHEN active=1 THEN 'active' ELSE 'inactive' END AS status FROM unified_portal_users WHERE id=? LIMIT 1");
    }
    $q->execute([$id]);$target=$q->fetch(PDO::FETCH_ASSOC);
    if(!$target)return null;
    $target['source']=$source;
    $target['id']=(string)$target['id'];
    $target['protected']=in_array((string)$target['role'],['admin','superadmin','admin_kelurahan'],true);
    return $target;
}
function control_current(string $source,string $id):array {
    $q=db('smartapp')->prepare('SELECT login_allowed, updated_at FROM unified_account_access WHERE actor_source=? AND actor_id=?');
    $q->execute([$source,$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return ['allowed'=>$r?(int)$r['login_allowed']===1:true,'updated_at'=>$r['updated_at']??null];
}
function control_decisions(string $source,string $id):array {
    $q=db('smartapp')->prepare('SELECT ability,decision FROM unified_module_access WHERE actor_source=? AND actor_id=?');
    $q->execute([$source,$id]);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(string)$r['ability']]=(string)$r['decision'];
    return $out;
}
function control_set(array $super,string $source,string $id,string $action,string $value,string $ability=''):void {
    if(!is_super_admin($super)) throw new RuntimeException('Akses ditolak.');
    $target=control_target($source,$id);
    if(!$target || $target['protected']) throw new RuntimeException('Akun ini tidak dapat diubah melalui halaman ini.');
    if($source===$super['source'] && hash_equals($id,(string)$super['id']))throw new RuntimeException('Tidak dapat mengubah akun sendiri.');
    $pdo=db('smartapp');$pdo->beginTransaction();
    try {
        $previous='default';
        if($action==='login') {
            if(!in_array($value,['active','blocked'],true))throw new InvalidArgumentException('Status akses tidak dikenal.');
            $before=$pdo->prepare('SELECT login_allowed FROM unified_account_access WHERE actor_source=? AND actor_id=? FOR UPDATE');
            $before->execute([$source,$id]);
            $was=$before->fetchColumn();
            $previous=$was===false?'active':((int)$was===1?'active':'blocked');
            // Do not reactivate a user already disabled in the legacy source.
            if($value==='active' && $target['status']!=='active') throw new RuntimeException('Akun sumber tidak aktif. Aktifkan dahulu melalui prosedur kepegawaian.');
            $q=$pdo->prepare('INSERT INTO unified_account_access(actor_source,actor_id,login_allowed,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE login_allowed=VALUES(login_allowed),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
            $q->execute([$source,$id,$value==='active'?1:0,$super['source'],$super['id']]);
        }elseif($action==='permission') {
            if(!in_array($ability,control_available_abilities($source),true))throw new RuntimeException('Modul tersebut tidak dapat dikelola untuk sumber akun ini.');
            if(!in_array($value,['default','grant','deny'],true))throw new InvalidArgumentException('Pilihan kewenangan tidak valid.');
            $before=$pdo->prepare('SELECT decision FROM unified_module_access WHERE actor_source=? AND actor_id=? AND ability=? FOR UPDATE');
            $before->execute([$source,$id,$ability]);
            $was=$before->fetchColumn();
            $previous=$was===false?'default':(string)$was;
            if($value==='default') {
                $q=$pdo->prepare('DELETE FROM unified_module_access WHERE actor_source=? AND actor_id=? AND ability=?');
                $q->execute([$source,$id,$ability]);
            }else{
                $q=$pdo->prepare('INSERT INTO unified_module_access(actor_source,actor_id,ability,decision,updated_by_source,updated_by_id,updated_at) VALUES(?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE decision=VALUES(decision),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id),updated_at=NOW()');
                $q->execute([$source,$id,$ability,$value,$super['source'],$super['id']]);
            }
        }else throw new InvalidArgumentException('Tindakan tidak diperbolehkan.');
        if($previous!==$value){
            $history=$pdo->prepare('INSERT INTO unified_access_history (actor_source,actor_id,action_type,ability,old_value,new_value,changed_by_source,changed_by_id,changed_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $history->execute([$source,$id,$action,$ability,$previous,$value,$super['source'],(string)$super['id']]);
        }
        $log=$pdo->prepare('INSERT INTO unified_audit (actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES (?,?,?,?,?,NOW())');
        $log->execute([$super['source'],(string)$super['id'],$action==='login'?'account_access_updated':'module_permission_updated',$action==='login'?'unified_account_access':'unified_module_access',hash('sha256',$source.':'.$id.':'.$ability.':'.$value)]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function control_recent_changes(int $limit=15):array {
    $limit=max(1,min(50,$limit));
    $query=db('smartapp')->query("SELECT actor_source,actor_id,action_type,ability,old_value,new_value,changed_at FROM unified_access_history ORDER BY id DESC LIMIT ".$limit);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

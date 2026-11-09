<?php
declare(strict_types=1);
function scalar_count(string $source, string $query, array $params=[]): int {
    $st=db($source)->prepare($query);$st->execute($params); return (int)$st->fetchColumn();
}
function public_posts(int $limit=6): array {
    $limit=max(1,min($limit,20));
    $st=db()->query("SELECT id,title,summary,published_at FROM posts WHERE status='published' ORDER BY published_at DESC,id DESC LIMIT $limit");
    return $st->fetchAll();
}
function public_events(int $limit=5): array {
    $limit=max(1,min($limit,20));
    $st=db()->query("SELECT id,title,description,location,starts_at FROM events ORDER BY starts_at DESC LIMIT $limit");
    return $st->fetchAll();
}
function dashboard_data(array $u): array {
    $result=['name'=>$u['name'],'role'=>$u['role'],'role_label'=>role_label($u['role']), 'source'=>$u['source'],'notices'=>[],'metrics'=>[]];
    if (is_admin($u)) {
        $result['metrics']=[
            ['label'=>'Akun Pegawai','value'=>scalar_count('pegawai','SELECT COUNT(*) FROM users')],
            ['label'=>'Akun PPPK Pancoran','value'=>scalar_count('pegawai','SELECT COUNT(*) FROM ck_pancoran_users')],
            ['label'=>'Akun Portal Cikoko','value'=>scalar_count('smartapp','SELECT COUNT(*) FROM unified_portal_users')],
            ['label'=>'Pengajuan Ahli Waris','value'=>scalar_count('smartapp','SELECT COUNT(*) FROM aw_applications')],
            ['label'=>'Permohonan Diproses','value'=>scalar_count('smartapp',"SELECT COUNT(*) FROM aw_applications WHERE status IN ('masuk','proses','revisi')")],
            ['label'=>'Laporan Kinerja PPPK','value'=>scalar_count('pegawai','SELECT COUNT(*) FROM ck_pppk_kinerja')],
            ['label'=>'Laporan PJLP','value'=>scalar_count('pegawai','SELECT COUNT(*) FROM laporan')],
        ];
    } else {
        if (permitted('waris',$u)) $result['notices'][]='Modul pelayanan ahli waris tersedia sesuai dengan hak akses Anda.';
        if (permitted('pppk',$u)) $result['notices'][]='Modul kepegawaian PPPK tersedia.';
        if (permitted('pjlp',$u)) $result['notices'][]='Modul kegiatan PJLP/PPSU tersedia.';
    }
    return $result;
}
function waris_list(array $u, int $limit=75): array {
    if (!permitted('waris',$u)) return [];
    $limit=max(1,min($limit,100));
    // Petugas hanya dapat mengakses seluruh data jika memiliki kewenangan pengelolaan.
    if (permitted('waris_manage',$u)) {
        $st=db()->query("SELECT id,reference_no,applicant_name,relationship,status,created_at,updated_at FROM aw_applications ORDER BY id DESC LIMIT $limit");
    } else {
        if ($u['source']!=='smartapp') return [];
        $st=db()->prepare("SELECT id,reference_no,applicant_name,relationship,status,created_at,updated_at FROM aw_applications WHERE account_id=? ORDER BY id DESC LIMIT $limit");
        $st->execute([$u['id']]);
    }
    return $st->fetchAll();
}
function waris_details(array $u, int $id): ?array {
    if (!permitted('waris',$u) || $id<1) return null;
    $st=db()->prepare('SELECT id,reference_no,account_id,applicant_name,relationship,deceased_name,status,staff_notes,created_at,updated_at FROM aw_applications WHERE id=?');
    $st->execute([$id]);$r=$st->fetch();if (!$r) return null;
    if (!permitted('waris_manage',$u) && !($u['source']==='smartapp' && hash_equals((string)$r['account_id'],(string)$u['id']))) return null;
    unset($r['account_id']);return $r;
}
function waris_change_status(array $u, int $id, string $status, string $notes): void {
    if (!permitted('waris_manage',$u)) throw new RuntimeException('Akses tidak diizinkan.');
    if (!in_array($status,['masuk','proses','revisi','selesai','ditolak'],true) || $id<1 || mb_strlen($notes)>1000) throw new RuntimeException('Data perubahan tidak valid.');
    $db=db();$db->beginTransaction();
    try {
        $s=$db->prepare('SELECT status FROM aw_applications WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetchColumn();
        if ($old===false) throw new RuntimeException('Permohonan tidak ditemukan.');
        $s=$db->prepare('UPDATE aw_applications SET status=?,staff_notes=? WHERE id=?');$s->execute([$status,$notes,$id]);
        $s=$db->prepare('INSERT INTO aw_history(application_id,previous_status,new_status,actor_id,notes) VALUES (?,?,?,?,?)');
        $s->execute([$id,$old,$status,$u['source'].':'.$u['id'],$notes]);
        $db->commit();log_event('waris_status_updated','aw_applications',(string)$id);
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
function employee_list(array $u): array {
    if (!is_admin($u)) return [];
    $q=db('pegawai')->query('SELECT id,nama_lengkap,username,role,status FROM users ORDER BY role,nama_lengkap LIMIT 250');return $q->fetchAll();
}
function pppk_list(array $u): array {
    if (!permitted('pppk',$u)) return [];
    if ($u['source']==='pancoran' && !is_admin($u)) return []; // sumber id berbeda, tidak dicampur.
    if (is_admin($u)) $q=db('pegawai')->query('SELECT k.id,k.user_id,u.nama_lengkap,k.month_key,k.report_type,k.status FROM ck_pppk_kinerja k LEFT JOIN users u ON u.id=k.user_id ORDER BY k.id DESC LIMIT 100');
    else { $q=db('pegawai')->prepare('SELECT k.id,k.user_id,k.month_key,k.report_type,k.status FROM ck_pppk_kinerja k WHERE k.user_id=? ORDER BY k.id DESC LIMIT 100');$q->execute([$u['id']]); }
    return $q->fetchAll();
}
function pjlp_list(array $u): array {
    if (!permitted('pjlp',$u)) return [];
    if (is_admin($u)) $q=db('pegawai')->query('SELECT d.session_id,d.user_id,u.nama_lengkap,d.work_date,d.route_no,d.planned_start,d.planned_end FROM pjlp_duty_session d LEFT JOIN users u ON u.id=d.user_id ORDER BY d.work_date DESC LIMIT 100');
    elseif ($u['source']==='pegawai') { $q=db('pegawai')->prepare('SELECT d.session_id,d.user_id,d.work_date,d.route_no,d.planned_start,d.planned_end FROM pjlp_duty_session d WHERE d.user_id=? ORDER BY d.work_date DESC LIMIT 100'); $q->execute([$u['id']]); }
    else return [];
    return $q->fetchAll();
}
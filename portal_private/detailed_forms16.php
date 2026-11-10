<?php
declare(strict_types=1);
/**
 * Tahap 16 — formulir terstruktur berdasarkan field arsip lama, TANPA menulis
 * ke tabel cuti / ck_pppk_kinerja / laporan / pjlp_duty_session yang asli.
 * Dokumen/foto/GPS dilarang sampai scanner dan otorisasi siap.
 */
function f16_kinds():array {
    return [
        'cuti'=>['name'=>'Pengajuan Cuti Tahunan','tag'=>'Pegawai','native'=>'cuti + cuti_detail','fields'=>[
            'tanggal_mulai'=>['label'=>'Tanggal mulai cuti','type'=>'date','required'=>true],
            'tanggal_selesai'=>['label'=>'Tanggal selesai cuti','type'=>'date','required'=>true],
            'alasan'=>['label'=>'Alasan permohonan','type'=>'textarea','required'=>true,'max'=>1500],
            'alamat_cuti'=>['label'=>'Alamat selama cuti','type'=>'textarea','required'=>true,'max'=>500],
            'phone'=>['label'=>'Nomor kontak selama cuti','type'=>'tel','required'=>true,'max'=>40],
            'jabatan'=>['label'=>'Jabatan / Jenis Pegawai','type'=>'text','required'=>true,'max'=>150],
            'unit_kerja'=>['label'=>'Unit kerja','type'=>'text','required'=>true,'max'=>180],
            'masa_kerja'=>['label'=>'Masa kerja','type'=>'text','required'=>false,'max'=>100],
            'kota_surat'=>['label'=>'Kota pembuatan surat','type'=>'text','required'=>true,'max'=>100],
            'tanggal_surat'=>['label'=>'Tanggal surat','type'=>'date','required'=>true],
            'lama_cuti'=>['label'=>'Keterangan lama cuti','type'=>'text','required'=>false,'max'=>100],
            'catatan_saldo'=>['label'=>'Catatan saldo cuti tahun berjalan (opsional)','type'=>'textarea','required'=>false,'max'=>500],
        ]],
        'pppk'=>['name'=>'Kinerja PPPK Bulanan','tag'=>'Kepegawaian','native'=>'ck_pppk_kinerja','fields'=>[
            'bulan'=>['label'=>'Periode laporan','type'=>'month','required'=>true],
            'jenis_laporan'=>['label'=>'Jenis laporan','type'=>'select','required'=>true,'options'=>['admin'=>'Administrasi','lapangan'=>'Lapangan']],
            'indikator'=>['label'=>'Indikator / target pekerjaan','type'=>'textarea','required'=>true,'max'=>1000],
            'uraian'=>['label'=>'Uraian kegiatan','type'=>'textarea','required'=>true,'max'=>3000],
            'hasil'=>['label'=>'Hasil / keluaran pekerjaan','type'=>'textarea','required'=>true,'max'=>1500],
            'kendala'=>['label'=>'Kendala yang ditemukan','type'=>'textarea','required'=>false,'max'=>1500],
            'tindak_lanjut'=>['label'=>'Rencana tindak lanjut','type'=>'textarea','required'=>false,'max'=>1500],
        ]],
        'pjlp'=>['name'=>'Kegiatan PJLP / PPSU','tag'=>'Kepegawaian','native'=>'pjlp_duty_session + laporan + pjlp_task_photos','fields'=>[
            'tanggal_tugas'=>['label'=>'Tanggal pelaksanaan','type'=>'date','required'=>true],
            'session_id'=>['label'=>'ID sesi tugas (opsional, harus milik sendiri)','type'=>'number','required'=>false,'max'=>16],
            'jalur'=>['label'=>'Jalur / rute tugas','type'=>'text','required'=>true,'max'=>120],
            'jam_mulai'=>['label'=>'Jam mulai','type'=>'time','required'=>true],
            'jam_selesai'=>['label'=>'Jam selesai','type'=>'time','required'=>true],
            'jenis_kegiatan'=>['label'=>'Jenis pekerjaan','type'=>'text','required'=>true,'max'=>160],
            'uraian'=>['label'=>'Uraian kegiatan lapangan','type'=>'textarea','required'=>true,'max'=>3000],
            'hasil'=>['label'=>'Hasil / capaian','type'=>'textarea','required'=>true,'max'=>1500],
            'kendala'=>['label'=>'Kendala / catatan','type'=>'textarea','required'=>false,'max'=>1000],
        ]],
    ];
}
function f16_stages():array {
    return ['draf'=>'Draf','diajukan'=>'Diajukan','ditinjau'=>'Ditinjau',
        'perbaikan'=>'Perlu Perbaikan','ditutup'=>'Ditutup Administratif'];
}
function f16_authorized(array $actor,string $kind):bool {
    if(!isset(f16_kinds()[$kind]))return false;
    $source=(string)($actor['source']??'');$role=(string)($actor['role']??'');
    $allowed=match($kind) {
        'cuti'=>$source==='pegawai' && in_array($role,['asn','pppk','pjlp'],true),
        'pppk'=>($source==='pegawai'&&$role==='pppk')||($source==='pancoran'&&$role==='pppk_pancoran'),
        'pjlp'=>$source==='pegawai'&&$role==='pjlp',
        default=>false,
    };
    return $allowed;
}
function f16_guard(array $actor,string $kind):void {
    if(!f16_authorized($actor,$kind))throw new RuntimeException('Akun tidak berwenang mengisi formulir ini.');
    if(!function_exists('account_control_allowed'))require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))throw new RuntimeException('Akses akun belum diizinkan.');
    if(!function_exists('account_control_permission_decision'))require_once __DIR__.'/account_control.php';
    $ability=$kind==='cuti'?'pegawai':$kind;
    if(account_control_permission_decision($actor,$ability)===false)
        throw new RuntimeException('Izin pengisian formulir dinonaktifkan.');
}
function f16_date(string $date):string {
    if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])-[0-3]\d$/D',$date))throw new InvalidArgumentException('Tanggal tidak sesuai.');
    [$y,$m,$d]=array_map('intval',explode('-',$date));
    if(!checkdate($m,$d,$y))throw new InvalidArgumentException('Tanggal kalender tidak valid.');
    return $date;
}
function f16_month(string $m):string {
    if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$m))throw new InvalidArgumentException('Periode bulanan tidak valid.');
    return $m;
}
function f16_validate(string $kind,array $values):array {
    $spec=f16_kinds()[$kind]??null;
    if($spec===null)throw new InvalidArgumentException('Jenis formulir tidak tersedia.');
    $result=[];
    foreach($spec['fields'] as $key=>$f){
        $value=$values[$key]??'';
        if(!is_string($value)&&!is_numeric($value))throw new InvalidArgumentException('Input bidang tidak valid.');
        $value=trim((string)$value);
        $max=(int)($f['max']??(in_array($f['type'],['date','month','time'],true)?20:250));
        if(mb_strlen($value)>$max)throw new InvalidArgumentException($f['label'].' terlalu panjang.');
        if(!empty($f['required'])&&$value==='')throw new InvalidArgumentException($f['label'].' wajib diisi.');
        if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$value))throw new InvalidArgumentException('Karakter kontrol tidak diperbolehkan.');
        if($value!==''){
            if($f['type']==='date')f16_date($value);
            elseif($f['type']==='month')f16_month($value);
            elseif($f['type']==='time'&&!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$value))
                throw new InvalidArgumentException('Format jam harus HH:MM.');
            elseif($f['type']==='number' && (!ctype_digit($value)||strlen($value)>16||(int)$value<1))
                throw new InvalidArgumentException('Nomor sesi harus angka positif.');
            elseif($f['type']==='select'&&!isset($f['options'][$value]))
                throw new InvalidArgumentException('Pilihan jenis laporan tidak tersedia.');
        }
        $result[$key]=$value;
    }
    if($kind==='cuti'){
        if($result['tanggal_selesai']<$result['tanggal_mulai'])throw new InvalidArgumentException('Tanggal cuti selesai lebih awal daripada mulai.');
        if($result['tanggal_mulai']<'2020-01-01')throw new InvalidArgumentException('Tanggal cuti terlalu lama.');
        $duration=(new DateTimeImmutable($result['tanggal_mulai']))->diff(new DateTimeImmutable($result['tanggal_selesai']))->days;
        if($duration>90)throw new InvalidArgumentException('Rentang cuti melebihi 90 hari kalender; gunakan prosedur khusus.');
        if(mb_strlen($result['alasan'])<10)throw new InvalidArgumentException('Alasan cuti minimal 10 karakter.');
    }
    if($kind==='pppk'){
        if($result['bulan']<'2020-01')throw new InvalidArgumentException('Periode terlalu lama.');
        if(mb_strlen($result['uraian'])<25)throw new InvalidArgumentException('Uraian kegiatan minimal 25 karakter.');
    }
    if($kind==='pjlp'){
        if($result['jam_selesai']===$result['jam_mulai'])throw new InvalidArgumentException('Waktu mulai dan selesai tidak boleh sama.');
        if(mb_strlen($result['uraian'])<25)throw new InvalidArgumentException('Uraian kegiatan minimal 25 karakter.');
    }
    return $result;
}
function f16_ready():bool {
    try{
        foreach(['unified_detailed_forms16','unified_detailed_form_history16'] as $table)
            db('smartapp')->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function f16_session_check(array $actor,string $kind,array $data):void {
    if($kind!=='pjlp'||$data['session_id']==='')return;
    if($actor['source']!=='pegawai')throw new RuntimeException('Sesi PJLP tidak berlaku untuk sumber akun ini.');
    $q=db('pegawai')->prepare('SELECT work_date FROM pjlp_duty_session WHERE session_id=? AND user_id=? LIMIT 1');
    $q->execute([$data['session_id'],(int)$actor['id']]);
    $day=$q->fetchColumn();
    if($day===false||$day!==$data['tanggal_tugas'])
        throw new RuntimeException('Sesi tugas harus milik Anda dan tanggalnya sesuai.');
}
function f16_create(array $actor,string $kind,array $values,string $nonce,bool $submit):int {
    f16_guard($actor,$kind);
    $data=f16_validate($kind,$values);
    f16_session_check($actor,$kind,$data);
    if(!preg_match('/^[a-f0-9]{32}$/D',$nonce))throw new InvalidArgumentException('Kunci penyimpanan tidak valid.');
    $db=db('smartapp');$source=(string)$actor['source'];$id=(string)$actor['id'];
    $q=$db->prepare('SELECT id FROM unified_detailed_forms16 WHERE actor_source=? AND actor_id=? AND request_key=?');
    $q->execute([$source,$id,$nonce]);$exists=$q->fetchColumn();
    if($exists!==false)return (int)$exists;
    $q=$db->prepare('SELECT COUNT(*) FROM unified_detailed_forms16 WHERE actor_source=? AND actor_id=? AND created_at>=CURRENT_DATE()');
    $q->execute([$source,$id]);if((int)$q->fetchColumn()>=10)throw new RuntimeException('Batas pembuatan formulir harian tercapai.');
    $db->beginTransaction();
    try {
        $status=$submit?'diajukan':'draf';
        $q=$db->prepare('INSERT INTO unified_detailed_forms16(kind,actor_source,actor_id,request_key,payload_json,status,version,created_at,updated_at,submitted_at) VALUES(?,?,?,?,?,?,1,NOW(),NOW(),?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $q->execute([$kind,$source,$id,$nonce,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,$submit?date('Y-m-d H:i:s'):null]);
        $newId=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO unified_detailed_form_history16(form_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$newId,'',$status,'Formulir dibuat.',$source,$id]);
        $db->commit();log_event('detailed_form_created','unified_detailed_forms16',(string)$newId);
        return $newId;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function f16_list(array $actor,string $kind='',int $page=1,int $limit=15):array {
    if($kind!==''&&!isset(f16_kinds()[$kind]))throw new InvalidArgumentException('Kategori tidak tersedia.');
    $admin=is_super_admin($actor);$page=max(1,min($page,10000));$limit=max(1,min($limit,25));
    $where=[];$params=[];
    if(!$admin){$where[]='actor_source=? AND actor_id=?';$params[]=$actor['source'];$params[]=(string)$actor['id'];}
    if($kind!==''){$where[]='kind=?';$params[]=$kind;}
    $clause=$where?' WHERE '.implode(' AND ',$where):'';
    $q=db('smartapp')->prepare('SELECT COUNT(*) FROM unified_detailed_forms16'.$clause);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);
    $q=db('smartapp')->prepare('SELECT id,kind,actor_source,actor_id,payload_json,status,review_note,version,created_at,updated_at FROM unified_detailed_forms16'.$clause.' ORDER BY id DESC LIMIT ? OFFSET ?');
    $i=1;foreach($params as $p)$q->bindValue($i++,$p,PDO::PARAM_STR);
    $q->bindValue($i++,$limit,PDO::PARAM_INT);$q->bindValue($i,($page-1)*$limit,PDO::PARAM_INT);$q->execute();
    return ['total'=>$total,'page'=>$page,'pages'=>$pages,'rows'=>$q->fetchAll(PDO::FETCH_ASSOC)];
}
function f16_read_one(array $actor,int $id):?array {
    if($id<1)return null;
    $q=db('smartapp')->prepare('SELECT * FROM unified_detailed_forms16 WHERE id=? LIMIT 1');$q->execute([$id]);
    $r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
    if(!is_super_admin($actor)&&(!hash_equals((string)$r['actor_id'],(string)$actor['id'])||$r['actor_source']!==$actor['source']))
        return null;
    return $r;
}
function f16_update(array $actor,int $id,int $version,array $values,bool $submit):void {
    if($id<1||$version<1)throw new InvalidArgumentException('Identitas formulir tidak valid.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT kind,actor_source,actor_id,status,version FROM unified_detailed_forms16 WHERE id=? FOR UPDATE');
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['actor_source']!==$actor['source']||!hash_equals((string)$row['actor_id'],(string)$actor['id']))
            throw new RuntimeException('Formulir bukan milik Anda.');
        f16_guard($actor,(string)$row['kind']);
        if(!in_array($row['status'],['draf','perbaikan'],true))
            throw new RuntimeException('Formulir sudah dikirim dan tidak dapat diedit.');
        if((int)$row['version']!==$version)throw new RuntimeException('Versi formulir telah berubah. Muat ulang halaman.');
        $data=f16_validate((string)$row['kind'],$values);
        f16_session_check($actor,(string)$row['kind'],$data);
        $status=$submit?'diajukan':$row['status'];
        $q=$db->prepare('UPDATE unified_detailed_forms16 SET payload_json=?,status=?,version=version+1,review_note=CASE WHEN ? THEN NULL ELSE review_note END,submitted_at=CASE WHEN ? THEN NOW() ELSE submitted_at END,updated_at=NOW() WHERE id=?');
        $q->execute([json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,$submit?1:0,$submit?1:0,$id]);
        $q=$db->prepare('INSERT INTO unified_detailed_form_history16(form_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$id,$row['status'],$status,$submit?'Formulir dikirim ulang.':'Draf diperbarui.',$actor['source'],(string)$actor['id']]);
        $db->commit();log_event('detailed_form_updated','unified_detailed_forms16',(string)$id);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function f16_review(array $actor,int $id,string $status,string $note):void {
    if(!is_super_admin($actor))throw new RuntimeException('Hanya Super Admin dapat meninjau formulir.');
    if(!in_array($status,['ditinjau','perbaikan','ditutup'],true)||$id<1)throw new InvalidArgumentException('Pilihan peninjauan tidak valid.');
    if(mb_strlen($note)>500)throw new InvalidArgumentException('Catatan terlalu panjang.');
    if($status==='perbaikan'&&mb_strlen(trim($note))<8)throw new InvalidArgumentException('Alasan perbaikan wajib dijelaskan.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT status FROM unified_detailed_forms16 WHERE id=? FOR UPDATE');$q->execute([$id]);
        $old=$q->fetchColumn();
        if($old===false)throw new RuntimeException('Formulir tidak ditemukan.');
        if(!in_array($old,['diajukan','ditinjau'],true))throw new RuntimeException('Status formulir tidak dapat ditinjau.');
        $q=$db->prepare('UPDATE unified_detailed_forms16 SET status=?,review_note=?,reviewed_by_source=?,reviewed_by_id=?,updated_at=NOW() WHERE id=?');
        $q->execute([$status,trim($note),$actor['source'],(string)$actor['id'],$id]);
        $q=$db->prepare('INSERT INTO unified_detailed_form_history16(form_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$id,$old,$status,trim($note),$actor['source'],(string)$actor['id']]);
        $db->commit();log_event('detailed_form_reviewed','unified_detailed_forms16',(string)$id);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function f16_native_for_actor(array $actor,string $kind):array {
    // Strictly scoped to the native Pegawai account; Pancoran uses separate IDs.
    if(($actor['source']??'')!=='pegawai'||!ctype_digit((string)($actor['id']??'')))return [];
    if(!f16_authorized($actor,$kind))return [];
    $uid=(int)$actor['id'];
    $sql=match($kind){
        'cuti'=>'SELECT id,jenis_cuti,tanggal_mulai,tanggal_selesai,status,created_at FROM cuti WHERE user_id=? ORDER BY id DESC LIMIT 10',
        'pppk'=>'SELECT id,month_key,report_type,status,submitted_at FROM ck_pppk_kinerja WHERE user_id=? ORDER BY id DESC LIMIT 10',
        'pjlp'=>'SELECT session_id,work_date,route_no,planned_start,planned_end FROM pjlp_duty_session WHERE user_id=? ORDER BY work_date DESC LIMIT 10',
        default=>null,
    };
    if($sql===null)return [];
    try{$q=db('pegawai')->prepare($sql);$q->execute([$uid]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    catch(Throwable $e){error_log('Stage 16 native source unavailable: '.$kind);return [];}
}

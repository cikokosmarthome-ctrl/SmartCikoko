<?php
declare(strict_types=1);
/**
 * Tahap 17 — formulir wilayah & kesehatan terstruktur.
 * Hanya menulis ke unified_field_reports17 dan riwayat baru.
 * Tabel mobile_rt_rw_letters/mobile_reports/mobile_fkdm_reports/
 * mobile_ppsu_reports tidak pernah diubah.
 */
function w17_catalog():array {
    return [
        'rt_rw'=>['name'=>'Laporan Kegiatan RT/RW','group'=>'Kewilayahan',
            'legacy'=>'mobile_rt_rw_letters (surat; BUKAN laporan kegiatan)',
            'note'=>'Laporan kegiatan pengurus. Tidak menerbitkan atau menyetujui surat pengantar.',
            'fields'=>[
                'tanggal'=>['label'=>'Tanggal kegiatan','type'=>'date','required'=>true],
                'rt'=>['label'=>'Nomor RT','type'=>'rt','required'=>true],
                'rw'=>['label'=>'Nomor RW','type'=>'rt','required'=>true],
                'jenis'=>['label'=>'Jenis kegiatan','type'=>'select','required'=>true,'options'=>['rapat'=>'Rapat warga','sosialisasi'=>'Sosialisasi','kerja_bakti'=>'Kerja bakti','pendataan'=>'Pendataan agregat','pemantauan'=>'Pemantauan wilayah','lain'=>'Kegiatan lain']],
                'lokasi'=>['label'=>'Tempat kegiatan (tanpa alamat pribadi)','type'=>'text','required'=>true,'max'=>180],
                'uraian'=>['label'=>'Uraian kegiatan','type'=>'textarea','required'=>true,'max'=>2000],
                'hasil'=>['label'=>'Hasil / tindak lanjut','type'=>'textarea','required'=>true,'max'=>1200],
            ]],
        'lmk'=>['name'=>'Laporan Kegiatan LMK','group'=>'Kewilayahan',
            'legacy'=>'Tidak ditemukan tabel laporan LMK setara pada SQL lama',
            'note'=>'Draf kegiatan LMK; bukan keputusan musyawarah atau dokumen yang disahkan.',
            'fields'=>[
                'tanggal'=>['label'=>'Tanggal kegiatan','type'=>'date','required'=>true],
                'wilayah'=>['label'=>'Wilayah / RW','type'=>'text','required'=>true,'max'=>100],
                'bidang'=>['label'=>'Bidang kegiatan','type'=>'select','required'=>true,'options'=>['aspirasi'=>'Aspirasi masyarakat','musyawarah'=>'Musyawarah','pembangunan'=>'Usulan pembangunan','monitoring'=>'Pemantauan','lain'=>'Kegiatan lain']],
                'lokasi'=>['label'=>'Tempat / lokasi umum','type'=>'text','required'=>true,'max'=>180],
                'uraian'=>['label'=>'Uraian aspirasi / kegiatan','type'=>'textarea','required'=>true,'max'=>2000],
                'tindak_lanjut'=>['label'=>'Usulan dan rencana tindak lanjut','type'=>'textarea','required'=>true,'max'=>1500],
            ]],
        'jumantik'=>['name'=>'Pemantauan Jentik / Jumantik','group'=>'Kesehatan',
            'legacy'=>'mobile_reports (jenis jumantik; termasuk laporan warga)',
            'note'=>'Data pemantauan AGREGAT; tanpa nama warga, nomor rumah, atau foto. Laporan warga bukan verifikasi kader.',
            'fields'=>[
                'tanggal'=>['label'=>'Tanggal pemantauan','type'=>'date','required'=>true],
                'rt'=>['label'=>'RT pemantauan','type'=>'rt','required'=>true],
                'rw'=>['label'=>'RW pemantauan','type'=>'rt','required'=>true],
                'lokasi'=>['label'=>'Area / lingkungan umum','type'=>'text','required'=>true,'max'=>150],
                'rumah_diperiksa'=>['label'=>'Jumlah rumah diperiksa','type'=>'count','required'=>true,'max'=>10000],
                'rumah_positif'=>['label'=>'Rumah ditemukan jentik','type'=>'count','required'=>true,'max'=>10000],
                'kontainer_diperiksa'=>['label'=>'Jumlah tempat penampungan diperiksa','type'=>'count','required'=>true,'max'=>50000],
                'kontainer_positif'=>['label'=>'Tempat penampungan positif jentik','type'=>'count','required'=>true,'max'=>50000],
                'psn'=>['label'=>'Tindakan PSN / edukasi','type'=>'textarea','required'=>true,'max'=>1500],
                'catatan'=>['label'=>'Catatan umum (tanpa identitas warga)','type'=>'textarea','required'=>false,'max'=>1000],
            ]],
        'fkdm'=>['name'=>'Laporan Kegiatan FKDM','group'=>'Kewilayahan',
            'legacy'=>'mobile_fkdm_reports (anggota, lokasi, kegiatan, foto)',
            'note'=>'Laporan administratif berbasis kegiatan. Nomor anggota tidak wajib; tidak mengunggah foto atau data sensitif.',
            'fields'=>[
                'tanggal'=>['label'=>'Tanggal kegiatan','type'=>'date','required'=>true],
                'nomor_anggota'=>['label'=>'Nomor anggota FKDM (opsional)','type'=>'text','required'=>false,'max'=>80],
                'lokasi'=>['label'=>'Lokasi kegiatan umum','type'=>'text','required'=>true,'max'=>180],
                'jenis'=>['label'=>'Jenis pemantauan','type'=>'select','required'=>true,'options'=>['deteksi'=>'Deteksi dini','koordinasi'=>'Koordinasi wilayah','sosialisasi'=>'Sosialisasi','lain'=>'Lainnya']],
                'uraian'=>['label'=>'Ringkasan kegiatan','type'=>'textarea','required'=>true,'max'=>2000],
                'tindak_lanjut'=>['label'=>'Tindak lanjut / rekomendasi umum','type'=>'textarea','required'=>true,'max'=>1200],
            ]],
        'ppsu'=>['name'=>'Laporan Kegiatan PPSU','group'=>'Kewilayahan',
            'legacy'=>'mobile_ppsu_reports (tim, tanggal, kegiatan, foto)',
            'note'=>'Laporan kegiatan ringkas; bukan bukti tugas/kehadiran ataupun pengganti foto lapangan yang diwajibkan.',
            'fields'=>[
                'tanggal'=>['label'=>'Tanggal kerja','type'=>'date','required'=>true],
                'tim'=>['label'=>'Tim / regu','type'=>'text','required'=>true,'max'=>80],
                'lokasi'=>['label'=>'Area kerja umum','type'=>'text','required'=>true,'max'=>180],
                'kegiatan'=>['label'=>'Jenis pekerjaan','type'=>'select','required'=>true,'options'=>['kebersihan'=>'Kebersihan','saluran'=>'Saluran air','penghijauan'=>'Penghijauan','perbaikan'=>'Perbaikan ringan','lain'=>'Pekerjaan lainnya']],
                'uraian'=>['label'=>'Uraian pekerjaan','type'=>'textarea','required'=>true,'max'=>2000],
                'hasil'=>['label'=>'Hasil / volume pekerjaan (teks)','type'=>'textarea','required'=>true,'max'=>1200],
                'kendala'=>['label'=>'Kendala pekerjaan','type'=>'textarea','required'=>false,'max'=>800],
            ]],
    ];
}
function w17_statuses():array{return [
    'draf'=>'Draf','diajukan'=>'Masuk','ditinjau'=>'Ditinjau',
    'perbaikan'=>'Perlu Perbaikan','ditutup'=>'Ditutup Administratif',
];}
function w17_actor_can(array $actor,string $kind):bool {
    if(!isset(w17_catalog()[$kind]))return false;
    $src=(string)($actor['source']??'');$role=(string)($actor['role']??'');
    return match($kind) {
        'rt_rw'=>$src==='smartapp'&&in_array($role,['rt','rw'],true),
        'lmk'=>$src==='smartapp'&&$role==='lmk',
        // Old citizen reporting form accepted kind=jumantik from role=warga.
        // 'warga' submissions are classified as community observations, not official field inspection.
        'jumantik'=>$src==='smartapp'&&in_array($role,['jumantik','warga'],true),
        'fkdm'=>$src==='smartapp'&&$role==='fkdm',
        'ppsu'=>($src==='smartapp'&&$role==='ppsu')||($src==='pegawai'&&$role==='pjlp'),
        default=>false,
    };
}
function w17_guard(array $actor,string $kind):void {
    if(!w17_actor_can($actor,$kind))throw new RuntimeException('Kategori ini bukan kewenangan akun Anda.');
    require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))throw new RuntimeException('Status akun tidak diizinkan.');
    // Honor existing explicit deny for PJLP personnel; other SmartApp roles
    // currently have no category overrides in the Stage-6 permission table.
    if($kind==='ppsu'&&$actor['source']==='pegawai'
       &&account_control_permission_decision($actor,'pjlp')===false)
        throw new RuntimeException('Akses modul PJLP/PPSU dinonaktifkan.');
}
function w17_validate(string $kind,array $raw):array {
    $form=w17_catalog()[$kind]??null;
    if(!$form)throw new InvalidArgumentException('Jenis laporan tidak tersedia.');
    $out=[];
    foreach($form['fields'] as $key=>$field){
        $v=$raw[$key]??'';
        if(!is_string($v)&&!is_int($v))throw new InvalidArgumentException('Tipe bidang tidak valid.');
        $v=trim((string)$v);
        $type=$field['type'];
        $max=(int)($field['max']??255);
        if(!empty($field['required'])&&$v==='')throw new InvalidArgumentException($field['label'].' wajib diisi.');
        if(mb_strlen($v)>($type==='count'?8:$max))throw new InvalidArgumentException($field['label'].' terlalu panjang.');
        if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$v))throw new InvalidArgumentException('Karakter kontrol tidak diizinkan.');
        if($v!==''){
            if($type==='date'){
                if(!preg_match('/^20\d\d-\d\d-\d\d$/D',$v))throw new InvalidArgumentException('Tanggal tidak valid.');
                [$y,$m,$d]=array_map('intval',explode('-',$v));
                if(!checkdate($m,$d,$y))throw new InvalidArgumentException('Tanggal kalender tidak valid.');
            }elseif($type==='rt'){
                if(!preg_match('/^\d{1,3}$/D',$v)||(int)$v<1||(int)$v>999)
                    throw new InvalidArgumentException('RT/RW harus angka 1 sampai 999.');
                $v=str_pad((string)(int)$v,2,'0',STR_PAD_LEFT);
            }elseif($type==='count'){
                if(!ctype_digit($v)||strlen($v)>8||(int)$v>(int)$max)
                    throw new InvalidArgumentException('Jumlah harus berupa angka dalam rentang yang diperbolehkan.');
                $v=(string)(int)$v;
            }elseif($type==='select'){
                if(!isset($field['options'][$v]))throw new InvalidArgumentException('Jenis kegiatan tidak tersedia.');
            }elseif(mb_strlen($v)>$max){
                throw new InvalidArgumentException($field['label'].' terlalu panjang.');
            }
        }
        $out[$key]=$v;
    }
    foreach(['uraian','hasil','tindak_lanjut','psn'] as $key)
        if(isset($out[$key])&&$out[$key]!==''&&mb_strlen($out[$key])<12)
            throw new InvalidArgumentException('Uraian kegiatan / hasil minimal 12 karakter.');
    if($kind==='jumantik'){
        if((int)$out['rumah_positif']>(int)$out['rumah_diperiksa']
            ||(int)$out['kontainer_positif']>(int)$out['kontainer_diperiksa'])
            throw new InvalidArgumentException('Jumlah positif tidak boleh melebihi jumlah yang diperiksa.');
        if((int)$out['rumah_diperiksa']===0)
            throw new InvalidArgumentException('Jumlah rumah diperiksa minimal satu.');
    }
    return $out;
}
function w17_ready():bool{
    try {
        foreach(['unified_field_reports17','unified_field_history17'] as $table)
            db('smartapp')->query("SELECT 1 FROM $table LIMIT 1")->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function w17_own(array $actor,array $item):bool{
    return (string)$actor['source']===(string)$item['actor_source']
        &&hash_equals((string)$actor['id'],(string)$item['actor_id']);
}
function w17_submit(array $actor,string $kind,array $raw,string $nonce,bool $send):int{
    w17_guard($actor,$kind);
    $payload=w17_validate($kind,$raw);
    if(!preg_match('/^[a-f0-9]{32}$/D',$nonce))throw new InvalidArgumentException('Kunci formulir tidak valid.');
    $source=(string)$actor['source'];$id=(string)$actor['id'];
    if($id===''||strlen($id)>100)throw new RuntimeException('Identitas akun tidak berlaku.');
    $db=db('smartapp');
    $q=$db->prepare('SELECT id FROM unified_field_reports17 WHERE actor_source=? AND actor_id=? AND request_key=?');
    $q->execute([$source,$id,$nonce]);$prior=$q->fetchColumn();
    if($prior!==false)return (int)$prior;
    $q=$db->prepare('SELECT COUNT(*) FROM unified_field_reports17 WHERE actor_source=? AND actor_id=? AND created_at>=CURRENT_DATE()');
    $q->execute([$source,$id]);
    if((int)$q->fetchColumn()>=12)throw new RuntimeException('Batas 12 formulir harian tercapai.');
    $db->beginTransaction();
    try{
        $state=$send?'diajukan':'draf';
        $q=$db->prepare("INSERT INTO unified_field_reports17(kind,actor_source,actor_id,request_key,payload_json,status,version,created_at,updated_at,submitted_at) VALUES(?,?,?,?,?,?,1,NOW(),NOW(),?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $q->execute([$kind,$source,$id,$nonce,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$state,$send?date('Y-m-d H:i:s'):null]);
        $reportId=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO unified_field_history17(report_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$reportId,'',$state,'Formulir baru.',$source,$id]);
        $db->commit();
        log_event('field17_submitted','field_reports17',(string)$reportId);
        return $reportId;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function w17_list(array $actor,string $kind='',int $page=1,int $per=12):array {
    if($kind!==''&&!isset(w17_catalog()[$kind]))throw new InvalidArgumentException('Jenis laporan tidak berlaku.');
    $isManager=is_super_admin($actor);
    $where=[];$params=[];
    if(!$isManager){$where[]='actor_source=? AND actor_id=?';$params[]=$actor['source'];$params[]=(string)$actor['id'];}
    else{$where[]="status<>'draf'";} // Draf pribadi belum boleh dipantau sebelum dikirim.
    if($kind!==''){$where[]='kind=?';$params[]=$kind;}
    $filter=$where?' WHERE '.implode(' AND ',$where):'';
    $db=db('smartapp');$q=$db->prepare('SELECT COUNT(*) FROM unified_field_reports17'.$filter);$q->execute($params);
    $count=(int)$q->fetchColumn();
    $per=max(1,min(25,$per));$pages=max(1,(int)ceil($count/$per));$page=max(1,min($pages,$page));
    $q=$db->prepare('SELECT id,kind,actor_source,actor_id,payload_json,status,review_note,version,created_at,updated_at FROM unified_field_reports17'.$filter.' ORDER BY id DESC LIMIT ? OFFSET ?');
    $n=1;foreach($params as $p)$q->bindValue($n++,$p,PDO::PARAM_STR);
    $q->bindValue($n++,$per,PDO::PARAM_INT);$q->bindValue($n,($page-1)*$per,PDO::PARAM_INT);
    $q->execute();
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$count,'page'=>$page,'pages'=>$pages];
}
function w17_get_own(array $actor,int $id):?array{
    if($id<1)return null;
    $q=db('smartapp')->prepare('SELECT * FROM unified_field_reports17 WHERE id=? AND actor_source=? AND actor_id=?');
    $q->execute([$id,$actor['source'],(string)$actor['id']]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    return $r?:null;
}
function w17_edit(array $actor,int $id,int $version,array $raw,bool $send):void{
    if($id<1||$version<1)throw new InvalidArgumentException('Formulir tidak valid.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM unified_field_reports17 WHERE id=? FOR UPDATE');$q->execute([$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||!w17_own($actor,$row))throw new RuntimeException('Formulir ini bukan milik Anda.');
        w17_guard($actor,(string)$row['kind']);
        if(!in_array($row['status'],['draf','perbaikan'],true))
            throw new RuntimeException('Laporan sudah diajukan dan tidak dapat diedit.');
        if((int)$row['version']!==$version)throw new RuntimeException('Versi laporan berubah. Muat ulang.');
        $payload=w17_validate((string)$row['kind'],$raw);
        $next=$send?'diajukan':$row['status'];
        $q=$db->prepare('UPDATE unified_field_reports17 SET payload_json=?,status=?,version=version+1,review_note=CASE WHEN ? THEN NULL ELSE review_note END,submitted_at=CASE WHEN ? THEN NOW() ELSE submitted_at END,updated_at=NOW() WHERE id=?');
        $q->execute([json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$next,(int)$send,(int)$send,$id]);
        $q=$db->prepare('INSERT INTO unified_field_history17(report_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$id,$row['status'],$next,$send?'Perbaikan/draf dikirim.':'Draf disimpan.',$actor['source'],(string)$actor['id']]);
        $db->commit();log_event('field17_updated','field_reports17',(string)$id);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function w17_review(array $admin,int $id,string $state,string $note):void{
    if(!is_super_admin($admin))throw new RuntimeException('Peninjauan hanya untuk Super Admin.');
    if(!in_array($state,['ditinjau','perbaikan','ditutup'],true)||$id<1)
        throw new InvalidArgumentException('Status peninjauan tidak valid.');
    if(mb_strlen($note)>500)throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
    if($state==='perbaikan'&&mb_strlen(trim($note))<8)
        throw new InvalidArgumentException('Alasan perbaikan wajib diisi.');
    $db=db('smartapp');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT status FROM unified_field_reports17 WHERE id=? FOR UPDATE');
        $q->execute([$id]);$old=$q->fetchColumn();
        if($old===false)throw new RuntimeException('Laporan tidak ditemukan.');
        if(!in_array($old,['diajukan','ditinjau'],true))
            throw new RuntimeException('Draf/perbaikan/arsip tidak boleh ditinjau langsung.');
        $q=$db->prepare('UPDATE unified_field_reports17 SET status=?,review_note=?,reviewed_by_source=?,reviewed_by_id=?,updated_at=NOW() WHERE id=?');
        $q->execute([$state,trim($note),$admin['source'],(string)$admin['id'],$id]);
        $q=$db->prepare('INSERT INTO unified_field_history17(report_id,old_status,new_status,note,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $q->execute([$id,$old,$state,trim($note),$admin['source'],(string)$admin['id']]);
        $db->commit();log_event('field17_reviewed','field_reports17',(string)$id);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function w17_native_own(array $actor,string $kind):array{
    // Only read public/workflow metadata, not identities or photos.
    // LMK did not have a matching native report table in the supplied SQL.
    if($actor['source']!=='smartapp'||!w17_actor_can($actor,$kind))return [];
    $queries=match($kind){
        'rt_rw'=>['SELECT id,rt,rw,subject,status,created_at FROM mobile_rt_rw_letters WHERE rt_officer_id=? OR rw_officer_id=? ORDER BY id DESC LIMIT 10',[(string)$actor['id'],(string)$actor['id']]],
        'jumantik'=>["SELECT id,kind,status,created_at FROM mobile_reports WHERE user_id=? AND kind='jumantik' ORDER BY id DESC LIMIT 10",[(string)$actor['id']]],
        'fkdm'=>['SELECT id,location,created_at FROM mobile_fkdm_reports WHERE user_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 10',[(string)$actor['id']]],
        'ppsu'=>['SELECT id,team,work_date,created_at FROM mobile_ppsu_reports WHERE user_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 10',[(string)$actor['id']]],
        default=>null,
    };
    if(!$queries)return [];
    try{
        $q=db('smartapp')->prepare($queries[0]);$q->execute($queries[1]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }catch(Throwable $e){error_log('Stage 17 native report metadata unavailable: '.$kind);return [];}
}
function w17_summary():array {
    $result=array_fill_keys(array_keys(w17_catalog()),['total'=>0,'pending'=>0,'revision'=>0]);
    try{
        $q=db('smartapp')->query("SELECT kind,COUNT(*) AS total,
             SUM(status IN ('diajukan','ditinjau')) AS pending,
             SUM(status='perbaikan') AS revision
             FROM unified_field_reports17 GROUP BY kind");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)
            if(isset($result[$row['kind']]))
                $result[$row['kind']]=['total'=>(int)$row['total'],'pending'=>(int)$row['pending'],'revision'=>(int)$row['revision']];
    }catch(Throwable $e){error_log('Stage 17 report summary unavailable.');}
    return $result;
}

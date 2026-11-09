<?php
declare(strict_types=1);
/**
 * Tahap 11: registry and non-destructive integrity checks for Stage 8/10.
 * All writes are additive to new SmartApp archive tables. No delete endpoints.
 * Integrity checks are NOT a malware scanner or official authenticity proof.
 */
function t11_sources():array {
    return [
        'pelayanan'=>[
            'label'=>'Lampiran Pelayanan Lama (Tahap 8)',
            'table'=>'unified_case_files',
            'parent'=>'application_id',
            'dir'=>'private_documents',
        ],
        'pengajuan'=>[
            'label'=>'Lampiran Pengajuan Warga (Tahap 10)',
            'table'=>'unified_citizen_files',
            'parent'=>'request_id',
            'dir'=>'private_requests',
        ],
    ];
}
function t11_source(string $source):array {
    $all=t11_sources();
    if(!isset($all[$source]))throw new InvalidArgumentException('Sumber arsip tidak dikenal.');
    return $all[$source];
}
function t11_review_labels():array {
    return [
        'belum'=>'Belum Diklasifikasi',
        'ditinjau'=>'Sudah Ditinjau',
        'tindak_lanjut'=>'Perlu Tindak Lanjut'
    ];
}
function t11_only_super(array $user):void {
    if(!is_super_admin($user))throw new RuntimeException('Akses arsip terbatas untuk Super Admin.');
}
function t11_file_record(string $source,int $id):?array {
    $cfg=t11_source($source);
    if($id<1)return null;
    $q=db('smartapp')->prepare('SELECT id,'.$cfg['parent'].' AS parent_id,doc_type,original_name,stored_name,mime_type,file_size,sha256,created_at FROM '.$cfg['table'].' WHERE id=? LIMIT 1');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function t11_root(string $source):string {
    $cfg=t11_source($source);
    $private=realpath(__DIR__);
    if(!$private)throw new RuntimeException('Folder privat tidak tersedia.');
    $folder=$private.'/'.$cfg['dir'];
    if(is_link($folder))throw new RuntimeException('Folder privat tidak boleh symlink.');
    $real=realpath($folder);
    if(!$real || dirname($real)!==$private || !is_dir($real))
        throw new RuntimeException('Folder arsip privat belum ditemukan.');
    return $real;
}
/** Pure filesystem verification. Never trust client-supplied paths. */
function t11_check_content(string $root,array $row):string {
    $token=(string)($row['stored_name']??'');
    $hash=strtolower((string)($row['sha256']??''));
    if(!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',$token) ||
       !preg_match('/^[a-f0-9]{64}$/D',$hash))return 'tidak_aman';
    if(!is_dir($root)||is_link($root))return 'tidak_aman';
    $path=$root.'/'.$token;
    if(is_link($path))return 'tidak_aman';
    if(!is_file($path))return 'hilang';
    $actualPath=realpath($path);
    if(!$actualPath || dirname($actualPath)!==realpath($root))return 'tidak_aman';
    $size=filesize($actualPath);
    if($size===false || $size!==(int)($row['file_size']??-1))return 'berbeda';
    $actual=hash_file('sha256',$actualPath);
    if(!is_string($actual))return 'tidak_aman';
    return hash_equals($hash,$actual)?'sesuai':'berbeda';
}
function t11_check_file(string $source,array $record):string {
    try{return t11_check_content(t11_root($source),$record);}
    catch(Throwable $e){
        error_log('SmartCikoko archive folder check failed: '.$e->getMessage());
        return 'tidak_aman';
    }
}
/** Called from both existing file-download endpoints; fail closed. */
function t11_require_intact(string $source,array $row):void {
    if(t11_check_file($source,$row)!=='sesuai')
        throw new RuntimeException('Integritas lampiran belum memenuhi pemeriksaan.');
}
function t11_inventory(string $source,int $page=1):array {
    $cfg=t11_source($source);
    $page=max(1,min(10000,$page));
    $pdo=db('smartapp');$limit=20;
    $count=(int)$pdo->query('SELECT COUNT(*) FROM '.$cfg['table'])->fetchColumn();
    $pages=max(1,(int)ceil($count/$limit));
    $page=min($page,$pages);
    $q=$pdo->prepare('SELECT f.id,f.'.$cfg['parent'].' AS parent_id,f.doc_type,f.file_size,f.created_at,COALESCE(r.review_status,\'belum\') AS review_status,COALESCE(r.integrity_status,\'belum\') AS integrity_status,COALESCE(r.preserve_flag,0) AS preserve_flag,r.checked_at FROM '.$cfg['table'].' f LEFT JOIN unified_archive_registry r ON r.source_key=? AND r.file_id=f.id ORDER BY f.id DESC LIMIT ? OFFSET ?');
    $q->bindValue(1,$source,PDO::PARAM_STR);
    $q->bindValue(2,$limit,PDO::PARAM_INT);
    $q->bindValue(3,($page-1)*$limit,PDO::PARAM_INT);
    $q->execute();
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$count,'page'=>$page,'pages'=>$pages];
}
function t11_registry(string $source,int $id):?array {
    $q=db('smartapp')->prepare('SELECT review_status,integrity_status,preserve_flag,retention_note,updated_at,checked_at FROM unified_archive_registry WHERE source_key=? AND file_id=?');
    $q->execute([$source,$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function t11_event(PDO $pdo,array $actor,string $source,int $id,string $action,string $summary):void {
    $q=$pdo->prepare('INSERT INTO unified_archive_events(source_key,file_id,event_type,event_summary,actor_source,actor_id,created_at) VALUES(?,?,?,?,?,?,NOW())');
    $q->execute([$source,$id,$action,$summary,(string)$actor['source'],(string)$actor['id']]);
}
function t11_verify(array $actor,string $source,int $id):string {
    t11_only_super($actor);
    $record=t11_file_record($source,$id);
    if(!$record)throw new RuntimeException('Berkas tidak ditemukan.');
    $status=t11_check_file($source,$record);
    $pdo=db('smartapp');$pdo->beginTransaction();
    try{
        $q=$pdo->prepare('INSERT INTO unified_archive_registry(source_key,file_id,integrity_status,checked_at,updated_at,updated_by_source,updated_by_id) VALUES(?,?,?,NOW(),NOW(),?,?) ON DUPLICATE KEY UPDATE integrity_status=VALUES(integrity_status),checked_at=NOW(),updated_at=NOW(),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id)');
        $q->execute([$source,$id,$status,(string)$actor['source'],(string)$actor['id']]);
        t11_event($pdo,$actor,$source,$id,'integrity_checked','Hasil pemeriksaan integritas: '.$status);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return $status;
}
function t11_set_review(array $actor,string $source,int $id,string $status,bool $preserve,string $note):void {
    t11_only_super($actor);
    if(!isset(t11_review_labels()[$status]))throw new InvalidArgumentException('Status pemeriksaan arsip tidak dikenal.');
    if(mb_strlen($note)>300)throw new InvalidArgumentException('Catatan maksimal 300 karakter.');
    if(!t11_file_record($source,$id))throw new RuntimeException('Berkas tidak ditemukan.');
    $pdo=db('smartapp');$pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT review_status,preserve_flag FROM unified_archive_registry WHERE source_key=? AND file_id=? FOR UPDATE');
        $q->execute([$source,$id]);$old=$q->fetch(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('INSERT INTO unified_archive_registry(source_key,file_id,review_status,preserve_flag,retention_note,updated_at,updated_by_source,updated_by_id) VALUES(?,?,?,?,?,NOW(),?,?) ON DUPLICATE KEY UPDATE review_status=VALUES(review_status),preserve_flag=VALUES(preserve_flag),retention_note=VALUES(retention_note),updated_at=NOW(),updated_by_source=VALUES(updated_by_source),updated_by_id=VALUES(updated_by_id)');
        $q->execute([$source,$id,$status,$preserve?1:0,$note,(string)$actor['source'],(string)$actor['id']]);
        $before=$old?(string)$old['review_status']:'belum';
        $oldPreserve=$old?(int)$old['preserve_flag']:0;
        t11_event($pdo,$actor,$source,$id,'archive_review_updated','Status: '.$before.' → '.$status.'; penahanan penghapusan: '.$oldPreserve.' → '.($preserve?'1':'0').'; catatan diperbarui');
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function t11_events(int $limit=20):array {
    $limit=max(1,min(50,$limit));
    $q=db('smartapp')->query('SELECT source_key,file_id,event_type,event_summary,created_at FROM unified_archive_events ORDER BY id DESC LIMIT '.$limit);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function t11_total():array {
    $data=[];
    foreach(t11_sources() as $key=>$source){
        try{$data[$key]=(int)db('smartapp')->query('SELECT COUNT(*) FROM '.$source['table'])->fetchColumn();}
        catch(Throwable $e){$data[$key]=null;}
    }
    return $data;
}

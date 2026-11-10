<?php
declare(strict_types=1);
/**
 * SmartCikoko mobile form adapter. Reuses exactly the Stage16/17
 * validators and transactions. No shadow tables, no bypassed permissions.
 */
require_once __DIR__.'/detailed_forms16.php';
require_once __DIR__.'/field_reports17.php';
require_once __DIR__.'/workflow_progress.php';
require_once __DIR__.'/unified_reports.php';

function mobile_forms_enabled(array $actor):bool {
    if(!function_exists('account_control_allowed'))
        require_once __DIR__.'/account_control.php';
    return account_control_allowed($actor);
}
function mobile_form_allowed(array $actor,string $group,string $kind):bool {
    if(!mobile_forms_enabled($actor))return false;
    if($group==='pegawai')return f16_authorized($actor,$kind);
    if($group==='wilayah')return w17_actor_can($actor,$kind);
    if($group==='laporan')return lr_can_submit($actor,$kind);
    return false;
}
function mobile_form_view_allowed(array $actor,string $group,string $kind):bool {
    if(!mobile_forms_enabled($actor))return false;
    if(mobile_form_allowed($actor,$group,$kind))return true;
    if(!function_exists('is_super_admin')||!is_super_admin($actor))return false;
    return match($group){
       'pegawai'=>isset(f16_kinds()[$kind]),
       'wilayah'=>isset(w17_catalog()[$kind]),
       'laporan'=>isset(lr_categories()[$kind])&&$kind!=='waris',
       default=>false,
    };
}
function mobile_form_catalog(array $actor):array {
    if(!mobile_forms_enabled($actor))return [];
    $result=[];
    foreach(['pegawai'=>f16_kinds(),'wilayah'=>w17_catalog()] as $group=>$categories){
        foreach($categories as $kind=>$meta){
            if(!mobile_form_view_allowed($actor,$group,(string)$kind))continue;
            $fields=[];
            foreach($meta['fields'] as $key=>$field){
                $fields[]=[
                    'key'=>(string)$key,
                    'label'=>(string)$field['label'],
                    'type'=>(string)$field['type'],
                    'required'=>(bool)($field['required']??false),
                    'max'=>(int)($field['max']??255),
                    'options'=>(object)($field['options']??[]),
                ];
            }
            $result[]=[
                'group'=>$group,'kind'=>(string)$kind,'title'=>(string)$meta['name'],
                'description'=>$group==='pegawai'
                    ?'Formulir administratif. Bukan pengesahan kinerja atau cuti.'
                    :(string)($meta['note']??'Laporan administratif wilayah.'),
                'fields'=>$fields,
                'can_create'=>mobile_form_allowed($actor,$group,(string)$kind),
                'can_review'=>function_exists('is_super_admin')&&is_super_admin($actor),
            ];
        }
    }
    foreach(lr_categories() as $kind=>$meta){
        if($kind==='waris'||!mobile_form_view_allowed($actor,'laporan',(string)$kind))continue;
        $result[]=[
          'group'=>'laporan','kind'=>(string)$kind,
          'title'=>(string)$meta['label'].' · Ringkasan',
          'description'=>(string)$meta['hint'],
          'can_create'=>mobile_form_allowed($actor,'laporan',(string)$kind),
          'can_review'=>function_exists('is_super_admin')&&is_super_admin($actor),
          'fields'=>[
            ['key'=>'title','label'=>'Judul kegiatan','type'=>'text','required'=>true,'max'=>150,'options'=>(object)[]],
            ['key'=>'body','label'=>'Uraian kegiatan / hasil','type'=>'textarea','required'=>true,'max'=>3000,'options'=>(object)[]],
            ['key'=>'location','label'=>'Lokasi kegiatan (tanpa alamat pribadi)','type'=>'text','required'=>in_array($kind,['rt_rw','jumantik','lmk','ppsu','fkdm'],true),'max'=>180,'options'=>(object)[]],
            ['key'=>'activity_date','label'=>'Tanggal kegiatan / mulai cuti','type'=>'date','required'=>true,'max'=>10,'options'=>(object)[]],
            ['key'=>'end_date','label'=>'Tanggal akhir (wajib untuk cuti)','type'=>'date','required'=>$kind==='cuti','max'=>10,'options'=>(object)[]],
          ],
        ];
    }
    return $result;
}
function mobile_form_group(string $group):void {
    if(!in_array($group,['pegawai','wilayah','laporan'],true))
        throw new InvalidArgumentException('Jenis layanan tidak tersedia.');
}
function mobile_form_list(array $actor,string $group,string $kind,int $page):array {
    mobile_form_group($group);
    if(!mobile_form_view_allowed($actor,$group,$kind))
        throw new RuntimeException('Akun tidak berwenang mengakses jenis formulir ini.');
    if($group==='pegawai') {
        if(!f16_ready())throw new RuntimeException('Formulir pegawai belum tersedia.');
        $data=f16_list($actor,$kind,$page,12);
    } elseif($group==='wilayah') {
        if(!w17_ready())throw new RuntimeException('Formulir wilayah belum tersedia.');
        $data=w17_list($actor,$kind,$page,12);
    } else {
        if(!lr_ready())throw new RuntimeException('Laporan umum belum tersedia.');
        $data=lr_query($actor,$kind,$page,12);
    }
    $rows=[];
    foreach($data['rows'] as $row){
        $rows[]=[
            'id'=>(int)$row['id'],
            'kind'=>(string)($row['kind']??$row['category']),
            'status'=>(string)$row['status'],
            'version'=>(int)$row['version'],
            'created_at'=>(string)$row['created_at'],
            'updated_at'=>(string)$row['updated_at'],
            'progress'=>sc_workflow_progress($group,(string)$row['status']),
            // Never leak payload_json or actor identifiers in list results.
        ];
    }
    return ['rows'=>$rows,'total'=>(int)$data['total'],
        'page'=>(int)$data['page'],'pages'=>(int)$data['pages']];
}
function mobile_form_detail(array $actor,string $group,int $id):?array {
    mobile_form_group($group);
    if(!mobile_forms_enabled($actor))return null;
    if($group==='pegawai'){
        $item=f16_read_one($actor,$id);
    }elseif($group==='wilayah'){
        if(function_exists('is_super_admin')&&is_super_admin($actor)){
            $q=db('smartapp')->prepare("SELECT * FROM unified_field_reports17 WHERE id=? AND status<>'draf' LIMIT 1");
            $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC)?:null;
        }else{$item=w17_get_own($actor,$id);}
    }else{
        if(!lr_ready())return null;
        $sql="SELECT id,category,title,body,location,activity_date,end_date,status,review_note,version,created_at,updated_at,actor_source,actor_id FROM unified_report_intake WHERE id=?";
        $params=[$id];
        if(!(function_exists('is_super_admin')&&is_super_admin($actor))){
            $sql.=" AND actor_source=? AND actor_id=?";
            $params[]=(string)$actor['source'];$params[]=(string)$actor['id'];
        }
        $q=db('smartapp')->prepare($sql." LIMIT 1");$q->execute($params);
        $item=$q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    if(!$item)return null;
    $kind=(string)($item['kind']??$item['category']);
    if(!mobile_form_view_allowed($actor,$group,$kind))return null;
    if($group==='laporan'){
        $payload=[];
        foreach(['title','body','location','activity_date','end_date'] as $key)
            $payload[$key]=(string)($item[$key]??'');
    }else{
        $payload=json_decode((string)$item['payload_json'],true);
        if(!is_array($payload))$payload=[];
    }
    return [
        'id'=>(int)$item['id'],'group'=>$group,'kind'=>$kind,
        'status'=>(string)$item['status'],'version'=>(int)$item['version'],
        'values'=>$payload,'created_at'=>(string)$item['created_at'],
        'updated_at'=>(string)$item['updated_at'],
        'progress'=>sc_workflow_progress($group,(string)$item['status']),
        'review_note'=>(string)($item['review_note']??''),
    ];
}
function mobile_form_values(mixed $raw):array {
    if(!is_array($raw)||count($raw)>32)
        throw new InvalidArgumentException('Bidang formulir tidak valid.');
    foreach($raw as $key=>$value){
        if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$key)
            ||!(is_string($value)||is_int($value)))
            throw new InvalidArgumentException('Tipe bidang formulir tidak valid.');
    }
    return $raw;
}
function mobile_form_create(array $actor,string $group,string $kind,array $values,string $nonce,bool $submit):int {
    mobile_form_group($group);
    if(!mobile_form_allowed($actor,$group,$kind))
        throw new RuntimeException('Akses pembuatan formulir ditolak.');
    if($group==='pegawai')return f16_create($actor,$kind,$values,$nonce,$submit);
    if($group==='wilayah')return w17_submit($actor,$kind,$values,$nonce,$submit);
    if(!$submit)throw new InvalidArgumentException('Laporan ringkasan langsung dikirim, tidak memiliki draf.');
    return lr_submit($actor,$kind,
        (string)($values['title']??''),(string)($values['body']??''),
        (string)($values['location']??''),(string)($values['activity_date']??''),
        (string)($values['end_date']??''),$nonce);
}
function mobile_form_update(array $actor,string $group,int $id,int $version,array $values,bool $submit):void {
    mobile_form_group($group);
    if(!mobile_forms_enabled($actor))throw new RuntimeException('Akses akun ditolak.');
    $item=mobile_form_detail($actor,$group,$id);
    if(!$item)throw new RuntimeException('Formulir tidak tersedia untuk akun Anda.');
    if($group==='pegawai')f16_update($actor,$id,$version,$values,$submit);
    elseif($group==='wilayah')w17_edit($actor,$id,$version,$values,$submit);
    else{
       if(!$submit)throw new InvalidArgumentException('Perbaikan ringkasan harus dikirim.');
       lr_resubmit($actor,$id,$version,
           (string)($values['title']??''),(string)($values['body']??''),
           (string)($values['location']??''),(string)($values['activity_date']??''),
           (string)($values['end_date']??''));
    }
}
function mobile_form_review(array $actor,string $group,int $id,string $status,string $note):void {
    mobile_form_group($group);
    if(!mobile_forms_enabled($actor)||!function_exists('is_super_admin')||!is_super_admin($actor))
       throw new RuntimeException('Hanya Super Administrator berwenang memeriksa laporan.');
    if(!mobile_form_detail($actor,$group,$id))
       throw new RuntimeException('Laporan tidak ditemukan.');
    if($group==='pegawai')f16_review($actor,$id,$status,$note);
    elseif($group==='wilayah')w17_review($actor,$id,$status,$note);
    else lr_review($actor,$id,$status,$note);
}

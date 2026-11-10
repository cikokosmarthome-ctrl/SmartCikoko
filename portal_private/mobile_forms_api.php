<?php
declare(strict_types=1);
/**
 * SmartCikoko mobile form adapter. Reuses exactly the Stage16/17
 * validators and transactions. No shadow tables, no bypassed permissions.
 */
require_once __DIR__.'/detailed_forms16.php';
require_once __DIR__.'/field_reports17.php';

function mobile_forms_enabled(array $actor):bool {
    if(!function_exists('account_control_allowed'))
        require_once __DIR__.'/account_control.php';
    return account_control_allowed($actor);
}
function mobile_form_allowed(array $actor,string $group,string $kind):bool {
    if(!mobile_forms_enabled($actor))return false;
    if($group==='pegawai')return f16_authorized($actor,$kind);
    if($group==='wilayah')return w17_actor_can($actor,$kind);
    return false;
}
function mobile_form_catalog(array $actor):array {
    if(!mobile_forms_enabled($actor))return [];
    $result=[];
    foreach(['pegawai'=>f16_kinds(),'wilayah'=>w17_catalog()] as $group=>$categories){
        foreach($categories as $kind=>$meta){
            if(!mobile_form_allowed($actor,$group,(string)$kind))continue;
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
            ];
        }
    }
    return $result;
}
function mobile_form_group(string $group):void {
    if(!in_array($group,['pegawai','wilayah'],true))
        throw new InvalidArgumentException('Jenis layanan tidak tersedia.');
}
function mobile_form_list(array $actor,string $group,string $kind,int $page):array {
    mobile_form_group($group);
    if(!mobile_form_allowed($actor,$group,$kind))
        throw new RuntimeException('Akun tidak berwenang mengakses jenis formulir ini.');
    if($group==='pegawai') {
        if(!f16_ready())throw new RuntimeException('Formulir pegawai belum tersedia.');
        $data=f16_list($actor,$kind,$page,12);
    } else {
        if(!w17_ready())throw new RuntimeException('Formulir wilayah belum tersedia.');
        $data=w17_list($actor,$kind,$page,12);
    }
    $rows=[];
    foreach($data['rows'] as $row){
        $rows[]=[
            'id'=>(int)$row['id'],
            'kind'=>(string)$row['kind'],
            'status'=>(string)$row['status'],
            'version'=>(int)$row['version'],
            'created_at'=>(string)$row['created_at'],
            'updated_at'=>(string)$row['updated_at'],
            // Never leak payload_json or actor identifiers in list results.
        ];
    }
    return ['rows'=>$rows,'total'=>(int)$data['total'],
        'page'=>(int)$data['page'],'pages'=>(int)$data['pages']];
}
function mobile_form_detail(array $actor,string $group,int $id):?array {
    mobile_form_group($group);
    if(!mobile_forms_enabled($actor))return null;
    $item=$group==='pegawai'?f16_read_one($actor,$id):w17_get_own($actor,$id);
    if(!$item)return null;
    $kind=(string)$item['kind'];
    if(!mobile_form_allowed($actor,$group,$kind))return null;
    $payload=json_decode((string)$item['payload_json'],true);
    if(!is_array($payload))$payload=[];
    return [
        'id'=>(int)$item['id'],'group'=>$group,'kind'=>$kind,
        'status'=>(string)$item['status'],'version'=>(int)$item['version'],
        'values'=>$payload,'created_at'=>(string)$item['created_at'],
        'updated_at'=>(string)$item['updated_at'],
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
    return $group==='pegawai'
        ?f16_create($actor,$kind,$values,$nonce,$submit)
        :w17_submit($actor,$kind,$values,$nonce,$submit);
}
function mobile_form_update(array $actor,string $group,int $id,int $version,array $values,bool $submit):void {
    mobile_form_group($group);
    if(!mobile_forms_enabled($actor))throw new RuntimeException('Akses akun ditolak.');
    $item=mobile_form_detail($actor,$group,$id);
    if(!$item)throw new RuntimeException('Formulir tidak tersedia untuk akun Anda.');
    if($group==='pegawai')f16_update($actor,$id,$version,$values,$submit);
    else w17_edit($actor,$id,$version,$values,$submit);
}

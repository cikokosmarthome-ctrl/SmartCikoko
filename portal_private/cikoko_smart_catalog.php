<?php
declare(strict_types=1);
/**
 * Cikoko Smart master catalogue.
 * Source-of-truth: existing Stage 9, Stage 15, Stage 16, Stage 17 models.
 * This is a route/capability index, NOT a replacement for source tables,
 * signature checks, personal attachments, or role authorization.
 */
require_once __DIR__.'/detailed_forms16.php';
require_once __DIR__.'/field_reports17.php';
require_once __DIR__.'/unified_reports.php';

function sc_smart_catalog(array $actor):array {
    if(!function_exists('account_control_allowed'))
        require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))
        throw new RuntimeException('Akun tidak aktif atau belum diizinkan.');
    $manager=function_exists('is_super_admin')&&is_super_admin($actor);
    $modules=[];
    foreach(f16_kinds() as $key=>$meta){
        $create=f16_authorized($actor,(string)$key);
        if(!$create&&!$manager)continue;
        $modules[]=[
            'group'=>'pegawai','kind'=>$key,'title'=>$meta['name'],
            'section'=>'Kepegawaian','source'=>'Formulir terstruktur Tahap 16',
            'description'=>'Bidang mengikuti pemetaan formulir Pegawai lama. Bukan persetujuan resmi otomatis.',
            'web_url'=>'/formulir-pegawai16.php?jenis='.rawurlencode((string)$key),
            'can_create'=>$create,'can_review'=>$manager,
            'fields_total'=>count($meta['fields']),'has_legacy_attachments'=>false,
            'parity'=>'bidang-terstruktur',
        ];
    }
    foreach(w17_catalog() as $key=>$meta){
        $create=w17_actor_can($actor,(string)$key);
        if(!$create&&!$manager)continue;
        $modules[]=[
            'group'=>'wilayah','kind'=>$key,'title'=>$meta['name'],
            'section'=>(string)$meta['group'],
            'source'=>'Formulir terstruktur Tahap 17',
            'description'=>(string)$meta['note'],
            'web_url'=>'/laporan-wilayah17.php?jenis='.rawurlencode((string)$key),
            'can_create'=>$create,'can_review'=>$manager,
            'fields_total'=>count($meta['fields']),'has_legacy_attachments'=>false,
            'parity'=>'bidang-terstruktur',
        ];
    }
    foreach(lr_categories() as $key=>$meta){
        if($key==='waris')continue;
        $create=lr_can_submit($actor,(string)$key);
        if(!$create&&!$manager)continue;
        $modules[]=[
            'group'=>'laporan','kind'=>$key,
            'title'=>(string)$meta['label'].' · Ringkasan',
            'section'=>(string)$meta['group'],
            'source'=>'Pusat laporan Tahap 15',
            'description'=>(string)$meta['hint'],
            'web_url'=>'/pusat-laporan.php?kategori='.rawurlencode((string)$key),
            'can_create'=>$create,'can_review'=>$manager,
            'fields_total'=>5,'has_legacy_attachments'=>false,
            'parity'=>'ringkasan-tambahan',
        ];
    }
    // The existing Stage 9 service remains authoritative for decisions and files.
    $citizen=(($actor['source']??'')==='smartapp'&&($actor['role']??'')==='warga');
    $reviewer=$manager||(($actor['source']??'')==='smartapp'&&
        in_array((string)($actor['role']??''),['ptsp','lurah'],true));
    if($citizen||$reviewer){
        $modules[]=[
            'group'=>'warga','kind'=>'ahli_waris','title'=>'Pengajuan Surat Ahli Waris',
            'section'=>'Pelayanan Surat','source'=>'Pengajuan dan lampiran Tahap 9–10',
            'description'=>'Pemohon mengajukan, petugas memeriksa; persetujuan administratif bukan surat yang telah terbit.',
            'web_url'=>'/pengajuan-warga.php',
            'can_create'=>$citizen,'can_review'=>$reviewer,
            'fields_total'=>3,'has_legacy_attachments'=>true,
            'parity'=>'persyaratan-waris-dipetakan',
        ];
    }
    return $modules;
}
function sc_smart_catalog_summary(array $catalog):array {
    $summary=['total'=>count($catalog),'can_create'=>0,'can_review'=>0,'groups'=>[]];
    foreach($catalog as $entry){
        if($entry['can_create'])$summary['can_create']++;
        if($entry['can_review'])$summary['can_review']++;
        $group=(string)$entry['group'];
        $summary['groups'][$group]=($summary['groups'][$group]??0)+1;
    }
    return $summary;
}

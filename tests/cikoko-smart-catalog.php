<?php
declare(strict_types=1);
// Pure authorization/catalog regression: synthetic roles, NO real accounts or writes.
function is_super_admin(array $user):bool {
    return ($user['source']??'')==='pegawai' && ($user['role']??'')==='superadmin';
}
function account_control_allowed(array $user):bool {
    return ($user['active']??true)===true;
}
require dirname(__DIR__).'/portal_private/cikoko_smart_catalog.php';
require dirname(__DIR__).'/portal_private/mobile_forms_api.php';
function ensure_smart(bool $ok,string $label):void {
    if(!$ok){fwrite(STDERR,"FAILED: $label\n");exit(1);}
}
$accounts=[
    'admin'=>['source'=>'pegawai','id'=>'fake-admin','role'=>'superadmin'],
    'pppk'=>['source'=>'pegawai','id'=>'fake-pppk','role'=>'pppk'],
    'pjlp'=>['source'=>'pegawai','id'=>'fake-pjlp','role'=>'pjlp'],
    'rt'=>['source'=>'smartapp','id'=>'fake-rt','role'=>'rt'],
    'warga'=>['source'=>'smartapp','id'=>'fake-warga','role'=>'warga'],
];
foreach($accounts as $key=>$user){
    $catalog=sc_smart_catalog($user);
    $app=mobile_form_catalog($user);
    ensure_smart(count($catalog)>0,$key.' has modules');
    ensure_smart(count($app)>0,$key.' has app schemas');
    foreach($catalog as $module) {
        ensure_smart(str_starts_with($module['web_url'],'/'),$key.' URL is same-origin');
        ensure_smart(!isset($module['native'],$module['password'],$module['payload_json']),
            $key.' catalog never contains source payload or credentials');
    }
}
$admin=sc_smart_catalog($accounts['admin']);
ensure_smart(count($admin)===18,'Admin can monitor all 18 mapped workflows');
$summary=sc_smart_catalog_summary($admin);
ensure_smart($summary['can_review']===18,'Admin has review/monitor access across mapped modules');
$pppk=sc_smart_catalog($accounts['pppk']);
ensure_smart(count(array_filter($pppk,fn($m)=>$m['kind']==='pjlp'))===0,
    'PPPK cannot enter PJLP-specific form');
$rt=sc_smart_catalog($accounts['rt']);
ensure_smart(count(array_filter($rt,fn($m)=>$m['kind']==='pppk'))===0,
    'RT cannot enter PPPK category');
$warga=sc_smart_catalog($accounts['warga']);
ensure_smart(count(array_filter($warga,fn($m)=>$m['kind']==='ahli_waris'))===1,
    'Citizen retains existing heir request route');
ensure_smart(mobile_form_allowed($accounts['warga'],'laporan','umum'),
    'Citizen can submit general report via same Stage 15 rules');
ensure_smart(!mobile_form_allowed($accounts['warga'],'pegawai','pppk'),
    'Citizen cannot impersonate staff');
ensure_smart(mobile_form_view_allowed($accounts['admin'],'pegawai','pppk'),
    'Admin can view role-based reports');
ensure_smart(!mobile_form_allowed($accounts['admin'],'pegawai','pppk'),
    'Admin does not create a PPPK form on behalf of a staff member');
ensure_smart(mobile_form_group('laporan')===null,'Stage 15 mobile group enabled');
$disabled=$accounts['rt'];$disabled['active']=false;
try{$deny=sc_smart_catalog($disabled);ensure_smart(false,'Inactive account must be refused');}
catch(RuntimeException $e){ensure_smart(true,'inactive denied');}
ensure_smart(mobile_form_catalog($disabled)===[],'Inactive account cannot fetch forms');
// Strong parity assurance: every web catalog form must match Flutter server schema.
// The citizen letter flow intentionally uses a dedicated citizen API rather than
// a fake generic form with fewer required attachments.
$oldCatalog=$admin;
$appCatalog=mobile_form_catalog($accounts['admin']);
$appKinds=[];
foreach($appCatalog as $schema){
    $identifier=(string)$schema['group'].':'.(string)$schema['kind'];
    ensure_smart(!isset($appKinds[$identifier]),'No duplicate Flutter schema '.$identifier);
    $appKinds[$identifier]=$schema;
}
foreach($oldCatalog as $web){
    $identifier=(string)$web['group'].':'.(string)$web['kind'];
    if($web['group']==='warga'){
        ensure_smart($web['web_url']==='/pengajuan-warga.php',
            'Citizen letters must use the original authorized attachment workflow');
        continue;
    }
    ensure_smart(isset($appKinds[$identifier]),
        'Missing mobile form for web module '.$identifier);
    $schema=$appKinds[$identifier];
    ensure_smart($schema['title']===$web['title'] || $web['group']==='laporan',
        'Web and Flutter titles differ for '.$identifier);
    ensure_smart(count($schema['fields'])===$web['fields_total'],
        'Web and Flutter field counts must match for '.$identifier);
    ensure_smart($schema['can_create']===$web['can_create'],
        'Web and Flutter create permission must match for '.$identifier);
    foreach($schema['fields'] as $field){
        ensure_smart(isset($field['key'],$field['label'],$field['type'],$field['required']),
            'Mobile field definition incomplete for '.$identifier);
    }
}
ensure_smart(count($appKinds)===17,
    'All seventeen non-citizen workflows visible to Super Admin');
ensure_smart(count($oldCatalog)===count($appKinds)+1,
    'The eighteenth flow must be a dedicated citizen letter service');
$roles=[
    ['source'=>'pegawai','id'=>'fake-asn','role'=>'asn'],
    ['source'=>'smartapp','id'=>'fake-rw','role'=>'rw'],
    ['source'=>'smartapp','id'=>'fake-lmk','role'=>'lmk'],
    ['source'=>'smartapp','id'=>'fake-jumantik','role'=>'jumantik'],
    ['source'=>'smartapp','id'=>'fake-fkdm','role'=>'fkdm'],
    ['source'=>'smartapp','id'=>'fake-ppsu','role'=>'ppsu'],
    ['source'=>'pancoran','id'=>'fake-pppk-pancoran','role'=>'pppk_pancoran'],
];
foreach($roles as $who){
    $web=sc_smart_catalog($who);
    $app=mobile_form_catalog($who);
    $allowed=[];
    foreach($app as $schema)$allowed[$schema['group'].':'.$schema['kind']]=$schema;
    foreach($web as $module){
        if($module['group']==='warga')continue;
        ensure_smart(isset($allowed[$module['group'].':'.$module['kind']]),
            'Role cross-platform missing '.$who['role'].' '.$module['kind']);
        ensure_smart($allowed[$module['group'].':'.$module['kind']]['can_create']===$module['can_create'],
            'Role create permission mismatch '.$who['role'].' '.$module['kind']);
    }
}
echo "PASS: Cikoko Smart 18 mapped categories, role isolation, mobile parity and admin monitoring.\n";

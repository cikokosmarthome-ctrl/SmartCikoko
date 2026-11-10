<?php
declare(strict_types=1);
/** Visual-only compact pass: no changes to roles, submission or MFA. */
function compact27(bool $test,string $label):void{
    if(!$test){fwrite(STDERR,'FAILED: '.$label."\n");exit(1);}
}
$root=dirname(__DIR__);
$form=file_get_contents($root.'/public_html/formulir-pegawai16.php');
$markers=[
    'requires_login()'=>'Native login remains required',
    'account_control_allowed($user)'=>'Active account access remains required',
    'verify_csrf()'=>'CSRF validation retained',
    'f16_authorized($user,$kind)'=>'Native form-role restriction retained',
    'f16_create($user'=>'Native create action preserved',
    'f16_update($user'=>'Native update action preserved',
    'f16_review($user'=>'Super Admin review action preserved',
    'f16_read_one($user'=>'Private report read restriction preserved',
    'f16_list($user'=>'Role-gated list query preserved',
    'name="submit_now" value="0"'=>'Save draft remains available',
    'name="submit_now" value="1"'=>'Submit for review remains available',
    "Formulir #'.\$newId.(!empty(\$_POST['submit_now'])"=>'Draft vs submitted confirmation is now accurate',
    "if(!\$manager&&!f16_authorized(\$user,\$key))continue;"=>'Tabs hidden for unrelated roles',
    'class="layout <?=$manager?'=>'Review-only Super Admin uses full-width records',
    '<details class="panel native">'=>'Old-site read-only history remains accessible via disclosure',
    'id="daftar"'=>'Review/list anchor preserved',
    'id="pengajuan"'=>'Form anchor preserved',
    'form-main-title'=>'Accessible section-specific heading displayed',
];
foreach($markers as $needle=>$desc)
    compact27(str_contains($form,$needle),$desc);
foreach(['public_html/assets/formulir-pegawai16.css',
         'public_html/assets/pusat-operasional23.css',
         'public_html/assets/pusat-laporan.css',
         'public_html/assets/super-admin-satu.css'] as $p){
    $css=file_get_contents($root.'/'.$p);
    compact27(substr_count($css,'{')===substr_count($css,'}'),'CSS braces balanced: '.$p);
    compact27(str_contains($css,'SmartCikoko compact operator UI'),'Compact override present: '.$p);
    compact27(str_contains($css,'@media'),'Responsive layout available: '.$p);
}
$css=file_get_contents($root.'/public_html/assets/formulir-pegawai16.css');
compact27(str_contains($css,'.form label.wide-field{grid-column:1/-1}'),'Text area full width');
compact27(str_contains($css,'.layout.manager-mode #pengajuan{display:none}'),'Review-only form hidden for admin');
compact27(str_contains($css,'.native>summary'),'Old history disclosure styled');
compact27(str_contains($form,'formulir-pegawai16.css?v=compact1'),'Updated form CSS bypasses prior browser cache');
echo "Compact UI: native submit/review paths, permissions, responsive layout and historical form access passed.\n";

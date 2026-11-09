<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/citizen_requests.php';
function check(bool $condition,string $message):void {
    if(!$condition){fwrite(STDERR,"GAGAL: $message\n");exit(1);}
}
check(count(t9_stages())===7,'Tujuh tahapan alur kerja tersedia');
check(isset(t9_relationships()['lainnya']),'Hubungan lainnya tersedia');
$warga=['source'=>'smartapp','role'=>'warga','id'=>'test-citizen'];
$ptsp=['source'=>'smartapp','role'=>'ptsp','id'=>'test-staff'];
$lurah=['source'=>'smartapp','role'=>'lurah','id'=>'test-official'];
$adminPegawai=['source'=>'pegawai','role'=>'admin','id'=>'test-admin'];
check(t9_is_citizen($warga),'Masyarakat SmartApp dikenali');
check(!t9_is_citizen($ptsp),'PTSP tidak boleh menjadi pemohon dalam alur ini');
check(t9_staff_role($ptsp)==='ptsp','PTSP dikenali');
check(t9_staff_role($lurah)==='lurah','Lurah dikenali');
check(t9_staff_role($adminPegawai)==='','Admin Pegawai bukan pejabat persetujuan');
t9_valid_form('Nama Almarhum','anak','Keterangan pelayanan');
foreach([['AB','anak',''],['Nama','tidak_ada',''],['Nama','anak',str_repeat('x',501)]] as $form){
    $failed=false;
    try{t9_valid_form(...$form);}catch(InvalidArgumentException $e){$failed=true;}
    check($failed,'Form tidak valid harus ditolak');
}
echo "Stage 9 unit checks passed.\n";

<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/detailed_forms16.php';
function check16(bool $test,string $label):void {
    if(!$test){fwrite(STDERR,"FAILED: $label\n");exit(1);}
}
$k=f16_kinds();
check16(count($k)===3,'Three legacy categories');
check16(isset($k['cuti']['fields']['masa_kerja'],$k['cuti']['fields']['tanggal_surat']),'Legacy leave details');
check16(isset($k['pppk']['fields']['indikator'],$k['pppk']['fields']['hasil']),'PPPK indicators');
check16(isset($k['pjlp']['fields']['session_id'],$k['pjlp']['fields']['jalur']),'PJLP session and route');
$pppk=['source'=>'pegawai','role'=>'pppk'];$pjlp=['source'=>'pegawai','role'=>'pjlp'];
$rt=['source'=>'smartapp','role'=>'rt'];$pancoran=['source'=>'pancoran','role'=>'pppk_pancoran'];
$warga=['source'=>'smartapp','role'=>'warga'];$admin=['source'=>'pegawai','role'=>'admin'];
check16(f16_authorized($pppk,'pppk'),'PPPK fills own report');
check16(!f16_authorized($pppk,'pjlp'),'PPPK cannot send PJLP form');
check16(f16_authorized($pjlp,'pjlp'),'PJLP fills own report');
check16(!f16_authorized($rt,'cuti'),'RT has no employee leave access');
check16(!f16_authorized($warga,'cuti'),'Citizen cannot request leave');
check16(!f16_authorized($admin,'pppk'),'Admin may review but not impersonate PPPK');
check16(f16_authorized($pancoran,'pppk'),'Pancoran has separate PPPK drafts');
check16(f16_date('2026-11-10')==='2026-11-10','Valid date');
check16(f16_month('2026-11')==='2026-11','Valid month');
foreach(['2026-02-30','2026-15-01','26-11-01'] as $bad){
    $fail=false;try{f16_date($bad);}catch(InvalidArgumentException $e){$fail=true;}
    check16($fail,'Invalid date refused');
}
$leave=['tanggal_mulai'=>'2026-12-01','tanggal_selesai'=>'2026-12-03',
    'alasan'=>'Keperluan keluarga selama beberapa hari','alamat_cuti'=>'Jakarta Selatan',
    'phone'=>'080000000000','jabatan'=>'Pegawai Lapangan','unit_kerja'=>'Unit Cikoko',
    'masa_kerja'=>'2 tahun','kota_surat'=>'Jakarta','tanggal_surat'=>'2026-11-10',
    'lama_cuti'=>'3 hari','catatan_saldo'=>''];
$valid=f16_validate('cuti',$leave);
check16($valid['tanggal_selesai']==='2026-12-03','Leave form normalized');
$bad=$leave;$bad['tanggal_selesai']='2026-11-20';$reject=false;
try{f16_validate('cuti',$bad);}catch(InvalidArgumentException $e){$reject=true;}
check16($reject,'Reversed leave dates refused');
$pppkForm=['bulan'=>'2026-11','jenis_laporan'=>'lapangan','indikator'=>'Laporan kegiatan wilayah',
 'uraian'=>'Pelaksanaan pekerjaan wilayah pada periode terkait.','hasil'=>'Laporan kegiatan tersusun',
 'kendala'=>'','tindak_lanjut'=>''];
check16(f16_validate('pppk',$pppkForm)['jenis_laporan']==='lapangan','PPPK report validation');
$bad=$pppkForm;$bad['jenis_laporan']='dibuat';$reject=false;
try{f16_validate('pppk',$bad);}catch(InvalidArgumentException $e){$reject=true;}
check16($reject,'Unrecognized PPPK report_type refused');
$pjlpForm=['tanggal_tugas'=>'2026-11-11','session_id'=>'17','jalur'=>'1',
 'jam_mulai'=>'07:00','jam_selesai'=>'15:00','jenis_kegiatan'=>'Kebersihan lingkungan',
 'uraian'=>'Pembersihan dan pemantauan kebersihan jalur lingkungan.','hasil'=>'Lingkungan dibersihkan','kendala'=>''];
check16(f16_validate('pjlp',$pjlpForm)['session_id']==='17','PJLP form validated');
$bad=$pjlpForm;$bad['jam_selesai']='07:00';$reject=false;
try{f16_validate('pjlp',$bad);}catch(InvalidArgumentException $e){$reject=true;}
check16($reject,'PJLP zero duration refused');
$bad=$pjlpForm;$bad['session_id']='../../3';$reject=false;
try{f16_validate('pjlp',$bad);}catch(InvalidArgumentException $e){$reject=true;}
check16($reject,'Session ID injection refused');
echo "Stage 16: legacy fields, form validation and authorization tests passed.\n";

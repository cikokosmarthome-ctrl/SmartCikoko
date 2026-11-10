<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/workflow_progress.php';
require dirname(__DIR__).'/portal_private/citizen_followup.php';
function verify_work(bool $condition,string $message):void{
    if(!$condition){fwrite(STDERR,"Workflow FAIL: $message\n");exit(1);}
}
foreach(['warga','pegawai','wilayah'] as $group){
    foreach(['draf','diajukan','ditinjau','perbaikan','revisi','ditutup','disetujui'] as $status){
        $progress=sc_workflow_progress($group,$status);
        verify_work(is_int($progress['progress_percent']),'Progress must be integer');
        verify_work($progress['progress_percent']>=0&&$progress['progress_percent']<=100,
          'Progress bound must be 0..100');
    }
}
verify_work(sc_workflow_progress('warga','draf')['progress_percent']===0,'Draft 0%');
verify_work(sc_workflow_progress('warga','diajukan')['progress_percent']===25,'Submitted 25%');
verify_work(sc_workflow_progress('warga','ditinjau')['progress_percent']===50,'Review 50%');
verify_work(sc_workflow_progress('warga','diverifikasi')['progress_percent']===75,'Verified 75%');
verify_work(sc_workflow_progress('warga','disetujui')['progress_percent']===90,
  'Internal approval is not an issued official letter');
verify_work(sc_workflow_progress('warga','ditolak')['progress_percent']<100,
  'Rejected application must not display successful completion');
verify_work(sc_workflow_progress('warga','selesai')['progress_percent']===100,'Real completed 100%');
verify_work(sc_workflow_progress('wilayah','ditutup')['administrative_complete']===true,
  'Administrative closure marked as completed only administratively');
verify_work(sc_workflow_progress('pegawai','perbaikan')['requires_action']===true,
  'Revision action must be explicit');
$requirements=t10_legacy_waris_requirements();
verify_work(count($requirements)===14,'Legacy Ahli Waris requirement count');
verify_work(isset($requirements['format_1a'],$requirements['format_1c'],
  $requirements['format_2a'],$requirements['format_3a']),'Preserve legacy formats');
verify_work($requirements['akta_cerai']['required']===false,
  'Conditional files must remain conditional');
verify_work($requirements['surat_pengantar']['required']===true,'Retain required letter');
$codes=t10_types();
verify_work(isset($codes['identitas'],$codes['kematian'],$codes['lainnya']),
  'Do not remove preexisting attachment categories');
$status=t10_requirement_status([['doc_type'=>'surat_pengantar']]);
verify_work($status[0]['uploaded']===true,'Uploaded status from server metadata');
verify_work($status[1]['uploaded']===false,'Missing documents cannot be marked uploaded');
echo "PASS: Shared progress semantics, conditional old attachments and no false 100%.\n";

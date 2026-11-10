<?php
declare(strict_types=1);
/** Read-only cross-platform activity center. Never exposes another account's drafts. */
require_once __DIR__.'/workflow_progress.php';
function sc_workflow_ready(string $table):bool {
    $allow=['unified_citizen_requests','unified_citizen_request_history',
      'unified_detailed_forms16','unified_detailed_form_history16',
      'unified_field_reports17','unified_field_history17'];
    if(!in_array($table,$allow,true))return false;
    try {
        db('smartapp')->query('SELECT 1 FROM '.$table.' LIMIT 1');
        return true;
    }catch(Throwable $e){return false;}
}
function sc_workflow_feed(array $actor):array {
    if(!function_exists('account_control_allowed'))require_once __DIR__.'/account_control.php';
    if(!account_control_allowed($actor))throw new RuntimeException('Akun belum diizinkan.');
    $identity=[(string)$actor['source'],(string)$actor['id']];
    $items=[];$events=[];$unavailable=[];
    $definitions=[
      ['group'=>'pegawai','table'=>'unified_detailed_forms16','history'=>'unified_detailed_form_history16',
       'history_fk'=>'form_id','category'=>'kind','label'=>'Formulir pegawai'],
      ['group'=>'wilayah','table'=>'unified_field_reports17','history'=>'unified_field_history17',
       'history_fk'=>'report_id','category'=>'kind','label'=>'Laporan wilayah'],
    ];
    foreach($definitions as $def) {
        if(!sc_workflow_ready($def['table'])||!sc_workflow_ready($def['history'])) {
            $unavailable[]=$def['group'];continue;
        }
        $table=$def['table'];$history=$def['history'];$fk=$def['history_fk'];
        $q=db('smartapp')->prepare('SELECT id,kind,status,created_at,updated_at FROM '.$table.
          ' WHERE actor_source=? AND actor_id=? ORDER BY id DESC LIMIT 30');
        $q->execute($identity);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $progress=sc_workflow_progress($def['group'],(string)$row['status']);
            $items[]=array_merge([
              'id'=>(int)$row['id'],'group'=>$def['group'],
              'reference'=>$def['label'].' #'.(int)$row['id'],
              'kind'=>(string)$row['kind'],'updated_at'=>(string)$row['updated_at'],
              'created_at'=>(string)$row['created_at'],
              'web_url'=>'/formulir-pegawai16.php',
            ],$progress);
        }
        $q=db('smartapp')->prepare('SELECT h.'.$fk.' AS request_id,h.new_status,h.note,h.created_at,a.kind'.
            ' FROM '.$history.' h INNER JOIN '.$table.' a ON a.id=h.'.$fk.
            ' WHERE a.actor_source=? AND a.actor_id=? ORDER BY h.created_at DESC,h.id DESC LIMIT 40');
        $q->execute($identity);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $prog=sc_workflow_progress($def['group'],(string)$row['new_status']);
            $events[]=[
              'key'=>$def['group'].':'.(int)$row['request_id'].':'.(string)$row['created_at'],
              'group'=>$def['group'],'request_id'=>(int)$row['request_id'],
              'title'=>$def['label'].' · '.$prog['status_label'],
              'message'=>mb_substr(trim((string)$row['note']),0,240),
              'status'=>(string)$row['new_status'],
              'progress_percent'=>$prog['progress_percent'],
              'created_at'=>(string)$row['created_at'],
            ];
        }
    }
    // Citizen requests use the existing Stage 9 ownership and Stage 6 access check.
    if(($actor['source']??'')==='smartapp'&&($actor['role']??'')==='warga') {
        require_once __DIR__.'/citizen_requests.php';
        if(t9_actor($actor)) {
            if(sc_workflow_ready('unified_citizen_requests')&&sc_workflow_ready('unified_citizen_request_history')){
                $q=db('smartapp')->prepare('SELECT id,reference_no,status,created_at,updated_at'.
                  ' FROM unified_citizen_requests WHERE account_id=? ORDER BY id DESC LIMIT 30');
                $q->execute([(string)$actor['id']]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $items[]=array_merge([
                      'id'=>(int)$row['id'],'group'=>'warga',
                      'reference'=>(string)$row['reference_no'],'kind'=>'ahli_waris',
                      'created_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at'],
                      'web_url'=>'/tindak-lanjut.php?id='.(int)$row['id'],
                    ],sc_workflow_progress('warga',(string)$row['status']));
                }
                $q=db('smartapp')->prepare('SELECT h.request_id,h.to_status,h.note,h.created_at,a.reference_no'.
                    ' FROM unified_citizen_request_history h'.
                    ' INNER JOIN unified_citizen_requests a ON a.id=h.request_id'.
                    ' WHERE a.account_id=? ORDER BY h.created_at DESC,h.id DESC LIMIT 40');
                $q->execute([(string)$actor['id']]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                    $prog=sc_workflow_progress('warga',(string)$row['to_status']);
                    $events[]=[
                      'key'=>'warga:'.(int)$row['request_id'].':'.(string)$row['created_at'],
                      'group'=>'warga','request_id'=>(int)$row['request_id'],
                      'title'=>(string)$row['reference_no'].' · '.$prog['status_label'],
                      'message'=>mb_substr(trim((string)$row['note']),0,240),
                      'status'=>(string)$row['to_status'],
                      'progress_percent'=>$prog['progress_percent'],
                      'created_at'=>(string)$row['created_at'],
                    ];
                }
            }else{$unavailable[]='warga';}
        }
    }
    usort($items,static fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
    usort($events,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
    $items=array_slice($items,0,60);$events=array_slice($events,0,60);
    $summary=['total'=>count($items),'need_action'=>0,'under_review'=>0,'completed'=>0];
    foreach($items as $item) {
        if($item['requires_action'])$summary['need_action']++;
        if($item['administrative_complete'])$summary['completed']++;
        if(!$item['requires_action']&&!$item['administrative_complete'])$summary['under_review']++;
    }
    return [
      'items'=>$items,'notifications'=>$events,'summary'=>$summary,
      'unavailable_modules'=>$unavailable,
      'notice'=>'Notifikasi riwayat ditampilkan di portal/aplikasi saat dibuka atau dimuat ulang. Belum berupa push sistem, SMS atau WhatsApp.',
      'progress_notice'=>'Persentase adalah tonggak alur administratif; bukan estimasi lama proses atau bukti penerbitan surat.',
    ];
}

<?php
declare(strict_types=1);
/** Read-only monthly report metadata; no applicant names, content, NIK, GPS. */
function e18_recap_sources():array {
    return [
      'ringkas15'=>['table'=>'unified_report_intake','category'=>'category'],
      'pegawai16'=>['table'=>'unified_detailed_forms16','category'=>'kind'],
      'wilayah17'=>['table'=>'unified_field_reports17','category'=>'kind'],
    ];
}
function e18_recap_range(string $start,string $end):array {
    e18_date($start);e18_date($end);
    $a=new DateTimeImmutable($start);$b=new DateTimeImmutable($end);
    if($b<$a||$a->diff($b)->days>366)
        throw new InvalidArgumentException('Pilih rentang waktu maksimum 367 hari.');
    return [$start,$b->modify('+1 day')->format('Y-m-d')];
}
function e18_recap_rows(array $admin,string $start,string $end,string $source=''):array{
    if(!is_super_admin($admin))throw new RuntimeException('Akses hanya Super Admin.');
    $map=e18_recap_sources();
    if($source!==''&&!isset($map[$source]))throw new InvalidArgumentException('Modul tidak dikenali.');
    [$from,$exclusive]=e18_recap_range($start,$end);
    $results=[];$omitted=[];$all=($source!=='')?[$source]:array_keys($map);
    foreach($all as $key){
        $spec=$map[$key];
        // Read metadata only; don't select body/JSON or personally identifiable fields.
        $sql='SELECT id,'.$spec['category'].' AS kategori,status,created_at,updated_at FROM '.$spec['table']
            ." WHERE created_at>=? AND created_at<? AND status<>'draf' ORDER BY id DESC LIMIT 1501";
        try{
            $q=db('smartapp')->prepare($sql);$q->execute([$from,$exclusive]);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>1500){$omitted[]=$key;$rows=array_slice($rows,0,1500);}
            foreach($rows as $r)$results[]=[
                'modul'=>$key,'id'=>(int)$r['id'],
                'kategori'=>(string)$r['kategori'],'status'=>(string)$r['status'],
                'dibuat'=>(string)$r['created_at'],'diperbarui'=>(string)$r['updated_at']
            ];
        }catch(Throwable $e){
            $omitted[]=$key;error_log('Stage18 recap module unavailable: '.$key);
        }
    }
    usort($results,static fn($a,$b)=>strcmp($b['dibuat'],$a['dibuat']));
    return ['rows'=>$results,'incomplete'=>$omitted,'start'=>$from,'end'=>$end,'source'=>$source];
}
function e18_recap_counts(array $rows):array {
    $count=[];
    foreach($rows as $r){
        $key=$r['modul'].':'.$r['status'];
        $count[$key]=($count[$key]??0)+1;
    }
    ksort($count);
    return $count;
}
function e18_recent_reports(array $actor):array {
    $result=[];
    $admin=is_super_admin($actor);
    foreach(['pegawai16'=>'unified_detailed_forms16','wilayah17'=>'unified_field_reports17'] as $source=>$table){
        $where=" WHERE status IN ('diajukan','ditinjau','perbaikan','ditutup')";
        $args=[];
        if(!$admin){
            $where.=' AND actor_source=? AND actor_id=?';
            $args=[(string)$actor['source'],(string)$actor['id']];
        }
        try{
            $q=db('smartapp')->prepare('SELECT id,kind,status,created_at,actor_source,actor_id FROM '.$table.$where.' ORDER BY id DESC LIMIT 25');
            $q->execute($args);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                if(!$admin){
                    $kind=(string)$row['kind'];
                    if($source==='pegawai16'){
                        require_once __DIR__.'/detailed_forms16.php';
                        if(!in_array($kind,['pppk','pjlp'],true)||!f16_authorized($actor,$kind))continue;
                    }else{
                        require_once __DIR__.'/field_reports17.php';
                        if(!w17_actor_can($actor,$kind))continue;
                    }
                }
                unset($row['actor_source'],$row['actor_id']);
                $row['source']=$source;
                $result[]=$row;
            }
        }catch(Throwable $e){error_log('Stage18 recent report unavailable: '.$source);}
    }
    usort($result,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
    return array_slice($result,0,25);
}

<?php
declare(strict_types=1);
/**
 * Stage 23 — read-only role-aware work hub; no duplicate report workflow,
 * no new DB, no role escalation, and no access to private report bodies.
 */
require_once __DIR__.'/account_control.php';
require_once __DIR__.'/unified_reports.php';
require_once __DIR__.'/detailed_forms16.php';
require_once __DIR__.'/field_reports17.php';
require_once __DIR__.'/evidence18.php';
require_once __DIR__.'/recap18.php';
require_once __DIR__.'/verification19.php';
require_once __DIR__.'/monitor20.php';
require_once __DIR__.'/tasks21.php';
require_once __DIR__.'/review22.php';

function h23_admin(array $actor):bool {
    return function_exists('is_super_admin')&&is_super_admin($actor);
}
function h23_role_reviewer(array $actor):bool {
    return v19_eligible($actor);
}
function h23_ability(array $actor,string $ability):bool {
    if(!permitted($ability,$actor))return false;
    try{return account_control_permission_decision($actor,$ability)!==false;}
    catch(Throwable $e){return false;}
}
function h23_report_allowed(array $actor,string $kind):bool {
    if(!lr_can_submit($actor,$kind))return false;
    $ability=match($kind){
        'pppk'=>'pppk','pjlp','ppsu'=>'pjlp','cuti'=>'pegawai',default=>''
    };
    if($ability==='')return true;
    // A Stage 6 explicit DENY must hide the shortcut; native page still checks.
    try{return account_control_permission_decision($actor,$ability)!==false;}
    catch(Throwable $e){return false;}
}
function h23_form16_allowed(array $actor,string $kind):bool {
    if(!f16_authorized($actor,$kind))return false;
    try{return account_control_permission_decision($actor,$kind==='cuti'?'pegawai':$kind)!==false;}
    catch(Throwable $e){return false;}
}
function h23_field17_allowed(array $actor,string $kind):bool {
    if(!w17_actor_can($actor,$kind))return false;
    if($kind!=='ppsu'||($actor['source']??'')!=='pegawai')return true;
    try{return account_control_permission_decision($actor,'pjlp')!==false;}
    catch(Throwable $e){return false;}
}
function h23_ready(string $module):bool {
    static $cache=[];
    if(array_key_exists($module,$cache))return $cache[$module];
    try{
        return $cache[$module]=match($module){
            '15'=>lr_ready(),'16'=>f16_ready(),'17'=>w17_ready(),
            '18'=>e18_ready(),'19'=>v19_ready(),'20'=>m20_ready(),
            '21'=>t21_ready(),'22'=>r22_ready(),
            default=>false,
        };
    }catch(Throwable $e){error_log('Stage23 unavailable module '.$module);return $cache[$module]=false;}
}
function h23_card(string $key,string $label,string $desc,string $href,string $group,string $stage,bool $ready=true):array {
    if(!str_starts_with($href,'/')||str_starts_with($href,'//'))
        throw new InvalidArgumentException('Tujuan navigasi harus bersifat lokal.');
    return ['key'=>$key,'label'=>$label,'description'=>$desc,'href'=>$href,
        'group'=>$group,'stage'=>$stage,'ready'=>$ready];
}
function h23_cards(array $actor):array {
    $cards=[];
    $isSuper=h23_admin($actor);
    $add=static function(array $c) use (&$cards):void{$cards[]=$c;};
    $add(h23_card('ruang_kerja','Ruang Kerja','Dashboard akun dan layanan dasar','/?page=dashboard','umum','Portal'));
    $isReviewer=h23_role_reviewer($actor);
    $reportAvailable=h23_ready('15');
    foreach(lr_categories() as $kind=>$meta){
        if($kind==='waris')continue;
        if(!h23_report_allowed($actor,$kind))continue;
        $add(h23_card('lr_'.$kind,(string)$meta['label'],
            'Ringkasan administratif · bukan transaksi resmi',
            '/pusat-laporan.php?kategori='.rawurlencode($kind),
            'laporan','15',$reportAvailable));
    }
    foreach(f16_kinds() as $kind=>$meta){
        if(!h23_form16_allowed($actor,$kind))continue;
        $add(h23_card('form16_'.$kind,(string)$meta['name'],
            $kind==='cuti'?'Pengajuan awal, bukan persetujuan cuti':'Formulir rincian kepegawaian',
            '/formulir-pegawai16.php?jenis='.rawurlencode($kind),'laporan','16',h23_ready('16')));
    }
    foreach(w17_catalog() as $kind=>$meta){
        if(!h23_field17_allowed($actor,$kind))continue;
        $add(h23_card('wilayah17_'.$kind,(string)$meta['name'],
            $kind==='jumantik'?'Observasi sesuai hak akun, bukan laporan kesehatan tervalidasi':'Laporan kegiatan kewilayahan',
            '/laporan-wilayah17.php?jenis='.rawurlencode($kind),'laporan','17',h23_ready('17')));
    }
    if(h23_ability($actor,'waris')){
        $add(h23_card('waris','Pelayanan Ahli Waris',
            'Buka proses pelayanan asli; bukan penerbitan otomatis',
            '/?page=waris','pelayanan','Layanan',true));
    }
    // Photo evidence is NEVER linked as a collection of other people's files.
    $evidenceAllowed=$isSuper
        ||h23_form16_allowed($actor,'pppk')
        ||h23_form16_allowed($actor,'pjlp');
    foreach(['rt_rw','lmk','fkdm','ppsu'] as $kind)
        if(h23_field17_allowed($actor,$kind))$evidenceAllowed=true;
    if($evidenceAllowed){
        $add(h23_card('bukti18','Bukti Foto Kegiatan',
            'Akses per laporan; unggah hanya jika pemindai ClamAV siap',
            '/bukti-laporan18.php','tugas','18',h23_ready('18')));
    }
    if($isReviewer){
        $add(h23_card('verifikasi19','Pemeriksaan Berjenjang',
            'Tindakan pemeriksa dan telaah Lurah sesuai izin',
            '/verifikasi-laporan19.php','tugas','19',h23_ready('19')));
        $add(h23_card('penugasan21','Tugas Pemeriksaan Saya',
            'Kategori aktif yang ditugaskan ke akun',
            '/penugasan-saya21.php','tugas','21',h23_ready('21')));
        $add(h23_card('cadangan22','Tugas Pengganti Saya',
            'Cadangan sementara dengan masa berlaku',
            '/pengganti-saya22.php','tugas','22',h23_ready('22')));
    }
    $add(h23_card('pengingat20','Pengingat Internal Saya',
        'Pesan tindak lanjut yang ditujukan kepada akun',
        '/pengingat-saya20.php','tugas','20',h23_ready('20')));
    if($isSuper){
        $adminCards=[
            ['super','Super Admin Ringkas','Dashboard utama yang sudah ada','/super-admin-satu.php','Portal'],
            ['rekap18','Rekap & CSV','Rekap metadata Tahap 15–17','/rekap-laporan18.php','18'],
            ['monitor20','Monitoring Bulanan','Ambang hari dan tindak lanjut','/monitor-laporan20.php','20'],
            ['aturan21','Penugasan Pemeriksa','Pilih pemeriksa, Lurah dan tenggat','/tugas-pemeriksa21.php','21'],
            ['kendali22','Kendali Verifikasi','Pembatasan dan pengganti sementara','/kendali-verifikasi22.php','22'],
            ['keamanan','Keamanan Super Admin','MFA, audit dan pemeriksaan keamanan','/keamanan-superadmin.php','Portal'],
        ];
        foreach($adminCards as [$key,$label,$desc,$url,$stage]){
            $add(h23_card($key,$label,$desc,$url,'pengendalian',$stage,
                preg_match('/^[0-9]+$/D',$stage)!==1||h23_ready($stage)));
        }
    }
    return $cards;
}
function h23_filter_cards(array $cards,string $query,string $group=''):array {
    $query=trim($query);
    if(mb_strlen($query)>80)$query=mb_substr($query,0,80);
    if($group!==''&&!in_array($group,['umum','laporan','pelayanan','tugas','pengendalian'],true))$group='';
    return array_values(array_filter($cards,static function($c)use($query,$group){
        if($group!==''&&$c['group']!==$group)return false;
        return $query===''||mb_stripos($c['label'].' '.$c['description'].' '.$c['stage'],$query)!==false;
    }));
}
function h23_counts(array $actor):array {
    $super=h23_admin($actor);
    $totals=['draf'=>0,'aktif'=>0,'perbaikan'=>0,'selesai'=>0];
    $missing=[];$available=0;
    $tables=[
        '15'=>'unified_report_intake',
        '16'=>'unified_detailed_forms16',
        '17'=>'unified_field_reports17',
    ];
    foreach($tables as $stage=>$table){
        if(!h23_ready($stage)){$missing[]=$stage;continue;}
        try{
            $sql='SELECT status,COUNT(*) AS total FROM '.$table;
            $args=[];
            if(!$super){
                $sql.=' WHERE actor_source=? AND actor_id=?';
                $args=[(string)$actor['source'],(string)$actor['id']];
            }
            $sql.=' GROUP BY status';
            $q=db('smartapp')->prepare($sql);$q->execute($args);
            ++$available;
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
                $s=(string)$r['status'];$n=(int)$r['total'];
                if($s==='draf')$totals['draf']+=$n;
                elseif(in_array($s,['diajukan','ditinjau'],true))$totals['aktif']+=$n;
                elseif($s==='perbaikan')$totals['perbaikan']+=$n;
                elseif($s==='ditutup')$totals['selesai']+=$n;
            }
        }catch(Throwable $e){$missing[]=$stage;error_log('Stage23 aggregate unavailable: '.$stage);}
    }
    return ['totals'=>$totals,'available'=>$available,'missing'=>$missing,'scope'=>$super?'semua laporan':'laporan akun ini'];
}
function h23_tasks(array $actor,int $limit=8):array {
    if(!h23_role_reviewer($actor))return ['rows'=>[],'incomplete'=>false];
    $rows=[];$incomplete=false;$seen=[];
    if(h23_ready('21')){
        try{
            $q=t21_my_tasks($actor,12);
            if(!is_array($q)||!isset($q['rows'])){$incomplete=true;}else{
                $incomplete=$incomplete||!empty($q['incomplete']);
                foreach($q['rows'] as $r){
                    $key=$r['source'].':'.$r['id'].':'.$r['step'];
                    $seen[$key]=true;
                    $rows[]=['source'=>$r['source'],'id'=>(int)$r['id'],
                        'category'=>$r['category'],'stage'=>$r['step'],
                        'days'=>(int)$r['age'],'overdue'=>(bool)$r['overdue'],'type'=>'utama'];
                }
            }
        }catch(Throwable $e){$incomplete=true;error_log('Stage23 primary tasks unavailable.');}
    }
    if(h23_ready('22')){
        try{
            $q=r22_deputy_tasks($actor,12);
            $incomplete=$incomplete||!empty($q['incomplete']);
            foreach($q['rows'] as $r){
                $key=$r['source'].':'.$r['id'].':'.$r['step'];
                if(isset($seen[$key]))continue;
                $seen[$key]=true;
                $rows[]=['source'=>$r['source'],'id'=>(int)$r['id'],
                    'category'=>$r['category'],'stage'=>$r['step'],
                    'days'=>(int)$r['age'],'overdue'=>(bool)$r['overdue'],'type'=>'cadangan'];
            }
        }catch(Throwable $e){$incomplete=true;error_log('Stage23 deputy tasks unavailable.');}
    }
    usort($rows,static fn($a,$b)=>($b['overdue']<=>$a['overdue'])?:($b['days']<=>$a['days']));
    if(count($rows)>$limit)$incomplete=true;
    return ['rows'=>array_slice($rows,0,max(1,min(15,$limit))), 'incomplete'=>$incomplete];
}
function h23_notifications(array $actor):array {
    $unread=0;$incomplete=false;
    if(h23_ready('20')){
        try{
            $q=db('smartapp')->prepare('SELECT COUNT(*) FROM unified_monitor_reminders20 WHERE recipient_source=? AND recipient_id=? AND read_at IS NULL');
            $q->execute([(string)$actor['source'],(string)$actor['id']]);
            $unread+=(int)$q->fetchColumn();
        }catch(Throwable $e){$incomplete=true;}
    }
    if(h23_ready('21')&&h23_role_reviewer($actor)){
        try{
            // Stage21 dynamically discards stale/reassigned notifications.
            $rows=t21_inbox($actor,30);
            $unread+=count(array_filter($rows,static fn($r)=>$r['read_at']===null));
            if(count($rows)>=30)$incomplete=true;
        }catch(Throwable $e){$incomplete=true;}
    }
    return ['unread'=>$unread,'incomplete'=>$incomplete];
}
/**
 * A broken reporting source must not blank the entire workspace.
 * Never catch authorization failures: live account status is checked first.
 * Log only the component name and exception class; no report/personal data.
 */
function h23_guarded(string $label,callable $loader,mixed $fallback,array &$warnings):mixed {
    try{return $loader();}
    catch(Throwable $e){
        error_log('Stage23 workspace component failed: '.$label.' ('.get_class($e).')');
        $warnings[]=$label;
        return $fallback;
    }
}
function h23_overview(array $actor):array {
    if(!account_control_allowed($actor))
        throw new RuntimeException('Akun tidak memiliki izin untuk Pusat Operasional.');
    $admin=h23_admin($actor);
    $warnings=[];
    $basicCards=[
        h23_card('ruang_kerja','Ruang Kerja','Buka dashboard akun','/?page=dashboard','umum','Portal'),
        h23_card('laporan','Pusat Laporan','Buka modul pelaporan sesuai izin','/pusat-laporan.php','laporan','15',h23_ready('15')),
    ];
    if($admin){
        $basicCards[]=h23_card('super','Super Admin Ringkas','Pusat kendali yang telah terpasang',
            '/super-admin-satu.php','pengendalian','Portal');
    }
    $cards=h23_guarded('menu modul',static fn()=>h23_cards($actor),$basicCards,$warnings);
    $counts=h23_guarded('ringkasan laporan',static fn()=>h23_counts($actor),
        ['totals'=>['draf'=>0,'aktif'=>0,'perbaikan'=>0,'selesai'=>0],
         'available'=>0,'missing'=>['15','16','17'],
         'scope'=>$admin?'semua laporan':'laporan akun ini'],$warnings);
    $tasks=h23_guarded('tugas pemeriksa',static fn()=>h23_tasks($actor,8),
        ['rows'=>[],'incomplete'=>true],$warnings);
    $notifications=h23_guarded('pengingat',static fn()=>h23_notifications($actor),
        ['unread'=>0,'incomplete'=>true],$warnings);
    return compact('cards','counts','tasks','notifications','admin','warnings');
}

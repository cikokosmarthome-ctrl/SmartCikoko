<?php
declare(strict_types=1);
/**
 * Stage 25 — non-mutating operational diagnostic.
 * No personal details, payloads, report identifiers, credentials or documents.
 * Never create dummy reports on production; acceptance tests are manual.
 */
require_once __DIR__.'/hub23.php';

function a25_entry(string $group,string $label,string $state,string $detail):array {
    if(!in_array($state,['pass','attention','unavailable'],true))
        throw new InvalidArgumentException('Invalid diagnostic status.');
    return compact('group','label','state','detail');
}
function a25_modules():array {
    return [
        '15'=>'Laporan Ringkas',
        '16'=>'Formulir Pegawai',
        '17'=>'Laporan Wilayah',
        '18'=>'Bukti dan Rekap',
        '19'=>'Verifikasi Berjenjang',
        '20'=>'Monitoring dan Pengingat',
        '21'=>'Penugasan Pemeriksa',
        '22'=>'Kendali Verifikasi',
    ];
}
function a25_count_map():array {
    return [
        '15'=>'unified_report_intake',
        '16'=>'unified_detailed_forms16',
        '17'=>'unified_field_reports17',
    ];
}
function a25_reconcile(array $expected,array $actual):array {
    // Stage24 reports all rows, even unrecognized future statuses; totals
    // must therefore match a simple, independently executed COUNT(*).
    foreach(a25_count_map() as $stage=>$table){
        $key=(string)$stage;
        $e=$expected[$key]??null;$a=$actual[$key]??null;
        if($e===null||$a===null)return ['state'=>'attention','detail'=>'Tidak semua sumber dapat dibandingkan.'];
        if(!is_int($e)||!is_int($a)||$e<0||$a<0)
            return ['state'=>'attention','detail'=>'Nilai penghitungan tidak valid.'];
        if($e!==$a)return ['state'=>'attention','detail'=>'Sedikitnya satu jumlah sumber berbeda.'];
    }
    return ['state'=>'pass','detail'=>'Tiga sumber laporan cocok dengan hitung ulang independen.'];
}
function a25_php_routes(string $private,string $public):array {
    return [
        'Pusat Operasional'=>[$public.'/pusat-operasional23.php','/pusat-operasional23.php'],
        'Pusat Input Laporan'=>[$public.'/pusat-laporan.php','/pusat-laporan.php'],
        'Pemeriksaan Tahap 19'=>[$public.'/verifikasi-laporan19.php','/verifikasi-laporan19.php'],
        'Monitoring Tahap 20'=>[$public.'/monitor-laporan20.php','/monitor-laporan20.php'],
        'Penugasan Tahap 21'=>[$public.'/tugas-pemeriksa21.php','/tugas-pemeriksa21.php'],
        'Pengendalian Tahap 22'=>[$public.'/kendali-verifikasi22.php','/kendali-verifikasi22.php'],
        'Fungsi verifikasi privat'=>[$private.'/verification19.php',null],
    ];
}
function a25_valid_route(string $path):bool{
    return is_file($path)&&!is_link($path)&&is_readable($path);
}
function a25_stage24_fixed(string $hub):bool{
    if(!is_file($hub)||is_link($hub))return false;
    $source=@file_get_contents($hub);
    return is_string($source)&&str_contains($source,'$key=(string)$stage;')
        &&str_contains($source,'h23_ready($key)')
        &&str_contains($source,'by_stage');
}
function a25_guard_state(bool $patched,int $enforced):array{
    if($enforced<0)return ['state'=>'unavailable','detail'=>'Status pembatasan belum dapat dibaca.'];
    if($enforced===0){
        return ['state'=>$patched?'pass':'attention',
            'detail'=>$patched
                ?'Pengaman Tahap 22 tersedia; belum ada kategori dalam mode ketat.'
                :'Belum ada kategori ketat; pengaman fungsi Tahap 19 belum terdeteksi.'];
    }
    return ['state'=>$patched?'pass':'unavailable',
        'detail'=>$patched
            ?'Pembatasan kategori aktif dan pengaman Tahap 19 ditemukan.'
            :'PERHATIAN: kebijakan ketat aktif tetapi pengaman verifikasi tidak terdeteksi. Jangan gunakan alur verifikasi sampai diperiksa.'];
}
function a25_aggregate(array $entries):array{
    $counts=['pass'=>0,'attention'=>0,'unavailable'=>0];
    foreach($entries as $item){
        $status=$item['state']??'unavailable';
        if(isset($counts[$status]))++$counts[$status];
        else ++$counts['unavailable'];
    }
    return $counts;
}
function a25_check_connection(string $source):array{
    try{
        $db=db($source);
        $value=$db->query('SELECT 1')->fetchColumn();
        return $value==1
            ?a25_entry('Database','Koneksi '.($source==='pegawai'?'Pegawai':'SmartApp'),'pass','Query baca sederhana berhasil.')
            :a25_entry('Database','Koneksi '.$source,'unavailable','Query cek tidak menghasilkan nilai valid.');
    }catch(Throwable $e){
        error_log('Stage25 database check failed: '.$source.' ('.get_class($e).')');
        return a25_entry('Database','Koneksi '.($source==='pegawai'?'Pegawai':'SmartApp'),
            'unavailable','Koneksi atau query baca tidak tersedia.');
    }
}
function a25_report(array $admin,string $private,string $public):array{
    if(!h23_admin($admin)||!account_control_allowed($admin))
        throw new RuntimeException('Pemeriksaan ini hanya untuk Super Admin yang aktif.');
    $entries=[];
    foreach(['smartapp','pegawai'] as $source)$entries[]=a25_check_connection($source);
    foreach(a25_modules() as $stage=>$label){
        try{
            $ready=h23_ready((string)$stage);
            $entries[]=a25_entry('Modul','Tahap '.(string)$stage.' · '.$label,
                $ready?'pass':'attention',
                $ready?'Tabel sumber bisa dibaca. Belum membuktikan alur input berjalan.'
                    :'Modul belum tersedia atau tabel tidak dapat dibaca.');
        }catch(Throwable $e){
            error_log('Stage25 module readiness failed: '.(string)$stage.' ('.get_class($e).')');
            $entries[]=a25_entry('Modul','Tahap '.(string)$stage.' · '.$label,
                'unavailable','Pemeriksaan kesiapan tidak berhasil.');
        }
    }
    $stat24=a25_stage24_fixed($private.'/hub23.php');
    $entries[]=a25_entry('Ringkasan','Perbaikan Statistik Tahap 24',$stat24?'pass':'attention',
        $stat24?'Perhitungan status versi terbaru terdeteksi.':'Perbaikan statistik belum terdeteksi; lanjutkan pemasangan Tahap 24.');
    $actual=[];$expected=[];$counts=['totals'=>[],'by_stage'=>[],'available'=>0,'missing'=>[]];
    try{
        $counts=h23_counts($admin);
        foreach(a25_count_map() as $stage=>$table){
            $key=(string)$stage;
            $info=$counts['by_stage'][$key]??null;
            if(!empty($info['ready'])&&isset($info['total']))
                $expected[$key]=(int)$info['total'];
        }
    }catch(Throwable $e){
        error_log('Stage25 source aggregate unavailable: '.get_class($e));
    }
    foreach(a25_count_map() as $stage=>$table){
        $key=(string)$stage;
        try{
            // Only a hardcoded table allowlist is used; no dynamic user SQL.
            $actual[$key]=(int)db('smartapp')->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            $entries[]=a25_entry('Konsistensi','Jumlah tabel Tahap '.$key,'pass','Sumber dapat dihitung tanpa membaca isi laporan.');
        }catch(Throwable $e){
            error_log('Stage25 independent count unavailable: '.$key.' ('.get_class($e).')');
            $entries[]=a25_entry('Konsistensi','Jumlah tabel Tahap '.$key,'unavailable','Sumber belum dapat dihitung ulang.');
        }
    }
    $comparison=a25_reconcile($expected,$actual);
    if(!$stat24&&$comparison['state']==='pass')
        $comparison=['state'=>'attention','detail'=>'Hitung ulang cocok, tetapi kode perbaikan Tahap 24 belum terdeteksi.'];
    $entries[]=a25_entry('Konsistensi','Kesamaan ringkasan dan sumber',
        $comparison['state'],$comparison['detail']);
    $v19Path=$private.'/verification19.php';
    $source=(is_file($v19Path)&&!is_link($v19Path))?@file_get_contents($v19Path):false;
    $patched=is_string($source)&&str_contains($source,'SMARTCIKOKO_TAHAP22_STRICT_GUARD');
    $enforced=-1;
    try{
        if(h23_ready('22'))
            $enforced=(int)db('smartapp')
                ->query('SELECT COUNT(*) FROM unified_review_policy22 WHERE enforced=1')
                ->fetchColumn();
    }catch(Throwable $e){error_log('Stage25 strict-policy status unavailable.');}
    $guard=a25_guard_state($patched,$enforced);
    $entries[]=a25_entry('Hak Akses','Pengaman penugasan Tahap 22',
        $guard['state'],$guard['detail']);
    try{
        if(h23_ready('21')){
            $schedule=t21_schedule();
            $enabled=!empty($schedule['enabled']);
            $entries[]=a25_entry('Pengingat','Penjadwal internal',
                'pass',$enabled
                    ?'Izin penjadwal aktif. Keberadaan Cron Jobs cPanel TIDAK diverifikasi.'
                    :'Izin penjadwal nonaktif. Ini dapat menjadi kebijakan yang disengaja.');
        }else{
            $entries[]=a25_entry('Pengingat','Penjadwal internal','attention','Tahap 21 belum siap dibaca.');
        }
    }catch(Throwable $e){
        $entries[]=a25_entry('Pengingat','Penjadwal internal','unavailable','Status pengingat tidak tersedia.');
    }
    foreach(a25_php_routes($private,$public) as $label=>[$path,$href]){
        $exists=a25_valid_route($path);
        $entries[]=a25_entry('Navigasi',$label,$exists?'pass':'attention',
            $exists?'Berkas PHP tersedia di server (HTTP dan hak pengguna belum diuji).'
                :'Berkas tidak tersedia/terbaca di lokasi yang diharapkan.');
    }
    $countsByState=a25_aggregate($entries);
    return [
        'entries'=>$entries,
        'totals'=>$countsByState,
        'counts'=>$counts,
        'source_totals'=>$actual,
        'generated_at'=>date('Y-m-d H:i:s'),
        'limitations'=>'Pemeriksaan baca-saja. Tidak menguji pengiriman formulir, keputusan Lurah, cron cPanel, tampilan browser setiap peran, antivirus atau status dokumen.',
    ];
}

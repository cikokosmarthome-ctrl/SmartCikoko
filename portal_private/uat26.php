<?php
declare(strict_types=1);
/**
 * Stage 26 — manual user-acceptance test journal (UAT), not a certification.
 * Append-only metadata; no citizen, employee or report content is collected.
 */
require_once __DIR__.'/audit25.php';

function u26_catalog():array {
    return [
        'pppk'=>['title'=>'PPPK · Draf dan Ajukan','area'=>'Pegawai',
            'steps'=>'Masuk memakai akun PPPK dummy; simpan draf, ajukan, kemudian periksa status pada akun dan Pusat Operasional.',
            'criterion'=>'Draf dan laporan diajukan muncul untuk pemiliknya, tanpa hak melihat laporan orang lain.'],
        'pjlp'=>['title'=>'PJLP · Laporan Kegiatan','area'=>'Pegawai',
            'steps'=>'Gunakan akun PJLP dummy; isi laporan, simpan dan lihat kembali.',
            'criterion'=>'Pelaporan hanya untuk akun berwenang dan status tersimpan sesuai tahap.'],
        'cuti'=>['title'=>'Cuti · Pengajuan Awal','area'=>'Pegawai',
            'steps'=>'Buat pengajuan cuti administratif dummy dan periksa statusnya.',
            'criterion'=>'Pengajuan tercatat tanpa menyatakan cuti telah disetujui secara resmi.'],
        'rt_rw'=>['title'=>'RT/RW · Laporan Wilayah','area'=>'Wilayah',
            'steps'=>'Buat laporan kegiatan menggunakan akun RT/RW dummy yang sesuai.',
            'criterion'=>'Hanya menu yang berwenang terlihat dan laporan hanya dapat diakses pemilik atau pemeriksa sah.'],
        'lmk'=>['title'=>'LMK · Laporan Kegiatan','area'=>'Wilayah',
            'steps'=>'Uji satu laporan LMK dengan akun dummy sesuai kewenangan.',
            'criterion'=>'Alur simpan, ajukan, koreksi dan daftar laporan dapat digunakan.'],
        'jumantik'=>['title'=>'Jumantik · Pelaporan','area'=>'Wilayah',
            'steps'=>'Uji laporan Jumantik dummy tanpa identitas atau informasi kesehatan warga.',
            'criterion'=>'Tidak ada informasi kesehatan atau laporan akun lain yang terbuka.'],
        'fkdm'=>['title'=>'FKDM · Kegiatan','area'=>'Wilayah',
            'steps'=>'Uji pembuatan laporan kegiatan FKDM dummy.',
            'criterion'=>'Formulir, hak akses, dan status laporan sesuai peran.'],
        'ppsu'=>['title'=>'PPSU · Kegiatan','area'=>'Wilayah',
            'steps'=>'Uji laporan kegiatan PPSU menggunakan akun dummy yang berwenang.',
            'criterion'=>'Kegiatan tersimpan dan hanya terlihat sesuai hak akses.'],
        'waris'=>['title'=>'Ahli Waris · Pelayanan Asli','area'=>'Layanan',
            'steps'=>'Buka pelayanan Ahli Waris dari akun dummy yang memiliki izin; jangan buat surat resmi.',
            'criterion'=>'Alur asli tersedia dan tidak ada penerbitan surat otomatis oleh modul UAT.'],
        'review'=>['title'=>'Tahap 19 · Pemeriksaan dan Lurah','area'=>'Verifikasi',
            'steps'=>'Uji laporan dummy dari pemeriksa dan akun Lurah, termasuk pengembalian dan pengajuan ulang.',
            'criterion'=>'Tidak bisa memeriksa laporan sendiri; Lurah hanya bertindak pada tahap yang tepat.'],
        'assignment'=>['title'=>'Tahap 21 · Penugasan','area'=>'Verifikasi',
            'steps'=>'Buat penugasan dummy; pastikan tugas tampil di akun pemeriksa dan tidak pada akun lain.',
            'criterion'=>'Penugasan tidak menaikkan izin verifikasi ataupun mengirim pesan ke luar portal.'],
        'deputy'=>['title'=>'Tahap 22 · Pengganti Sementara','area'=>'Verifikasi',
            'steps'=>'Uji pengganti dummy yang sah, lalu cabut; lakukan ulang aksesnya.',
            'criterion'=>'Pengganti hanya berlaku selama periode dan peran yang ditentukan, lalu ditolak ketika dicabut.'],
        'reminder'=>['title'=>'Tahap 20–21 · Pengingat','area'=>'Verifikasi',
            'steps'=>'Uji pengingat internal pada akun yang ditugaskan menggunakan laporan dummy.',
            'criterion'=>'Pengingat tidak ganda, tidak bocor antarakun, dan bukan persetujuan.'],
        'statistics'=>['title'=>'Tahap 24–25 · Statistik','area'=>'Sistem',
            'steps'=>'Bandingkan jumlah laporan dummy dan hitung sumber pada pemeriksaan operasional.',
            'criterion'=>'Angka tiga sumber konsisten, dan sumber gagal dibaca tidak disamakan dengan nol.'],
        'mobile'=>['title'=>'Antarmuka Desktop dan Ponsel','area'=>'Sistem',
            'steps'=>'Buka Pusat Operasional, formulir, dan Super Admin dengan ukuran layar berbeda.',
            'criterion'=>'Menu dapat diakses, tidak tumpang tindih, dan data pribadi tidak tampil pada akun salah.'],
        'security'=>['title'=>'Pembatasan Akun dan MFA','area'=>'Sistem',
            'steps'=>'Periksa penolakan akun dummy nonaktif dan jalur Super Admin tanpa MFA yang sah.',
            'criterion'=>'Akun nonaktif, izin ditolak, dan akses admin tanpa MFA tidak dapat memproses data.'],
    ];
}
function u26_states():array {
    return ['pass'=>'Lulus','fail'=>'Gagal','blocked'=>'Terhambat','pending'=>'Belum diuji'];
}
function u26_ready():bool{
    try{
        db('smartapp')->query('SELECT id,case_key,result,recorded_at FROM unified_uat_events26 LIMIT 1')->fetch();
        return true;
    }catch(Throwable $e){return false;}
}
function u26_guard(array $actor):void{
    if(!h23_admin($actor)||!account_control_allowed($actor))
        throw new RuntimeException('Pencatatan uji hanya untuk Super Admin yang aktif.');
    if(!u26_ready())throw new RuntimeException('Tabel pencatatan uji belum terpasang.');
}
function u26_note(string $note):string{
    $note=trim(preg_replace('/\s+/u',' ',$note)??$note);
    $len=mb_strlen($note);
    if($len<15||$len>240)
        throw new InvalidArgumentException('Catatan uji wajib 15–240 karakter, tanpa identitas pribadi.');
    if(preg_match('/\b[0-9]{16}\b/u',$note)
        ||preg_match('/[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}/u',$note)
        ||preg_match('/(?:password|kata\s*sandi|otp|token|secret)\s*[:=]/iu',$note))
        throw new InvalidArgumentException('Jangan masukkan NIK, alamat email, kata sandi, OTP, atau token dalam catatan.');
    return $note;
}
function u26_validate(string $case,string $state,string $note,bool $tested,string $token):array{
    if(!isset(u26_catalog()[$case]))throw new InvalidArgumentException('Jenis uji tidak diketahui.');
    if(!in_array($state,['pass','fail','blocked','pending'],true))
        throw new InvalidArgumentException('Status uji tidak valid.');
    if(!preg_match('/^[0-9a-f]{32}$/D',$token))
        throw new InvalidArgumentException('Token formulir tidak valid.');
    if($state==='pass'&&!$tested)
        throw new InvalidArgumentException('Status lulus hanya boleh dicatat setelah uji dengan akun dummy benar-benar dilakukan.');
    if($state==='pending'){
        if($note!=='')throw new InvalidArgumentException('Untuk mengembalikan ke Belum diuji, kosongkan catatan.');
        return ['case'=>$case,'state'=>$state,'note'=>'','tested'=>false,'token'=>$token];
    }
    return ['case'=>$case,'state'=>$state,'note'=>u26_note($note),'tested'=>$tested,'token'=>$token];
}
function u26_save(array $actor,string $case,string $state,string $note,bool $tested,string $token):bool {
    u26_guard($actor);
    $v=u26_validate($case,$state,$note,$tested,$token);
    $db=db('smartapp');$db->beginTransaction();
    try{
        // Immutable record for every manual observation, never a modification
        // to original employment, service, leave, or reporting tables.
        $q=$db->prepare('INSERT INTO unified_uat_events26(case_key,result,note,tested_confirmed,actor_source,actor_id,submit_token,recorded_at) VALUES(?,?,?,?,?,?,?,NOW())');
        $q->execute([$v['case'],$v['state'],$v['note'],(int)$v['tested'],$actor['source'],(string)$actor['id'],$v['token']]);
        $q=$db->prepare('INSERT INTO unified_audit(actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->execute([$actor['source'],(string)$actor['id'],'uat26_observation','unified_uat_events26',
            hash('sha256',$v['case'].'|'.$v['state'].'|'.$v['token'])]);
        $db->commit();return true;
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if($e->getCode()==='23000'){
            // Exact idempotent request only (same actor, same case, same state).
            $q=$db->prepare('SELECT case_key,result,actor_source,actor_id FROM unified_uat_events26 WHERE submit_token=? LIMIT 1');
            $q->execute([$token]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if($old&&$old['case_key']===$v['case']&&$old['result']===$v['state']
                &&$old['actor_source']===$actor['source']&&hash_equals((string)$old['actor_id'],(string)$actor['id']))
                return false;
        }
        throw $e;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function u26_snapshot(array $actor):array {
    u26_guard($actor);
    $catalog=u26_catalog();
    // Only latest entry per canonical UAT key; preserving full history.
    $q=db('smartapp')->query('SELECT e.id,e.case_key,e.result,e.note,e.tested_confirmed,e.recorded_at FROM unified_uat_events26 e INNER JOIN (SELECT case_key,MAX(id) AS latest FROM unified_uat_events26 GROUP BY case_key) x ON x.latest=e.id ORDER BY e.id DESC LIMIT 100');
    $latest=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)
        if(isset($catalog[$row['case_key']]))$latest[$row['case_key']]=$row;
    $rows=[];$totals=['pending'=>0,'pass'=>0,'fail'=>0,'blocked'=>0];
    foreach($catalog as $key=>$case){
        $v=$latest[$key]??null;
        $state=$v['result']??'pending';
        if(!isset($totals[$state]))$state='pending';
        ++$totals[$state];
        $rows[]=['key'=>$key,'title'=>$case['title'],'area'=>$case['area'],
            'steps'=>$case['steps'],'criterion'=>$case['criterion'],
            'result'=>$state,'note'=>$v['note']??'', 'recorded_at'=>$v['recorded_at']??null];
    }
    return ['rows'=>$rows,'totals'=>$totals,'total_cases'=>count($rows),
        'all_passed'=>$totals['pass']===count($rows)&&$totals['pending']===0
            &&$totals['fail']===0&&$totals['blocked']===0];
}
function u26_history(array $actor,int $limit=25):array{
    u26_guard($actor);
    $q=db('smartapp')->prepare('SELECT id,case_key,result,note,tested_confirmed,recorded_at FROM unified_uat_events26 ORDER BY id DESC LIMIT ?');
    $q->bindValue(1,max(1,min(50,$limit)),PDO::PARAM_INT);
    $q->execute();
    $catalog=u26_catalog();
    return array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC),
        static fn($row)=>isset($catalog[$row['case_key']])));
}
function u26_blockers(array $snapshot):array {
    return array_values(array_filter($snapshot['rows'],
        static fn($r)=>in_array($r['result'],['fail','blocked'],true)));
}

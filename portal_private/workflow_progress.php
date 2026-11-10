<?php
declare(strict_types=1);
/**
 * Shared, deterministic milestone indicator for SmartCikoko web and Flutter.
 * Percentages are ADMINISTRATIVE MILESTONES, not time estimates or promises.
 * Never treat an approval alone as proof that an official letter was issued.
 */
function sc_workflow_progress(string $group,string $status):array {
    $citizen=[
        'draf'=>[0,'Draf','Lengkapi identitas dan lampiran sebelum mengirim.',true,false],
        'diajukan'=>[25,'Diajukan','Pengajuan terkirim dan menunggu pemeriksaan petugas.',false,false],
        'ditinjau'=>[50,'Dalam pemeriksaan','Petugas sedang meninjau data dan lampiran.',false,false],
        'revisi'=>[40,'Perlu perbaikan','Periksa catatan petugas, perbaiki data atau lampiran, lalu kirim ulang.',true,false],
        'diverifikasi'=>[75,'Diverifikasi','Pemeriksaan administratif selesai, menunggu keputusan.',false,false],
        'disetujui'=>[90,'Disetujui secara administratif','Persetujuan internal belum berarti surat resmi telah diterbitkan.',false,false],
        'ditolak'=>[90,'Ditolak','Proses berhenti karena ditolak; bukan permohonan yang selesai disetujui.',false,false],
        'selesai'=>[100,'Selesai','Tahap pelayanan selesai menurut sistem resmi.',false,true],
    ];
    $reports=[
        'draf'=>[0,'Draf','Lengkapi bidang formulir; belum dikirim ke pemeriksa.',true,false],
        'diajukan'=>[25,'Diajukan','Laporan dikirim dan menunggu pemeriksaan.',false,false],
        'ditinjau'=>[60,'Ditinjau','Laporan sedang ditinjau petugas yang berwenang.',false,false],
        'perbaikan'=>[45,'Perlu perbaikan','Periksa catatan pemeriksa, perbaiki laporan dan ajukan ulang.',true,false],
        'ditutup'=>[100,'Ditutup administratif','Proses laporan telah ditutup; tidak berarti surat/cuti disahkan.',false,true],
        'selesai'=>[100,'Selesai','Proses dinyatakan selesai oleh sistem.',false,true],
    ];
    $row=($group==='warga'?$citizen:$reports)[$status]??[0,'Status belum dipetakan','Periksa rincian status pada portal.',false,false];
    return [
        'progress_percent'=>(int)$row[0],
        'status_label'=>$row[1],
        'status'=>$status,
        'next_step'=>$row[2],
        'requires_action'=>$row[3],
        'administrative_complete'=>$row[4],
        'progress_type'=>'milestone',
        'milestones'=>$group==='warga'
            ?[['percent'=>0,'label'=>'Draf'],['percent'=>25,'label'=>'Diajukan'],['percent'=>50,'label'=>'Pemeriksaan'],['percent'=>75,'label'=>'Verifikasi'],['percent'=>90,'label'=>'Keputusan'],['percent'=>100,'label'=>'Selesai resmi']]
            :[['percent'=>0,'label'=>'Draf'],['percent'=>25,'label'=>'Diajukan'],['percent'=>60,'label'=>'Peninjauan'],['percent'=>100,'label'=>'Penutupan administratif']],
    ];
}

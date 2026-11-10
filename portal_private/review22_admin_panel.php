<?php
declare(strict_types=1);
/** Stage22 miniature section included only by existing MFA-gated Super Admin. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){http_response_code(403);return;}
require_once __DIR__.'/review22.php';
$r22OK=r22_ready();
$r22Policies=[];$r22Deputies=[];$r22Monthly=['total_actions'=>0,'modules'=>[]];
if($r22OK){
    try{
        $r22Policies=r22_policies();$r22Deputies=r22_delegations();
        $r22Monthly=r22_monthly($admin,date('Y-m'));
    }catch(Throwable $e){error_log('Stage22 compact panel unavailable.');}
}
$r22Enforced=count(array_filter($r22Policies,static fn($p)=>!empty($p['enforced'])));
$r22ActiveDeputies=count(array_filter($r22Deputies,static fn($p)=>
    empty($p['revoked_at'])&&$p['starts_on']<=date('Y-m-d')&&$p['ends_on']>=date('Y-m-d')));
?>
<section id="kendali-verifikasi" class="section">
<div class="title"><h2>Kendali Penugasan Verifikasi</h2>
<a class="link" href="/kendali-verifikasi22.php">Kelola Pemeriksa dan Pengganti →</a></div>
<p class="hint">Penguncian pemeriksaan sesuai penugasan, pengganti sementara dengan masa berlaku, dan jumlah tindakan administratif. Pengaturan awal tidak aktif sampai Super Admin memutuskan.</p>
<div class="r22-cards">
<div><small>Modul</small><strong><?=$r22OK?'Tersedia':'Belum dipasang'?></strong></div>
<div><small>Kategori dibatasi</small><strong><?=$r22Enforced?></strong></div>
<div><small>Pengganti berlaku</small><strong><?=$r22ActiveDeputies?></strong></div>
<div><small>Tindakan bulan ini</small><strong><?=(int)($r22Monthly['total_actions']??0)?></strong></div>
</div>
<?php if(!$r22OK):?><p class="r22-warning">Jalankan installer Tahap 22 melalui akun Super Admin. Penugasan ketat masih nonaktif.</p>
<?php else:?>
<div class="r22-links"><a href="/kendali-verifikasi22.php">Atur Pembatasan dan Cadangan</a>
<a href="/pengganti-saya22.php">Lihat Tugas Pengganti Saya</a></div>
<?php endif;?>
</section>
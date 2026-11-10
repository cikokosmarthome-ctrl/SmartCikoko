<?php
declare(strict_types=1);
/** Compact entry only; full checks run on demand, never at dashboard load. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){
    http_response_code(403);return;
}
?>
<section id="cek-operasional" class="section">
<div class="title"><h2>Pemeriksaan Operasional</h2><small>Pemeriksaan teknis baca-saja · Tahap 25</small></div>
<div class="audit25-row">
<div><strong>Periksa keterhubungan layanan dan statistik</strong>
<p>Verifikasi kesiapan modul 15–22, kesamaan jumlah laporan dan pengaman penugasan. Tidak menjalankan transaksi atau mengubah database.</p></div>
<a href="/cek-operasional25.php">Buka Pemeriksaan Operasional →</a>
</div>
</section>
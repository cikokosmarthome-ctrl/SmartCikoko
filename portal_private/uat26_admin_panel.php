<?php
declare(strict_types=1);
/** One compact entry into Stage26; heavy UAT queries only on demand. */
if(!isset($admin)||!is_array($admin)||!is_super_admin($admin)){
    http_response_code(403);return;
}
?>
<section id="uji-operasional26" class="section">
<div class="title"><h2>Uji Penerimaan Pengguna</h2><small>Tahap 26 · Temuan dan tindak lanjut manual</small></div>
<div class="uat26-row"><div><strong>Uji modul dan catat tindak lanjut</strong>
<p>16 skenario uji operasional untuk PPPK, PJLP, cuti, RT/RW, LMK, Jumantik dan verifikasi. Catat hasil dan kendala tanpa mengubah laporan asli.</p></div>
<a href="/uji-operasional26.php">Buka Uji Operasional →</a></div>
</section>
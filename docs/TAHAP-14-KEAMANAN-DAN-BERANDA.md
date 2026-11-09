# Tahap 14 — Perbaikan Beranda dan Keamanan Dokumen SmartCikoko

Pembaruan dipisah menjadi dua ZIP untuk menjaga portal dan login MFA yang sudah berjalan.

## A. Perbaikan Beranda (lakukan pertama)

Tampilan screenshot menunjukkan header/menu yang rapat dan hero yang tidak tertata. Beranda memuat public_html/assets/style.css; CSS dari repository telah dilengkapi untuk menu responsif, hero biru-putih, kartu hero gelap, dan font sistem tanpa ketergantungan Google Fonts.

1. Buat backup aman file public_html/assets/style.css. Simpan backup di luar public_html.
2. Download paket/SmartCikoko-PERBAIKAN-BERANDA.zip dari GitHub.
3. cPanel → File Manager → /home/datd1747/ → Upload dan Extract. Hanya public_html/assets/style.css yang ditimpa.
4. Buka https://datawebs.my.id/ kemudian tekan Ctrl+F5 atau gunakan jendela privat.
5. Periksa desktop dan ponsel: logo KC, menu, hero, dua tombol, daftar layanan, publikasi dan footer.
6. Jika masih berantakan, cek bahwa URL /assets/style.css memberikan respons HTTP 200 dengan tipe text/css. Periksa cache dan apakah file CSS live memang terganti. Jangan mengubah database atau menimpa index.php.

## B. Tahap 14 — Panel Audit Keamanan

1. Backup kedua database dan file portal secara privat.
2. Download paket/SmartCikoko-TAHAP14-KEAMANAN.zip.
3. Upload dan Extract di /home/datd1747/. Paket hanya MENAMBAHKAN berkas baru tanpa mengganti login, MFA, atau file database.
4. Login Super Admin dan verifikasi MFA.
5. Buka https://datawebs.my.id/uji-keamanan14.php.
6. Tinjau HTTPS, versi PHP, keamanan sesi, status MFA, folder privat, nama berkas mencurigakan pada webroot, pemindai malware, installer sisa dan simulasi akses berbasis identitas dummy.

Tidak diperlukan impor SQL atau installer database.

## Interpretasi hasil

- ClamAV hanya diuji dengan perintah PING jika opsi upload_scanning.enforce sudah aktif dan socket diatur oleh pengelola hosting. Balasan PONG bukan bukti bahwa file unggahan sudah dipindai.
- Jika ClamAV belum dikonfigurasi, panel menampilkan PERLU TINDAKAN. Jangan mengunggah dokumen asli warga hingga pemindai malware dan prosedur perlindungan data siap serta teruji.
- Simulasi Warga A/B, PTSP, dan Lurah tidak menjalankan transaksi nyata atau akses akun sungguhan. Uji penetrasi, MFA per petugas, privasi, retensi, backup terenkripsi, dan uji pemulihan masih wajib.
- Modul audit tidak membuka isi dokumen warga atau mengubah data pada kedua database.
- Kode GitHub belum otomatis terpasang pada cPanel Rumahweb.

## Isi ZIP

SmartCikoko-PERBAIKAN-BERANDA.zip:
- public_html/assets/style.css (satu file yang diperbarui)

SmartCikoko-TAHAP14-KEAMANAN.zip:
- portal_private/security_audit14.php (baru)
- public_html/uji-keamanan14.php (baru)
- public_html/assets/uji-keamanan14.css (baru)

Jika muncul HTTP 500, periksa error_log pada cPanel dan pulihkan file hasil backup seperlunya tanpa menghapus database.

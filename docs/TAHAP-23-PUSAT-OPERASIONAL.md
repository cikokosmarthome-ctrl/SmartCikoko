# Tahap 23 — Pusat Operasional SmartCikoko

Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Hasil

**Pusat Operasional Tahap 23 menyatukan akses ke modul Tahap 15–22 dalam satu halaman navigasi, bukan mengganti seluruh halaman transaksi.** Super Admin Ringkas yang sudah ada tetap dipakai untuk pengaturan; halaman pusat baru hanya menampilkan ringkasan dan tautan berbasis peran.

Alamat setelah ZIP diekstrak dan dipasang:
- **Pusat Operasional:** https://datawebs.my.id/pusat-operasional23.php
- **Installer satu tombol:** https://datawebs.my.id/instal-operasional23.php
- **Super Admin Ringkas lama:** https://datawebs.my.id/super-admin-satu.php
- **Pusat Input Laporan lama:** https://datawebs.my.id/pusat-laporan.php

## Apa yang ada di satu halaman

1. **Status laporan Tahap 15–17:** jumlah draf, dalam proses, perlu perbaikan, dan selesai administratif. Untuk akun biasa, hanya laporan milik sumber+ID akun tersebut; untuk Super Admin terotorisasi, agregat semua laporan tambahan. Bila tabel modul belum siap, tampil peringatan, bukan dianggap nol.
2. **Tugas aktif pemeriksa dan pengganti:** pratinjau terbatas tugas sesuai penugasan Tahap 21 dan cadangan Tahap 22; hanya label kategori, ID laporan, fase, dan usia antrean. Tidak memuat uraian, NIK, data warga, lokasi pribadi, atau dokumen. Detail penuh tetap membutuhkan izin modul Tahap 19.
3. **Pengingat internal:** jumlah pesan belum dibaca dan tautan kotak pengingat Tahap 20 dan penugasan Tahap 21 sesuai hak akun.
4. **Menu cepat berdasarkan peran:** filter pencarian atau kelompok menu. Menampilkan formulir yang sesuai:
   - PPPK, PJLP, Cuti Tahap 16 untuk akun yang boleh mengisi;
   - RT/RW, LMK, Jumantik, FKDM, PPSU Tahap 17 sesuai sumber identitas/peran;
   - laporan ringkas Tahap 15; pelayanan Ahli Waris mengarah ke sistem pelayanan yang sudah ada, **bukan membuat surat otomatis**;
   - bukti foto Tahap 18 khusus akun yang berwenang, bukan dokumen warga atau foto pemeriksaan rumah tangga;
   - verifikasi administratif Tahap 19, pengingat Tahap 20, penugasan Tahap 21, pengganti Tahap 22 hanya untuk peran pemeriksa yang relevan;
   - menu pengaturan Super Admin, rekap CSV, monitoring, penugasan dan kendali verifikasi hanya untuk Super Admin yang lolos MFA.
5. **Modul belum siap diberi label:** halaman boleh dibuka sebagai navigasi tetapi sumber yang belum terpasang tetap ditandai; tidak ada klaim fungsi tersedia.

## Keamanan dan batasan

- Halaman hanya dapat dibuka setelah login; **pemeriksaan status dan izin akun yang masih aktif melalui `account_control_allowed()`**.
- Super Admin dikenali melalui `is_super_admin()` yang mempertahankan pengamanan MFA Tahap 13. Pengguna biasa tidak menerima tautan konfigurasi admin.
- Pembatasan Stage 6 (penolakan PPPK/PJLP) tetap diperhitungkan sebelum menampilkan menu; halaman target tetap mengulang validasi izin.
- Tidak ada input laporan, pengubahan status, persetujuan surat, ekspor data atau modifikasi tabel di Tahap 23. Semua tindakan penting tetap terjadi dalam modul masing-masing.
- Pratinjau pekerjaan dibatasi agar halaman tetap ringkas; tidak dimaksudkan sebagai daftar audit yang lengkap. Status "selesai" hanya status administratif.
- **Tahap 23 tidak menambah tabel SQL**, tidak memindahkan data dan tidak mengubah database Pegawai atau SmartApp.
- Mengakses halaman belum berarti modul asal telah terpasang; periksa label kesiapan masing-masing.
- Link `/?page=waris` hanya tampak untuk peran yang memiliki izin; keputusan dan penerbitan surat tetap melalui alur resmi lama.

## Instalasi cPanel Rumahweb (tanpa SQL manual)

1. Backup website, kedua database, konfigurasi privat, serta kunci MFA ke media aman **di luar public_html**.
2. Pastikan paket Tahap 15–22 telah diekstrak dan modul yang dibutuhkan sudah dapat dibuka. Jika belum, tahap 23 dapat menunjukkan status belum siap, namun tidak bisa menggantikannya.
3. Download paket **`paket/SmartCikoko-TAHAP23-PUSAT-OPERASIONAL.zip`** dari GitHub.
4. Masuk **cPanel → File Manager → `/home/datd1747/`**.
5. Upload ZIP dan **Extract pada direktori itu**, bukan di dalam public_html. Paket **hanya berisi 4 file baru**: `portal_private/hub23.php`, `portal_private/hub23_patch.php`, `public_html/pusat-operasional23.php`, `public_html/instal-operasional23.php`, ditambah `public_html/assets/pusat-operasional23.css` dan petunjuk.
6. Paket tidak menimpa `index.php`, `super-admin-satu.php`, `pusat-laporan.php`, `auth.php`, `bootstrap.php`, `mfa.php`, `verification19.php` atau skrip perlindungan Tahap 22.
7. Login Super Admin memakai Google Authenticator lalu buka **https://datawebs.my.id/instal-operasional23.php**.
8. Klik **Hubungkan Pusat Operasional**. Installer menambah satu tautan di menu Super Admin Ringkas dan satu tautan di menu Pusat Laporan, dengan validasi **SHA-256 file asli**. Backup sebelum perubahan dibuat di **`portal_private/hub23_backups/`**, di luar public_html. Jika layout file hosting sudah berubah, installer menolak penyisipan agar tidak merusak tampilan.
9. Uji halaman dengan akun dummy: warga, RT/RW, LMK, PPPK, PJLP, PTSP, Lurah, dan Super Admin. Pastikan menu admin tidak muncul pada akun biasa, dan penolakan akses Stage 6 tetap bekerja.
10. Setelah berhasil, hapus **`public_html/instal-operasional23.php` dan ZIP** dari hosting. Simpan salinan backup privat untuk rollback.

## Pemeriksaan setelah instalasi

- Tidak ada pergeseran struktur Beranda: `public_html/index.php` tidak diubah.
- Super Admin tetap membuka `super-admin-satu.php` untuk pengendalian; hanya satu tautan ditambahkan.
- Akun RT tidak melihat form PPPK atau pengaturan kebijakan Super Admin.
- Akun PPPK tidak melihat form PJLP atau data warga.
- Akun warga tidak melihat modul pemeriksaan; pelayanan Ahli Waris melalui jalur asal.
- Semua laporan perorangan dijumlahkan menggunakan `actor_source` dan `actor_id`, bukan berdasarkan nama.
- Pengganti sementara yang sudah tidak berlaku tidak tampil sebagai tugas aktif.
- Tautan selalu dalam domain yang sama; tidak menggunakan redirect ke host pihak ketiga.
- Pusat Operasional tidak mengubah laporan atau formulir yang sudah ada.
- Sintaks/tes GitHub hanya memvalidasi kode; belum membuktikan integrasi database dan kondisi produksi pada Rumahweb.

## Arah Tahap 24

Bila Tahap 23 sudah dipasang dan akun nyata diuji, prioritas berikutnya adalah mengevaluasi **beban navigasi dan responsivitas dashboard**, penyelarasan hak akses/label layanan, pengujian end-to-end menggunakan akun dummy, serta retensi dan pemulihan data yang disetujui pengelola. Hindari membangun halaman baru yang tumpang tindih.

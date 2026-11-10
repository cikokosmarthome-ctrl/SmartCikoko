# SmartCikoko — Tahap 21: Penugasan Pemeriksa, Tenggat Internal & Pengingat CLI

Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Fungsi

Tahap 21 melengkapi Tahap 19 (verifikasi administratif berjenjang) dan Tahap 20 (monitoring bulanan/pengingat manual). Fokusnya **menentukan akun pemeriksa per kategori** dan menyediakan **kotak tugas yang berisi laporan aktif**, serta pengingat internal setelah melewati tenggat yang ditetapkan secara eksplisit. **Super Admin tetap satu halaman** di `super-admin-satu.php`.

Laporan yang dipakai:
- Tahap 15: `unified_report_intake`; modul Ahli Waris resmi **tidak dimasukkan** sebagai kategori penugasan.
- Tahap 16: `unified_detailed_forms16` (PPPK, PJLP dan Cuti administratif).
- Tahap 17: `unified_field_reports17` (RT/RW, LMK, Jumantik, FKDM, PPSU).
- Tahap 19: status dan versi pemeriksaan dari `unified_verification19`.
- Tahap 20: perhitungan fase `m20_phase()` dan usia laporan.

Penugasan tidak menulis tabel laporan lama, saldo cuti, penilaian ASN/PPPK, maupun surat RT/RW. Menetapkan petugas di Tahap 21 **tidak meningkatkan hak akses Tahap 19** atau memberi kewenangan persetujuan resmi.

## Alur

1. Super Admin menentukan kategori laporan dan tahap **pemeriksa** atau **Lurah**.
2. Memilih akun aktif yang sudah terdapat pada database Pegawai/SmartApp. Admin Pegawai untuk laporan kepegawaian; PTSP SmartApp untuk wilayah; Lurah SmartApp untuk tahap telaah Lurah.
3. Mengisi tenggat **1–60 hari kalender**, nomor/nama SOP atau dasar pengaturan internal (8–180 karakter) dan tanggal efektif. Referensi disimpan sebagai metadata; sistem **tidak memverifikasi otomatis** bahwa SOP tersebut resmi atau mengklaim ada aturan hukum tertentu.
4. Mengaktifkan aturan kategori secara terpisah. Semuanya nonaktif jika belum ada pengaturan.
5. Petugas membuka **Penugasan Saya** dan dapat melihat laporan aktif yang menjadi tanggung jawabnya **sebelum** tenggat terlampaui. Pelapor tidak boleh memeriksa laporannya sendiri. Laporan draf, sudah ditutup atau yang pemeriksaannya sudah selesai tidak masuk tugas aktif.
6. Notifikasi internal dapat dibuat melalui tombol **Jalankan Pemeriksaan Manual Sekali** oleh Super Admin, atau melalui PHP CLI privat di cPanel **hanya setelah jadwal diizinkan secara eksplisit**.
7. Notifikasi hanya untuk laporan yang sudah melewati ambang hari dan masih menunggu tahap yang benar. Sistem membatasi satu pengingat per laporan, versi, fase dan penerima; duplikat tidak dibuat. Pengingat lama tidak tampil jika penugasan berpindah atau status laporan berubah.
8. Kotak tugas dan pengingat hanya ditampilkan ke pemilik akun yang terverifikasi. Menandai telah dibaca tidak berarti laporan disetujui.

## Pembatasan pengingat

- **Tidak ada pengiriman WhatsApp, email, SMS atau push notification.** Ini hanya pesan internal SmartCikoko.
- Aturan tenggat **tidak ada yang diaktifkan secara otomatis** saat installer berjalan. Izin jadwal CLI juga nonaktif.
- **Installer tidak memasang Cron Jobs cPanel.** Jika diperlukan, pengelola hosting memasang jadwal sendiri setelah semua tes dengan akun dummy berhasil.
- Skrip `portal_private/cron_tugas21.php` hanya berjalan dalam **PHP CLI**, bukan melalui URL HTTP, dan memerlukan argumen `--dispatch`. Tidak ada token rahasia di URL.
- Contoh perintah di Cron Jobs, setelah memastikan path executable PHP versi yang benar: `php /home/datd1747/portal_private/cron_tugas21.php --dispatch`. Tentukan frekuensi, misalnya **sekali sehari**, secara manual pada cPanel.
- Penjadwal menggunakan MySQL named lock untuk mencegah tugas paralel dan transaksi sebelum pembuatan pemberitahuan. Bila penugasan atau status berubah, pemberitahuan dibatalkan.
- Pratinjau penugasan dibatasi 120 laporan aktif per aturan dan hingga 40 laporan yang disajikan di UI. Proses dispatch maksimal 80 kandidat per eksekusi; apabila melewati batas, tampilkan peringatan bahwa pratinjau atau eksekusi belum menyapu semua laporan.
- Pengingat ini bukan dasar penilaian keterlambatan disiplin, nilai kinerja ASN, atau peringatan hukum.

## Database dan keamanan

Tiga tabel **baru pada SmartApp**, tanpa memodifikasi Pegawai:
- `unified_assignments21`: source, kategori, tahap, akun pemeriksa yang aktif, tenggat, referensi, tanggal efektif, status aktif/nonaktif.
- `unified_alerts21`: satu pengingat per laporan/versi/fase/penerima, pesan internal, waktu dibuat dan waktu dibaca.
- `unified_schedule21`: satu baris pengendalian izin dispatcher CLI; default nonaktif; audit penjadwalan.

Akun aktif diverifikasi ulang dari tabel asal dan kontrol pembatasan akun. Pemeriksa biasa tidak dapat menyamar sebagai Lurah. Perubahan aturan hanya untuk Super Admin setelah verifikasi MFA. Semua formulir POST memakai CSRF; semua query menggunakan parameter. Tindakan perubahan aturan, aktivasi jadwal, pengiriman dan penandaan baca dicatat dalam audit.

## Instalasi satu ZIP di Rumahweb

1. Pastikan Tahap 15–20 yang menjadi prasyarat (terutama 15, 16, 17, 19 dan 20) telah dipasang dan **diuji di hosting menggunakan akun dummy**.
2. Backup dua database, kode website, konfigurasi privat, dan kunci MFA ke tempat aman di luar direktori publik.
3. Download `paket/SmartCikoko-TAHAP21-PENUGASAN-PENGINGAT.zip` dari GitHub.
4. cPanel File Manager → `/home/datd1747/` → Upload ZIP → **Extract di direktori tersebut**, jangan di dalam `public_html`.
5. ZIP hanya menambahkan file; tidak menimpa `index.php`, `bootstrap.php`, `auth.php`, `mfa.php`, `super-admin-satu.php`, `pusat-laporan.php`, atau database lama.
6. Login Super Admin menggunakan MFA dan buka `https://datawebs.my.id/instal-tugas21.php`.
7. Klik **Pasang Penugasan Tahap 21**. Installer membuat tiga tabel baru pada SmartApp dengan pengaturan awal nonaktif. Bagian Penugasan ditambahkan ke Super Admin Ringkas, dan tautan Kotak Tugas ke Pusat Laporan, setelah membuat **backup asli** di `portal_private/tasks21_backups/` di luar `public_html`.
8. Bila versi live berbeda, penyisipan ditolak; installer tidak memaksa mengganti seluruh file.
9. Buka `https://datawebs.my.id/tugas-pemeriksa21.php`. Pilih satu kategori dummy, pemeriksa/Lurah aktif dan referensi SOP internal untuk pengujian. **Jangan mengaktifkan notifikasi terjadwal untuk produksi dulu.**
10. Buka `https://datawebs.my.id/penugasan-saya21.php` dengan akun pemeriksa untuk memastikan daftar tugas benar. Uji penolakan akun lain, pengingat ganda, pergantian tugas, status laporan selesai, dan pengecekan versi.
11. Jika sudah disetujui dan perlu otomasi, aktifkan izin jadwal lewat dashboard lalu atur Cron Jobs secara manual di cPanel dengan PHP CLI yang sesuai. Jangan jalankan melalui HTTP.
12. Setelah sukses, hapus `public_html/instal-tugas21.php` dan ZIP dari hosting. Jangan hapus tabel, pengingat atau backup privat.

## Tautan halaman setelah pemasangan

- Super Admin Ringkas: https://datawebs.my.id/super-admin-satu.php#penugasan-pemeriksa
- Kelola penugasan: https://datawebs.my.id/tugas-pemeriksa21.php
- Kotak tugas petugas/Lurah: https://datawebs.my.id/penugasan-saya21.php
- Pemeriksaan administratif: https://datawebs.my.id/verifikasi-laporan19.php
- Installer: https://datawebs.my.id/instal-tugas21.php

### Hal yang perlu diuji di server

Lint PHP dan unit tests otomatis tidak membuktikan integrasi cPanel, akses CLI PHP 8.4, hak koneksi MySQL, job cron, maupun status akun nyata. Jangan memakai data warga, NIK, dokumen kesehatan atau laporan produksi untuk pengujian.

**Batasan integrasi:** Tahap 21 membuat penugasan dan kotak tugas internal, bukan mengganti atau memperketat logika otorisasi aplikasi verifikasi Tahap 19. Jika organisasi membutuhkan hanya pemeriksa yang ditunjuk boleh mengambil tindakan, perubahan tersendiri harus diuji pada Tahap 19 beserta mekanisme pengganti petugas yang berhalangan.

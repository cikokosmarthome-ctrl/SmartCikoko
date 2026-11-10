# Tahap 22 — Pengendalian Verifikasi dan Pemeriksa Pengganti

**SmartCikoko** — Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Tujuan

Menghubungkan penugasan Tahap 21 dengan **fungsi tindakan verifikasi Tahap 19**, bukan sekadar menambah daftar tugas. Pusat kendali tetap **Super Admin Ringkas**. Pengendalian dipasang melalui perubahan kecil, terverifikasi versinya, dengan backup sebelum mengubah kode verifikasi privat.

Fitur:
- **Mode verifikasi ketat per kategori (default NONAKTIF).** Setelah Super Admin mengaktifkan kategori yang punya pemeriksa dan Lurah aktif, pemeriksaan Tahap 19 **hanya boleh dilakukan akun yang ditugaskan atau cadangan aktif**, tetap mengikuti peran dan larangan memeriksa laporan sendiri. Tidak ada bypass untuk Super Admin yang tidak ditugaskan, meskipun ia tetap dapat membaca laporan untuk pengawasan.
- **Cadangan sementara**, atas keputusan Super Admin, hanya untuk kategori dan tahap tertentu, maksimum **90 hari kalender**, dengan alasan dan tanggal berlaku yang jelas. Cadangan tetap harus memenuhi peran; hanya akun Lurah asli dapat melakukan tahap Lurah. Cadangan adalah akses tambahan sementara; petugas utama tetap dapat bertindak.
- **Riwayat dan audit** setiap perubahan mode dan penunjukan/pencabutan cadangan.
- **Kotak tugas cadangan** yang hanya menampilkan laporan aktif yang masih cocok dengan penugasan, peran, dan status versi laporan.
- **Rekap tindakan bulanan**, berdasarkan catatan riwayat Tahap 19 (pemeriksaan, pengembalian, dan telaah Lurah), tidak menghitung nilai kinerja individu atau keputusan resmi.
- **Perbaikan alur versi:** laporan yang diperbaiki dan dikirim ulang harus melewati pemeriksaan awal lagi walaupun versi terdahulu pernah diperiksa.

Mode ketat tidak otomatis berlaku setelah pemasangan, sehingga pekerjaan sedang berjalan tidak langsung terhenti. Mengaktifkan kategori membutuhkan dua penugasan aktif Tahap 21: pemeriksa yang sah dan Lurah yang sah.

## Keamanan penting

Modul mengubah fungsi otorisasi sisi server **dalam file Tahap 19**, sehingga semua tindakan verifikasi juga mengikuti kebijakan ketat; bukan hanya menyembunyikan tombol pada tampilan. Filter tambahan membatasi daftar dan rincian agar pemeriksa yang tidak ditugaskan tidak dapat mengakses isi laporan hanya dengan menebak nomor ID. Super Admin yang menjalankan MFA tetap memiliki akses pengawasan, tetapi tidak otomatis diizinkan melakukan tindakan pada kategori yang dikunci.

Jika modul verifikasi atau tabel pengaman hilang setelah pengaman tersambung, tindakan ditolak (**fail closed**). Aktivasi mode ketat ditolak bila marker integrasi Tahap 19 tidak ditemukan. Perubahan yang terjadi pada versi file hosting yang tidak cocok ditolak, bukan memaksakan penimpaan kode.

Ketika pengganti tidak aktif lagi, sudah dicabut, tidak berwenang, masa tugasnya habis, atau petugas utama dialihkan, hak cadangan tidak berlaku. Semua langkah masih mengikuti otorisasi dan pemeriksaan ulang akun pada Tahap 19 dan kontrol akun.

**Batasan:** penunjukan pengganti ini masih cadangan tambahan; bukan pemindahan eksklusif. Daftar tugas utama Tahap 21 tetap berlaku untuk petugas utama. Untuk pergantian eksklusif, Super Admin perlu mengubah penugasan utama Tahap 21 dan memastikan akses lama dicabut.

## Database

Hanya membuat tiga tabel **baru** di SmartApp:
1. `unified_review_policy22` — kategori laporan, mode pembatasan, pengubah, dan waktu.
2. `unified_replacements22` — pengganti per kategori+tahap, batas tanggal, alasan, status pencabutan, dan pembuat.
3. `unified_review_events22` — jejak perubahan pengendalian dan pengganti.

Tidak memigrasi atau menimpa isi tabel Pegawai, aplikasi surat RT/RW, cuti, ahli waris atau laporan lama. Tindakan Tahap 19 tetap berada pada tabel laporan tambahan dan audit sesuai skema Tahap 19.

## Instalasi satu ZIP untuk cPanel Rumahweb

1. **Pastikan Tahap 15, 16, 17, 19, 20 dan 21 sudah terpasang dan diuji** dengan akun dummy. Jangan menerapkan penguncian di situs produksi jika masih ada modul yang belum siap.
2. Backup **kedua database, seluruh website, file privat, dan kunci MFA** ke media aman di luar `public_html`.
3. Download `paket/SmartCikoko-TAHAP22-KENDALI-VERIFIKASI.zip` dari repository GitHub.
4. File Manager cPanel → `/home/datd1747/` → Upload ZIP → **Extract pada direktori tersebut**, bukan di dalam public_html.
5. Paket ZIP **hanya menambah berkas baru**. Tidak mengganti `verification19.php`, `super-admin-satu.php`, `bootstrap.php`, `auth.php`, `mfa.php`, `index.php`, `pusat-laporan.php` pada saat ekstraksi.
6. Login Super Admin menggunakan Google Authenticator.
7. Buka **https://datawebs.my.id/instal-review22.php**; klik **Pasang Kendali Verifikasi Tahap 22**.
8. Installer menambah tiga tabel baru pada SmartApp, lalu **mencadangkan file `portal_private/verification19.php` sebelum menambahkan pengaman**, mencadangkan `public_html/super-admin-satu.php` sebelum menambahkan panel, dan opsional mencadangkan `public_html/pusat-laporan.php` sebelum menambahkan tautan. Semua salinan masuk **`portal_private/review22_backups/`**, di luar webroot.
9. Installer hanya mengubah file lama jika struktur dan hash SHA-256-nya cocok. Bila berbeda atau tidak dapat ditulis, proses ditolak dan administrator perlu memeriksa versi file. **Jangan menyalin versi lama dari GitHub ke hosting secara paksa.**
10. Buka **https://datawebs.my.id/kendali-verifikasi22.php**. Pilih kategori uji yang sudah mempunyai pemeriksa/Lurah aktif Tahap 21, aktifkan kebijakan ketat, lalu uji sebagai pemeriksa yang ditugaskan, pemeriksa tidak ditugaskan, Lurah, dan Super Admin.
11. Tetapkan pengganti dummy maksimal 90 hari dan uji kondisi pengganti sah, kedaluwarsa, akun dinonaktifkan, dan pencabutan. Pastikan versi laporan yang dikirim ulang kembali memerlukan pemeriksaan awal.
12. Jika semua uji berhasil, hapus `public_html/instal-review22.php` dan ZIP dari hosting. **Jangan hapus tabel, audit dan backup privat**.

### Pemulihan

Jika ada kendala, **nonaktifkan dulu semua kebijakan ketat** pada halaman kendali ketika masih dapat diakses. Pulihkan backup asli dari `portal_private/review22_backups/` hanya setelah mengamankan perubahan lain yang mungkin dibuat sejak pemasangan. Jangan mengembalikan file `verification19.php` lama saat kategori ketat masih aktif karena pengaman server bisa hilang.

## Alamat

- Super Admin Ringkas: https://datawebs.my.id/super-admin-satu.php#kendali-verifikasi
- Kebijakan dan pengganti: https://datawebs.my.id/kendali-verifikasi22.php
- Kotak tugas cadangan: https://datawebs.my.id/pengganti-saya22.php
- Pemeriksaan Tahap 19: https://datawebs.my.id/verifikasi-laporan19.php
- Installer satu tombol: https://datawebs.my.id/instal-review22.php

## Uji wajib sebelum produksi

- Pemeriksa yang **tidak ditugaskan** ditolak server saat kategori mode ketat aktif, walaupun masih memiliki peran administratif.
- Super Admin tidak bisa mengambil tindakan mewakili pemeriksa/Lurah kecuali ditugaskan secara sah; pengawasan metadata tetap tersedia.
- Lurah tidak dapat melewati pemeriksaan tahap pertama atau memeriksa laporannya sendiri.
- Pengganti hanya dapat berperan sesuai akun asli; tidak ada konversi PTSP menjadi Lurah.
- Masa berlaku habis atau pencabutan segera menghentikan akses cadangan.
- Laporan draf, sudah selesai, atau berganti nomor versi tidak dapat ditindak dengan status lama.
- Semua catatan audit, CSRF, MFA, dan akses akun lama tetap bekerja.
- Rekap bulanan menunjukkan **tindakan administratif dalam bulan itu**, bukan ukuran disiplin atau efektivitas kinerja pegawai.
- Uji PHP lint, simulasi patch, dan unit test GitHub tidak menggantikan pengujian di hosting Rumahweb menggunakan data dummy.

**Status kode GitHub tidak sama dengan pemasangan live.** Perubahan baru aktif setelah installer dijalankan dan masing-masing kategori ditetapkan dalam mode ketat.

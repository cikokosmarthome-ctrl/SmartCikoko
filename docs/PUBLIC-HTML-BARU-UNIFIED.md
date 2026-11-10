# SmartCikoko 2026 — PUBLIC_HTML BARU, DESAIN TERPADU

**Kelurahan Cikoko · Kecamatan Pancoran · Jakarta Selatan**

## Maksud paket

Ini adalah **pengganti lengkap folder web publik**, bukan Tahap 27 atau installer tambahan. Semua PHP aktif, API, CSS pendukung, stylesheet baru, dan berkas `.htaccess` dihimpun dari repository SmartCikoko. Installer dan skrip perbaikan sementara **tidak dibawa ke folder baru**.

Tampilan seluruh halaman HTML diseragamkan: **header konsisten di atas (sticky)**, **footer selalu berada di bagian paling bawah konten/tampilan**, ruang kosong dikurangi, panel dan formulir dipadatkan, serta desain putih–biru modern yang responsif.

Rute dan formulir lama tetap ada, termasuk PPPK, PJLP, cuti, RT/RW, LMK, Jumantik, pengajuan warga, Ahli Waris, statistik, bukti, pemantauan, penugasan, dan verifikasi.

## Super Admin satu halaman

Halaman utama Administrator: `/super-admin-satu.php`.

Tab utama **Ringkasan · Laporan · Kelola · Progres · Pengaturan** semuanya berada pada **satu dokumen**. Bagian *Kelola* memuat akses ke akun, kepegawaian, layanan warga, verifikasi, input dan evaluasi, serta operasional. Setiap menu terbuka **di panel kerja dalam halaman admin yang sama** memakai iframe *same-origin*. Panel modul sendiri menyembunyikan header/footer ganda agar tidak membingungkan. Rute modul asli tetap ada untuk bookmark dan akses peran yang bukan Super Admin.

Akses tetap divalidasi ulang oleh setiap halaman PHP, dan tab administrasi dilindungi oleh `require_super_admin()` dengan MFA. Iframe bukan bypass otorisasi. Halaman admin lama `/super-admin.php` dialihkan ke `/super-admin-satu.php` setelah pemeriksaan Super Admin.

Bagi Super Admin yang membuka `/?page=dashboard`, halaman diarahkan ke `/super-admin-satu.php`; akun warga/petugas biasa tidak diubah. Menu public Super Admin disederhanakan menjadi Admin dan Akun.

## Pembatasan keamanan

- Tidak mengubah, memindahkan, atau mereset konfigurasi database, password, MFA, data laporan, dan berkas warga di **`/home/datd1747/portal_private/`**. Paket hanya **menambahkan satu file helper baru** `portal_private/mobile_forms_api.php` untuk formulir Android/iOS; file konfigurasi privat tidak disertakan.
- PHP inti untuk login, pengajuan, verifikasi, ekspor, riwayat, dan formulir dari repository **dipertahankan**. Perubahan dilakukan pada kerangka tampilan dan navigasi Admin utama.
- File `api/index.php`, `api/.htaccess` serta `public_html/.htaccess` dipertahankan.
- Paket baru **tidak memuat** skrip sementara `instal-*.php`, `perbaiki-*.php`, `pasang-*.php`, atau `aktivasi-super-admin.php`. Pastikan modul Tahap 15–26 yang diperlukan sudah terpasang.
- **Paket GitHub tidak mengetahui seluruh isi hosting.** Folder `.well-known`, dokumen atau gambar unggahan, aset kustom, file verifikasi domain, dan berkas lain yang hanya ada di cPanel **tidak otomatis ikut**. Identifikasi dan salin yang memang diperlukan sebelum penggantian.
- Ketika mengganti `public_html`, semua alamat yang menghasilkan PHP memakai lokasi privat `dirname(__DIR__).'/portal_private'`. Itulah alasan folder *staging* ditempatkan tepat di bawah `/home/datd1747/`.

## Paket

`paket/SmartCikoko-WEBSITE-MOCKUP-V4.zip` berisi:

```text
public_html_baru/
  .htaccess
  index.php
  api/index.php
  api/.htaccess
  api/mobile-forms.php
  super-admin-satu.php
  mfa.php
  formulir-pegawai16.php
  pusat-operasional23.php
  ... seluruh modul aktif dari repository ...
  assets/ui-modern.css
  assets/portal-mockup.css
  assets/cikoko-office-illustration.svg
  assets/ui-modern.js
  assets/admin-modern.js
  ... stylesheet pendukung ...
portal_private/
  mobile_forms_api.php # helper BARU: bukan berkas konfigurasi
BACA-SEBELUM-GANTI.txt
MANIFEST-DEPLOY.json
```

Tidak ada SQL manual dan tidak ada installer di folder publik baru.

**Integrasi Flutter:** endpoint `public_html_baru/api/mobile-forms.php` memerlukan helper baru `portal_private/mobile_forms_api.php`. Keduanya sudah disertakan dalam ZIP V4, sedangkan konfigurasi database privat tetap milik hosting. Setelah situs baru diuji, aplikasi Flutter bisa menggunakan fungsi laporan PPPK, PJLP, cuti, RT/RW, LMK, Jumantik, dan layanan lainnya sesuai hak akses.

## CARA PASANG YANG AMAN — JANGAN HAPUS WEB AKTIF DULU

1. **Backup penuh** folder `/home/datd1747/public_html/`, `/home/datd1747/portal_private/`, kedua database, file tersembunyi `.htaccess`, file verifikasi SSL/domain (`.well-known`), serta unggahan yang hanya ada di hosting. Simpan backup di tempat aman **di luar webroot**.
2. Buka cPanel File Manager, menuju `/home/datd1747/`.
3. Upload `SmartCikoko-WEBSITE-MOCKUP-V4.zip` lalu klik **Extract di `/home/datd1747/`**. Ini membuat **`/home/datd1747/public_html_baru/`**, terpisah dari website aktif; tidak menimpa `public_html`. ZIP juga menambahkan `portal_private/mobile_forms_api.php` untuk API Flutter, tanpa mengganti konfigurasi, MFA, atau file database.
4. Periksa `public_html_baru/index.php`, `.htaccess`, `api/`, `assets/` serta file modul penting tersedia.
5. **Uji staging** melalui domain/subdomain uji dengan Document Root `/home/datd1747/public_html_baru`, jika tersedia pada paket Rumahweb. Karena letaknya satu tingkat di bawah home, kode PHP yang menggunakan `dirname(__DIR__)` tetap menemukan `portal_private`. Perlu diketahui: URL absolut dan cookie pada modul tertentu mungkin tetap memerlukan uji akhir di domain asli.
6. Uji dengan **akun dummy**: login biasa, Super Admin + Google Authenticator, menu Admin dengan tab internal, PPPK/JLP/cuti, RT/RW, LMK, Jumantik, pelayanan Ahli Waris, penugasan, verifikasi, unggah/unduh, dan logout. Jangan gunakan data warga asli untuk UAT.
7. Jika semua rute penting telah diuji, pilih masa pemeliharaan dan **pastikan ada backup yang bisa dipulihkan**. Jika cPanel mengizinkan rename document root, ganti nama `public_html` menjadi nama backup sementara di luar webroot, lalu `public_html_baru` menjadi `public_html`. Jangan lakukan bila cPanel melarang atau bergantung pada symbolic link/domain/subdomain; gunakan mekanisme ganti Document Root/migrasi yang disediakan hosting. Lakukan secara singkat agar tidak ada transaksi baru selama perpindahan. **Jangan hapus backup**.
8. Pulihkan berkas/unggahan khusus yang diperlukan dari backup; pertahankan hak akses file dan `.htaccess`. Pastikan folder `portal_private` tetap **di luar** `public_html`.
9. Pada domain utama buka `https://datawebs.my.id/`, `https://datawebs.my.id/super-admin-satu.php`, formulir PPPK, Pusat Operasional, serta jalur unduh. Tekan **Ctrl+F5** agar stylesheet baru terbaca.
10. Jika muncul gangguan, kembalikan document root lama atau folder `public_html` dari backup. Jangan menghapus backup sampai pemeriksaan akhir berhasil.

## Pengujian sebelum rilis dan batasan

GitHub Actions memeriksa seluruh PHP dalam paket dengan `php -l`, regresi PHP Tahap 16 dan Tahap 23, sintaks JavaScript, cakupan file/rute, serta kehadiran header/footer dan stylesheet pada seluruh halaman HTML.

**Ini bukan verifikasi live**. Pengujian otomatis tidak mampu mengonfirmasi versi file lokal hosting yang tidak masuk GitHub, isi unggahan lokal, keberhasilan semua transaksi MySQL produksi, perilaku perangkat fisik, atau konfigurasi subdomain/cPanel.

Jika ada satu modul yang gagal, lakukan rollback dan perbaiki modul spesifik tersebut; jangan langsung menghapus folder produksi atau database.

## Jika folder lama mempunyai file lebih banyak

Daftar file yang benar-benar ada di cPanel dapat berbeda dari repository. Paket ini tidak boleh dianggap salinan lengkap file unggahan atau konfigurasi server. **Jangan hapus `public_html` lama sebelum kontennya dicatat, dibackup, dan dipastikan tidak mengandung data unik**.

## Penyempurnaan V4 — navigasi sesuai mockup

Beranda V4 sekarang menyertakan tautan **Profil, Layanan, Informasi, Agenda, Galeri, Kontak** di samping Beranda dan Masuk Portal. Bagian Profil dan Kontak berisi informasi institusi yang tidak memerlukan data sensitif; Galeri menampilkan ilustrasi layanan dengan keterangan yang jelas **bukan dokumentasi foto nyata**, sehingga tidak mengarang foto kegiatan atau nomor kontak.

Perubahan CSS memakai versi `portal-mockup.css?v=20261110b` agar pembaruan tampilan dapat terambil saat staging. Paket `SmartCikoko-WEBSITE-MOCKUP-V4.zip` tetap dihasilkan GitHub Actions dalam folder `public_html_baru` dan **tidak dipasang otomatis** ke hosting. Instruksi backup, inspeksi upload, pengujian, migrasi dan rollback di atas tetap berlaku.

**Pemeriksaan akhir setelah penggantian:** buka `/#profil`, `/#layanan`, `/#galeri`, `/#kontak`, uji tampilan ponsel, login akun uji, halaman Admin, `/api/?resource=posts`, dan `/api/mobile-forms.php?action=catalog` dengan sesi yang berwenang. Respons gagal dari API privat sebelum deployment bukan alasan untuk menghapus database atau menonaktifkan MFA.

## Pembaruan Alur Laporan, Surat, dan Notifikasi Terpadu

Pelajari [ALUR-LAPORAN-SURAT-WEB-FLUTTER.md](ALUR-LAPORAN-SURAT-WEB-FLUTTER.md) sebelum memasang penyempurnaan ini. ZIP V4 sekarang menambah **dua berkas privat baru** `portal_private/workflow_progress.php` dan `portal_private/workflow_feed.php` (di samping helper `mobile_forms_api.php`), serta menyediakan pembaruan `citizen_followup.php` pada folder **`PRIVATE-PATCH-REVIEW/`** untuk ditinjau secara manual.

Berkas `citizen_followup.php` **tidak ditimpa otomatis** ketika ZIP diekstrak di home hosting. Pemilik harus membandingkan dan membackup versi produksi sebelum menerapkan daftar 14 jenis persyaratan Ahli Waris. Tanpa patch tersebut, jenis lampiran lama yang baru ditambahkan belum aktif; pengajuan Tahap 9 tetap mengikuti kemampuan server yang terpasang.

Pusat aktivitas: `/aktivitas-terpadu.php`; API mobile: `/api/?resource=workflow_feed`, `/api/?resource=citizen_requirements&id=...` dan `/api/citizen-upload.php`. Semuanya memakai autentikasi/izin server. **Tidak tersedia push notification ketika aplikasi tertutup**, juga tidak ada klaim otomatis bahwa persetujuan administratif berarti surat resmi sudah terbit.

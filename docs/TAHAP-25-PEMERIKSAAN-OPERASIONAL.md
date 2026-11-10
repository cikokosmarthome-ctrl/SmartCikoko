# SmartCikoko Tahap 25 — Pemeriksaan Operasional Baca-Saja

**Kelurahan Cikoko · Kecamatan Pancoran · DKI Jakarta**

## Mengapa tahap ini diperlukan?

Tahap 23 telah menyediakan Pusat Operasional, sementara Tahap 24 memperbaiki penghitungan ringkasan yang sebelumnya gagal karena kunci numerik `15`, `16`, `17` diperlakukan PHP sebagai integer tetapi dikirim ke fungsi dengan deklarasi `string` dan `strict_types=1`.

Sebelum membuat modul baru, Tahap 25 membantu pengelola menilai apakah **komponen laporan sudah bisa dibaca, ringkasan konsisten dengan tabel sumber, dan jalur pemeriksaan tersedia**, tanpa mengubah data produksi. Ini **bukan** uji penyelesaian transaksi warga atau sertifikasi kelayakan produksi.

## Fitur

- **Cek koneksi baca** ke SmartApp dan Pegawai tanpa membuka data identitas atau konfigurasi koneksi.
- **Kesiapan Tahap 15–22** berdasarkan fungsi pendeteksi tabel masing-masing. “Tersedia” hanya berarti tabel dapat dibaca, **bukan semua tombol/formulir pasti berfungsi**.
- **Ketepatan angka**: menghitung status ringkas Tahap 15–17 berdasarkan perhitungan Tahap 24, kemudian membandingkannya dengan `SELECT COUNT(*)` independen pada masing-masing tiga tabel.
- **Rincian total sumber** Tahap 15, 16, dan 17. Jika sumber belum dapat dibaca, tampil “belum terbaca” dan bukan angka nol palsu. Sumber lain tetap muncul.
- **Pemeriksaan pengaman Stage22**: jika ada kebijakan penugasan ketat yang aktif tetapi marker pengaman otorisasi Tahap 19 tidak terdeteksi, hasil menunjukkan kondisi serius dan menyarankan penghentian verifikasi sampai diperiksa.
- **Status penjadwalan Tahap 21**: menunjukkan izin penjadwal internal aktif/nonaktif. Tidak mengklaim Cron Jobs sudah terpasang atau berjalan; hal itu harus diperiksa tersendiri di cPanel.
- **Keberadaan berkas halaman**: hanya mengonfirmasi berkas PHP dapat dibaca di server. Tidak mengirim HTTP request, tidak mencoba menyamar sebagai akun lain, dan tidak mengeksekusi tindakan laporan.
- **Daftar UAT manual**: akun dummy PPPK, PJLP, cuti, RT/RW, LMK, Jumantik, FKDM, PPSU, pemeriksa, Lurah, petugas pengganti, dan Super Admin.

Halaman pemeriksaan menampilkan status **Terbaca**, **Perlu diperiksa**, atau **Tidak tersedia**. Semua penamaan status dijelaskan sebagai bukti teknis terbatas, bukan jaminan keamanan.

## Keamanan

- Halaman `/cek-operasional25.php` hanya untuk **Super Admin setelah MFA dan pemeriksaan ulang status akun**.
- Tidak menampilkan NIK, nama pelapor, isi laporan, payload JSON, nomor dokumen, foto, sandi, token, dan pengaturan database.
- Pemeriksaan hanya memakai query `SELECT` dan pemeriksaan keberadaan berkas. Tidak membuat tabel atau laporan, tidak melakukan perubahan status, dan tidak mengirim WhatsApp/SMS/email.
- Perhitungan total pada halaman diagnostics hanya untuk administrator berwenang; petugas tidak mendapat link atau akses.
- Panel Super Admin Ringkas hanyalah **tautan ke halaman cek**, sehingga pengecekan beberapa sumber tidak memperlambat setiap pembukaan dashboard.
- Integrasi menu menggunakan **SHA-256 file saat itu** dan backup privat sebelum penambahan panel; jika struktur file atau hash berubah, pemasangan ditolak.

## Petunjuk cPanel — satu ZIP, tanpa SQL

1. Sebelum pemasangan, **backup file website, database Pegawai dan SmartApp, konfigurasi privat, serta kunci MFA** ke tempat aman di luar `public_html`.
2. Pastikan Tahap 15–24 yang diperlukan sudah diekstrak/diuji. Jika Tahap 24 belum terpasang, halaman cek dapat menunjukkan bahwa perbaikan statistik belum terdeteksi; lanjutkan pemasangan Tahap 24 terlebih dahulu.
3. Download `paket/SmartCikoko-TAHAP25-PEMERIKSAAN-OPERASIONAL.zip` dari repo GitHub.
4. cPanel File Manager → **`/home/datd1747/`** → Upload ZIP → Extract **di direktori ini**, bukan di `public_html`.
5. ZIP berisi **hanya file baru**:
   - `portal_private/audit25.php`
   - `portal_private/audit25_admin_panel.php`
   - `portal_private/audit25_patch.php`
   - `public_html/cek-operasional25.php`
   - `public_html/instal-operasional25.php`
   - `public_html/assets/cek-operasional25.css`
   - `public_html/assets/audit25-admin.css`
   - `BACA-TAHAP25.txt`
6. Login Super Admin dan selesaikan verifikasi Google Authenticator.
7. Buka **https://datawebs.my.id/instal-operasional25.php** lalu klik **Hubungkan Pemeriksaan Tahap 25**. Tautan ringkas dipasang ke `super-admin-satu.php`. Sebelumnya installer menyimpan backup ke **`portal_private/audit25_backups/`**, di luar folder publik.
8. Buka **https://datawebs.my.id/cek-operasional25.php**. Periksa angka laporan, kesamaan total tiga sumber, hasil kesiapan, dan peringatan. Jika berbeda, periksa log PHP **tanpa membagikan rahasia**.
9. Jalankan UAT manual pada akun dan laporan dummy dengan izin resmi, jangan di data warga asli. Setelah pengujian berhasil, hapus `public_html/instal-operasional25.php` dan ZIP dari hosting. **Jangan hapus backup privat**.

## Daftar UAT manual yang harus dicatat

| Uji | Akun uji | Kondisi lulus | Dilakukan di hosting? |
|---|---|---|---|
| Input draf + ajukan PPPK | PPPK dummy | Draf muncul di akun yang benar, sesudah dikirim berubah jadi dalam proses | **Belum** |
| Input kegiatan PJLP | PJLP dummy | Hanya laporan miliknya yang tampil | **Belum** |
| Pengajuan awal cuti | Akun Pegawai yang berwenang | Status administratif benar, **bukan** persetujuan cuti resmi | **Belum** |
| Kegiatan RT/RW | RT/RW dummy | Menu sesuai peran, laporan baru dapat diajukan | **Belum** |
| Kegiatan LMK | LMK dummy | Akses hanya sesuai peran | **Belum** |
| Observasi Jumantik | Akun yang berwenang | Tidak memperlihatkan data kesehatan warga lain | **Belum** |
| Kegiatan FKDM dan PPSU | Akun dummy masing-masing | Status dan kepemilikan benar | **Belum** |
| Pemeriksaan Tahap 19 | Pemeriksa + Lurah dummy | Urutan dan larangan memeriksa sendiri dipatuhi | **Belum** |
| Penugasan dan pengganti 21–22 | Akun dummy ditunjuk/tidak ditunjuk | Akun lain ditolak jika kategori mode ketat | **Belum** |
| Peringatan internal | Akun dummy yang ditugaskan | Tidak muncul pada akun berbeda, tidak memutuskan persetujuan | **Belum** |
| Perbandingan statistik | Super Admin | Total tiga sumber cocok dengan tabel serta ringkasan | **Belum** |
| Responsif antarmuka | Desktop dan ponsel | Menu, tabel dan status dapat digunakan | **Belum** |

**Catatan:** Kolom “Belum” berarti pengujian otomatis GitHub tidak menggantikan uji langsung di hosting Rumahweb.

## Batasan yang sengaja dijaga

- Tidak memverifikasi bahwa dokumen fisik atau arsip foto aman dari malware.
- Tidak mengetes pengiriman surat resmi, pengesahan Lurah, penilaian kinerja PPPK/PJLP, atau pembaruan saldo cuti.
- Tidak menjadwalkan cron atau mengirim pesan otomatis.
- Tidak memeriksa izin petugas dengan login ke akun lain tanpa persetujuan.
- Tidak mengubah tabel dan tidak merekayasa angka 0 jika sumber gagal dibaca.
- Status koneksi “Terbaca” **tidak sama dengan** aman untuk seluruh operasi SQL atau pengetesan beban.

**Langkah berikut setelah UAT** adalah memperbaiki hanya kegagalan yang tercatat, lalu memadatkan navigasi atau mengoptimalkan query yang terbukti lambat. Hindari menambahkan modul yang tidak dibutuhkan.

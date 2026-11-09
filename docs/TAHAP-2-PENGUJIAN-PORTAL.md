# Tahap 2 — Validasi Portal Terpadu SmartCikoko

**Tahap ini dimulai setelah installer cPanel dan impor kedua database selesai.**

Portal domain: `https://datawebs.my.id` — kode: `https://github.com/cikokosmarthome-ctrl/SmartCikoko`.

## A. Keamanan sebelum penggunaan nyata

1. Hapus `public_html/instalasi.php`, `/home/datd1747/KUNCI-INSTALASI.txt`, file ZIP instalasi, dan file sementara impor akun. Jangan hapus `public_html`, `portal_private`, atau `portal_private/config.php`.
2. Pastikan `/home/datd1747/portal_private/config.php` berada di luar `public_html` dan hanya bisa dibaca pemilik akun hosting.
3. Ganti password kedua user MySQL yang pernah dibagikan, lalu perbarui `config.php`.
4. Pastikan HTTPS aktif serta tidak ada informasi rahasia di repo GitHub, berkas publik, atau screenshot uji.

## B. Uji website publik dan API

- [ ] `https://datawebs.my.id/` menampilkan beranda Kelurahan Cikoko dan tidak menunjukkan error PHP.
- [ ] `https://datawebs.my.id/api/?resource=health` merespons JSON `{"ok":true,...}`.
- [ ] `/?page=berita` dan `/?page=agenda` dapat dibuka.
- [ ] `/?page=login` menampilkan form login terpadu.
- [ ] Tidak ada file SQL, JSON akun, file konfigurasi, arsip ZIP, maupun installer yang dapat diunduh dari URL publik.

## C. Uji akun dari seluruh sumber — gunakan akun uji/yang memiliki izin

| Sumber | Jenis akun | Hasil yang diharapkan |
|---|---|---|
| Database Pegawai | Administrator | Login berhasil; dashboard menampilkan seluruh modul admin |
| Database Pegawai | PPPK | Login berhasil; hanya modul dan data PPPK yang diizinkan |
| Database Pegawai | PJLP/PPSU | Login berhasil; hanya laporan/aktivitas pengguna terkait |
| Database Pegawai | PPPK Pancoran | Login berhasil; akses yang dipetakan; tidak melihat data PPPK orang lain |
| Database SmartApp | Administrator lama | Login dari akun hasil migrasi berhasil; dashboard berbasis kewenangan |
| Database SmartApp | Petugas pelayanan | Dapat melihat dan mengelola ahli waris hanya sesuai kewenangan |
| Database SmartApp | Masyarakat | Tidak bisa membaca data pengajuan warga lain |

**Penting:** satu identitas dapat memiliki dua sumber akun. Jika username dan password cocok di beberapa sumber, sistem harus menawarkan pemilihan identitas tanpa menggabungkan hak aksesnya.

## D. Uji pengamanan hak akses

- [ ] Akses URL internal tanpa login harus mengarah ke login atau HTTP 401.
- [ ] Pengguna PPPK/PJLP tidak dapat membaca daftar seluruh pegawai.
- [ ] Pengguna masyarakat tidak dapat membaca pengajuan ahli waris milik akun lain meskipun menebak ID URL/API.
- [ ] Percobaan kata sandi salah dibatasi.
- [ ] Logout mengakhiri sesi; API token tidak lagi dapat digunakan setelah logout.
- [ ] Aktivitas pengelolaan pengajuan terekam dalam log audit.

## E. Kesesuaian data

- [ ] Data asli pada dua database tidak berubah setelah instalasi (tidak ada tabel sumber yang ditimpa).
- [ ] Jumlah akun dan laporan di dashboard cocok dengan hasil query phpMyAdmin.
- [ ] Relasi akun dengan laporan, foto, lampiran, dan permohonan ahli waris diuji satu per satu; berkas asli dari backup lama harus dipulihkan secara privat bila diperlukan.
- [ ] Identifikasi data duplikat dan data percobaan dilakukan sebelum aplikasi digunakan masyarakat.

## F. Lanjut ke Android setelah uji A–E lulus

1. Pastikan API `/api/?resource=login`, `me`, `dashboard`, dan modul berjalan dengan HTTPS.
2. Jalankan `android_flutter` dengan Flutter SDK; uji login lintas peran, token, logout, error jaringan, dan tampilan perangkat.
3. Siapkan identitas aplikasi, pengendali data, kebijakan privasi final, signing key dan pengujian Play Store.

**Dilarang menaruh NIK, nomor telepon, password, token, hash password, atau dump warga/pegawai dalam issue publik GitHub atau screenshot yang dibagikan.**

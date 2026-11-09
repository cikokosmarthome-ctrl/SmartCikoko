# SmartCikoko — Tahap 13: MFA, Audit Login, dan Pengamanan Lampiran

Tahap 13 memperkuat akun Super Admin dengan TOTP dua faktor (6 digit, 30 detik), **8 kode pemulihan sekali pakai**, perlindungan token API setelah aktivasi MFA, pencatatan login gagal, dan integrasi pemindai ClamAV yang dapat diwajibkan oleh konfigurasi privat.

**Penting:** ini bukan sertifikasi keamanan. Pemeriksaan ClamAV **tidak aktif secara default** karena ketersediaan layanan scanner di Rumahweb belum diverifikasi. Validasi MIME/ukuran/hash tetap bukan pemeriksaan virus. Dokumen warga yang nyata belum boleh diunggah tanpa tata kelola dan pengamanan lengkap.

## Dua paket instalasi — aman bagi website aktif

### A. Persiapan (hanya file baru)
1. Backup seluruh file website dan **kedua database** ke tempat aman (bukan ke GitHub atau folder publik).
2. Download **`paket/SmartCikoko-TAHAP13-PERSIAPAN.zip`** dan upload via cPanel File Manager ke **`/home/datd1747/`**. Ekstrak dari direktori ini, **bukan dari dalam public_html**.
3. Login akun Super Admin seperti biasa. Buka **https://datawebs.my.id/instal-keamanan13.php**.
4. Klik **Pasang Tahap 13** untuk membuat tiga tabel baru di SmartApp serta kunci enkripsi lokal `portal_private/mfa_key.php`.
5. **Jangan mendaftarkan MFA dulu** sampai paket aktivasi terpasang dan login normal diuji.

### B. Aktivasi (mengganti file inti, WAJIB BACKUP)
1. Buat backup **terpisah** file:
   - `portal_private/bootstrap.php`
   - `portal_private/auth.php`
   - `portal_private/super_admin.php`
   - `portal_private/document_workflow.php`
   - `portal_private/citizen_followup.php`
   - `public_html/index.php`
   - `public_html/api/index.php`
2. Paket aktivasi **menggantikan file inti tersebut**. Pastikan hosting benar-benar menjalankan PHP 8.3/8.4 pada domain datawebs.my.id dan situs berjalan normal, serta file live sesuai versi repository. Jika pernah ada modifikasi langsung di cPanel, cocokkan perubahannya lebih dulu atau gunakan situs staging — jangan timpa tanpa tinjauan.
3. Download **`paket/SmartCikoko-TAHAP13-AKTIVASI.zip`** dan ekstrak ke `/home/datd1747/` setelah backup.
4. Buka website dan login sebagai akun biasa, lalu Super Admin. **Sebelum MFA didaftarkan, login harus tetap berfungsi seperti sebelumnya.**
5. Buka **https://datawebs.my.id/mfa.php**. Konfirmasi kata sandi; tambahkan kunci ke aplikasi authenticator; masukkan kode enam digit.
6. Simpan **delapan kode cadangan** saat ditampilkan (sekali saja), di tempat yang aman dan terpisah dari kata sandi. Jangan mengirimkan tangkapan layar/OTP/kunci melalui chat.
7. Logout, login ulang dan pastikan akun Super Admin tidak dapat masuk ke dashboard tanpa kode MFA atau kode pemulihan. Uji aplikasi Android dengan OTP. API token Super Admin lama harus ditolak.
8. Lihat **https://datawebs.my.id/keamanan-superadmin.php** untuk status MFA, audit, scanner, dan petunjuk tindak lanjut.
9. Hapus `public_html/instal-keamanan13.php` serta kedua ZIP dari hosting setelah pemasangan benar-benar berhasil. **Jangan menghapus `portal_private/mfa_key.php` atau tabel MFA** setelah MFA didaftarkan. Kunci tersebut perlu backup terenkripsi bersama basis data, karena diperlukan untuk mendekripsi secret MFA.

## Fitur yang benar-benar tersedia

- MFA TOTP baru diwajibkan setelah akun Super Admin **menyelesaikan pendaftaran** menggunakan password dan kode pertama.
- Secret TOTP disimpan terenkripsi AES-256-GCM dengan kunci acak 32 byte dalam `portal_private/mfa_key.php` (di luar `public_html`).
- Kode TOTP yang sama hanya dapat digunakan **sekali per timestep**, menggunakan `SELECT ... FOR UPDATE` untuk mencegah replay.
- Kode pemulihan 16 karakter acak disimpan dalam bentuk hash dan dihapus dari database setelah dipakai. Tidak ada fitur reset mandiri tanpa verifikasi.
- Web browser Super Admin harus menyelesaikan kode MFA. Token API baru untuk akun Super Admin yang telah didaftarkan memerlukan kode MFA dan attestation dengan batas 12 jam; token lama tidak memenuhi attestation.
- Perubahan login gagal dan hasil MFA dicatat lewat tabel audit yang sudah ada. Identitas akun pada kegagalan login dicatat sebagai hash, bukan password.
- Unggahan Tahap 8 dan 10 dapat melewati scanner ClamAV **hanya jika** opsi `upload_scanning.enforce` di `portal_private/config.php` dibuat `true` dan `upload_scanning.clamd_socket` disetel ke socket pemindai lokal yang telah diverifikasi. Jika diaktifkan tetapi daemon tidak tersedia atau berkas tidak bersih, upload **ditolak**. Dengan kondisi default (belum aktif), file belum dapat dinyatakan telah dipindai.
- Tidak ada perubahan skema pada database Pegawai, akun pengguna asli, atau dokumen lama.

Contoh opsional untuk pengelola server (bukan instruksi menebak lokasi socket):
```php
'upload_scanning' => [
    'enforce' => true,
    'clamd_socket' => '/path/dikonfirmasi-pengelola-server/clamd.sock',
],
```
Hanya aktifkan sesudah Rumahweb mengonfirmasi socket ClamAV yang benar, PHP memiliki izin membuka socket, serta pengujian deteksi/penolakan file berbahaya melalui berkas uji aman berhasil dilakukan. Tanpa itu, biarkan fitur belum aktif dan batasi pemrosesan dokumen pribadi.

## Uji keamanan sebelum produksi

- Warga A tidak dapat membaca berkas Warga B. Akun PTSP/Lurah tidak dapat mengakses halaman Super Admin.
- Akun Super Admin yang sudah MFA aktif akan diarahkan ke tantangan MFA pada semua halaman berbasis sesi browser, bukan hanya dashboard.
- Login API Super Admin tanpa kode MFA ditolak; dengan kode valid baru memperoleh token attested. Token lama dan kode replay ditolak.
- Gunakan kode pemulihan sekali, pastikan kode yang sama tidak dapat digunakan kembali.
- Pastikan kode rahasia MFA dan database credential **tidak pernah diunggah ke GitHub**.
- Uji kembali login akun biasa, pengajuan warga, API kesehatan, seluruh dua database serta unduh/unggah berkas dummy.
- Uji antivirus di lingkungan terisolasi dan tetapkan kebijakan retensi data dan backup.
- Jika sesudah aktivasi situs error, pulihkan file inti dari backup yang dibuat sebelum aktivasi. **Jangan hapus basis data atau kunci MFA**. Jika MFA sudah didaftarkan, pemulihan autentikasi harus tetap dilakukan dengan prosedur aman dan tidak boleh membypass MFA secara diam-diam.

## Batasan

**MFA bukan pengganti audit independen, pengamanan session, pengujian penetrasi atau kebijakan privasi.** Fitur ini hanya untuk Super Admin yang masuk allowlist; kewajiban MFA untuk PTSP/Lurah belum diberlakukan. ClamAV tidak dipastikan tersedia pada cPanel. Belum ada integrasi surat resmi/TTE. Kode GitHub tidak mengubah hosting secara otomatis.

Jika tidak yakin file live cocok dengan GitHub, gunakan **paket persiapan saja** dan jangan memasang paket aktivasi sebelum backup dan pemeriksaan kode selesai.

# Tahap 4 — Aktivasi Akun Super Administrator SmartCikoko

Tujuan: satu akun administrator tertentu memperoleh **Pusat Super Admin** untuk pengawasan dan akses lintas modul yang didukung dua database. Database \`datd1747_Pegawai\` dan \`datd1747_smartapp\` tetap terpisah. Tidak ada data lama ditimpa.

**Status:** kode ada di GitHub, tetapi perubahan di GitHub **tidak otomatis** tampil di hosting Rumahweb. Aktivasi dilakukan lewat File Manager dan phpMyAdmin oleh pengelola cPanel.

## 0. Keamanan

- Cadangkan \`/home/datd1747/portal_private/bootstrap.php\` dan \`config.php\`, serta database \`datd1747_smartapp\`.
- Jangan menyimpan kata sandi, SQL dump, NIK, atau file konfigurasi aktif di GitHub publik.
- Aktifkan hanya satu akun administrator terpercaya; akun admin lain **tidak otomatis** menjadi Super Admin.
- Password database yang pernah dibagikan harus diganti, dan penggunaan Super Admin pada layanan pemerintah memerlukan MFA sebelum produksi.

## 1. Tentukan ID akun Administrator yang akan dinaikkan

Buka cPanel → **phpMyAdmin** → database \`datd1747_Pegawai\` → SQL, jalankan **hanya SELECT**:

\`\`\`sql
SELECT id, username, role, status
FROM users
WHERE role IN ('admin','superadmin','admin_kelurahan')
ORDER BY id;
\`\`\`

Pilih baris yang benar-benar mewakili akun administrator yang ingin diberi kendali pusat. Catat \`id\` baris tersebut di komputer Anda, jangan menebaknya.

Jika memilih admin dari portal lama yang sudah dimigrasi ke SmartApp, jalankan pada database \`datd1747_smartapp\`:

\`\`\`sql
SELECT id, username, role, active
FROM unified_portal_users
WHERE role IN ('admin','superadmin','admin_kelurahan');
\`\`\`

**Satu ID admin cukup untuk mengendalikan dua database.** Tidak perlu mengubah \`role\` pada \`users\` atau memindahkan akun antar database.

## 2. Upload file baru sesuai direktori

Dari repository GitHub \`cikokosmarthome-ctrl/SmartCikoko\`, unggah file berikut ke lokasi yang sama pada cPanel Rumahweb:

| File di GitHub | Direktori di cPanel |
| --- | --- |
| \`portal_private/super_admin.php\` | \`/home/datd1747/portal_private/super_admin.php\` |
| \`public_html/super-admin.php\` | \`/home/datd1747/public_html/super-admin.php\` |
| \`public_html/assets/super-admin.css\` | \`/home/datd1747/public_html/assets/super-admin.css\` |

**Jangan mengunggah \`portal_private\` ke \`public_html\`.**

## 3. Perbarui bootstrap.php yang sudah ada

Buka \`/home/datd1747/portal_private/bootstrap.php\`. Pada bagian bawah, tepat setelah \`require_once __DIR__.'/data.php';\`, tambahkan:

\`\`\`php
require_once __DIR__.'/super_admin.php';
\`\`\`

Jangan mengganti seluruh isi \`bootstrap.php\` di server jika sudah mempunyai perubahan tersendiri. Cukup tambahkan baris ini satu kali.

## 4. Aktifkan satu identitas Super Admin di konfigurasi privat

Buka \`/home/datd1747/portal_private/config.php\`, dan tambahkan satu entri pada array konfigurasi utama, misalnya tepat setelah \`site_name\`:

\`\`\`php
'super_admin_accounts' => [
    ['source' => 'pegawai', 'id' => 'ID_SEBENARNYA_DARI_SQL'],
],
\`\`\`

Ganti \`ID_SEBENARNYA_DARI_SQL\` dengan \`id\` akun yang dipilih pada langkah 1.

Jika memilih administrator dari \`unified_portal_users\`, gunakan \`'source'=>'smartapp'\`. **Jangan menyalin file \`config.example.php\` sebagai pengganti \`config.php\` yang sudah berisi koneksi database.**

Pengaturan ini tidak mengubah role lama. Sistem memverifikasi sumber, ID, role administrator, dan status aktif setiap kali membuka halaman Super Admin. Jika pengaturan kosong, akses ditolak.

## 5. Instal tabel progres (1 kali)

Buka phpMyAdmin → pilih **database \`datd1747_smartapp\`** → **Import** → pilih berkas \`portal_private/schema-superadmin.sql\` dari GitHub, lalu klik **Go / Kirim**.

Tabel yang dibuat: \`unified_module_progress\`, dengan 7 modul dan progres awal **0%**. SQL memakai \`CREATE TABLE IF NOT EXISTS\` dan \`INSERT IGNORE\`. Tidak ada data pegawai maupun data warga yang diubah.

## 6. Uji

1. Login sebagai administrator yang dipilih.
2. Buka \`https://datawebs.my.id/super-admin.php\`.
3. Pastikan kedua koneksi bertanda **Aktif** dan angka rekap konsisten dengan dashboard lama.
4. Ubah status modul yang sedang dikerjakan, isi persen yang benar, tambahkan catatan, kemudian **Simpan Progres**.
5. Login dengan akun admin biasa atau akun pengguna lain; halaman \`/super-admin.php\` harus menolak akses **HTTP 403**.
6. Cek tabel \`unified_audit\` untuk aktivitas \`super_admin_progress_updated\` setelah pembaruan.

## Apa yang bisa dilakukan Super Admin?

- Melihat statistik lintas database secara agregat.
- Memantau apakah dua database dapat dihubungi.
- Membuka modul kepegawaian, PPPK, PJLP/PPSU, ahli waris, berita dan agenda melalui link yang sama.
- Mencatat dan memperbarui status pelaksanaan dan persentase pekerjaan pada tujuh modul.
- Mengakses data melalui modul lama sesuai kewenangan administrator.

### Pembatasan jujur
Panel ini bukan mesin yang secara otomatis menjalankan semua tugas atau fitur CRUD semua modul. Pengelolaan data tetap dilakukan melalui modul masing-masing; fungsi baru tersebut perlu dibangun dan diuji bertahap. API menambahkan layanan `superadmin_dashboard` hanya untuk akun Super Admin, dan aplikasi Android mempunyai ringkasan baca-saja status serta progres. **Pengubahan progres hanya lewat website** agar lebih mudah diaudit. Aplikasi Android masih membutuhkan build, uji perangkat dan rilis baru sebelum fitur tersebut tersedia pada aplikasi terpasang.

# Aktivasi Super Admin — Tanpa SQL (Rumahweb cPanel)

Untuk pengguna yang sudah berhasil login sebagai **Administrator** di \`https://datawebs.my.id\`. Ini **bukan** pembuatan ulang website atau database: data Pegawai dan SmartApp tetap dipertahankan.

## Instalasi sederhana

1. **Backup** berkas \`/home/datd1747/portal_private/bootstrap.php\` dan file Super Admin lama (jika ada). Database sudah terpasang, **jangan impor SQL lama lagi**.
2. Unduh paket kecil \`SmartCikoko-AKTIVASI-SUPERADMIN.zip\` yang berisi berkas perubahan. Masuk cPanel Rumahweb → **File Manager** → \`/home/datd1747/\` → **Upload** → **Extract**. Jangan mengekstrak dari dalam \`public_html\`.
3. Login ke \`https://datawebs.my.id/?page=login\` memakai **akun Administrator yang akan dijadikan Super Admin**.
4. Kunjungi \`https://datawebs.my.id/aktivasi-super-admin.php\`. Halaman mengenali ID akun Anda dari sesi login.
5. Di File Manager, buka \`/home/datd1747/portal_private/KODE-SUPERADMIN.txt\` → klik **View** atau **Edit** → **salin isi kode**. Berkas dibuat otomatis oleh halaman aktivasi. Jangan mengunggahnya ke GitHub atau mengirim screenshot isi kode.
6. Tempel kode pada halaman aktivasi, klik **Aktifkan Akun Saya**.
7. Setelah berhasil, halaman akan membuka \`https://datawebs.my.id/super-admin.php\`. Kedua database tetap terpisah, sedangkan akun yang barusan login memperoleh akses pusat kendali.

**Selesai. Tidak perlu membuka SQL atau memilih ID akun di phpMyAdmin.**

## Apa yang dilakukan sistem?

- Memastikan akun yang sedang login masih aktif dan memiliki role administrator dalam database asli.
- Memeriksa kode aktivasi acak yang hanya tersedia di cPanel, **di luar folder publik**.
- Membuat tabel \`unified_module_progress\` di database SmartApp melalui koneksi MySQL yang sudah ada dan menambahkan 7 baris modul dengan progres awal 0%. Tidak menghapus tabel lain.
- Menambahkan satu baris \`require_once\` ke bootstrap.php, serta menyimpan backup bootstrap lama di direktori privat.
- Menyimpan penetapan identitas \`source\` + \`id\` secara aman di \`portal_private/super_admin_access.php\`, bukan di GitHub.
- Menghapus kode aktivasi sekali pakai setelah sukses, dan memasang pembatas akses berdasar ID+role+status akun saat setiap permintaan.

Jika gagal membuat tabel, hubungi pengelola hosting untuk memeriksa izin MySQL user. Tidak perlu mengimpor ulang SQL sumber.

## Penting setelah berhasil

**Hapus** \`/home/datd1747/public_html/aktivasi-super-admin.php\` dan paket ZIP dari hosting. Pertahankan \`portal_private/super_admin_access.php\` karena menentukan Super Admin, serta \`portal_private/super_admin.php\` dan tabel progres.

Periksa lewat browser penyamaran bahwa halaman \`/super-admin.php\` meminta login, dan uji menggunakan akun non-admin agar akses ditolak (HTTP 403).

**Kewenangan:** Super Admin dapat memantau dua database, statistik operasional, progres 7 modul, dan membuka modul-modul yang tersedia. Tidak otomatis mendapatkan fitur CRUD yang belum dibangun. Akses sensitif memerlukan MFA dan pengujian sebelum dipakai untuk data pemerintah dalam produksi.

## Isi paket

\`\`\`text
/home/datd1747/
├── portal_private/
│   └── super_admin.php                   (diperbarui)
└── public_html/
    ├── aktivasi-super-admin.php           (hapus setelah berhasil)
    ├── super-admin.php                   (diperbarui)
    └── assets/
        ├── aktivasi-super-admin.css
        └── super-admin.css
\`\`\`

**Jangan** menimpa \`index.php\`, \`portal_private/config.php\` atau kedua database. Pengubahan bootstrap yang diperlukan dilakukan oleh wizard setelah kode aktivasi terbukti benar.

## Jika tidak bisa membuka halaman

- 403 saat belum login: masuk ke akun Administrator.
- 403 saat memakai HTTP: gunakan \`https://\`.
- Kode tidak muncul di File Manager: buka halaman aktivasi terlebih dahulu; pastikan \`portal_private\` dapat ditulis oleh PHP.
- Kode cocok tetapi aktivasi gagal: periksa izin tulis folder privat dan izin CREATE TABLE dari user MySQL \`datd1747_smartapp\`; error teknis hanya dicatat pada error log server.
- Jika akun Super Admin sudah pernah diaktifkan, aktivasi ulang ditolak. Perubahan akun harus melalui prosedur pemulihan yang diawasi pemilik hosting, bukan melalui halaman publik.

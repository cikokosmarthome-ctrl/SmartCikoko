# Tahap 6 — Manajemen Pengguna dan Hak Akses (Satu ZIP cPanel)

Tahap 6 dipasang setelah Super Admin dan Direktori Akun aktif. Tujuannya: **mengelola akses login portal dan izin modul** untuk akun Pegawai, PPPK Pancoran, dan SmartApp dari satu tempat, tanpa membuka phpMyAdmin.

## 1. Instalasi singkat di Rumahweb

1. **Cadangkan** berkas \`/home/datd1747/portal_private/bootstrap.php\` dan \`auth.php\`, serta database \`datd1747_smartapp\` dari cPanel.
2. Download \`paket/SmartCikoko-TAHAP6-MANAJEMEN.zip\` dari repository GitHub SmartCikoko.
3. cPanel Rumahweb → File Manager → masuk ke **\`/home/datd1747/\`** → Upload ZIP → Extract. **Jangan** ekstrak dari dalam \`public_html\`. Paket hanya menambah file baru dan memperbarui file publik Super Admin/Direktori.
4. Login menggunakan akun **Super Administrator**.
5. Buka \`https://datawebs.my.id/pasang-tahap6.php\` dan klik **Pasang Manajemen Pengguna**.
6. Installer memeriksa format file autentikasi yang aktif, membuat tiga tabel baru pada database SmartApp, menyimpan backup file PHP autentikasi di folder privat, dan mengaktifkan pengendalian akses portal.
7. Setelah berhasil, buka \`https://datawebs.my.id/kelola-pengguna.php\`.
8. Hapus \`public_html/pasang-tahap6.php\` serta ZIP dari hosting setelah pemasangan. Pertahankan \`portal_private/account_control.php\`, \`bootstrap.php\`, \`auth.php\` dan tabel baru.

**Tidak perlu memasukkan perintah SQL atau memilih ID pengguna di phpMyAdmin.** File akun dan dua database lama tidak perlu diimpor ulang.

## 2. Fitur

- Tab Pegawai, PPPK Pancoran, dan Portal Cikoko.
- Pencarian nama/username/email; pemilihan akun dari daftar.
- Izinkan atau blokir **akses login SmartCikoko** (tidak menghapus akun sumber).
- Izin khusus modul: PPPK dan PJLP untuk akun Pegawai, PPPK untuk akun PPPK Pancoran, atau Ahli Waris untuk akun SmartApp.
- Opsi \`Mengikuti Peran\`, \`Diizinkan\`, atau \`Ditolak\` untuk kemampuan yang tersedia.
- Log audit kegiatan untuk tiap perubahan akses/izin dan halaman riwayat 15 perubahan terakhir.
- Pemeriksaan ulang status dan role sumber serta izin portal pada sesi web aktif, login baru, dan pemakaian token API.

## 3. Batasan keamanan

- **Tidak ada fitur membuat akun Super Admin, mengubah role utama, atau mereset kata sandi** di tahap ini.
- Akun admin, akun superadmin, dan akun administrator kelurahan dilindungi dari perubahan di UI.
- Izin modul dibatasi sesuai sumber, sehingga ID akun dua database tidak dicampur (tidak membocorkan laporan milik orang lain).
- Pemblokiran akun pada tahap ini hanya mengatur portal SmartCikoko dan API-nya, bukan secara otomatis semua sistem legacy eksternal yang mungkin masih digunakan.
- Untuk memindahkan tugas sensitif seperti pengelolaan semua permohonan ahli waris, diperlukan pengesahan hak akses dengan MFA, audit dan kebijakan berjenjang.
- Tahap 6 adalah kontrol akses awal yang dilindungi Super Admin, **bukan pengganti audit keamanan penuh** atau sistem manajemen identitas pemerintah yang sudah tersertifikasi.

## 4. Pengujian wajib

1. Akun warga yang diblokir harus ditolak saat login ulang.
2. Bila akun tersebut masih aktif di browser lain, akses sesi sebelumnya juga harus ditolak saat membuka halaman baru.
3. Token API milik akun yang diblokir harus memperoleh HTTP 401.
4. Akun yang diberi izin PPPK/PJLP/ahli waris hanya melihat data yang memang terkait identitas akunnya.
5. Akun non-Super Admin yang membuka \`kelola-pengguna.php\` harus ditolak dengan HTTP 403.
6. Akun Administrator lain tidak boleh dinonaktifkan melalui UI ini.
7. \`unified_audit\` mencatat \`account_access_updated\` dan \`module_permission_updated\`.
8. Pastikan website publik dan dashboard lama tetap berjalan dan statistik tidak berubah karena pemasangan.

### Jika installer menunjukkan kegagalan kompatibilitas

Jangan menimpa secara manual \`bootstrap.php\` atau \`auth.php\` dari GitHub. Installer sengaja menghentikan proses jika struktur file server berbeda dari titik integrasi yang dikenal. Cadangkan, lalu sesuaikan kode server secara terkontrol.

### File baru/yang diperbarui

\`\`\`text
/home/datd1747/
├── portal_private/
│   └── account_control.php               (baru)
└── public_html/
    ├── pasang-tahap6.php                  (hapus setelah berhasil)
    ├── kelola-pengguna.php                (baru)
    ├── super-admin.php                   (navigasi diperbarui)
    ├── direktori-akun.php                (navigasi diperbarui)
    └── assets/
        ├── pasang-tahap6.css
        └── kelola-pengguna.css
\`\`\`

Tiga tabel kontrol dibuat otomatis saat instalasi pertama:
- \`unified_account_access\`
- \`unified_module_access\`

Keduanya berada pada database SmartApp; tabel akun lama tidak diubah.

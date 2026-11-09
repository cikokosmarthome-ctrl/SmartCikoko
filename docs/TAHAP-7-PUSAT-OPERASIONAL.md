# Tahap 7 — Pusat Operasional Kelurahan Cikoko

Tahap ini menambahkan **Pusat Operasional** ke Super Admin SmartCikoko. Empat modul terpadu: Data Pegawai (baca-saja), Kinerja PPPK, Kegiatan PJLP/PPSU, dan Permohonan Ahli Waris. Dua database sumber tetap terpisah.

## Instalasi cepat di cPanel Rumahweb

1. Sebelum instalasi, buat backup database SmartApp dan file \`/home/datd1747/public_html/super-admin.php\`.
2. Download **\`paket/SmartCikoko-TAHAP7-OPERASIONAL.zip\`** di GitHub.
3. Masuk cPanel → File Manager → \`/home/datd1747/\` → **Upload** dan **Extract** di direktori itu (bukan dari dalam \`public_html\`). Konfirmasi mengganti \`public_html/super-admin.php\` yang telah dibackup.
4. Login ke website menggunakan akun Super Admin.
5. Buka \`https://datawebs.my.id/instal-operasional.php\`, lalu klik **Pasang Pusat Operasional**.
6. Setelah ada pesan sukses, buka \`https://datawebs.my.id/operasional.php\`.
7. Periksa ringkasan setiap modul serta daftar akun dan laporan. Setelah berhasil, **hapus \`public_html/instal-operasional.php\` dan ZIP dari hosting**.

**Tidak perlu membuat database baru, impor ulang dump SQL, atau mengedit phpMyAdmin.** Installer hanya membuat dua tabel peninjauan pada database SmartApp.

## Fitur dan perlindungan

| Modul | Tindakan Tahap 7 | Database sumber |
| --- | --- | --- |
| Data Pegawai | Lihat identitas, username, peran dan status; pencarian dan paginasi | Pegawai |
| PPPK | Lihat laporan kepegawaian, catat peninjauan dan tindak lanjut (tanpa mengubah laporan asli) | Pegawai |
| PJLP / PPSU | Lihat sesi tugas, catat peninjauan dan tindak lanjut (tanpa mengubah sesi asli) | Pegawai |
| Ahli Waris | Periksa daftar permohonan dan ubah status layanan dengan catatan, melalui riwayat lama \`aw_history\` | SmartApp |

### Detail batas perubahan

- Catatan PPPK dan PJLP disimpan di **\`unified_operational_reviews\`**; riwayat peninjauan di **\`unified_operational_history\`**. Status laporan PPPK maupun PJLP lama tidak diubah.
- Status permohonan ahli waris benar-benar mengubah \`aw_applications.status\` menggunakan fungsi portal yang sudah tersedia dan mencatat \`aw_history\`.
- Setiap perubahan memerlukan sesi Super Admin yang aktif, token CSRF, formulir konfirmasi, validasi terhadap keberadaan data dan persyaratan input.
- Seluruh data sensitif hanya disajikan setelah autentikasi Super Admin. Tidak ada ekspor NIK, lampiran pribadi, file JSON akun, atau informasi kata sandi.
- Tidak ada penghapusan massal, tidak ada modifikasi role pengguna dan tidak ada upload file pada tahap ini.
- **Progres modul 0% pada Super Admin tetap merupakan laporan pekerjaan manual**, bukan status otomatis atas laporan sumber.

## Pengujian setelah pemasangan

1. Dua database pada halaman Super Admin tetap **Aktif**, angka rekap sesuai data sumber.
2. Akun bukan Super Admin yang membuka \`/operasional.php\` memperoleh **HTTP 403**; pengguna belum login dialihkan ke login.
3. Pencarian dan paginasi bekerja pada keempat modul.
4. Peninjauan PPPK dan PJLP mengubah status review di Pusat Operasional, tanpa mengubah status laporan sumber.
5. Setelah perubahan tinjauan, riwayat operasional menampilkan entri baru.
6. Coba ubah status satu permohonan ahli waris uji yang memang boleh diubah; cek status dan riwayat pengajuan di modul Ahli Waris.
7. Jika installer mengeluhkan hak akses MySQL, pastikan user database SmartApp memiliki izin CREATE TABLE; jangan mengimpor ulang semua SQL.
8. Hapus installer setelah berhasil dan uji bahwa URL installer sudah tidak dapat diakses.

## Status rilis

Kode tersedia di repository GitHub, sedangkan pemasangan pada hosting perlu dijalankan pengelola akun Rumahweb. Pengujian sintaks PHP otomatis bukan pengganti tes transaksi, hak akses, perlindungan data, dan pengujian hosting produksi.

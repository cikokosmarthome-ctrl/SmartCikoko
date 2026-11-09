# Tahap 5 — Direktori Akun Terpadu SmartCikoko

**Tujuan:** memudahkan Super Admin mengawasi tiga sumber akun tanpa membuka phpMyAdmin. Ini tahap baca-saja; tidak boleh dipakai untuk mengganti hak akses, password atau menghapus akun.

## Cara memasang di Rumahweb (satu ZIP)

1. Cadangkan file `/home/datd1747/public_html/super-admin.php` dan `/home/datd1747/public_html/assets/super-admin.css`.
2. Download paket `paket/SmartCikoko-TAHAP5-DIREKTORI.zip` dari GitHub.
3. Buka cPanel → File Manager → `/home/datd1747/` → Upload ZIP → Extract **di direktori itu**.
4. Paket memperbarui `public_html/super-admin.php` dan `public_html/assets/super-admin.css`, serta menambahkan `public_html/direktori-akun.php` dan `public_html/assets/direktori-akun.css`.
5. Login sebagai akun Super Administrator yang sudah diaktifkan.
6. Buka `https://datawebs.my.id/direktori-akun.php`, atau klik **Direktori Akun** dari menu Pusat Super Admin.
7. Uji pencarian dan tab Pegawai, PPPK Pancoran, Portal Cikoko. Angka tiap sumber bisa berbeda; sebagian akun mungkin merupakan orang yang sama.
8. Uji memakai akun biasa (HTTP 403) serta browser yang belum login (diarahkan ke login). Hapus ZIP dari hosting setelah berhasil.

## Ruang lingkup
- Ringkasan jumlah akun per sumber
- Pencarian nama atau username/email akun
- Status akun aktif dan tidak aktif
- Tabel dengan paginasi maksimum 30 akun per halaman
- Pembatasan akses hanya Super Admin
- Tidak menampilkan password, hash, token, NIK, atau informasi lebih rinci

## Pekerjaan sesudah Tahap 5
- Pemetaan peran dan hak akses granular untuk seluruh modul
- Pengelolaan akun dengan persetujuan admin dan audit yang tidak dapat dimodifikasi
- Pembatasan data kepegawaian dan warga menurut kebutuhan tugas
- MFA Super Admin sebelum mengaktifkan perubahan akun yang bersifat sensitif
- Penerapan prinsip perlindungan data pribadi dan pengujian keamanan

**Catatan:** Progres 0% pada Pusat Super Admin merupakan progres pekerjaan yang belum dicatat, bukan tanda database gagal. Konektivitas database dilaporkan secara terpisah.

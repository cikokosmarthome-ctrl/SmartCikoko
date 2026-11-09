# Tahap 3 — Aktivasi Pusat Kendali Administrator

Halaman dashboard SmartCikoko di Rumahweb sudah berhasil ditampilkan berdasarkan screenshot pengelola pada 9 November 2026. **Jangan mengganti seluruh isi `public_html` dengan source GitHub**, karena tampilan server dapat lebih baru daripada `index.php` yang tersimpan pada repository.

## Cara memasang pembaruan ini tanpa menimpa website aktif

1. Cadangkan dua berkas yang akan dibuat/diperbarui jika telah ada.
2. Di GitHub SmartCikoko, unduh **hanya** dua berkas berikut:
   - `public_html/pusat-kendali.php`
   - `public_html/assets/pusat-kendali.css`
3. Upload ke cPanel File Manager di:
   - `/home/datd1747/public_html/pusat-kendali.php`
   - `/home/datd1747/public_html/assets/pusat-kendali.css`
4. Login sebagai akun administrator lewat `https://datawebs.my.id/?page=login`.
5. Buka `https://datawebs.my.id/pusat-kendali.php`.
6. Cek bahwa **kedua database menunjukkan Terhubung**, dan jumlah data pada kartu sesuai dashboard lama.
7. Uji dari browser anonim: alamat `/pusat-kendali.php` harus mengarahkan ke login.
8. Uji menggunakan akun bukan admin: alamat ini harus menolak akses (HTTP 403).

## Data yang ditampilkan

- Status konektivitas database Pegawai dan SmartApp;
- Rekap jumlah akun dari masing-masing sumber;
- Rekap pengajuan ahli waris berdasarkan status;
- Jumlah laporan PPPK dan PJLP;
- Distribusi kategori akun dan total log audit 7 hari terakhir;
- Tautan navigasi ke modul lama.

Modul **hanya membaca agregat data**. Tidak melakukan impor SQL, perubahan tabel, penghapusan akun, ataupun pemrosesan data pribadi secara terbuka.

## Perhatian penting

- Tidak ada deployment otomatis ke cPanel dari GitHub pada tahap ini. Dua berkas tetap perlu diunggah oleh pengelola.
- Tidak perlu membuat ulang database atau mengunggah SQL kembali.
- Jangan mengirim data NIK, kata sandi, token login, atau isi database melalui percakapan terbuka maupun issue GitHub.
- Pemeriksaan sintaks GitHub Actions bukan pengganti uji aplikasi pada hosting Rumahweb.

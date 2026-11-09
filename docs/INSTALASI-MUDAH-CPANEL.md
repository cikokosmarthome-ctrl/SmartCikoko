# Instalasi mudah SmartCikoko — cPanel Rumahweb

**Pilihan tercepat:** gunakan paket `SmartCikoko-INSTAL-CPANEL.zip` yang disediakan bersama proyek, bukan ZIP kode sumber umum.

## Tahapan

1. Masuk ke **cPanel → File Manager → /home/datd1747/**. Upload ZIP dan klik **Extract** di direktori utama, **bukan** di dalam `public_html`. Arsip sudah memiliki dua folder `public_html/` serta `portal_private/` pada tingkat teratas.
2. Buka **Manage My Databases**, buat `datd1747_Pegawai` dan `datd1747_smartapp` serta pengguna MySQL dengan password unik dan berbeda. Pasangkan pengguna dengan database masing-masing.
3. Di **phpMyAdmin**, impor berkas `cikoko_pegawai(2).sql` ke database Pegawai, dan `datd1747_smartapp (1)(2).sql` ke database SmartApp. **Jangan** menyimpan dump SQL di webroot/GitHub.
4. Buka `https://datawebs.my.id/instalasi.php` setelah DNS dan SSL aktif. Isi kode dari `/home/datd1747/KUNCI-INSTALASI.txt`, dua koneksi database, dan bila ada file migrasi akun privat `AKUN-CIKOKO-PRIVAT.json`.
5. Klik **Uji Database dan Pasang Portal**; installer memverifikasi tabel sumber, membuat empat tabel integrasi dalam SmartApp, memasukkan akun portal lama bila dilampirkan, lalu menyimpan `portal_private/config.php` di luar `public_html`.
6. Setelah sukses, **hapus** `public_html/instalasi.php`, `KUNCI-INSTALASI.txt` dan ZIP dari hosting. Uji login multi-peran, modul ahli waris, PPPK, PJLP, serta API `/api/?resource=health`.

## Keamanan

- Berkas migrasi akun mengandung identitas pengguna dan hash kata sandi. Kirim hanya melalui form HTTPS sementara dan jangan commit ke GitHub atau unggah ke `public_html`.
- Kode instalasi hanya dipakai satu kali, dan disimpan di luar webroot.
- Password database lama yang pernah dibagikan harus diganti.
- Instalasi belum sama dengan kesiapan produksi atau Play Store; perlu uji fungsional, otorisasi, data pribadi dan Android.

Bila berkas `instalasi.php` tidak ada pada checkout kode sumber GitHub, gunakan paket ZIP cPanel khusus yang menyertakan wizard. Paket ZIP pengguna tidak diterbitkan di repository publik karena berisi kunci instalasi unik.
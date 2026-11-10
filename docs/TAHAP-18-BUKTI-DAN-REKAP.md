# Tahap 18 — Bukti Foto Terlindungi dan Rekap Laporan Terpadu

SmartCikoko — Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Tujuan

Menambahkan bukti foto kegiatan yang **fail-closed** bila antivirus tidak berfungsi, sekaligus rekap lintas Tahap 15, 16 dan 17. Super Admin tetap satu halaman, bukan dashboard terpisah.

Alamat setelah instalasi:
- https://datawebs.my.id/super-admin-satu.php#bukti-dan-rekap
- https://datawebs.my.id/bukti-laporan18.php
- https://datawebs.my.id/rekap-laporan18.php
- https://datawebs.my.id/instal-bukti18.php

## Foto yang diperbolehkan

Foto kegiatan PPPK dan PJLP Tahap 16, serta RT/RW, LMK, FKDM dan PPSU Tahap 17. **Foto Jumantik (rumah tangga/kesehatan), dokumen Ahli Waris dan cuti tidak diperbolehkan**. Hanya pemilik laporan yang sudah mengajukan dan belum ditutup boleh mengunggah. Super Admin membaca/mengunduh dengan MFA, bukan mengunggah atas nama petugas.

- 1 berkas per pengiriman, maksimal 4 foto per laporan.
- Maksimal 3 MB unggahan, hanya JPEG atau PNG. Tidak menerima PDF, DOCX, SVG, HTML atau berkas executable.
- Pemeriksaan file HTTP asli dan antivirus ClamAV/clamd **wajib aktif dan harus berhasil**. Tanpa mode upload_scanning.enforce=true dan koneksi ke scanner, unggah ditolak.
- Foto didekode dan dikodekan ulang menggunakan GD sebelum disimpan. Metadata EXIF/lokasi bawaan file tidak disalin. Piksel maksimal 10 juta, ukuran hasil maksimal 1800 px sisi terpanjang.
- Berkas disimpan pada folder privat di luar public_html dengan nama acak dan izin terbatas, bukan sebagai URL publik.
- Unduhan memeriksa identitas/izin, SHA-256 dan audit sebelum mengirim data.

**Status PING ClamAV bukan jaminan scan berhasil**: setiap file tetap dipindai ulang saat unggah. Ketersediaan scanner pada hosting belum teruji. Gunakan rekap CSV saja bila scanner belum siap.

## Rekap administratif

Tiga modul: Tahap 15 laporan ringkas, Tahap 16 formulir pegawai dan Tahap 17 laporan kewilayahan. Super Admin memilih rentang tanggal maksimum 367 hari dan modul. Hanya kategori, ID laporan, status administratif dan tanggal yang ditampilkan/diekspor. Tidak menyertakan nama, NIK, uraian, identitas warga, koordinat, foto, ataupun data pemohon. CSV dilindungi dari injeksi formula.

Maksimum 1.500 laporan per modul per permintaan. Jika melebihi batas atau salah satu modul belum tersedia, sistem memperingatkan bahwa rekap tidak lengkap. Persempit rentang tanggal jika perlu.

Rekap ini bukan penilaian PPPK resmi, penetapan cuti, pengesahan surat RT/RW, atau statistik kesehatan resmi.

## Pemasangan sederhana di Rumahweb

1. Backup file website, kedua database, konfigurasi privat dan kunci MFA ke media aman di luar public_html.
2. Download paket SmartCikoko-TAHAP18-BUKTI-REKAP.zip dari GitHub.
3. cPanel File Manager: buka /home/datd1747/, Upload ZIP, Extract pada direktori tersebut (bukan di dalam public_html).
4. ZIP hanya menambahkan berkas baru. Tidak mengganti index.php, bootstrap.php, auth.php, mfa.php, super-admin-satu.php, pusat-laporan.php, atau database lama.
5. Login Super Admin menggunakan MFA, kemudian buka https://datawebs.my.id/instal-bukti18.php.
6. Klik **Aktifkan Tahap 18 dan Hubungkan ke Dashboard**.
7. Installer menambah satu tabel baru di SmartApp: unified_evidence18. Juga mencoba menautkan panel di Super Admin Ringkas dan tautan di Pusat Laporan.
8. Sebelum halaman lama diubah, installer membuat backup privat ke portal_private/evidence18_backups/. File live yang strukturnya berbeda ditolak dan tidak ditimpa.
9. Uji CSV dengan data dummy terlebih dahulu. Foto hanya diuji dengan foto dummy setelah pemindai terverifikasi dan diaktifkan oleh hosting.
10. Hapus installer public_html/instal-bukti18.php dan ZIP dari hosting sesudah sukses. Jangan hapus tabel dan folder bukti yang sudah dipakai.

## Uji akses dan keamanan yang wajib

- Dua akun berbeda tidak bisa mengakses lampiran satu sama lain.
- Super Admin perlu verifikasi MFA untuk ekspor dan melihat bukti.
- Antivirus nonaktif/socket tidak tersambung harus menolak upload, bukan sekadar menampilkan peringatan.
- Data warga/rumah dan foto kesehatan tidak diterima.
- File berbahaya atau terlalu besar ditolak. Metadata lokasi EXIF tidak terbawa setelah pemrosesan.
- CSV tidak mengandung identitas atau uraian laporan, akses CSV harus diaudit.
- Tahap 15–17, Beranda, login dan MFA tetap berfungsi.

Pengujian otomatis di GitHub hanya menguji kode/struktur dan bukan uji integrasi server. Sebelum memakai data nyata, diperlukan persetujuan pengelola, retensi, pemulihan backup dan evaluasi keamanan produksi.

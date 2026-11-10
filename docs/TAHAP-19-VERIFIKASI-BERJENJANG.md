# Tahap 19 — Verifikasi Berjenjang Laporan SmartCikoko

Kelurahan Cikoko, Kecamatan Pancoran. Pembaruan lanjutan setelah Tahap 18.

## Konsep

Tahap 19 menambahkan **pemeriksaan kelengkapan laporan secara administratif** dalam dua langkah: pemeriksa bidang memeriksa laporan yang telah dikirim, lalu Lurah dapat mencatat **telaah administratif** setelah laporan lolos langkah pertama. **Ini bukan persetujuan cuti, penilaian resmi PPPK/PJLP, pengesahan surat RT/RW, atau tanda tangan elektronik.** Semua keputusan resmi tetap melalui alur yang berwenang di aplikasi sumber.

Tampilan utama tetap **Super Admin Ringkas**; tidak ada dashboard Super Admin baru.

### Hak akses

- Form PPPK/PJLP/Cuti (Tahap 16) dan laporan ringkas kepegawaian (Tahap 15): pemeriksa akun **admin Pegawai/admin_kelurahan Pegawai**, atau Super Admin terpilih yang melewati MFA, untuk pemeriksaan tahap pertama.
- Laporan wilayah RT/RW, LMK, Jumantik, FKDM, PPSU (Tahap 17) dan laporan umum (Tahap 15): pemeriksa dari akun **PTSP/admin_kelurahan SmartApp**, atau Super Admin terpilih yang melewati MFA, untuk tahap pertama.
- **Lurah SmartApp** dapat melakukan langkah kedua setelah tahap pertama tercatat.
- Lurah tidak otomatis menjadi Super Admin; Super Admin tidak otomatis boleh melakukan tindakan Lurah.
- Pelapor tidak dapat memeriksa laporannya sendiri. Identitas pemeriksa dan pelaksana telaah Lurah harus berbeda.
- Status akun diperiksa kembali pada setiap permintaan; pembatasan akun Tahap 6 dihormati.
- Setiap tindakan dicatat pada histori dan audit, lewat query parameter, validasi CSRF dan transaksi database.

### Alur

1. Pelapor mengajukan laporan Tahap 15, 16 atau 17. Laporan draf tidak bisa ditinjau.
2. Pemeriksa membuka laporan, membaca rincian, memilih **Kelengkapan Diperiksa** atau **Kembalikan** disertai alasan.
3. Jika dikembalikan, status pada formulir laporan tambahan di SmartApp menjadi **Perlu Perbaikan**. Pemilik laporan dapat memperbaiki dan mengirim ulang melalui formulir awalnya.
4. Ketika formulir diajukan ulang dan **nomor versi meningkat**, laporan dapat diperiksa lagi. Versi lama tidak bisa diperiksa ulang.
5. Setelah lolos pemeriksaan awal, Lurah bisa mencatat **telaah administratif** atau meminta perbaikan. Catatan telaah tidak menerbitkan surat atau mengganti status pada tabel cuti/penilaian asli.
6. Super Admin memonitor antrean dari halaman ringkas, memberi catatan pada tahap pertama sesuai akun Super Admin sendiri, dan dapat menelusuri hasil. Tidak boleh berperan sebagai Lurah tanpa akun Lurah yang sah.

### Data

**Dua tabel baru pada SmartApp saja:**
- unified_verification19: status, nomor versi laporan, ID dan sumber akun pemeriksa/Lurah, catatan, waktu perubahan; satu alur per laporan.
- unified_verification_history19: histori tindakan, status lama/baru, aktor dan waktu.

Sumber laporan tetap tabel tambahan Tahap 15–17:
- unified_report_intake
- unified_detailed_forms16
- unified_field_reports17

Ketika permintaan perbaikan dikembalikan, hanya status/catatan pada **tabel laporan tambahan tersebut** serta riwayatnya yang diperbarui. Database Pegawai asli, permohonan ahli waris resmi, serta data surat RT/RW tidak diubah.

**Prasyarat:** Tahap 15, 16, 17 sudah dipasang dengan kolom version dan riwayat yang sesuai. Installer memeriksa kesiapan tersebut sebelum menambah modul. Jika belum siap, pasang modul prasyarat terlebih dahulu.

## Instalasi cPanel tanpa SQL manual

1. Backup kedua database, berkas website, konfigurasi privat serta kunci MFA di lokasi aman di luar public_html.
2. Download paket SmartCikoko-TAHAP19-VERIFIKASI-BERJENJANG.zip dari GitHub.
3. cPanel File Manager: buka /home/datd1747/, upload ZIP, kemudian Extract di direktori tersebut.
4. Paket hanya **menambahkan file baru**. Tidak mengganti index.php, auth.php, bootstrap.php, super-admin-satu.php, pusat-laporan.php, mfa.php atau konfigurasi privat.
5. Login Super Admin menggunakan Google Authenticator.
6. Buka https://datawebs.my.id/instal-verifikasi19.php dan periksa prasyarat Tahap 15–17 semuanya Siap.
7. Klik **Aktifkan Tahap 19**. Installer membuat dua tabel baru pada SmartApp dan menambahkan satu bagian pada dashboard Super Admin, serta satu tautan pada Pusat Input.
8. Sebelum file Super Admin/Pusat Input disentuh, salinan asli disimpan otomatis di luar public_html: portal_private/verification19_backups/. Jika file live berbeda, perubahan ditolak tanpa menimpa seluruh halaman.
9. Buka https://datawebs.my.id/super-admin-satu.php#verifikasi-berjenjang dan https://datawebs.my.id/verifikasi-laporan19.php.
10. Uji tiga akun dummy terpisah: pelapor, pemeriksa sesuai bidang, dan Lurah. Uji juga akses yang ditolak, pengembalian alasan, perbaikan dan pengiriman ulang.
11. Setelah sukses, hapus public_html/instal-verifikasi19.php dan arsip ZIP dari hosting. Jangan hapus tabel, catatan audit, atau folder backup.

## Uji wajib

- Akun warga, RT/RW, PJLP, PPPK dan peran tak berwenang tidak bisa menjadi pemeriksa laporan orang lain.
- Lurah tidak bisa melompati pemeriksaan awal; Super Admin tidak bisa mengisi posisi Lurah secara otomatis.
- Satu orang tidak bisa memeriksa laporannya sendiri atau menjalankan dua langkah sebagai dua petugas.
- Pelapor tidak bisa mengubah laporan yang belum dikembalikan; pengembalian menambahkan alasan dan mengaktifkan jalur perbaikan yang ada.
- Laporan ditutup atau masih draf tidak dapat diproses.
- Setelah laporan dikoreksi, pemeriksa harus menilai **versi terbaru**, bukan memakai status versi sebelumnya.
- Tidak ada penulisan ke tabel Pegawai asli, penilaian kinerja, kuota cuti, atau penerbitan surat.
- Uji integrasi dengan database live/staging, MFA dan tiga akun berbeda sebelum digunakan untuk pelayanan yang sebenarnya.

## Akses setelah pemasangan

- Super Admin: https://datawebs.my.id/super-admin-satu.php#verifikasi-berjenjang
- Pemeriksaan petugas dan Lurah: https://datawebs.my.id/verifikasi-laporan19.php
- Installer: https://datawebs.my.id/instal-verifikasi19.php

**Status:** kode disimpan ke repository GitHub, tetapi belum otomatis diterapkan pada Rumahweb. Kelulusan PHP lint/unit tests tidak membuktikan pengujian MySQL dan akun produksi.

# Tahap 15 - Pusat Super Admin Ringkas dan Input Laporan Terpadu

SmartCikoko, Kelurahan Cikoko, Kecamatan Pancoran.

## Dua halaman utama

- Pusat Super Admin satu halaman: https://datawebs.my.id/super-admin-satu.php
- Form input dan riwayat laporan: https://datawebs.my.id/pusat-laporan.php
- Installer Super Admin: https://datawebs.my.id/instal-laporan.php

Super Admin ringkas berisi status kedua database, metrik pegawai dan layanan, 10 kategori pelaporan, antrean untuk mencatat tindak lanjut langsung, akses cepat modul, progres pekerjaan, serta pengaturan keamanan dalam panel lipat. Dashboard super-admin.php yang lama tetap ada sebagai cadangan; tidak ditimpa oleh ZIP.

## Pemetaan ke sistem lama

| Kategori | Tabel asli | Cara kerja |
|---|---|---|
| Ahli Waris | smartapp.aw_applications | Pengajuan langsung ke alur warga lama, tanpa form duplikat |
| PPPK | pegawai.ck_pppk_kinerja | Form ringkasan tambahan; format kinerja resmi lama tidak diganti |
| PJLP | pegawai.laporan | Form ringkasan tambahan; bukti foto/GPS asli tidak diganti |
| RT/RW | smartapp.mobile_rt_rw_letters | Laporan kegiatan baru; tidak menggantikan surat pengantar |
| LMK | Tabel laporan lama belum dapat dipastikan | Input ringkasan LMK baru, terpisah |
| Jumantik | smartapp.mobile_reports | Input ringkasan baru; bukan migrasi laporan mobile |
| Pengajuan Cuti | pegawai.cuti | Permohonan awal baru; bukan persetujuan resmi atau pemotongan saldo |
| FKDM | smartapp.mobile_fkdm_reports | Ringkasan tambahan; format foto tetap di aplikasi lama |
| PPSU | smartapp.mobile_ppsu_reports | Ringkasan tambahan; foto resmi tetap pada aplikasi lama |
| Umum | smartapp.reports | Laporan ringkasan baru |

Data lama dan laporan ringkas baru ditampilkan dalam kolom terpisah. Tidak ada migrasi data, penggabungan tabel, atau perubahan catatan transaksi lama.

## Hak akses

- RT/RW mengirim laporan wilayah, LMK mengirim laporan LMK, Jumantik mengirim laporan Jumantik, FKDM dan PPSU sesuai peran.
- PPPK dan PJLP mengirim ringkasan kegiatan dengan akun Pegawai/Pancoran yang sesuai.
- Pengajuan awal cuti disediakan untuk akun Pegawai dengan peran asn/pppk/pjlp/admin.
- Super Admin dapat melihat semua laporan baru dan mencatat tindak lanjut administratif, tetapi tidak membuat laporan atas nama petugas lain atau mengesahkan cuti/surat.
- Akun hanya membaca laporan barunya sendiri kecuali Super Admin. Form dijaga status akun, larangan izin modul, CSRF, validasi tanggal, batas 12 laporan per hari, idempotency key, dan audit.
- MFA Super Admin Tahap 13 tetap diterapkan; tidak ada reset atau perubahan password.

## Instalasi Rumahweb (tanpa SQL manual)

1. Backup website, database Pegawai dan SmartApp, serta kunci MFA ke tempat privat yang aman.
2. Download paket/SmartCikoko-TAHAP15-PUSAT-LAPORAN.zip dari GitHub.
3. cPanel File Manager: buka /home/datd1747/, upload ZIP, kemudian Extract di direktori tersebut, bukan di dalam public_html.
4. ZIP hanya menambahkan file PHP dan CSS baru. File index.php, super-admin.php, bootstrap.php, auth.php, mfa.php, dan config.php tidak ditimpa.
5. Login sebagai Super Admin dan selesaikan verifikasi MFA.
6. Buka https://datawebs.my.id/instal-laporan.php lalu klik Aktifkan Pusat Laporan.
7. Buka https://datawebs.my.id/super-admin-satu.php lalu https://datawebs.my.id/pusat-laporan.php.
8. Uji dengan akun dummy berbagai peran dan periksa hasil laporan pada layar ringkas.
9. Setelah berhasil, hapus public_html/instal-laporan.php dan ZIP dari hosting; jangan hapus tabel laporan setelah digunakan.

Installer membuat hanya dua tabel baru di database SmartApp: unified_report_intake dan unified_report_intake_history. Tidak memindahkan atau menimpa tabel Pegawai ataupun SmartApp lama.

## Batasan yang perlu dipahami

Form pada tahap ini merupakan INPUT RINGKAS. Belum seluruh formulir dan workflow kompleks website lama dipulihkan; misalnya penilaian PPPK lengkap, bukti foto/GPS PJLP/PPSU, surat pengantar RT/RW bertanda tangan, laporan Jumantik berfoto, dan persetujuan cuti bertingkat. Tahap berikutnya perlu integrasi tiap formulir menggunakan field dan kewenangan tepat berdasarkan SQL/berkas sumber, tidak mengarang skema baru.

Status Ditutup (Administratif) bukan persetujuan cuti, penerbitan surat, atau penilaian kinerja resmi. Laporan Ahli Waris baru tetap masuk lewat alur pengajuan warga. Sebelum operasi nyata, perlindungan dokumen, antivirus, backup, privasi, serta prosedur otorisasi harus diuji.

Sumber GitHub tidak otomatis menerapkan perubahan pada hosting Rumahweb. Pengujian unit tidak menggantikan pengujian MySQL live.
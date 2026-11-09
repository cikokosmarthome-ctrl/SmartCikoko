# SmartCikoko — Tahap 12: Audit Kesiapan Produksi

**Tujuan:** pengawasan pra-produksi di bawah akun Super Administrator, bukan penerbitan sertifikasi keamanan atau surat resmi.

## Fungsi

- **11 pemeriksaan otomatis:** HTTPS, versi PHP, display_errors, keamanan cookie/sesi, konfigurasi privat, dua koneksi database, dua folder lampiran privat, registry arsip, serta sisa file installer yang perlu dihapus.
- **9 persyaratan manual:** pemulihan backup, pengujian hak akses lintas akun, MFA petugas, pemindaian malware, tata kelola data pribadi, kebijakan retensi, pengujian penetrasi, persetujuan penanggung jawab, serta format/kewenangan surat dan tanda tangan elektronik.
- Setiap persyaratan manual dapat dicatat sebagai Belum Diverifikasi, Sudah Diverifikasi, atau Ada Kendala, dengan catatan bukti ringkas maksimal 250 karakter.
- Seluruh perubahan checklist dicatat pada tabel audit baru. Halaman hanya dapat dibuka Super Admin; tidak memunculkan kredensial, data warga, atau nama database privat.
- Kemajuan checklist ditampilkan sebagai persentase **pekerjaan pemeriksaan**, bukan angka kesesuaian hukum ataupun bukti keamanan produksi.
- Status tetap **Belum Dinyatakan Siap Produksi**, bahkan apabila seluruh item secara administratif sudah dicentang. Keputusan penggunaan layanan resmi berada pada instansi yang berwenang.

## Instalasi satu ZIP di Rumahweb

**Prasyarat:** Tahap 6–11 dipasang dan diuji sesuai panduan sebelumnya.

1. **Backup** database SmartApp dan file `public_html/super-admin.php`. Simpan backup dalam media privat.
2. Unduh file `paket/SmartCikoko-TAHAP12-KESIAPAN.zip` dari repository GitHub SmartCikoko.
3. Buka **cPanel → File Manager → /home/datd1747/**, upload ZIP kemudian **Extract di direktori tersebut**, bukan di dalam `public_html`.
4. Login menggunakan akun Super Admin.
5. Buka **https://datawebs.my.id/instal-kesiapan.php** kemudian klik **Pasang Tahap 12**.
6. Setelah berhasil, buka **https://datawebs.my.id/kesiapan-produksi.php**.
7. Periksa 11 indikator otomatis dan isi checklist manual **hanya untuk pekerjaan yang memang sudah diselesaikan**.
8. Setelah pemasangan dan pengujian, **hapus public_html/instal-kesiapan.php serta ZIP dari hosting**, lalu muat ulang dashboard agar pemeriksaan pembersihan installer menjadi hijau.

**Tidak perlu membuka phpMyAdmin atau menjalankan SQL manual.** Installer hanya menambahkan dua tabel:
- `unified_release_checks` — status dan bukti ringkas per pemeriksaan.
- `unified_release_events` — histori perubahan status, waktu, dan akun pemeriksa.

File lain, database Pegawai, akun lama, berkas lampiran, alur Tahap 9, dan pengajuan ahli waris lama tidak diubah oleh installer.

## Pengujian yang harus dilakukan setelah instalasi

1. Buka halaman dengan akun tidak login → harus diarahkan ke login.
2. Buka dengan akun non-Super Admin → HTTP 403.
3. Kedua database tampil sebagai memenuhi jika koneksi memang sehat.
4. Pada saat installer masih ada, pemeriksaan "Berkas installer publik" harus merah. Setelah installer dihapus dan halaman dimuat ulang, menjadi hijau jika tidak ada installer lain.
5. Periksa folder private_documents dan private_requests: harus berada di luar public_html, tidak berupa symlink, dan tidak dapat diakses langsung lewat web.
6. Simpan satu checklist dengan status Ada Kendala, kemudian buka riwayat perubahan.
7. Periksa bahwa menandai status Sudah Diverifikasi memerlukan catatan bukti minimal 10 karakter.
8. Pastikan dashboard utama, pengajuan warga, portal PTSP/Lurah, arsip, serta API Android tetap berjalan.
9. Pastikan tidak ada password, NIK, konfigurasi, atau dokumen asli yang muncul dalam HTML atau log pengujian.
10. Jalankan pemeriksaan keamanan serta pemulihan backup melalui tim berwenang sebelum memproses dokumen warga nyata.

## Keterbatasan keselamatan dan hukum

- SHA-256 dan validasi MIME tidak menggantikan pemindaian malware. **Pemindaian antivirus bawaan belum diterapkan**.
- MFA bawaan belum diterapkan. Menandai checklist MFA berhasil **tidak mengaktifkan** MFA di aplikasi.
- **Belum tersedia** integrasi tanda tangan elektronik bersertifikat maupun penerbitan naskah dinas resmi. Menandai item format/kewenangan sebagai selesai **tidak mengaktifkan** fitur tersebut.
- Audit dan checklist bukan pengujian penetrasi, asesmen kepatuhan, atau persetujuan untuk pengolahan dokumen warga.
- Tidak ada otomatisasi keputusan "go live"; persetujuan harus dibuat sesuai prosedur instansi.

## Berkas paket

```text
/home/datd1747/
├── portal_private/
│   └── release_readiness.php       (baru)
└── public_html/
    ├── kesiapan-produksi.php       (baru)
    ├── instal-kesiapan.php         (hapus setelah pemasangan)
    ├── super-admin.php            (tautan pusat kesiapan)
    └── assets/
        ├── kesiapan-produksi.css
        └── instal-kesiapan.css
```

**Status:** pembuatan ZIP dan lint PHP dilakukan di GitHub Actions; instalasi pada Rumahweb harus dilakukan oleh pemilik hosting. Tahap ini sengaja belum membuka fitur surat dinas resmi atau perizinan digital yang memerlukan kebijakan dan otorisasi instansi.

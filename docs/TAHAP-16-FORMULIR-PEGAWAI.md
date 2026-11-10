# Tahap 16 — Formulir Terstruktur Berdasarkan Website Pegawai Lama

SmartCikoko, Kelurahan Cikoko, Kecamatan Pancoran.

Tahap 15 sudah menyediakan pusat input ringkas dan satu halaman Super Admin. Tahap 16 mulai menerapkan **bidang formulir yang benar-benar ada pada arsip lama** (PegawaiCikoko dan SQL Pegawai). Sistem lama tidak dihapus.

## Pemetaan sumber lama

- **Cuti:** arsip pegawai/cuti.php dan tabel cuti + cuti_detail: tanggal mulai/selesai, alasan, alamat selama cuti, nomor telepon, jabatan, unit kerja, masa kerja, kota/tanggal surat, dan catatan saldo.
- **Kinerja PPPK:** tabel ck_pppk_kinerja: bulan laporan, jenis admin atau lapangan, indikator, uraian pekerjaan, hasil, kendala, dan tindak lanjut.
- **PJLP/PPSU:** tabel pjlp_duty_session, laporan, dan pjlp_task_photos: sesi tugas milik pegawai, tanggal, jalur/rute, waktu, jenis kegiatan, hasil, dan kendala. Bukti foto/GPS belum aktif.

## Fitur Tahap 16

1. Formulir terstruktur tersendiri untuk PPPK, PJLP dan Cuti — bukan satu textarea generik.
2. Simpan draf, kirim untuk peninjauan, kembali untuk perbaikan, kirim ulang.
3. Super Admin dapat melihat semua formulir dan meninjau setelah MFA; pegawai biasa hanya melihat/mengubah miliknya.
4. Baca-saja ringkasan data sumber lama untuk akun Pegawai yang sama, tanpa mencampur ID Pancoran.
5. Pemeriksaan izin akun, CSRF, tanggal dan periode, jenis laporan, sesi tugas PJLP, validasi ukuran input, penguncian baris, nomor versi, idempotency key, batas harian dan audit.

**Penting:** Formulir Tahap 16 adalah **pengajuan administrasi terstruktur**. Belum mengubah tabel cuti atau saldo cuti, belum menghitung penilaian PPPK, belum membuat laporan PJLP resmi, tidak menghasilkan surat sah dan tidak mengunggah bukti foto/GPS. Status "Ditutup" bukan persetujuan cuti.

## Instalasi cPanel tanpa SQL manual

1. Backup kedua database, file website, konfigurasi server privat, dan kunci enkripsi MFA.
2. Download paket/SmartCikoko-TAHAP16-FORMULIR-PEGAWAI.zip dari repository GitHub.
3. Di cPanel File Manager, upload paket ke /home/datd1747/ dan Extract dari direktori tersebut — jangan dari dalam public_html.
4. Paket ini hanya menambahkan file baru. Tidak mengganti index.php, bootstrap.php, auth.php, mfa.php, super-admin-satu.php, konfigurasi, ataupun tabel lama Pegawai.
5. Login Super Admin dan selesaikan verifikasi autentikator.
6. Buka https://datawebs.my.id/instal-formulir16.php lalu klik Pasang Tahap 16.
7. Buka https://datawebs.my.id/formulir-pegawai16.php untuk memilih Formulir PPPK, PJLP atau Cuti. Uji menggunakan akun dummy dengan peran yang sesuai.
8. Super Admin dapat melihat dan meninjau pengajuan pada halaman yang sama.
9. Hapus file public_html/instal-formulir16.php dan arsip ZIP di hosting sesudah pengujian berhasil. Jangan hapus tabel hasil pemasangan yang sudah digunakan.

## Tabel baru (SmartApp saja)

- unified_detailed_forms16: isian JSON terstruktur, kategori, identitas pemilik, status, versi dan catatan.
- unified_detailed_form_history16: riwayat tindakan dan aktor.

Tidak ada impor ulang SQL asli, tidak ada perubahan pada dua database sumber kecuali penambahan tabel baru pada SmartApp.

## Langkah untuk integrasi resmi berikutnya

- **Cuti:** uji kalender, hari kerja, benturan tanggal, saldo dan persetujuan atasan/Lurah sesuai proses asli sebelum menghubungkan transaksi ke tabel Pegawai.
- **PPPK:** samakan format indikator/payload, foto, revisi, status penilaian dan ekspor PDF dengan sistem asli.
- **PJLP:** samakan roster, sesi lapangan, jalur, target foto, waktu, pemeriksaan GPS dan bukti pekerjaan sebelum menyimpan ke tabel asli.
- **RT/RW, LMK, Jumantik, FKDM, PPSU:** lanjutkan pemetaan rinci, otorisasi, foto, format surat dan tanda tangan. Tahap 15 tetap menyediakan laporan ringkas sementara.

Uji peran dan akses objek dengan akun dummy. Jangan masukkan data NIK atau dokumen warga nyata ke formulir uji. Scanner antivirus pada hosting harus diverifikasi sebelum upload bukti dibuka.

Kode tersedia di GitHub, **belum otomatis terpasang pada Rumahweb**. Pengujian sintaks dan unit test tidak menggantikan pengujian integrasi pada hosting.
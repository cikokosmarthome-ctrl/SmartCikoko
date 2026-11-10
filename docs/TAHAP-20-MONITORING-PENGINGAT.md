# Tahap 20 — Monitoring Bulanan, Tindak Lanjut dan Pengingat Internal

SmartCikoko — Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Tujuan

Satu sistem yang menampilkan **jumlah laporan berdasarkan bulan**, **laporan menunggu tindak lanjut lebih lama dari ambang internal**, dan **pengingat di dalam akun portal**, tetap di bawah satu Super Admin Ringkas.

Tahap 20 membaca tiga generasi laporan yang telah dibuat:
- Tahap 15: unified_report_intake
- Tahap 16: unified_detailed_forms16
- Tahap 17: unified_field_reports17

Tahap 19 digunakan untuk membedakan **menunggu pemeriksa**, **menunggu telaah Lurah**, **perlu perbaikan**, dan **selesai administratif**.

**Tidak ada daftar kewajiban laporan individu, jadwal tugas pegawai, atau batas laporan resmi di data Tahap 15–19.** Karena itu Tahap 20 tidak menyimpulkan pegawai lalai jika tidak memiliki laporan; hanya menghitung laporan yang sudah tercatat dan belum ditindaklanjuti. Ini **bukan penilaian kinerja atau disiplin resmi**.

## Fitur

1. **Monitoring Bulanan**: pilih periode YYYY-MM dan lihat jumlah laporan yang dibuat pada periode tersebut dan saat ini sudah diajukan, sedang perlu perbaikan, atau ditutup. Perhitungan status adalah snapshot terkini, bukan kejadian historis yang terjadi pada bulan itu.
2. **Aturan ambang hari**: Super Admin mengatur 1–60 hari kalender per modul. Ketiga aturan **NONAKTIF secara default**. Contoh nilai 3 hari pada kolom pengaturan belum berlaku sampai disimpan dengan opsi aktif; ambang ini bukan kebijakan instansi.
3. **Antrean tindak lanjut**: laporan aktif Tahap 15–17 dievaluasi menggunakan tanggal perubahan laporan atau status verifikasi terakhir, dibandingkan batas internal. Draf dan laporan selesai tidak ditampilkan.
4. **Pengingat internal manual**: Super Admin hanya dapat membuat pengingat saat laporan melewati aturan aktif. Tidak ada WhatsApp, SMS, email, push notification atau cron otomatis.
5. **Tujuan pengingat**: laporan yang **perlu perbaikan** dikirim ke kotak internal **pelapor asli**. Jika masih **menunggu pemeriksa/Lurah**, pemberitahuan dimasukkan ke **kotak Super Admin** sebagai pengawasan (sistem belum memiliki pemetaan tugas pemeriksa per orang). Ini tidak menugaskan Lurah secara otomatis.
6. **Kotak Pengingat Saya**: akun masuk hanya dapat melihat pesan untuk ID sumber akun miliknya dan menandai dibaca, tanpa mengakses laporan orang lain. Status dibaca tidak menyelesaikan laporan.
7. **Audit**: perubahan aturan, pembuatan pengingat, dan penandaan telah dibaca tercatat dalam audit.
8. **Anti-spam**: maksimal **satu pesan per laporan, nomor versi dan fase**. Pengiriman ulang pengingat yang sama akan ditolak.
9. **Privasi**: antrean Super Admin menampilkan nomor, kategori, fase dan usia antrean; tidak menampilkan NIK, isi laporan, lokasi pribadi atau nama pelapor.

## Alamat akses

- Super Admin Ringkas (panel tambahan): https://datawebs.my.id/super-admin-satu.php#monitor-bulanan
- Monitoring bulanan, aturan dan antrean: https://datawebs.my.id/monitor-laporan20.php
- Kotak Pengingat Saya (petugas): https://datawebs.my.id/pengingat-saya20.php
- Installer satu tombol: https://datawebs.my.id/instal-monitor20.php

## Database

Dua tabel **tambahan pada SmartApp** (bukan Pegawai):
- unified_monitor_rules20: aturan per modul, hari ambang, aktif/nonaktif, dan pengubah terakhir.
- unified_monitor_reminders20: hubungan laporan (sumber/ID/versi/fase), penerima, pengirim, pesan internal, waktu dibuat dan dibaca.

Tidak memindahkan data, tidak mengubah tabel kepegawaian, izin akun, riwayat, dokumen, saldo cuti, surat RT/RW, atau pengesahan Lurah.

## Pemasangan cPanel — tanpa SQL manual

1. Pastikan Tahap 15–19 telah dipasang dan lolos uji akun serta tabel.
2. Backup website, database Pegawai & SmartApp, file konfigurasi privat, dan kunci MFA ke media aman **di luar public_html**.
3. Download **paket/SmartCikoko-TAHAP20-MONITORING-PENGINGAT.zip**.
4. cPanel File Manager → buka direktori **/home/datd1747/** → Upload ZIP → Extract **pada direktori itu**, bukan di dalam public_html.
5. ZIP hanya menambah berkas baru. Tidak mengganti index.php, bootstrap.php, auth.php, mfa.php, super-admin-satu.php, pusat-laporan.php atau config.php.
6. Login Super Admin menggunakan kode autentikator.
7. Buka **https://datawebs.my.id/instal-monitor20.php**, periksa prasyarat, klik **Aktifkan Monitoring Tahap 20**.
8. Installer menambah 2 tabel baru dan menyisipkan panel monitoring ke Super Admin Ringkas serta tautan Pengingat Saya ke Pusat Input. Sebelum halaman live diubah, installer membuat backup di **portal_private/monitor20_backups/**, di luar direktori publik. Jika file hosting telah berubah, penyisipan ditolak, bukan memaksa penimpaan.
9. Buka **https://datawebs.my.id/monitor-laporan20.php**, pilih bulan, lalu cek jumlahnya. Uji aturan menggunakan akun dan laporan dummy terlebih dahulu; **jangan langsung mengaktifkan ambang hari untuk semua modul produksi**.
10. Uji pengingat koreksi ke akun pelapor dummy, serta pengingat pemeriksa/Lurah yang hanya masuk ke kotak Super Admin. Uji hak akses akun lain dan penolakan pengingat ganda.
11. Setelah berhasil, hapus file **public_html/instal-monitor20.php** dan ZIP dari hosting. Jangan hapus tabel atau folder backup privat.

## Batasan monitoring dan hal yang tidak boleh diklaim

- Monitoring memakai aktivitas laporan yang sudah ada; **tidak mengukur kehadiran, capaian target kerja, beban tugas atau disiplin individu**.
- Angka "Perlu Perbaikan" dan "Ditutup" merupakan **status terkini pada laporan yang awalnya dibuat di bulan terpilih**, bukan jumlah tindakan yang terjadi di bulan tersebut.
- Antrean memindai maksimal 300 laporan aktif lama per sumber dan menampilkan maksimal 60 yang melewati ambang. Jika melebihi batas atau salah satu sumber terganggu, tampil peringatan bahwa daftar dapat tidak lengkap.
- Tanpa roster penugasan pemeriksa yang diotorisasi, tidak ada pengingat otomatis kepada akun Lurah atau pemeriksa tertentu.
- Tidak ada pengiriman otomatis atau pemantauan latar belakang. Semua pembuatan pengingat membutuhkan klik langsung Super Admin.
- Tidak menyetujui cuti, penilaian PPPK/PJLP, penerbitan surat RT/RW atau data hasil verifikasi kesehatan.
- Hasil test PHP lint, role logic, dan packaging tidak membuktikan operasi pada hosting Rumahweb. Uji integrasi dengan dua database dan akun dummy wajib sebelum penggunaan nyata.

## Arah lanjutan

Jika organisasi ingin pengingat otomatis, harus dibuat pemetaan petugas bertanggung jawab, batas hari resmi yang disetujui, kebijakan privasi, retensi, jadwal eksekusi dan notifikasi agar pesan tidak salah tujuan. Tahap 20 sengaja mendahulukan monitoring manual yang aman.

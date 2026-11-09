# Tahap 10 — Lampiran Pengajuan dan Pemberitahuan SmartCikoko

Pembaruan ini melengkapi pengajuan masyarakat pada Tahap 9 tanpa memindahkan data ahli waris lama atau mengubah tabel pegawai.

## Instalasi cepat Rumahweb

1. Pastikan Tahap 9 sudah terpasang. Cadangkan database SmartApp serta file portal_private/citizen_requests.php, public_html/api/index.php, public_html/pengajuan-warga.php dan public_html/super-admin.php.
2. Download paket/SmartCikoko-TAHAP10-TINDAKLANJUT.zip dari GitHub.
3. cPanel Rumahweb > File Manager > /home/datd1747/ > Upload > Extract. Jangan ekstrak dari dalam public_html.
4. Login sebagai Super Administrator. Buka https://datawebs.my.id/instal-tindak-lanjut.php dan klik Pasang Tahap 10.
5. Buka https://datawebs.my.id/tindak-lanjut.php. Uji dengan akun Warga, PTSP dan Lurah menggunakan pengajuan dan berkas dummy.
6. Setelah pengujian, hapus public_html/instal-tindak-lanjut.php dan arsip ZIP dari hosting. Jangan menghapus portal_private/private_requests.

Installer membuat dua tabel baru dalam database SmartApp: unified_citizen_files dan unified_citizen_notifications. Tidak perlu membuka phpMyAdmin atau mengimpor ulang SQL.

## Fitur

- Pemohon dapat mengunggah PDF/JPG/PNG sampai 5 MB per berkas, maksimal 10 lampiran per permohonan, hanya pada status Draf dan Perlu Perbaikan.
- Berkas disimpan di portal_private/private_requests di luar public_html. Hak baca diperiksa terhadap permohonan induk; pengguna lain tidak dapat mengambil file dengan menebak ID.
- Petugas PTSP, Lurah, dan Super Admin yang berwenang dapat meninjau daftar berkas tanpa mengubah isi berkas.
- Notifikasi perubahan status tersedia di portal dan sebagai daftar pemberitahuan dalam kode aplikasi Android; belum ada SMS, WhatsApp, email, atau push.
- Rekap antrean menurut status untuk petugas berwenang.
- Bukti informasi permohonan dapat dicetak dan jelas ditandai BUKAN SURAT RESMI. Persetujuan alur kerja belum sama dengan penerbitan surat bertanda tangan pejabat.
- Pengunggahan lampiran lewat Android belum disediakan, untuk saat ini lewat website.

## Endpoint API

GET citizen_notifications, POST citizen_notice_read, GET citizen_files?id=..., dan GET citizen_pending menggunakan token login dan pemeriksaan kewenangan yang sama dengan Tahap 9.

## Pengujian wajib sebelum operasional

1. Warga A tidak boleh mengunduh lampiran milik Warga B, sekalipun mengetahui ID berkas.
2. Akun warga diblokir Tahap 6 tidak boleh membuka endpoint.
3. Unggah pada pengajuan yang sudah diajukan atau disetujui harus ditolak.
4. Perubahan status oleh petugas harus menghasilkan notifikasi kepada pemohon yang tepat.
5. Pastikan URL berkas tidak tersedia secara publik; akses hanya melalui unduh-pengajuan.php.
6. Cetakan bukti tidak boleh menyerupai surat dinas yang sah.
7. Periksa kembali alur Tahap 9 dan data ahli waris lama tetap utuh.

**Batasan keamanan:** Jangan gunakan dokumen warga nyata sebelum pemindaian malware, MFA petugas, kebijakan retensi, pencadangan terenkripsi dan audit keamanan tersedia. Pengecekan MIME, ukuran file, serta header bukan pemeriksaan antivirus. Kode GitHub belum otomatis diterapkan pada hosting Rumahweb. PHP lint dan Flutter analyze bukan pengganti pengujian transaksi di server produksi.

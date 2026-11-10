# SmartCikoko — Tahap 26: Uji Operasional Terpandu & Tindak Lanjut

## Tujuan
Sesudah pemeriksaan teknis Tahap 25, perlu bukti bahwa operator **benar-benar menguji** alur input, perbaikan, pemeriksaan, penugasan, dan navigasi. Tahap 26 menyediakan **jurnal uji penerimaan pengguna (UAT)**, bukan melakukan ujian secara otomatis atau mengesahkan kesiapan produksi.

Pusat Super Admin tetap `/super-admin-satu.php`. Satu panel ringkas **Uji Penerimaan Pengguna** ditambahkan ke halaman tersebut, sedangkan pencatatan dijalankan di `/uji-operasional26.php`.

## 16 skenario uji
- PPPK: draf dan ajukan laporan.
- PJLP: laporan kegiatan.
- Cuti: pengajuan administratif awal, **bukan persetujuan cuti**.
- RT/RW: laporan kegiatan wilayah.
- LMK: laporan kegiatan.
- Jumantik: observasi dummy tanpa informasi kesehatan warga.
- FKDM: laporan kegiatan.
- PPSU: laporan kegiatan.
- Ahli Waris: membuka layanan asli, **bukan penerbitan surat otomatis**.
- Tahap 19: pemeriksaan dan telaah Lurah.
- Tahap 21: penugasan pemeriksa.
- Tahap 22: pengganti sementara, periode dan pencabutan.
- Tahap 20–21: pengingat internal.
- Tahap 24–25: konsistensi statistik.
- Tampilan pada desktop dan ponsel.
- Kontrol akun nonaktif dan MFA.

## Cara kerja
1. Super Admin berwenang menggunakan **akun dummy** untuk menjalankan langkah uji yang tertera di setiap skenario.
2. Setelah melakukan langkah, pilih hasil **Lulus**, **Gagal**, atau **Terhambat**, dan isi catatan teknis ringkas 15–240 karakter tanpa data pribadi. Status awal adalah **Belum diuji**.
3. Jika memilih **Lulus**, wajib mencentang pernyataan bahwa uji sebenarnya sudah dilakukan. Ini hanya konfirmasi manual; aplikasi tidak dapat memverifikasi kebenaran klaim tersebut secara otomatis.
4. Status **Gagal/Terhambat** muncul dalam bagian *Temuan yang Perlu Tindak Lanjut*. Setelah perbaikan, tes diulang dan status dicatat lagi.
5. Semua perubahan membuat **baris riwayat baru**; entri lama tidak dihapus. Status terkini diambil dari id terbesar per skenario, bukan dari tanggal yang dimasukkan penguji. Formulir memiliki token unik untuk mencegah kiriman ulang yang sama.
6. Ada riwayat 25 entri terakhir. Ini tampilan ringkas; data jurnal lama tetap dalam tabel dan memerlukan kebijakan retensi yang ditetapkan pengelola.
7. Bahkan ketika semua skenario ditandai **Lulus**, sistem tidak menerbitkan sertifikat, surat pernyataan keamanan, atau otomatis mengubah status kesiapan produksi.

## Perlindungan
- Hanya **Super Admin dengan MFA terverifikasi**, dilanjutkan validasi akun aktif, boleh melihat/mengubah jurnal.
- Semua formulir penulisan menggunakan CSRF token dan query berparameter. Riwayat perubahan dicatat juga pada `unified_audit`.
- Catatan menolak pola NIK 16 digit, alamat email, dan teks berlabel rahasia tertentu. Filter pola **tidak menjamin seluruh data sensitif tertolak**; operator dilarang menulis nama warga, dokumen, identitas pegawai, nomor surat asli, foto, kata sandi, OTP, dan token.
- Tidak menyimpan atau mengunggah lampiran, screenshot, data pelanggan, lokasi warga, atau dokumen asli.
- Tidak mengubah data Pegawai, nilai PPPK, saldo cuti, surat waris, laporan warga, status verifikasi, atau izin sistem.
- Tabel baru `unified_uat_events26` pada **SmartApp** menyimpan case_key, hasil, catatan non-pribadi, pernyataan telah diuji, identitas akun penguji untuk audit privat, token anti-duplikat, dan waktu. Semua skor tetap manual.
- Installer menambah satu panel pada `super-admin-satu.php` hanya jika struktur file kompatibel dan SHA-256 cocok; salinan asli disimpan ke `portal_private/uat26_backups/` di luar webroot.
- Tidak memasang cron, tidak mengirim WhatsApp/email/SMS dan tidak memproses transaksi asli.

## Instalasi cPanel Rumahweb
1. Pastikan Tahap 25 telah terpasang; backup **dua database, website, file privat dan kunci MFA** ke tempat aman di luar folder publik.
2. Download ZIP `paket/SmartCikoko-TAHAP26-UJI-OPERASIONAL.zip` dari repository GitHub.
3. File Manager cPanel → `/home/datd1747/` → Upload ZIP → klik **Extract pada direktori tersebut**, bukan dalam `public_html`.
4. Paket **hanya menambah file**:
   - `portal_private/uat26.php`
   - `portal_private/uat26_patch.php`
   - `portal_private/uat26_admin_panel.php`
   - `public_html/uji-operasional26.php`
   - `public_html/instal-uji26.php`
   - `public_html/assets/uji-operasional26.css`
   - `public_html/assets/uat26-admin.css`
   - `BACA-TAHAP26.txt`
5. Login Super Admin menggunakan Google Authenticator. Buka **https://datawebs.my.id/instal-uji26.php** lalu klik **Aktifkan Uji Operasional Tahap 26**.
6. Installer membuat satu tabel baru dengan default belum diuji, lalu mencadangkan `public_html/super-admin-satu.php` ke `portal_private/uat26_backups/` dan menambahkan panel. **Tidak perlu impor SQL manual**.
7. Buka **https://datawebs.my.id/uji-operasional26.php**; pastikan 16 skenario berstatus Belum diuji. Uji satu skenario dengan akun dummy, catat Gagal, lakukan ulang dan catat Lulus; lihat riwayat tetap mempertahankan hasil Gagal.
8. Uji bahwa akun biasa dan sesi tanpa MFA tidak dapat mengakses halaman pencatatan. Periksa halaman pada ponsel. Jangan menguji dengan dokumen asli.
9. Setelah pengujian berhasil, hapus `public_html/instal-uji26.php` dan file ZIP dari hosting. **Simpan folder backup privat**.
10. Jika pemasangan ditolak karena halaman Super Admin berbeda, **jangan menimpa file lama secara manual**. Periksa versi dan izin file, serta laporkan tampilan hasil installer.

## Batasan dan tahapan selanjutnya
Pengujian PHP/SQLite melalui GitHub hanya membuktikan skema simulasi dan aturan kode. **Belum ada tes end-to-end di Rumahweb**, tidak ada klaim bahwa layanan sudah aman untuk produksi. Jalankan uji dengan akun dan catatan dummy terlebih dahulu.

Setelah memperoleh hasil dari Tahap 26, prioritas berikutnya adalah **memperbaiki temuan Gagal/Terhambat yang benar-benar terukur** pada modul tertentu—bukan membuat lebih banyak halaman dan fitur tanpa dasar operasional.

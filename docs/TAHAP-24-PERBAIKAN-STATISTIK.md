# SmartCikoko Tahap 24 — Pemulihan Statistik Pusat Operasional

## Masalah pada hosting
Pada screenshot pengguna setelah Tahap 23 V2, Pusat Operasional berhasil dirender, tetapi empat angka status laporan tampil sebagai **—** dan muncul peringatan **“Informasi sebagian belum termuat: ringkasan laporan”**.

Pemeriksaan kode mengidentifikasi penyebab kuat: di `portal_private/hub23.php`, `foreach($tables as $stage=>$table)` atas array dengan kunci `'15'`, `'16'`, `'17'` mengubah kunci menjadi **integer**, sedangkan `h23_ready(string $module)` mewajibkan **string** dalam file `declare(strict_types=1)`. Terjadi `TypeError` sebelum query statistik; berkat failover V2, halaman tidak lagi putih. Modul sumber masih tersedia dan data tidak terbukti hilang.

## Perbaikan
1. Ubah nomor tahap ke string sebelum pemeriksaan kesiapan: `$key=(string)$stage`, lalu `h23_ready($key)`.
2. Setiap sumber Tahap 15, 16, 17 dibaca terpisah dan dijaga `try/catch` agar jika satu sumber gagal, dua sumber lain tetap ditampilkan.
3. Hitung status sesuai tabel formulir tambahan saja:
   - Draf: `draf`
   - Dalam proses: `diajukan` dan `ditinjau`
   - Perlu perbaikan: `perbaikan`
   - Selesai administratif: `ditutup`
4. Tampilkan jumlah per sumber (Tahap 15 Ringkas, Tahap 16 Pegawai, Tahap 17 Wilayah). Sumber tidak siap ditandai **belum terbaca**, bukan dipaksa angka nol.
5. Super Admin melihat agregat administratif seluruh laporan tambahan, akun lainnya hanya catatan `actor_source` + `actor_id` miliknya. Ini **bukan data kinerja resmi PPPK, keputusan cuti atau pengesahan surat**.
6. Tambahkan pengujian SQLite dengan contoh laporan RT, warga, PPPK dan PJLP. Uji menjamin kunci modul numerik tidak lagi menghasilkan `TypeError`, serta total Super Admin/pemilik laporan tepat.

## Isi paket
`paket/SmartCikoko-TAHAP24-PERBAIKAN-STATISTIK.zip` hanya menambahkan:
- `portal_private/recovery24/hub23.php` — kandidat versi yang sudah dibetulkan; **tidak langsung menimpa** file live
- `portal_private/recovery24/pusat-operasional23.php` — kandidat tampilan rincian sumber laporan
- `portal_private/stats24_repair.php` — pemeriksaan checksum rilis lama/baru, backup privat, dan instalasi atomik
- `public_html/perbaiki-statistik24.php` — installer satu tombol hanya untuk Super Admin dengan MFA

Tidak ada SQL, tidak menambah/mengubah database, tidak mengganti Beranda, login, MFA, Super Admin Ringkas, modul Tahap 19–22 atau file pengaturan.

## Pemasangan cPanel
1. **Backup website, kedua database, konfigurasi privat, serta kunci MFA** terlebih dahulu.
2. cPanel → File Manager → `/home/datd1747/` → upload ZIP → **Extract di lokasi tersebut**, bukan di `public_html`.
3. Login Super Admin menggunakan Google Authenticator.
4. Buka https://datawebs.my.id/perbaiki-statistik24.php.
5. Installer harus menunjukkan kedua file sebagai **“Versi Tahap 23 V2 dikenali — siap diperbaiki”**. Jika versi lain sudah dipasang, installer akan menolaknya demi menjaga kode setempat.
6. Klik **Perbaiki Statistik Tahap 24**. Dua file Tahap 23 diperbarui setelah masing-masing backup dibuat di luar webroot: `portal_private/stats24_backups/`.
7. Buka https://datawebs.my.id/pusat-operasional23.php?v=24 dan periksa empat angka serta rekap per sumber. **Jika sumber kosong tetapi benar-benar terbaca, angka 0 valid**. Jika masih ada tanda belum terbaca, laporkan screenshot dan periksa log PHP privat.
8. Uji dengan akun dummy Super Admin serta petugas biasa, pastikan perbandingan tidak mengekspos laporan orang lain.
9. Setelah berhasil hapus `public_html/perbaiki-statistik24.php` dan ZIP dari hosting. **Jangan hapus folder backup privat**.

## Pengujian
GitHub Actions menjalankan sintaks PHP, unit tests Stage23 dan Stage24, pembacaan agregat tiga tabel pada SQLite, pembatasan per akun, serta ketepatan hash rilis. Tes tidak menggantikan pengecekan SQL data produksi; jumlah akhir di hosting harus divalidasi terhadap sumber aslinya.

## Langkah setelah statistik pulih
Sederhanakan pengelompokan modul di Super Admin Ringkas dan uji responsivitas/pengalaman operator, kemudian lakukan pengujian end-to-end input, perbaikan, pemeriksaan, dan rekap pada **data dummy**. Jangan menyatakan laporan asli disetujui atau pegawai dinilai oleh indikator ringkasan ini.

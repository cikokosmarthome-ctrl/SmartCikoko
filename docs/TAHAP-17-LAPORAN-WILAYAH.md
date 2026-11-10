# Tahap 17 — Laporan Kewilayahan & Jumantik Terstruktur (SmartCikoko)

Kelurahan Cikoko — Kecamatan Pancoran, Jakarta Selatan.

## Sasaran

Melengkapi Tahap 15–16 dengan formulir spesifik RT/RW, LMK, Jumantik, FKDM dan PPSU. **Super Admin tetap satu halaman**, melalui panel baru di `/super-admin-satu.php#laporan-wilayah`; petugas masuk dari `/pusat-laporan.php` lalu membuka form detail.

## Pemetaan ke SQL sumber

Dari SQL SmartApp yang disediakan, ditemukan:

| Modul | Tabel asli / struktur | Form baru Tahap 17 | Batas integrasi |
|---|---|---|---|
| RT/RW | `mobile_rt_rw_letters`, kolom RT, RW, pejabat RT/RW, subject, status dan riwayat | Tanggal, RT, RW, jenis kegiatan, tempat umum, uraian dan hasil | **Bukan** surat pengantar, tidak melakukan verifikasi surat |
| LMK | Tabel laporan terstruktur khusus LMK belum ditemukan | Tanggal, wilayah, bidang aspirasi/musyawarah/pembangunan, tempat umum, uraian dan tindak lanjut | Tidak mengarang tabel atau keputusan LMK resmi |
| Jumantik | `mobile_reports` dengan `kind='jumantik'` dan catatan laporan **warga** | Tanggal, RT/RW, rumah dan penampungan diperiksa/positif, PSN dan catatan agregat | Warga diperbolehkan mencatat *observasi komunitas*, bukan sertifikasi kader Jumantik |
| FKDM | `mobile_fkdm_reports`, user ID, nomor anggota, lokasi, kegiatan dan foto | Nomor anggota opsional, tanggal, lokasi umum, jenis, uraian, tindak lanjut | Tidak memproses file foto atau data yang bersifat rahasia |
| PPSU | `mobile_ppsu_reports`, tim, tanggal pekerjaan, kegiatan dan foto | Tanggal, tim, lokasi, jenis pekerjaan, uraian, hasil dan kendala | Tidak menggantikan bukti foto/lokasi/kehadiran yang diwajibkan |

Riwayat lama dapat ditampilkan hanya sebagai metadata baca-saja sesuai ID pemilik: RT/RW dapat membaca surat yang ditugaskan kepadanya (tanpa nama pemohon), warga dapat membaca metadata laporan Jumantik miliknya, FKDM dan PPSU melihat metadata laporannya masing-masing. LMK belum mempunyai tabel riwayat lama yang pasti.

**Semua laporan baru disimpan terpisah dari data asli.** Tidak mengimpor ulang SQL, tidak memindahkan atau mengubah data warisan.

## Fitur

- Satu formulir dinamis yang berbeda menurut kategori; role RT/RW, LMK, Jumantik/warga, FKDM dan PPSU/PJLP.
- Validasi rentang tanggal, jenis kegiatan, RT/RW dan jumlah agregat. Rumah/penampungan positif tidak boleh melebihi jumlah diperiksa.
- Draf milik pelapor, pengajuan, pemeriksaan Super Admin, perbaikan oleh pemilik, dan penutupan **administratif**.
- Draf belum dikirim tidak tampil pada antrean Super Admin; laporan milik orang lain tidak dapat diubah oleh pelapor.
- CSRF, otorisasi akun, batas 12 formulir per hari, query SQL terparameter, nonce idempoten, riwayat revisi dan jejak audit.
- Tahap 17 tidak membuat kolom NIK, alamat privat rumah tangga atau form foto/berkas. Jangan masukkan identitas sensitif ke kolom uraian.

## Satu Super Admin + satu Pusat Input

Pemasang Tahap 17 membuat dua tabel baru di SmartApp dan mencoba **menyisipkan bagian kecil** secara aman pada dua halaman yang sudah terpasang:

1. `public_html/super-admin-satu.php`: menampilkan 5 kategori, jumlah laporan dan antrean peninjauan di dalam dashboard ringkas, tanpa dashboard admin tambahan.
2. `public_html/pusat-laporan.php`: satu tautan menuju formulir detail Tahap 17 untuk petugas.

Installer menyimpan salinan asli kedua file tersebut secara otomatis ke **`portal_private/wilayah17_backups/` di luar public_html** sebelum menyisipkan bagian baru. Perubahan tidak menimpa file keseluruhan dengan versi GitHub. Jika struktur halaman live berbeda atau izin file tidak sesuai, penyisipan ditolak dan data lama tetap utuh. Modul laporan baru masih dapat diakses lewat alamat langsung.

## Satu ZIP untuk cPanel

1. Backup website, **kedua database**, kunci MFA, dan file konfigurasi privat ke lokasi aman di luar direktori publik.
2. Unduh ZIP `paket/SmartCikoko-TAHAP17-LAPORAN-WILAYAH.zip`.
3. cPanel File Manager → `/home/datd1747/` → Upload ZIP → **Extract** di direktori tersebut, bukan di dalam public_html.
4. Paket ZIP berisi file **baru saja** (tidak mengganti `bootstrap.php`, `index.php`, `auth.php`, `mfa.php`, `super-admin-satu.php`, atau `pusat-laporan.php` pada tahap ekstraksi).
5. Login Super Admin dengan MFA, kemudian buka **https://datawebs.my.id/instal-wilayah17.php**.
6. Klik **Pasang & Sambungkan ke Super Admin**. Installer membuat tabel baru dan, hanya bila struktur cocok, menyisipkan panel dan tautan ke dua halaman lama dengan backup privat.
7. Buka **https://datawebs.my.id/super-admin-satu.php#laporan-wilayah** untuk melihat antrean dan kategori dari halaman admin yang sudah ada.
8. Buka **https://datawebs.my.id/laporan-wilayah17.php** atau Pusat Input untuk mulai menguji form menggunakan akun dummy yang sesuai.
9. Lakukan uji koreksi dan peninjauan; sesudah berhasil, hapus `public_html/instal-wilayah17.php` dan ZIP dari hosting. **Jangan** hapus tabel atau backup privat.

Tabel baru hanya pada database SmartApp:
- `unified_field_reports17`
- `unified_field_history17`

Tidak perlu phpMyAdmin atau SQL manual.

## Uji keamanan / produksi

- RT/RW tidak dapat membuat laporan FKDM/LMK, warga tidak dapat membuat surat RT/RW atau laporan PPSU.
- Warga dapat mencatat observasi jentik agregat dengan catatan **belum diverifikasi kader**.
- Pelapor hanya dapat melihat/mengubah laporan sendiri (status Draf/Perlu Perbaikan). Super Admin yang melewati MFA dapat meninjau laporan **yang sudah diajukan**.
- Jangan menggunakan NIK, alamat lengkap warga, foto, nomor telepon warga atau data kesehatan pribadi dalam pengujian.
- Periksa peran akun asal SmartApp yang benar-benar aktif sebelum menguji; tidak semua peran sumber mungkin memiliki akun terpasang.
- Uji laporan jumlah positif jentik melebihi diperiksa (harus ditolak), tanggal tidak valid, akses dokumen orang lain, dan status ditutup yang tidak dapat dibuka kembali sendiri.
- Form resmi berbasis foto, GPS, tanda tangan surat RT/RW, penugasan FKDM, serta verifikasi lapangan baru boleh diintegrasikan setelah scanner malware, persetujuan instansi dan perlindungan data siap.
- Pengujian otomatis di GitHub tidak menggantikan uji integrasi dengan akun dan database aktual pada Rumahweb.

## Alamat

- **Super Admin Ringkas:** https://datawebs.my.id/super-admin-satu.php#laporan-wilayah
- **Pusat Input:** https://datawebs.my.id/pusat-laporan.php
- **Formulir Kewilayahan:** https://datawebs.my.id/laporan-wilayah17.php
- **Installer:** https://datawebs.my.id/instal-wilayah17.php

Perubahan kode di GitHub **tidak otomatis terpasang** pada hosting. Pengujian sintaks, validasi form, dan struktur ZIP bukan bukti pelaksanaan produksi.

# CIKOKO SMART — Master Integrasi Website Pegawai + Website Cikoko + APK

Tanggal pengembangan: 10 November 2026.

## Tujuan

Satu gerbang aplikasi **/cikoko-smart.php**, satu sumber tabel/backend MySQL, satu API Flutter, akses tiap akun berdasarkan identitas dan wewenang, dan Super Administrator dapat membaca/meninjau seluruh modul yang relevan. Semua perubahan di GitHub ini untuk **staging/UAT** sampai diverifikasi di cPanel.

## Matriks modul yang sudah dapat diverifikasi dari source repository

| Asal | Grup di sistem | Formulir / jenis | Data & API yang dipakai | Kemampuan |
|---|---|---|---|---|
| Pegawai (Tahap 16) | pegawai | Pengajuan Cuti Tahunan, Kinerja PPPK Bulanan, Kegiatan PJLP/PPSU | `unified_detailed_forms16`, `mobile-forms.php` | Simpan draf, kirim, perbaiki, tinjau |
| Kewilayahan (Tahap 17) | wilayah | RT/RW, LMK, Jumantik, FKDM, PPSU | `unified_field_reports17`, `mobile-forms.php` | Simpan draf, kirim, perbaiki, tinjau |
| Pusat Laporan (Tahap 15) | laporan | PPPK, PJLP, RT/RW, LMK, Jumantik, Cuti, FKDM, PPSU, Umum | `unified_report_intake`, `mobile-forms.php` | Ringkasan dikirim tanpa draf; pemilik dapat memperbaiki saat diminta; Super Admin meninjau |
| Pelayanan Surat (Tahap 9–10) | warga | Ahli Waris | `unified_citizen_requests`, lampiran privat Tahap 10, `/api/?resource=citizen_*` | Pengajuan, unggah syarat, verifikasi sesuai peran, progres |

**Jumlah jalur: 18**. Nama yang serupa pada Tahap 15 dan Tahap 16/17 **BUKAN formulir identik**. Tahap 15 adalah ringkasan tambahan; Tahap 16/17 lebih terstruktur. Tidak boleh mengganti rekaman resmi lama dengan ringkasan tanpa migrasi terverifikasi.

### Asal tabel lama yang teridentifikasi dalam source terdahulu

Tabel `ck_pppk_kinerja`, `laporan`, `pjlp_duty_session`, `pjlp_task_photos`, `cuti`, `cuti_detail`, `mobile_rt_rw_letters`, `mobile_reports`, `mobile_fkdm_reports`, `mobile_ppsu_reports`, `aw_applications`, serta `reports` adalah **referensi legacy**. Di tahap ini, tabel lama **tidak digabung, ditimpa, dihapus atau dipindahkan**. Dashboard Super Admin menampilkan hitungan apabila tabel tersedia tanpa membuka data warga ke publik.

**Batasan verifikasi ZIP:** Dua arsip yang diunggah pada percakapan ini (`PegawaiCikoko(3).zip` dan `websitecikoko(3).zip`) belum berhasil dibuka melalui lingkungan pemrosesan berkas saat perubahan kode ini dibuat. Matriks di atas didasarkan pada source yang telah dipetakan di repository SmartCikoko. **Karena itu belum sah menyebut seluruh kolom, template cetak, hak akses dan lampiran sudah cocok 100% dengan dua ZIP terbaru.**

## Struktur sinkronisasi

- Website: `/cikoko-smart.php` sebagai gerbang bagi akun aktif; `/aktivitas-terpadu.php` menampilkan progres dan notifikasi.
- Flutter: `FormsPage` memakai `GET /api/mobile-forms.php?action=catalog`. Formulir tiap grup dikirim ke `create`/`update`, dan Super Admin dapat memeriksa dengan `review`—semua memakai validator dan transaksi PHP lama.
- Pengajuan warga tetap memakai modul `citizen_*`, bukan menduplikasi formulir surat di tabel laporan umum.
- Backend daftar status: `portal_private/workflow_progress.php`. `workflow_feed.php` mencakup laporan umum Tahap 15, Pegawai Tahap 16, Wilayah Tahap 17, dan pengajuan warga Tahap 9–10.
- Akses: `mobile_form_allowed` mengatur **siapa yang boleh menginput**; `mobile_form_view_allowed` mengatur **siapa yang boleh melihat**. Super Admin berwenang melakukan peninjauan menurut fungsi `f16_review`, `w17_review`, `lr_review`, tetapi tidak membuat formulir PPPK/RT/RW atas nama pelapor lain.
- Pesan status di website dan APK berasal dari query database saat dimuat ulang. Belum ada push FCM/WhatsApp ketika aplikasi mati.

## Super Admin

Masuk dari `/super-admin-satu.php` atau `/cikoko-smart.php`. Super Admin melihat kategori dalam satu grid, dapat membuka modul asal untuk tindakan yang diizinkan, serta membaca perbandingan jumlah laporan legacy dan ringkasan baru. **Meninjau administratif tidak sama dengan menandatangani surat, menerbitkan SK, atau menggantikan keputusan pejabat yang berwenang.**

## Hak akses dan peran akun

| Akun | Penginputan | Pemeriksaan |
|---|---|---|
| Warga | Ahli Waris; laporan umum; observasi Jumantik bila diizinkan modul | Permohonan milik sendiri |
| PPPK | Kinerja PPPK; cuti jika memenuhi aturan; ringkasan terkait | Milik sendiri |
| PJLP | Kegiatan PJLP/PPSU, cuti bila diizinkan, ringkasan tugas | Milik sendiri |
| RT/RW | Laporan kegiatan RT/RW, ringkasan kegiatan | Milik sendiri |
| LMK | Laporan LMK, ringkasan LMK | Milik sendiri |
| Jumantik | Laporan agregat pemeriksaan jentik, ringkasan | Milik sendiri |
| FKDM | Laporan FKDM, ringkasan | Milik sendiri |
| PPSU | Laporan kegiatan PPSU, ringkasan | Milik sendiri |
| PTSP / Lurah | Pemeriksaan pelayanan Ahli Waris sesuai tahapan dan izin | Antrean yang diizinkan |
| Super Admin | Laporan administratif yang mengatasnamakan dirinya sendiri | Semua kategori yang telah dipetakan, tetap mengikuti audit |

## Logo yang diberikan pengguna

Logo Cikoko dalam percakapan ini adalah acuan identitas yang benar. **Logo gambar asli belum dipublikasikan sebagai aset biner repository** pada perubahan ini karena sistem pembacaan berkas unggahan tidak berhasil. Logo sementara yang berada di proyek web/Flutter tidak boleh diklaim sama dengan logo pengguna. Penggantian harus memakai file gambar asli, tanpa memodifikasi kata, figur, struktur ataupun warna yang ada, dan perlu diuji pada ukuran ikon kecil.

## Pembuktian kesesuaian sebelum rilis produksi

Untuk setiap form dalam kedua ZIP terbaru, buat matriks *file sumber → kolom masukan → wajib/kondisional → validasi → attachment → hasil cetak → akses peran → tabel/data → status → notifikasi*, lalu lakukan tes otomatis dan perbandingan manual berpasangan pada satu set data dummy. Perhatikan khusus:

1. Foto dan GPS laporan PPPK, PJLP, FKDM, PPSU lama **belum dicopy**; jangan menganggap ringkasan data teks menggantikan berkas asli.
2. Surat RT/RW lama `mobile_rt_rw_letters` berbeda dari formulir *laporan kegiatan* RT/RW. Jalur penerbitan/persetujuan surat harus dipetakan tersendiri.
3. Format dokumen Ahli Waris, lampiran 1a, 1c, 2a, 3a dan kondisi khusus perlu persetujuan SOP.
4. Template hasil cetak dan tanda tangan pejabat tidak boleh dibangun dari asumsi.
5. Kompatibilitas login semua peran, MFA, lampiran PDF/foto privat, rollback, pencabutan token, dan status lintas web–APK harus dites di server staging.

## Deploy cPanel (aman)

1. Backup **kedua database**, `public_html`, `portal_private`, dan semua unggahan dokumen; jangan pernah unggah berkas konfigurasi ke repository publik.
2. Unduh paket V4 staging terbaru dari GitHub Actions, ekstrak ke folder home hosting, bukan menimpa webroot aktif. Paket membuat `public_html_baru`.
3. Tambahkan helper privat **baru** `portal_private/cikoko_smart_catalog.php` dari ZIP ke direktori privat hosting. Sesuaikan helper `mobile_forms_api.php` dan `workflow_feed.php` hanya setelah backup serta membandingkan perubahan; jangan menimpa `bootstrap.php`, kredensial atau skema database lama.
4. Atur subdomain staging ke `public_html_baru` dan uji `/cikoko-smart.php`, `/api/mobile-forms.php?action=catalog`, daftar laporan, input draf/kirim/perbaikan, seluruh review Super Admin dengan akun dummy, dan audit/tidak ada lintas akun.
5. Tes APK ARM64 terbaru **setelah** backend baru berfungsi; tanpa API yang sesuai, aplikasi tidak bisa dijamin sinkron.
6. Pindah ke hosting produksi hanya setelah checklist tes hijau dan ada rencana rollback. **Tidak ada perubahan database otomatis** pada paket ini.

## Status kelengkapan

- [x] Gerbang Cikoko Smart untuk kategori laporan yang sudah ada di repository.
- [x] Satu API Flutter untuk Tahap 15/16/17 dan peran Super Admin, tanpa menggandakan tabel.
- [x] Satu feed laporan Tahap 15/16/17 dan layanan warga Tahap 9–10.
- [ ] Uji kesetaraan file dan template dari kedua ZIP unggahan **terbaru**.
- [ ] Lampiran dan cetakan lama per jenis laporan/surat, byte-untuk-byte atau fungsional setara.
- [ ] Logo asli dalam build web dan APK.
- [ ] UAT instalasi dan transaksi lintas perangkat/akun pada server nyata.
- [ ] Push notification saat aplikasi ditutup, bila memang diwajibkan.

**Kesimpulan:** fondasi penyatuan nyata sudah dibuat, tetapi angka kesetaraan "100%" belum dibuktikan; rilis produksi belum boleh diklaim selesai.

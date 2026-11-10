# SmartCikoko — Alur Pengajuan Surat, Laporan dan Notifikasi Web–Flutter

## Ruang lingkup implementasi

Seluruh halaman website dan aplikasi Flutter menggunakan tabel MySQL SmartCikoko yang sama melalui backend, bukan data tiruan atau penyimpanan lokal ganda.

**Akun pelapor**
- Warga: pengajuan tahap 9 (pencatatan Ahli Waris), pengunggahan lampiran tahap 10.
- Pegawai: formulir Tahap 16 (PPPK, PJLP, cuti) sesuai sumber akun dan izin.
- Wilayah: Tahap 17 (RT/RW, LMK, Jumantik, FKDM, PPSU), sesuai sumber dan peran.
- Petugas PTSP/Lurah: tinjau/beri keputusan pada modul pengajuan Tahap 9 dengan wewenang server.
- Super Admin: menggunakan halaman dashboard dan modul pemeriksaan yang sudah ada. Pusat aktivitas personal tidak secara otomatis menampilkan data orang lain.

Perubahan ini **tidak** mengubah izin, otorisasi, MFA, database atau status resmi di arsip lama, serta tidak mengganti proses tanda tangan sah.

## Bukti acuan arsip lama

Arsip sumber yang diperiksa dalam pengembangan: `websitecikoko(2).zip`, `PegawaiCikoko(2).zip`, dan arsip SQL lama. Nama persyaratan berasal dari `aplikasi/includes/waris_definisi.php` pada arsip pegawai. Formulir Ahli Waris juga pernah tersedia dalam varian `ahli-waris/index.php` pada website lama; kedua varian tidak sepenuhnya sama. Implementasi ini mempertahankan **14 kategori lampiran** dari daftar `waris_berkas()` pegawai, mempertahankan kode lama `identitas/keluarga/kematian/lainnya`, dan membedakan syarat wajib/kondisional. Petugas wajib memastikan daftar final sesuai SOP pelayanan yang berlaku sebelum aktivasi warga nyata.

File lampiran dari arsip lama, berkas identitas, foto, kata sandi dan data pegawai **tidak disalin** ke GitHub.

## API dan sinkronisasi

- `GET /api/?resource=workflow_feed`: daftar laporan dan pengajuan milik akun serta riwayat notifikasi proses berdasarkan tabel status/history; autentikasi Bearer; akses berdasarkan identitas, belum ada notifikasi lintas akun.
- `GET /api/?resource=citizen_list` dan `citizen_detail`: menyertakan `progress` untuk warga/pemeriksa berwenang.
- `GET /api/mobile-forms.php?action=list&group=...&kind=...` dan `detail`: menyertakan `progress` untuk laporan milik akun.
- `GET /api/?resource=citizen_requirements&id=...`: jenis, wajib/kondisional, dan metadata lampiran yang sudah tersimpan.
- `POST /api/citizen-upload.php`: multipart `request_id`, `doc_type`, `confirmed=1`, `document`; memakai autentikasi Bearer dan **fungsi penyimpanan privat `t10_upload()` yang sama dengan website**. Batas yang berlaku: PDF/JPG/PNG maksimal 5 MB, hanya pada status draf/revisi, diperiksa lagi di server.

Gunakan `/aktivitas-terpadu.php` pada website setelah login, atau ikon lonceng di aplikasi Flutter. Tarik untuk memuat ulang notifikasi di aplikasi. Riwayat diambil dari tabel perubahan status dan unggahan asli; **belum ada push OS/FCM, WA, SMS, email, atau pemberitahuan ketika aplikasi tertutup**. Jangan mencantumkan fitur tersebut sebagai aktif.

## Makna progres 0–100%

Progres adalah *tonggak administratif*, bukan estimasi waktu pelayanan.

| Kategori | Tahap | Progres |
|---|---|---:|
| Pengajuan warga | Draf | 0% |
| Pengajuan warga | Diajukan | 25% |
| Pengajuan warga | Ditinjau | 50% |
| Pengajuan warga | Perlu perbaikan | 40% (kembali memerlukan tindakan) |
| Pengajuan warga | Diverifikasi | 75% |
| Pengajuan warga | Disetujui administratif | 90%; bukan surat diterbitkan |
| Pengajuan warga | Ditolak | 90%; hasil penolakan jelas, tidak 100% sukses |
| Pengajuan warga | Selesai dalam sistem resmi (apabila status benar-benar ada) | 100% |
| PPPK/PJLP/Cuti/Wilayah | Draf | 0% |
| PPPK/PJLP/Cuti/Wilayah | Diajukan | 25% |
| PPPK/PJLP/Cuti/Wilayah | Ditinjau | 60% |
| PPPK/PJLP/Cuti/Wilayah | Perlu perbaikan | 45% |
| PPPK/PJLP/Cuti/Wilayah | Ditutup administratif | 100% penutupan administrasi, **bukan pengesahan surat atau cuti** |

Jangan memperlakukan 100% laporan administratif sebagai persetujuan cuti yang berlaku, tanda tangan elektronik, atau surat dengan nomor registrasi resmi.

## Instalasi cPanel tanpa SQL manual

**Sebelum pemasangan:** backup `public_html`, `portal_private`, tabel database, unggahan dan pengaturan MFA.

1. Unduh paket V4 terbaru melalui GitHub Actions `PUBLIC_HTML BARU — SmartCikoko Unified`.
2. Ekstrak di `/home/datd1747/`. Paket membuat `public_html_baru/` terpisah serta menambahkan `portal_private/workflow_progress.php`, `workflow_feed.php`, dan `mobile_forms_api.php`. File pengaturan database dan akun tidak diganti.
3. Karena `citizen_followup.php` lama sudah ada dan dapat berbeda dari source GitHub, berkas versi baru ditempatkan di **`PRIVATE-PATCH-REVIEW/citizen_followup.php`**, bukan langsung menimpa file yang aktif. Bandingkan dengan berkas live; backup dulu versi lama di luar `public_html`. **Untuk mengaktifkan 14 kode lampiran lama, pengelola harus meninjau dan memasang patch privat ini secara sadar.** Tidak ada skrip yang otomatis mengubah tabel maupun berkas privat lama.
4. Uji pada domain staging dengan Document Root `public_html_baru`: login warga uji, buat draf, unggah PDF dummy, cek daftar lampiran, ajukan, pemeriksaan PTSP/Lurah, riwayat notifikasi dan progres. Uji PPPK, PJLP dan setiap role yang memiliki hak akses.
5. Setelah berhasil, lakukan pergantian webroot sesuai panduan V4 lama dan jalankan UAT Android. Jika tidak sesuai, rollback ke backup.

**Perhatian:** lampiran lama yang sudah ada di hosting tidak dimigrasi secara otomatis. Jangan memindahkan foto/berkas identitas riil ke repository publik.

## Perilaku aplikasi

Tombol kembali di root aplikasi menampilkan dialog **Keluar dari SmartCikoko?**, memberi opsi **Tetap di Aplikasi** atau **Keluar Aplikasi**. Proses unggah yang sedang berjalan tetap harus diselesaikan sebelum keluar; dialog tidak bisa menjamin seluruh background transfer.

## Belum selesai / membutuhkan UAT

1. Penginputan **semua jenis surat lainnya** di website lama (misalnya Domisili, pengantar RT/RW lain dan surat pertanahan) belum dipetakan satu per satu ke data/form resmi; jangan menampilkan formulir kosong yang pura-pura menyimpan ke backend lama.
2. Lampiran foto/tracking/report PPPK dan PJLP dari sistem lama berbeda dari formulir Tahap 16–17 yang kini terstruktur. Sinkronisasi ulang *seluruh format lampiran laporan* memerlukan pemetaan dan uji per modul agar dokumen lama tidak hilang.
3. Belum ada push notification native saat aplikasi tertutup. Riwayat kini tampil saat halaman aktif dan dimuat ulang.
4. Alur penerbitan surat resmi 100% harus dipastikan melalui backend asli, termasuk nomor surat, penandatanganan dan kewenangan; status `disetujui` Tahap 9 adalah tahap administratif internal.
5. APK baru memerlukan CI, instalasi perangkat Android UAT, pengujian upload dan izin berkas, pengujian cPanel serta iOS macOS terpisah.

## Pengujian keamanan minimum

- Pemohon A tidak bisa membaca atau mengunggah untuk ID milik pemohon B (404/403).
- Akun bukan warga tidak bisa menggunakan upload warga.
- PDF palsu, berkas di luar mime yang diizinkan, file lebih 5MB dan status proses berjalan ditolak.
- Nama file asli dan path privat tidak pernah dikirim sebagai path disk publik.
- Penolakan dan revisi tidak tampil sebagai `100% berhasil`.
- Login/MFA dan audit backend lama tetap berfungsi.

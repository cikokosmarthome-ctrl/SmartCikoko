# Tahap 9 — Pengajuan Warga dan Persetujuan Berjenjang

**SmartCikoko | Kelurahan Cikoko, Kecamatan Pancoran**

Tahap 9 membentuk alur pengajuan baru untuk akun masyarakat, pemeriksaan PTSP, keputusan Lurah, dan pemantauan Super Admin dalam website maupun API Android. **Dua database lama tetap terpisah, dan 28 permohonan ahli waris lama tidak dipindahkan atau ditimpa.**

## Fitur dan hak akses

| Akun | Kewenangan |
|---|---|
| Warga dari database SmartApp | Membuat draf, memperbarui keterangan pada draf atau revisi, mengajukan, melihat pengajuan dan riwayatnya sendiri |
| PTSP dari database SmartApp | Melihat antrean pengajuan baru, memulai pemeriksaan, meminta perbaikan, menandai verifikasi administratif |
| Lurah dari database SmartApp | Menindaklanjuti permohonan terverifikasi: menyetujui, menolak dengan alasan, atau mengembalikan untuk perbaikan |
| Super Administrator terdaftar | Memantau seluruh pengajuan, **tanpa mengambil alih keputusan PTSP/Lurah** |
| Akun lain | Tidak dapat membuka modul |

**Alur:** \`Draf → Diajukan → Dalam Pemeriksaan → Diverifikasi → Disetujui\`. Bila ada kekurangan: \`Dalam Pemeriksaan/Diverifikasi → Perlu Perbaikan → Diajukan\`. Penolakan hanya dapat dilakukan Lurah setelah verifikasi PTSP. Status “disetujui” pada tahap ini menandakan keputusan alur kerja internal, **bukan surat resmi yang telah ditandatangani secara elektronik**.

Permohonan memuat nama almarhum/almarhumah, hubungan keluarga, dan keterangan singkat; nama pemohon diambil dari akun yang sedang login. **Tidak meminta NIK atau upload berkas** pada Tahap 9. Dokumen baru Tahap 8 belum otomatis dikaitkan dengan pengajuan tahap 9 karena tabel sumber dan persyaratan layanan perlu dipetakan dan divalidasi lebih dahulu.

## Instalasi singkat di cPanel Rumahweb

1. Pastikan Tahap 6–8 telah dipasang dan situs serta API berhasil digunakan.
2. Backup file \`public_html/super-admin.php\` dan \`public_html/api/index.php\`, serta backup database \`datd1747_smartapp\`. Jangan unggah backup ke GitHub.
3. Download \`paket/SmartCikoko-TAHAP9-PENGAJUAN.zip\` dari GitHub SmartCikoko.
4. cPanel → File Manager → buka **\`/home/datd1747/\`** → Upload ZIP → Extract. **Jangan ekstrak dari dalam \`public_html\`**. Konfirmasi penggantian dua file web yang telah dicadangkan.
5. Login menggunakan akun Super Admin, lalu buka **\`https://datawebs.my.id/instal-pengajuan.php\`**, klik **Pasang Tahap 9**.
6. Buka **\`https://datawebs.my.id/pengajuan-warga.php\`**. Super Admin melihat daftar pengajuan baru; warga yang login melihat hanya permohonannya sendiri.
7. Setelah pengujian berhasil, **hapus file \`public_html/instal-pengajuan.php\` dan ZIP dari hosting**.

Tidak perlu membuat ulang database Pegawai atau mengimpor SQL lama. Installer menambah dua tabel ke SmartApp: \`unified_citizen_requests\` dan \`unified_citizen_request_history\`. Tidak ada data lama yang dimutasi.

## Integrasi Android

Kode Flutter sudah menyediakan menu **Pengajuan Pelayanan** bagi akun warga, PTSP, Lurah, dan Super Admin yang telah tervalidasi. Endpoint API baru:
- \`GET /api/?resource=citizen_list&page=1\` (hanya daftar milik sendiri kecuali petugas berwenang)
- \`GET /api/?resource=citizen_detail&id=...\`
- \`POST /api/?resource=citizen_create\`
- \`POST /api/?resource=citizen_edit\`
- \`POST /api/?resource=citizen_transition\`

Semua endpoint selain login memerlukan token Bearer dan memvalidasi status/role akun terhadap sumber asli serta kontrol akses Tahap 6. Membuat draf menggunakan kunci permintaan idempoten untuk membantu mencegah duplikasi saat jaringan bermasalah. **Android belum otomatis terpasang di ponsel**; setelah pengujian, harus dibuat APK/AAB rilis baru.

## Verifikasi sebelum membuka pelayanan kepada masyarakat

1. Gunakan **akun uji** berperan warga, PTSP dan Lurah. Jangan memakai nama/data almarhum nyata dalam uji coba.
2. Warga A membuat draf dan hanya melihat permohonannya. Warga B **tidak boleh** mengakses ID permohonan warga A melalui URL ataupun API.
3. Warga mengajukan; PTSP memulai pemeriksaan dan menandai verifikasi. PTSP tidak boleh menyetujui keputusan final.
4. Lurah dapat menyetujui/menolak hanya setelah status “diverifikasi”. Admin lain termasuk Super Admin tidak boleh menyetujui dengan otoritas role PTSP/Lurah.
5. Revisi dapat diminta dengan catatan; warga memperbaiki draf dan mengajukan kembali.
6. Percobaan membuka pengajuan tanpa login harus dialihkan ke halaman login; akun yang tidak berhak harus menerima penolakan.
7. Riwayat tindakan memiliki informasi waktu, status lama/baru, serta identitas aktor internal.
8. Pengajuan lama di \`aw_applications\` dan statistiknya **tidak boleh berubah** setelah instalasi Tahap 9.
9. Sesuaikan prosedur keputusan, dasar hukum, retensi, MFA, keamanan data pribadi dan kontrol akses sebelum operasional nyata.
10. Uji Flutter pada Android sebelum rilis, termasuk koneksi lambat, akses token berakhir, dan perubahan status dari perangkat berbeda.

## Batasan penting

- Penyimpanan berkas permohonan baru belum terhubung dengan penyimpanan Tahap 8.
- Belum tersedia penerbitan surat, tanda tangan elektronik, notifikasi otomatis, atau layanan publik anonim.
- Daftar pengajuan baru di halaman Tahap 9 masih terpisah dari arsip lama; penggabungan historis membutuhkan pemetaan dan pengujian data.
- GitHub tidak secara otomatis mengubah hosting Rumahweb. Website harus diperbarui menggunakan ZIP dan installer di atas.

**Setelah Tahap 9 teruji**, prioritas berikutnya adalah integrasi lampiran aman per pengajuan, otorisasi dokumen, notifikasi, dan mekanisme penerbitan dokumen resmi sesuai kewenangan.

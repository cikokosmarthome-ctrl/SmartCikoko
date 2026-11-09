# Tahap 8 — Verifikasi Dokumen Pelayanan dan Laporan Administrasi

Paket pembaruan ini menambahkan dua ruang kerja **Super Administrator** ke SmartCikoko: (1) checklist peninjauan dokumen dan penyimpanan berkas pendukung ahli waris yang bersifat privat; (2) rekap agregat bulanan dari dua database dan ekspor CSV nonpersonal.

**Batas fungsi:** checklist verifikasi merupakan pencatatan administratif, **bukan validasi keaslian dokumen atau keputusan hukum**. Persyaratan pelayanan yang sebenarnya tetap harus mengikuti ketentuan resmi yang berlaku. Status permohonan asli tidak diubah oleh checklist. Status layanan yang berwenang diubah lewat **Pusat Operasional** (Tahap 7).

## Instalasi singkat (tanpa SQL manual)

1. Buat **backup** database \`datd1747_smartapp\`, \`/home/datd1747/public_html/super-admin.php\`, dan \`/home/datd1747/public_html/operasional.php\`. Simpan backup di lokasi terlindungi.
2. Unduh \`paket/SmartCikoko-TAHAP8-PELAYANAN.zip\` dari repository GitHub SmartCikoko.
3. Di cPanel Rumahweb → File Manager → buka **\`/home/datd1747/\`**, upload ZIP, lalu **Extract dari folder tersebut**. Jangan ekstrak dari dalam \`public_html\`. Paket menambah sejumlah file dan memperbarui navigasi dua halaman.
4. Login menggunakan akun **Super Administrator** yang sudah teraktivasi.
5. Buka **\`https://datawebs.my.id/instal-layanan.php\`** kemudian klik **Pasang Tahap 8**.
6. Setelah pesan sukses, buka **\`https://datawebs.my.id/verifikasi-layanan.php\`**.
7. Periksa tab **Verifikasi Dokumen Ahli Waris** dan **Laporan Administrasi**. Lakukan pengujian menggunakan permohonan/berkas yang **secara khusus dibuat untuk uji coba**.
8. Setelah berhasil, **hapus \`public_html/instal-layanan.php\` dan ZIP dari hosting**. Jangan hapus \`portal_private/document_workflow.php\`, tabel baru, atau folder \`portal_private/private_documents\`.

**Tidak perlu mengimpor ulang SQL, menyalin data antar-database, atau membuka phpMyAdmin.** Installer membuat tiga tabel baru dalam database SmartApp tanpa mengubah tabel sumber.

## Fitur Tahap 8

### Formulir pelayanan dan verifikasi
- Cari nomor permohonan atau nama pemohon dari tabel \`aw_applications\`.
- Checklist peninjauan atas dokumen identitas, hubungan keluarga, kematian, pernyataan/persetujuan, dan dokumen pendukung. **Daftar ini hanya pengelompokan berkas, bukan daftar persyaratan hukum final.**
- Pilih \`Belum Diperiksa\`, \`Telah Diperiksa\`, atau \`Perlu Kelengkapan/Perbaikan\`; tulis catatan maksimal 500 karakter.
- Upload satu berkas PDF/JPG/PNG maksimum 5 MB per kiriman. Maksimum 30 berkas tambahan per permohonan.
- Berkas disimpan di \`/home/datd1747/portal_private/private_documents/\`, di luar webroot, dengan nama acak dan izin folder/file terbatas.
- Download **hanya** melalui \`/unduh-berkas.php?id=...\` yang memeriksa sesi serta identitas Super Admin.
- Setiap pemeriksaan/unggahan memiliki riwayat audit.

### Rekap administrasi
- Pilih bulan \`YYYY-MM\` dan tampilkan angka agregat: pengajuan ahli waris berdasarkan tanggal masuk dan status saat ini, laporan kinerja PPPK berdasarkan periode, sesi tugas PJLP berdasarkan tanggal, dan jumlah aktivitas verifikasi dokumen.
- Ekspor CSV agregat \`rekap-smartcikoko-YYYY-MM.csv\` tanpa kolom nama, NIK, username, token, atau identitas pemohon.
- Status akhir permohonan saat rekap dibuka dapat berubah dari bulan sebelumnya. Laporan ini **belum merupakan snapshot historis yang ditutup/dikunci**.

## Batas keamanan dan pengujian sebelum data nyata

- Akses hanya Super Admin. Uji browser yang belum login (harus diarahkan ke login) dan akun non-Super Admin (HTTP 403).
- Folder private_documents **tidak boleh** ditempatkan di \`public_html\`, symlink, atau lokasi yang dapat diakses melalui URL publik.
- Sebelum menyimpan dokumen warga sungguhan, perlu ditetapkan dasar pemrosesan, retensi/pemusnahan dokumen, pencadangan terenkripsi, pengendalian akses per petugas, MFA, dan pemeriksaan malware pada upload. Pemeriksaan MIME dan struktur PDF/JPG/PNG **tidak sama dengan pemindaian antivirus**.
- Pengujian unggah/unduh sebaiknya hanya memakai dokumen dummy yang tidak berisi data pribadi.
- Pastikan \`file_uploads\` aktif, \`post_max_size\` dan \`upload_max_filesize\` pada PHP cPanel cukup untuk unggahan maksimal 5 MB (misalnya setidaknya 6 MB untuk \`upload_max_filesize\` dan 8 MB untuk \`post_max_size\`).
- Penghapusan atau penggantian dokumen tidak tersedia pada tahap ini, untuk mencegah hilangnya barang bukti pelayanan tanpa prosedur pengarsipan.
- Tingkat keamanan dan kesiapan produksi belum dikonfirmasi hanya melalui pengujian sintaks.

## Berkas paket

\`\`\`text
/home/datd1747/
├── portal_private/
│   └── document_workflow.php             (baru)
└── public_html/
    ├── verifikasi-layanan.php             (baru)
    ├── unduh-berkas.php                  (baru)
    ├── instal-layanan.php                (hapus setelah sukses)
    ├── super-admin.php                   (link navigasi)
    ├── operasional.php                   (link navigasi)
    └── assets/
        ├── verifikasi-layanan.css
        └── instal-layanan.css
\`\`\`

Installer akan membuat:
- \`unified_document_checks\`
- \`unified_case_files\`
- \`unified_document_events\`

## Prosedur pemulihan

Jika pemasangan terganggu, **jangan menghapus** tabel sumber atau berkas privat. Pulihkan file \`super-admin.php\` dan \`operasional.php\` dari backup cPanel; file Tahap 8 lainnya dapat dibiarkan sampai penyebabnya diperiksa. Database hanya ditambah tabel baru, tidak menghapus data lama.

**Status:** kode dan installer di GitHub; aplikasi Rumahweb belum diperbarui otomatis. Pemasangan dan pengujian data nyata tetap memerlukan tindakan pemilik hosting.

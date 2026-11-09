# Tahap 11 — Keamanan Arsip Digital dan Draf Administrasi

**SmartCikoko | Kelurahan Cikoko — Instalasi Rumahweb cPanel, tanpa SQL manual.**

Tahap 11 merupakan penguatan arsip Tahap 8 (lampiran pelayanan lama) dan Tahap 10 (lampiran permohonan baru) serta persiapan draf administrasi. **Tidak menerbitkan surat resmi, tidak membuat tanda tangan, tidak menghapus arsip, dan tidak memindahkan data lama.**

## Instalasi satu ZIP

1. **Backup** database SmartApp dan empat berkas yang akan diperbarui: portal_private/document_workflow.php, portal_private/citizen_followup.php, public_html/super-admin.php. Pastikan database Pegawai juga memiliki backup rutin. Simpan backup hanya di tempat privat.
2. Download `paket/SmartCikoko-TAHAP11-ARSIP.zip` dari GitHub SmartCikoko.
3. Buka cPanel → File Manager → **/home/datd1747/** → Upload ZIP → **Extract** pada direktori itu, bukan di dalam public_html.
4. Login akun Super Administrator. Buka **https://datawebs.my.id/instal-arsip.php** lalu klik **Pasang Tahap 11**.
5. Setelah sukses, buka **https://datawebs.my.id/arsip-keamanan.php**. Modul draf internal: **https://datawebs.my.id/draf-administrasi.php**.
6. Uji dengan data dummy: pilih berkas dan klik **Periksa Integritas SHA-256**; lihat riwayat audit. Uji bahwa akun non-Super Admin tertolak.
7. Setelah berhasil, **hapus public_html/instal-arsip.php dan ZIP di hosting**. Jangan menghapus registry, tabel audit atau folder privat.

**Prasyarat:** Tahap 8 dan Tahap 10 telah dipasang, termasuk unified_case_files dan unified_citizen_files. Installer membuat dua tabel tambahan **unified_archive_registry** dan **unified_archive_events**, tanpa mengubah file asli.

## Fungsi yang tersedia

- Ringkasan jumlah arsip dua kelompok, dipisah berdasarkan sumbernya.
- Pemeriksaan ukuran dan SHA-256 setiap berkas terhadap nilai yang tersimpan saat unggah. Status hasil: sesuai, berbeda, hilang, tidak aman.
- Pemeriksaan integritas sebelum endpoint unduh Tahap 8 maupun Tahap 10 mengirim berkas; isi yang tidak cocok **ditolak**. Kinerja unduh dipengaruhi proses hashing, paling besar 5 MB per berkas baru.
- Klasifikasi operasional internal: belum diklasifikasi, sudah ditinjau, atau perlu tindak lanjut. Catatan terbatas hingga 300 karakter.
- Penanda “Pertahankan berkas” tanpa penghapusan otomatis. **Ini bukan jadwal retensi resmi ataupun mekanisme legal hold yang sudah disahkan.**
- Riwayat pemeriksaan dan perubahan metadata di database SmartApp.
- Halaman draf administrasi internal berdasarkan permohonan Tahap 9 yang telah berada pada tahap “Disetujui”, dengan label yang tidak dapat dihilangkan dari lembar cetakan: **DRAF · TIDAK SAH**. Tidak ada nomor surat, tanda tangan pejabat, stempel, atau tanda tangan elektronik.

## Checklist keamanan

- Uji berkas valid (hasil sesuai), berkas rusak/berubah (hasil berbeda) dan berkas hilang menggunakan fixture dummy. Jangan sengaja mengubah dokumen pribadi nyata.
- Coba akses /arsip-keamanan.php dan /draf-administrasi.php dengan akun bukan Super Admin: harus ditolak 403.
- Verifikasi bahwa file dalam portal_private tidak dapat diakses sebagai URL publik.
- Pastikan tidak ada jalur mengunduh file dengan hash berbeda melalui /unduh-berkas.php atau /unduh-pengajuan.php.
- Uji ulang Tahap 8, 9 dan 10; data lama harus tetap ada.
- Hapus installer setelah selesai.
- Tetapkan prosedur retensi/pemusnahan resmi, enkripsi backup, serta kewenangan penanganan arsip sesuai kebijakan instansi.

## Batasan — penting sebelum penggunaan produksi

**Pemeriksaan SHA-256 bukan antivirus, bukan bukti keaslian dokumen, dan bukan tanda tangan digital.** Berkas berbahaya yang telah disimpan bisa mempunyai hash yang cocok. Untuk operasional dokumen warga nyata, tetap memerlukan pemindaian malware pada saat unggah, MFA, kebijakan pemrosesan dan retensi data pribadi, backup terenkripsi, uji pemulihan, serta pengujian keamanan independen.

**Draf internal tidak boleh dikeluarkan sebagai surat resmi.** Penerbitan surat pemerintah membutuhkan format naskah dinas yang ditetapkan instansi, nomor surat, pejabat yang berwenang, tata naskah serta tanda tangan elektronik yang sah. Integrasi tersebut belum tersedia di Tahap 11.

**Status rilis:** Kode dan ZIP di GitHub bukan deployment otomatis. Pemeriksaan PHP dan unit test belum menguji MySQL dan izin file di cPanel. Android tidak mendapatkan layar arsip ini karena arsip tetap dibatasi di pusat Super Admin website.

## Struktur paket (tanpa konfigurasi privat)

```text
/home/datd1747/
├── portal_private/
│   ├── archive_security.php        (baru)
│   ├── document_workflow.php       (pengetatan unduh Tahap 8)
│   └── citizen_followup.php        (pengetatan unduh Tahap 10)
└── public_html/
    ├── super-admin.php             (menu arsip)
    ├── arsip-keamanan.php          (baru)
    ├── draf-administrasi.php       (baru, bukan surat resmi)
    ├── instal-arsip.php            (hapus setelah berhasil)
    └── assets/
        ├── arsip-keamanan.css
        ├── draf-administrasi.css
        └── instal-arsip.css
```

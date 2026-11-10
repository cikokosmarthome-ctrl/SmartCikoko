# SmartCikoko — Portal Terpadu Kelurahan Cikoko

**Satu portal publik dan ruang kerja sesuai kewenangan** untuk `datawebs.my.id`, menggunakan dua database MySQL terpisah dan API bersama untuk website dan aplikasi Android.

## Cakupan pengembangan

- Beranda publik, berita, agenda, serta identitas visual biru-putih bergaya institusi pemerintahan.
- Login lintas sumber: `cikoko_pegawai.users`, `cikoko_pegawai.ck_pancoran_users`, dan akun website Cikoko lama yang diimpor dari `portal_users.json`/`admin.json` ke `datd1747_smartapp.unified_portal_users`.
- Saat username + password cocok di lebih dari satu sumber, akun dipilih secara eksplisit.
- Ruang kerja berbasis peran: administrator, ASN, PPPK, PJLP/PPSU, petugas kelurahan, dan masyarakat.
- Data kepegawaian, laporan PPPK/PJLP, dan pelayanan ahli waris dengan kontrol akses pada tingkat server.
- API autentikasi token, profil, dashboard, berita, agenda, ahli waris, pegawai, dan ringkasan laporan.
- Aplikasi Android berbasis Flutter sebagai klien API; tidak menyimpan kata sandi database.

## Struktur

```text
public_html/           Website PHP, UI responsif, endpoint API dan aset publik
portal_private/        Koneksi database, otentikasi, layanan data dan skema SQL
android_flutter/       Sumber aplikasi Flutter (Android)
tools/                 Importer akun JSON lama (CLI saja)
docs/                  Panduan instalasi dan verifikasi
```

## Instalasi

Baca [`docs/DEPLOY-RUMAHWEB.md`](docs/DEPLOY-RUMAHWEB.md). **Jangan upload dump SQL, file JSON akun, password, atau berkas kepegawaian/warga ke repository GitHub publik.**

Status: website mockup V4 sudah dikemas dan lolos pemeriksaan otomatis di repository, tetapi **belum dapat dinyatakan terpasang dan teruji di hosting pengguna**. Android Flutter debug UAT sudah berhasil dikompilasi dan dipublikasikan pada [GitHub prerelease SmartCikoko Mobile UAT](https://github.com/cikokosmarthome-ctrl/SmartCikoko/releases/tag/smartcikoko-mobile-uat-v1). Job iOS dipisahkan ke workflow manual untuk mencegah antrean macOS menyebabkan kegagalan palsu pada jalur Android; **belum tersedia IPA iOS bertanda tangan**. APK UAT bukan rilis Play Store. Rilis produksi tetap memerlukan signing, uji perangkat dan endpoint server, kebijakan privasi, serta peninjauan toko aplikasi.
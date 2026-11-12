# SmartCikoko — Flutter Android dan iOS

Proyek aplikasi native Flutter yang mengikuti mockup portal digital Kelurahan Cikoko, Kecamatan Pancoran, Jakarta Selatan.

## Sudah diimplementasikan

- Material 3 dengan warna biru-putih, aksen emas, bentuk kartu compact, serta ilustrasi kantor pelayanan
- Beranda publik, enam akses cepat, berita dan agenda dari API SmartCikoko
- Bottom navigation: **Beranda · Layanan · Riwayat · Akun**
- Login multi-akun dan Google Authenticator MFA melalui API yang sudah ada
- Penyimpanan token dalam secure storage Android/iOS dan logout yang mencabut token server
- Pengajuan warga/Ahli Waris memakai modul `citizen_requests.dart` yang sudah ada
- Formulir dinamis PPPK, PJLP, cuti, RT/RW, LMK, Jumantik, FKDM, PPSU melalui **`/api/mobile-forms.php`**
- Simpan draf, ajukan, perbaiki, baca status dan riwayat akun melalui aturan server Tahap 16–17
- Monitoring ringkas Super Admin melalui API; keputusan/konfigurasi administratif tetap melalui website resmi

## Struktur proyek

```
android_flutter/
  lib/
    main.dart
    citizen_requests.dart
    src/api_client.dart
    src/design.dart
    src/login_page.dart
    src/forms_page.dart
    src/portal_app.dart
  assets/cikoko-office-illustration.svg
  test/app_test.dart
  pubspec.yaml
```

Repo awal berisi source Flutter tanpa folder native Android/iOS. GitHub Actions menjalankan `flutter create --project-name smartcikoko --platforms android,ios .` untuk melengkapi kedua platform, kemudian mengemas source yang bisa dibuka di Android Studio / Xcode.

## Jalankan pengembangan

1. Instal Flutter SDK stable, Android Studio dan Xcode untuk iOS di macOS.
2. Ekstrak paket Flutter dan buka folder proyek.
3. Jalankan:
   ```bash
   flutter pub get
   flutter test
   flutter analyze
   flutter run
   ```
4. Untuk Android: `flutter build apk --debug` untuk internal UAT. Paket rilis `aab` perlu keystore dan konfigurasi Play Store.
5. Untuk iOS: `flutter build ios --no-codesign` hanya memeriksa kompilasi di macOS; untuk IPA/TestFlight perlu Apple Developer Team, bundle ID, dan tanda tangan yang valid.

## API dan data

Default backend: `https://datawebs.my.id/api/` serta `https://datawebs.my.id/api/mobile-forms.php`. Saat pertama memasang, deploy dulu **website/API PHP** dan pastikan endpoint dengan login uji dapat mengakses schema. Tanpa server, formulir tidak dapat menyimpan data.

Semua akses data dan keputusan otorisasi dilakukan **di server**. Pengujian jangan memakai identitas atau data warga asli.

## Batasan

- Foto gedung berupa **ilustrasi**, bukan klaim foto kantor sebenarnya. Logo KC adalah identitas sementara; ganti dengan lambang resmi berizin sebelum publikasi.
- Google Sign-In, GPS, pengambilan foto kamera langsung, penyusunan surat resmi, dan persetujuan cuti **tidak diklaim aktif**. FCM sudah ditambahkan sebagai integrasi bersyarat, tetapi pengiriman push ke HP belum aktif tanpa konfigurasi Firebase, Cron server, dan uji perangkat.
- Source Android/iOS sama, namun kompatibilitas perangkat, hasil Apple build, keystore Android, store publishing, dan akurasi pixel-per-pixel belum terbukti 100% sebelum UAT.
- Aplikasi Flutter tidak mengganti PHP/MySQL; tetap memakai backend SmartCikoko.

## Status UAT dan pipeline terpisah (10 November 2026)

- **Android:** build debug untuk pengujian internal, `flutter analyze`, dan widget tests berhasil pada job Android. APK dan source ZIP tersedia pada [GitHub prerelease SmartCikoko Mobile UAT](https://github.com/cikokosmarthome-ctrl/SmartCikoko/releases/tag/smartcikoko-mobile-uat-v1).
- **iOS:** struktur proyek iOS berada dalam source ZIP, tetapi belum ada IPA siap pasang atau bukti tanda tangan Apple. Kompilasi tanpa signing tersedia pada workflow terpisah `Flutter SmartCikoko iOS UAT (manual)` di tab Actions, memerlukan runner macOS tersedia.
- **Alasan pemisahan:** job iOS sebelumnya dibatalkan sehingga workflow gabungan berstatus gagal walaupun job Android sukses. Pipeline Android dipisahkan agar kegagalan antrean macOS tidak membatalkan atau salah menandai proses Android.
- **Pengujian aplikasi:** pada perangkat Android UAT, periksa login, pilihan akun ganda, MFA, berita, agenda, daftar formulir, simpan draf, pengiriman, riwayat, logout serta kondisi internet terputus. Jangan pakai data warga asli.
- **Server:** sebelum uji formulir, deploy V4 beserta `portal_private/mobile_forms_api.php`; bila API tidak tersedia tombol *Coba lagi* akan memuat ulang data, bukan membuat data contoh.
- APK ini **debug internal**, belum ditandatangani untuk produksi dan belum siap dipublikasikan ke Play Store/App Store. Rilis produksi memerlukan verifikasi instalasi perangkat, identitas aplikasi/logo resmi, kebijakan privasi, signing, dan pengujian backend produksi.


## Integrasi cPanel terbaru (branch pengembangan, November 2026)

- Tombol **Layanan Cikoko Terhubung (Beta)** di Beranda menggunakan `https://datawebs.my.id/mobile-api/index.php?action=...` dengan login dan token khusus API cPanel.
- Chat warga/petugas, pengiriman foto galeri, receipt terkirim/diterima/terbaca, notifikasi personal, serta riwayat RT/RW memakai kontrak Mobile API PHP 8.4 yang digunakan pada hosting terbaru.
- Untuk menjaga formulir portal lama, kedua sesi login belum digabung. Jangan klaim single sign-on sebelum migrasi seluruh modul selesai.
- Firebase Messaging hanya dapat digunakan setelah konfigurasi Firebase Android, izin notifikasi, dan Cron FCM aktif di cPanel. Ketuk notifikasi chat akan membuka modul chat bila payload benar dan akun terotorisasi.
- Pembuatan AAB Play Store membutuhkan lima GitHub Actions Secrets, upload signing key, target SDK 36, pengujian perangkat, dan persetujuan Play Console. **Build release UAT lama bukan paket siap unggah.**
- Panduan lengkap: [`PLAYSTORE-INTEGRASI.md`](PLAYSTORE-INTEGRASI.md).

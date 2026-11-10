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
- Tombol Google Sign-In, push notification, GPS, unggahan kamera, penyusunan surat resmi, dan persetujuan cuti **tidak diklaim aktif**; tidak ada tombol palsu untuk fitur tersebut.
- Source Android/iOS sama, namun kompatibilitas perangkat, hasil Apple build, keystore Android, store publishing, dan akurasi pixel-per-pixel belum terbukti 100% sebelum UAT.
- Aplikasi Flutter tidak mengganti PHP/MySQL; tetap memakai backend SmartCikoko.

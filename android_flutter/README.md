# Android Flutter — SmartCikoko

API: `https://datawebs.my.id/api/`. Autentikasi identik dengan portal; token akses disimpan di secure storage. Jangan pernah memasukkan password database ke APK.

## Menjalankan

```bash
cd android_flutter
flutter create --platforms=android --org id.my.datawebs .
flutter pub get
flutter run
```

Pastikan API sudah dipasang dan berjalan melalui HTTPS di Rumahweb.

## Persiapan publikasi Play Store

- Pastikan URL dan identitas aplikasi sesuai keputusan pengelola Kelurahan Cikoko.
- Uji fungsionalitas login dan masing-masing kategori akun pada perangkat Android.
- Terapkan penandatanganan aplikasi menggunakan keystore yang hanya dikuasai pemilik aplikasi.
- Selesaikan materi Play Store, data safety, kebijakan privasi yang akurat, pengujian keamanan, dan proses peninjauan Play Console.
- Jalankan `flutter build appbundle --release` setelah konfigurasi signing selesai.

Repositori ini berisi **kode sumber**, bukan APK/AAB siap unggah. Rilis memerlukan pengujian dan persetujuan pengelola.
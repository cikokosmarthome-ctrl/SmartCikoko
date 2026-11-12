# SmartCikoko Android — Integrasi cPanel dan Persiapan Google Play

Status: **BETA / BELUM RILIS PRODUKSI**. Baca sebelum merge ke main.

## Arsitektur

- Website dan Admin: PHP 8.4 di `https://datawebs.my.id`.
- Android terhubung melalui `https://datawebs.my.id/mobile-api/index.php?action=...`.
- Login Android memakai token Bearer yang dikeluarkan server Mobile API (bukan kredensial database).
- Server melakukan seluruh pemeriksaan hak akses. Flutter tidak diberikan password MySQL, akun layanan Firebase, atau akses langsung ke phpMyAdmin.
- Dua kontrak API masih hidup selama migrasi:
  - Portal UAT lama: `/api/?resource=...`, `/api/mobile-forms.php`.
  - Workspace baru: `/mobile-api/index.php?action=...`.
- **Token dan sesi kedua backend tidak dapat dipertukarkan.** Tombol Beranda *Layanan Cikoko Terhubung (Beta)* membuka workspace baru dengan login terpisah sampai seluruh formulir lama dimigrasi.

## Fitur workspace terhubung

- `login`, `me`, `permissions`, `logout` dari cPanel.
- `integrated_services_public` untuk katalog publik.
- `chat`, `chat_send`, `chat_inbox` bagi warga.
- `chat_threads`, `support_admin_thread`, `chat_reply` bagi petugas dengan `chat:reply`.
- `chat_photo_send`, `chat_photo_reply`, `chat_photo_view` untuk foto privat dengan header Bearer. **Memerlukan patch Chat+Push PHP 8.4 telah terpasang di cPanel.**
- `events` dan `event_read` untuk notifikasi personal.
- `history` untuk pengajuan/laporan yang tersedia di backend.
- Dua centang hijau menggunakan `delivery_status` dari backend; tidak dibuat-buat oleh Flutter.
- Chat dilakukan polling setiap empat detik ketika halaman chat terbuka. Bukan WebSocket.

## Sebelum pengujian perangkat

1. Backup website dan database cPanel.
2. Pastikan `/mobile-api/index.php?action=health` tersedia dan `ready=true`. Health hanya memeriksa berkas, bukan memastikan database berhasil digunakan.
3. Uji `login`, `me`, `permissions`, `chat`, `events`, `history` menggunakan **akun pengujian**, bukan akun warga sebenarnya.
4. Pastikan patch foto dan linked notification di Mobile API telah benar-benar dipasang; keberadaannya di ZIP saja tidak cukup.
5. Pastikan file foto privat tidak bisa dibuka tanpa Bearer token dan akun lain tidak dapat melihatnya.
6. Uji dua akun pada dua HP: warga kirim chat, petugas baca, status berubah dari terkirim → diterima → terbaca, dan kedua arah foto.
7. Uji saat perangkat offline, login gagal, sesi habis, akses petugas tanpa izin, dan klik notifikasi saat aplikasi di-background maupun setelah ditutup.

## Firebase dan push notification

Kode Flutter memakai `firebase_core` dan `firebase_messaging`, tetapi **tidak ada konfigurasi Firebase publik/privat yang ditanam di repository**.

1. Daftarkan Android application dengan package ID **`id.smartcikoko.smartcikoko`** di Firebase project yang dimiliki organisasi.
2. Unduh `google-services.json` untuk Android dari Firebase Console. File konfigurasi klien ini berbeda dengan **service-account JSON server**.
3. Simpan konfigurasi klien Android sebagai GitHub Actions Secret `SC_FIREBASE_ANDROID_JSON_BASE64` (base64 dari isi file).
4. Di cPanel server, service-account Firebase untuk Cron FCM harus disimpan **di luar `public_html`**, sesuai dokumentasi backend; jangan pernah taruh service-account di Flutter/GitHub publik.
5. Pastikan Cron dispatch FCM berjalan dan token perangkat telah tercatat di tabel `mobile_devices` untuk user yang benar.
6. Saat akun terhubung login, aplikasi meminta izin notifikasi (Android 13+), memperoleh token FCM, lalu memanggil `device`; logout memanggil `device_unregister`.
7. Backend harus mengirim payload `data.screen=chat` dan `data.thread_user_id=...`. Flutter menangani `onMessageOpenedApp` dan `getInitialMessage` setelah konfigurasi FCM aktif.
8. Saat aplikasi sedang foreground, FCM tidak otomatis menampilkan banner sistem. In-app `events` tetap dapat dibuka; untuk heads-up foreground perlu penambahan local notifications.

Tidak ada bukti push sudah sampai ke perangkat hanya dari lolos kompilasi.

## Google Play Store

Untuk aplikasi Android biasa, pengiriman app baru/update pada November 2026 wajib **target SDK 36** (Android 16). Gunakan workflow manual `.github/workflows/build-playstore-aab.yml`. Workflow ini membuat AAB **bertanda tangan upload key** dan menyiapkannya sebagai artifact untuk diunggah secara manual ke Play Console. Tidak ada upload otomatis ke Play Store.

Konfigurasi GitHub Actions Secrets (**jangan kirim nilainya melalui percakapan atau commit**):

| Secret | Isi |
| --- | --- |
| `SC_PLAY_KEYSTORE_BASE64` | Berkas keystore upload key, di-encode base64 |
| `SC_PLAY_STORE_PASSWORD` | Password keystore |
| `SC_PLAY_KEY_ALIAS` | Alias upload key |
| `SC_PLAY_KEY_PASSWORD` | Password key |
| `SC_FIREBASE_ANDROID_JSON_BASE64` | Konfigurasi klien Firebase Android, base64 |

Langkah:
1. Siapkan keystore upload key yang aman dan backup offline.
2. Di GitHub repository, buka Settings → Secrets and variables → Actions → New repository secret, buat lima secret di atas.
3. Buka Actions → **Build Google Play Signed AAB (manual, API 36)** → Run workflow.
4. Workflow membangun Flutter Android project, mengunci application ID, menambahkan izin notifikasi, target API 36, Firebase Google Services, dan signing release.
5. Download artifact `SmartCikoko-Play-API36-Signed-AAB` dari GitHub Actions setelah build berhasil. **Jangan publikasi sebelum uji perangkat dan keamanan selesai.**
6. Unggah AAB lewat Play Console menggunakan Play App Signing, isi Privacy Policy, Data Safety, dukungan akun, konten store listing, dan jalani pengujian internal/tertutup sesuai jenis akun.
7. **Jika aplikasi sudah pernah tayang**, application ID dan upload signing harus cocok dengan aplikasi lama atau proses penggantian upload key harus diselesaikan di Play Console. Ganti package ID tidak dapat menjadi update atas listing yang berbeda.

PENTING: AAB yang berhasil dibuat tidak membuktikan login/chat/notifikasi berfungsi di server produksi. Pastikan full UAT di atas.

## Yang belum selesai

- Migrasi semua layanan dan formulir UAT lama ke kontrak Mobile API PHP 8.4, sehingga saat ini masih ada dua mekanisme login. Jangan menyebutnya single sign-on.
- Handler foto kamera langsung (saat ini pemilih gambar dari perangkat/galeri).
- Pengujian Flutter di runner/perangkat Android dan pengujian Firebase sesudah client config disediakan.
- Foreground notification banner serta pengaturan notifikasi lebih lengkap.
- Play Console approval, aspek hukum kebijakan privasi, aksesibilitas, pemeriksaan keamanan, dan release produksi.

Sebelum merge ke main, jalankan `flutter pub get`, `flutter analyze`, `flutter test`, dan UAT sesuai daftar di atas.

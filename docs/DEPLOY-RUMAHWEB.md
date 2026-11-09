# Instalasi SmartCikoko di Rumahweb

**Belum dipasang ke hosting dan belum lolos pengujian produksi.** Pengaturan aman adalah dua database terpisah yang disambungkan aplikasi PHP melalui PDO.

1. Ciptakan database `datd1747_Pegawai` serta `datd1747_smartapp` dan user masing-masing lewat MySQL Databases di cPanel. Verifikasi penamaan aktual.
2. Impor SQL asli (file pegawai ke Pegawai, file smartapp ke SmartApp) lewat phpMyAdmin. Jangan mengunggah file SQL atau data pribadi ke GitHub atau public_html.
3. Jalankan `portal_private/schema.sql` di database SmartApp. Perintah ini hanya membuat tabel integrasi baru.
4. Upload isi `public_html/` ke `/home/datd1747/public_html/` dan folder `portal_private/` ke `/home/datd1747/portal_private/`, di luar webroot.
5. Salin `portal_private/config.example.php` menjadi `portal_private/config.php`. Ganti kredensial placeholder dengan kata sandi database BARU dan TERPISAH. Password yang pernah dibagikan harus diganti.
6. Dua berkas akun website lama, `data/portal_users.json` (17 akun) dan `data/admin.json` (akun administrator) di ZIP website lama, perlu dimigrasi ke `unified_portal_users` memakai `tools/import_accounts.php`. Jalankan hanya lewat PHP CLI dengan berkas JSON di luar webroot; jangan masukkan JSON ke GitHub. Hapus berkas sementara setelah proses selesai.
7. Uji `https://datawebs.my.id/api/?resource=health`; verifikasi HTTPS, login dari dua sumber, pilihan akun ganda, menu berdasar peran, pengajuan ahli waris, PPPK/PJLP, dan pembatasan akses.
8. Foto serta lampiran di ZIP lama memerlukan pemulihan selektif dari backup dengan penyimpanan privat dan pemeriksaan akses. Dump SQL saja tidak menyertakan berkasnya.
9. Jalankan pengujian keamanan, aksesibilitas dan perangkat sebelum domain/aplikasi dipublikasikan.

## Android

Jalankan Flutter SDK `flutter create --platforms=android --org id.my.datawebs .` di `android_flutter/` (untuk membuat scaffolding Android), `flutter pub get`, lalu `flutter run`. Build release memerlukan keystore dan penandatanganan AAB, uji perangkat, kebijakan privasi, serta evaluasi Google Play. Tidak ada AAB siap unggah saat ini.
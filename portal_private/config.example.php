<?php
/** Salin ke config.php pada direktori di luar public_html. JANGAN commit kredensial. */
return [
  'base_url' => 'https://datawebs.my.id',
  'site_name' => 'Portal Terpadu Kelurahan Cikoko',
  // Kosong = Super Admin dinonaktifkan. Masukkan ID pasti setelah verifikasi di phpMyAdmin.
  // Satu akun superadmin dapat mengendalikan kedua database melalui portal, tidak perlu dua akun.
  'super_admin_accounts' => [
    // ['source' => 'pegawai', 'id' => 'ISI_ID_ADMIN_DARI_DATABASE'],
  ],
  'pegawai' => [
    'host' => 'localhost', 'port' => '3306',
    'database' => 'datd1747_Pegawai',
    'username' => 'ISI_USER_DATABASE_PEGAWAI',
    'password' => 'ISI_PASSWORD_BARU_UNIK',
  ],
  'smartapp' => [
    'host' => 'localhost', 'port' => '3306',
    'database' => 'datd1747_smartapp',
    'username' => 'ISI_USER_DATABASE_SMARTAPP',
    'password' => 'ISI_PASSWORD_BARU_BERBEDA',
  ],
];
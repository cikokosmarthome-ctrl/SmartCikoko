-- Eksekusi HANYA pada datd1747_smartapp melalui phpMyAdmin.
-- Tidak mengubah peran ataupun data lama kedua database.
CREATE TABLE IF NOT EXISTS unified_module_progress (
  module_key varchar(32) NOT NULL PRIMARY KEY,
  status enum('belum','berjalan','tertunda','selesai') NOT NULL DEFAULT 'belum',
  progress_percent tinyint unsigned NOT NULL DEFAULT 0,
  notes varchar(500) NOT NULL DEFAULT '',
  updated_by_source varchar(32) DEFAULT NULL,
  updated_by_id varchar(100) DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  CONSTRAINT chk_progress_pct CHECK (progress_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO unified_module_progress (module_key) VALUES
 ('portal'),('pegawai'),('pppk'),('pjlp'),('waris'),('informasi'),('android');
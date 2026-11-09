-- Jalankan di database SmartApp setelah mengimpor SQL sumber. Tidak mengganti tabel lama.
CREATE TABLE IF NOT EXISTS unified_portal_users (
  id VARCHAR(100) NOT NULL PRIMARY KEY,
  username VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL,
  department VARCHAR(160) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_upu_username (username),
  KEY idx_upu_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS unified_login_limits (
  bucket CHAR(64) NOT NULL PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS unified_api_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  actor_source ENUM('pegawai','pancoran','smartapp') NOT NULL,
  actor_id VARCHAR(100) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_uat_actor (actor_source,actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS unified_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_source VARCHAR(30) NOT NULL,
  actor_id VARCHAR(100) NOT NULL,
  event_type VARCHAR(70) NOT NULL,
  entity VARCHAR(60) NOT NULL DEFAULT '',
  subject_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ua_actor (actor_source,actor_id),
  KEY idx_ua_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
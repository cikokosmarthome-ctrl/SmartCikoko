<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\'; script-src \'self\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'self\'');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('CIKOKO_UNIFIED');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'), 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
}
function cfg(): array {
    static $settings = null;
    if ($settings !== null) return $settings;
    $file = __DIR__.'/config.php';
    if (!is_file($file)) throw new RuntimeException('Konfigurasi portal belum dipasang.');
    $settings = require $file;
    if (!is_array($settings)) throw new RuntimeException('Konfigurasi portal tidak valid.');
    return $settings;
}
function db(string $source = 'smartapp'): PDO {
    static $pool = [];
    if (isset($pool[$source])) return $pool[$source];
    if (!in_array($source, ['smartapp','pegawai'], true)) throw new InvalidArgumentException('Sumber data tidak dikenal.');
    $config = cfg()[$source] ?? [];
    foreach (['host','database','username','password'] as $key) if (!isset($config[$key])) throw new RuntimeException('Konfigurasi database belum lengkap.');
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? '3306', $config['database']);
    return $pool[$source] = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
}
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page=''): string { return '/'.($page === '' ? '' : '?page='.rawurlencode($page)); }
function redirect(string $page): never { header('Location: '.url($page), true, 303); exit; }
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf']??''))) { http_response_code(419); exit('Formulir tidak valid. Muat ulang halaman.'); } }
function actor(): ?array {return $_SESSION['actor'] ?? null;}
function logged_in(): bool {return actor() !== null;}
function requires_login(): array { if (!logged_in()) redirect('login'); return actor(); }
function role_label(string $role): string {return [
    'admin'=>'Administrator Pegawai', 'superadmin'=>'Administrator Kelurahan', 'admin_kelurahan'=>'Admin Kelurahan', 'lurah'=>'Lurah',
    'pjlp'=>'PJLP / PPSU', 'pppk'=>'PPPK', 'pppk_pancoran'=>'PPPK Pancoran', 'asn'=>'ASN', 'warga'=>'Masyarakat',
    'rt'=>'Pengurus RT', 'rw'=>'Pengurus RW', 'ptsp'=>'Petugas PTSP', 'dukcapil'=>'Dukcapil',
    'bendahara_rt'=>'Bendahara RT', 'bendahara_rw'=>'Bendahara RW', 'lmk'=>'LMK',
    'ppsu'=>'PPSU', 'fkdm'=>'FKDM', 'dasawisma'=>'Dasawisma', 'posyandu'=>'Posyandu',
][strtolower($role)] ?? strtoupper(str_replace('_', ' ', $role));}
function is_admin(?array $u=null): bool { $u ??= actor(); return $u !== null && in_array($u['role'],['admin','superadmin','admin_kelurahan'],true); }
function permitted(string $ability, ?array $u=null): bool {
    $u ??= actor(); if (!$u) return false; if (is_admin($u)) return true;
    return match ($ability) {
        'pegawai' => in_array($u['source'],['pegawai','pancoran'],true),
        'pppk' => in_array($u['role'],['pppk','pppk_pancoran'],true),
        'pjlp' => in_array($u['role'],['pjlp','ppsu'],true),
        'waris' => in_array($u['role'],['warga','lurah','ptsp','dukcapil'],true),
        'waris_manage' => in_array($u['role'],['lurah','ptsp'],true),
        'news_manage' => in_array($u['role'],['lurah'],true),
        default => false,
    };
}
function require_ability(string $ability): array { $u=requires_login(); if (!permitted($ability,$u)) {http_response_code(403); exit('Akses ditolak. Hak akses Anda tidak mencakup modul ini.');} return $u; }
function log_event(string $event, string $entity='', string $subject=''): void {
    try { $u = actor(); $st=db()->prepare('INSERT INTO unified_audit (actor_source,actor_id,event_type,entity,subject_hash,created_at) VALUES (?,?,?,?,?,NOW())');
      $st->execute([$u['source']??'anonymous', (string)($u['id']??''),$event,$entity,$subject===''?null:hash('sha256',$subject)]); } catch(Throwable $e) {error_log('Audit failure: '.$e->getMessage());}
}
function respond(array $payload, int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
require_once __DIR__.'/auth.php';
require_once __DIR__.'/data.php';
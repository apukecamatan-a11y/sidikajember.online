<?php
// api/storage.php
// Endpoint tunggal yang meng-implementasikan window.storage.get/set/delete/list
// di index.html, di atas tabel app_storage (lihat schema.sql).
//
// Protokol:
//   GET  storage.php?action=ping                    -> {ok:true, time:"..."} (uji koneksi, tanpa perlu key)
//   GET  storage.php?action=get&key=KEY              -> null | {key,value,shared:false}
//   GET  storage.php?action=list&prefix=PREFIX       -> {keys:[...]}
//   POST storage.php  {"action":"set","key":K,"value":V}    -> {key,value,shared:false}
//        (body boleh dikompres gzip bila header X-Body-Encoding: gzip dikirim)
//   POST storage.php  {"action":"delete","key":K}           -> {key,deleted:true,shared:false}
//
// Kalau $API_TOKEN diisi di config.php, setiap request WAJIB menyertakan header:
//   X-Api-Key: <nilai $API_TOKEN>
// (lihat catatan keamanan di config.php).
//
// Kalau $FORCE_HTTPS diisi true di config.php, request yang datang lewat http://
// biasa (bukan https://) akan ditolak dengan status 403.

declare(strict_types=1);

// Jangan biarkan warning/notice PHP ikut tercetak ke output — kalau itu terjadi,
// output tidak lagi JSON murni dan fetch(...).json() di index.html akan gagal parse.
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require __DIR__ . '/config.php';

// Fallback defensif kalau config.php yang dipakai masih versi lama (belum punya
// $DB_PORT / $API_TOKEN / $ALLOWED_ORIGIN / $FORCE_HTTPS) — aplikasi tetap jalan
// seperti sediakala, tanpa proteksi tambahan yang belum diisi.
$DB_PORT        = $DB_PORT        ?? 3306;
$API_TOKEN      = $API_TOKEN      ?? '';
$ALLOWED_ORIGIN = $ALLOWED_ORIGIN ?? '';
$FORCE_HTTPS    = $FORCE_HTTPS    ?? false;

const MAX_KEY_LEN   = 191;        // harus sinkron dengan VARCHAR(191) di schema.sql
const MAX_VALUE_LEN = 50 * 1024 * 1024; // batas wajar 50MB per value, sesuaikan bila perlu

function respond($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/** Deteksi HTTPS dengan aman di balik proxy/load-balancer (umum di hosting bersama
 *  seperti Hostinger), yang biasanya meneruskan skema asli lewat header ini. */
function isRequestSecure(): bool {
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
  $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  if (strtolower((string)$proto) === 'https') return true;
  $port = $_SERVER['SERVER_PORT'] ?? '';
  return (string)$port === '443';
}

/** Ambil alamat IP client untuk keperluan audit log saja (bukan untuk keamanan). */
function clientIp(): string {
  $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
  if ($fwd !== '') {
    $parts = explode(',', $fwd);
    $ip = trim($parts[0]);
    if ($ip !== '') return substr($ip, 0, 45);
  }
  return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// Tangkap error fatal / exception yang tak terduga supaya tetap balas JSON rapi,
// bukan halaman error HTML mentah yang bisa merusak parsing di frontend.
set_exception_handler(function ($e) {
  error_log('[storage.php] Uncaught: ' . $e);
  respond(['error' => 'internal_error'], 500);
});
set_error_handler(function ($severity, $message, $file, $line) {
  // Hormati mask error_reporting() saat ini (mis. error yang sengaja disenyapkan
  // dengan operator @) — jangan diubah jadi exception kalau memang sedang disenyapkan.
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- CORS (hanya aktif kalau $ALLOWED_ORIGIN diisi di config.php) ---
if (!empty($ALLOWED_ORIGIN)) {
  header('Access-Control-Allow-Origin: ' . $ALLOWED_ORIGIN);
  header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Body-Encoding');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --- Paksa HTTPS (opsional, lihat $FORCE_HTTPS di config.php) ---
if ($FORCE_HTTPS === true && !isRequestSecure()) {
  respond(['error' => 'https_required', 'message' => 'Akses harus lewat https://'], 403);
}

// --- Autentikasi opsional lewat token (lihat config.php) ---
if (!empty($API_TOKEN)) {
  $sentToken = $_SERVER['HTTP_X_API_KEY'] ?? '';
  if (!hash_equals($API_TOKEN, (string)$sentToken)) {
    respond(['error' => 'unauthorized'], 401);
  }
}

// --- Pastikan config.php sudah diisi, bukan masih nilai contoh ---
if ($DB_NAME === 'namadb_anda' || $DB_USER === 'namauser_anda' || $DB_PASS === 'password_anda') {
  respond(['error' => 'config_not_set', 'message' => 'Isi dulu kredensial database di api/config.php'], 500);
}

// --- Health-check ringan, tidak butuh koneksi DB — untuk memastikan endpoint
//     terpasang & token/CORS benar sebelum menguji fitur lain. ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'ping') {
  respond(['ok' => true, 'time' => date('c')]);
}

try {
  $port = (int)$DB_PORT;
  $pdo = new PDO(
    "mysql:host={$DB_HOST};port={$port};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
  // Samakan zona waktu kolom updated_at dengan WIB (Kabupaten Jember).
  $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
  // Jangan bocorkan kredensial/detail error ke client, cukup status generik.
  error_log('[storage.php] DB connect failed: ' . $e->getMessage());
  respond(['error' => 'db_connect_failed'], 500);
}

/** Catat jejak audit (best-effort): kalau tabel app_storage_audit belum dibuat
 *  atau insert gagal karena sebab apa pun, JANGAN sampai menggagalkan operasi
 *  utama (set/delete) — audit log bersifat pelengkap, bukan syarat. */
function writeAudit(PDO $pdo, string $key, string $action): void {
  try {
    $stmt = $pdo->prepare(
      "INSERT INTO app_storage_audit (storage_key, action, client_ip) VALUES (:k, :a, :ip)"
    );
    $stmt->execute([':k' => $key, ':a' => $action, ':ip' => clientIp()]);
  } catch (\Throwable $e) {
    // Sengaja disenyapkan: tabel audit opsional (lihat schema.sql).
  }
}

/** Validasi key: harus string, tidak kosong, dan muat di kolom VARCHAR(191). */
function validateKey($key): string {
  if (!is_string($key) || $key === '') {
    respond(['error' => 'missing_key'], 400);
  }
  if (strlen($key) > MAX_KEY_LEN) {
    respond(['error' => 'key_too_long', 'max_length' => MAX_KEY_LEN], 400);
  }
  return $key;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $action = $_GET['action'] ?? 'get';
  if (!is_string($action)) respond(['error' => 'invalid_action'], 400);

  if ($action === 'list') {
    $prefixRaw = $_GET['prefix'] ?? '';
    if (!is_string($prefixRaw)) respond(['error' => 'invalid_prefix'], 400);
    $prefix = $prefixRaw;

    try {
      $stmt = $pdo->prepare(
        "SELECT storage_key FROM app_storage WHERE storage_key LIKE :p ORDER BY storage_key ASC"
      );
      // Escape wildcard karakter milik LIKE yang mungkin ada di dalam prefix itu sendiri.
      // Urutan penting: backslash literal harus di-escape LEBIH DULU sebelum % dan _.
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);
      $stmt->execute([':p' => $escaped . '%']);
      $keys = array_column($stmt->fetchAll(), 'storage_key');
      respond(['keys' => $keys]);
    } catch (PDOException $e) {
      error_log('[storage.php] list failed: ' . $e->getMessage());
      respond(['error' => 'query_failed'], 500);
    }
  }

  if ($action === 'get') {
    $key = validateKey($_GET['key'] ?? '');

    try {
      $stmt = $pdo->prepare(
        "SELECT storage_value FROM app_storage WHERE storage_key = :k LIMIT 1"
      );
      $stmt->execute([':k' => $key]);
      $row = $stmt->fetch();
    } catch (PDOException $e) {
      error_log('[storage.php] get failed: ' . $e->getMessage());
      respond(['error' => 'query_failed'], 500);
    }

    if ($row === false) respond(null); // key belum ada, sama seperti localStorage.getItem() -> null
    respond(['key' => $key, 'value' => $row['storage_value'], 'shared' => false]);
  }

  respond(['error' => 'unknown_action'], 400);
}

if ($method === 'POST') {
  $raw  = file_get_contents('php://input');

  // index.html (versi terbaru) mengompres body POST berukuran besar dengan gzip dan
  // menandainya lewat header X-Body-Encoding: gzip (Content-Type: application/octet-stream).
  // Tanpa blok ini, body biner gagal di-json_decode -> 400 invalid_body dan frontend
  // selalu jatuh ke pengiriman tanpa kompresi (lebih lambat di koneksi Puskesmas).
  $bodyEncoding = strtolower((string)($_SERVER['HTTP_X_BODY_ENCODING'] ?? ''));
  if ($bodyEncoding === 'gzip') {
    if (!function_exists('gzdecode')) respond(['error' => 'gzip_unsupported'], 415);
    // Batas ukuran hasil dekompresi mencegah "gzip bomb" (body kecil yang meledak jadi ratusan MB).
    $decoded = @gzdecode($raw, MAX_VALUE_LEN + 4096);
    if ($decoded === false) respond(['error' => 'invalid_gzip_body'], 400);
    $raw = $decoded;
  }

  $body = json_decode($raw, true);
  if (!is_array($body)) respond(['error' => 'invalid_body'], 400);

  $action = $body['action'] ?? 'set';
  if (!is_string($action)) respond(['error' => 'invalid_action'], 400);
  $key = validateKey($body['key'] ?? '');

  if ($action === 'set') {
    $value = $body['value'] ?? '';
    if (!is_string($value) && !is_numeric($value)) {
      respond(['error' => 'invalid_value_type', 'message' => 'value harus berupa string'], 400);
    }
    $value = (string)$value;
    if (strlen($value) > MAX_VALUE_LEN) {
      respond(['error' => 'value_too_large', 'max_bytes' => MAX_VALUE_LEN], 400);
    }

    try {
      $stmt = $pdo->prepare(
        "INSERT INTO app_storage (storage_key, storage_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE storage_value = VALUES(storage_value)"
      );
      $stmt->execute([':k' => $key, ':v' => $value]);
    } catch (PDOException $e) {
      error_log('[storage.php] set failed: ' . $e->getMessage());
      respond(['error' => 'query_failed'], 500);
    }
    writeAudit($pdo, $key, 'set');
    respond(['key' => $key, 'value' => $value, 'shared' => false]);
  }

  if ($action === 'delete') {
    try {
      $stmt = $pdo->prepare("DELETE FROM app_storage WHERE storage_key = :k");
      $stmt->execute([':k' => $key]);
    } catch (PDOException $e) {
      error_log('[storage.php] delete failed: ' . $e->getMessage());
      respond(['error' => 'query_failed'], 500);
    }
    writeAudit($pdo, $key, 'delete');
    respond(['key' => $key, 'deleted' => true, 'shared' => false]);
  }

  respond(['error' => 'unknown_action'], 400);
}

respond(['error' => 'method_not_allowed'], 405);

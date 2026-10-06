<?php
date_default_timezone_set('Asia/Manila');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

const THEMES = ['Birthday', 'Wedding', 'Christening', 'Graduation', 'Fiesta', 'Business', 'Other'];
const STATUSES = ['Pending', 'Designing', 'For approval', 'Approved', 'Printing', 'Ready', 'Claimed'];

function out($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function user() { return $_SESSION['user'] ?? null; }
function need($role = null) {
    $u = user();
    if (!$u) out(['error' => 'Please log in.'], 401);
    if ($role && $u['role'] !== $role) out(['error' => 'You do not have access to this.'], 403);
    return $u;
}
function body() { return json_decode(file_get_contents('php://input'), true) ?: []; }
function setting($pdo, $name) {
    $st = $pdo->prepare('SELECT value FROM settings WHERE name = ?'); $st->execute([$name]); return $st->fetchColumn();
}
// Validates every uploaded image first, then saves them. Returns [[stored_name, original_name], ...]
function save_images($field, $max = 5) {
    $f = $_FILES[$field] ?? null; if (!$f) return [];
    $names = (array)$f['name']; $tmp = (array)$f['tmp_name']; $err = (array)$f['error']; $size = (array)$f['size'];
    $ok = [];
    foreach ($names as $i => $n) {
        if ($err[$i] === UPLOAD_ERR_NO_FILE) continue;
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        if ($err[$i] !== 0 || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) || $size[$i] > 15 * 1048576 || !@getimagesize($tmp[$i]))
            out(['error' => "\"$n\" cannot be used. Upload JPG, PNG or WEBP files under 15 MB."], 422);
        $ok[] = [$tmp[$i], $ext, $n];
    }
    if (count($ok) > $max) out(['error' => "You can upload up to $max image" . ($max > 1 ? 's' : '') . ' at a time.'], 422);
    $res = [];
    foreach ($ok as [$t, $ext, $n]) {
        $s = bin2hex(random_bytes(10)) . ".$ext";
        move_uploaded_file($t, __DIR__ . "/../uploads/$s");
        $res[] = [$s, $n];
    }
    return $res;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=tarp_shop;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) { out(['error' => 'Cannot connect to the database. Check that MySQL is running and database.sql was imported.'], 500); }

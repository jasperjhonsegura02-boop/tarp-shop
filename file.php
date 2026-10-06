<?php require 'config.php';
// Serves private uploads. Admin can open everything; a customer only their own files.
$u = need(); $t = $_GET['t'] ?? ''; $id = (int)($_GET['id'] ?? 0);
if ($t === 'img') $st = $pdo->prepare('SELECT i.file, i.original_name, o.user_id FROM order_images i JOIN orders o ON o.id = i.order_id WHERE i.id = ?');
elseif ($t === 'proof') $st = $pdo->prepare('SELECT p.file, p.file AS original_name, o.user_id FROM order_proofs p JOIN orders o ON o.id = p.order_id WHERE p.id = ?');
else out(['error' => 'Not found.'], 404);
$st->execute([$id]); $r = $st->fetch();
if (!$r || ($u['role'] !== 'admin' && $r['user_id'] != $u['id'])) out(['error' => 'Not found.'], 404);
$path = __DIR__ . '/../uploads/' . basename($r['file']);
if (!is_file($path)) out(['error' => 'File not found.'], 404);
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($mimes[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($r['original_name'])) . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);

<?php require 'config.php';
$a = $_GET['action'] ?? ''; $in = body();
if ($a === 'me') out(['user' => user()]);
if ($a === 'logout') { session_destroy(); out(['ok' => true]); }
if ($a === 'login') {
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ?'); $st->execute([trim($in['email'] ?? '')]);
    $u = $st->fetch();
    if (!$u || !password_verify($in['password'] ?? '', $u['password'])) out(['error' => 'Email or password is incorrect.'], 401);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
    out(['user' => $_SESSION['user']]);
}
if ($a === 'register') {
    $name = trim($in['name'] ?? ''); $email = trim($in['email'] ?? ''); $pw = $in['password'] ?? '';
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6)
        out(['error' => 'Enter your name, a valid email, and a password of at least 6 characters.'], 422);
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $st->execute([$email]);
    if ($st->fetch()) out(['error' => 'That email is already registered.'], 422);
    $pdo->prepare('INSERT INTO users (name,email,phone,password) VALUES (?,?,?,?)')
        ->execute([$name, $email, trim($in['phone'] ?? ''), password_hash($pw, PASSWORD_DEFAULT)]);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $pdo->lastInsertId(), 'name' => $name, 'role' => 'customer'];
    out(['user' => $_SESSION['user']]);
}
out(['error' => 'Unknown action.'], 400);

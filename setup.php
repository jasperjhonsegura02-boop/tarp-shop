<?php // Run once at http://localhost/tarp-shop/setup.php, then DELETE this file.
header('Content-Type: text/plain');
$pdo = new PDO('mysql:host=localhost;dbname=tarp_shop;charset=utf8mb4', 'root', '');
if ($pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES ('Admin','admin@tarp.local',?,'admin')")
        ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
    echo 'Admin created: admin@tarp.local / admin123. Change the password and delete setup.php.';
} else echo 'Admin already exists. Delete setup.php.';

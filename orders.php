<?php require 'config.php';
$a = $_GET['action'] ?? '';

function attach($pdo, $orders) {
    if (!$orders) return [];
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    $img = $pro = [];
    foreach ($pdo->query("SELECT id, order_id, original_name FROM order_images WHERE order_id IN ($ids) ORDER BY id") as $r) $img[$r['order_id']][] = $r;
    foreach ($pdo->query("SELECT * FROM order_proofs WHERE order_id IN ($ids) ORDER BY id") as $r) $pro[$r['order_id']][] = $r;
    foreach ($orders as $k => $o) { $orders[$k]['images'] = $img[$o['id']] ?? []; $orders[$k]['proofs'] = $pro[$o['id']] ?? []; }
    return $orders;
}
function latest_proof($pdo, $id) {
    $st = $pdo->prepare('SELECT * FROM order_proofs WHERE order_id = ? ORDER BY id DESC LIMIT 1'); $st->execute([$id]); return $st->fetch();
}

if ($a === 'options') out([
    'sizes' => $pdo->query('SELECT label, price FROM sizes WHERE active = 1 ORDER BY price')->fetchAll(),
    'themes' => THEMES, 'rush_rate' => (float)setting($pdo, 'rush_rate'), 'layout_fee' => (float)setting($pdo, 'layout_fee'),
]);

if ($a === 'create') {
    if ($a === 'create') {
    $u = need('customer');
    $theme = $_POST['theme'] ?? ''; $size = $_POST['size'] ?? ''; $event = $_POST['event_date'] ?? ''; $dead = $_POST['deadline'] ?? '';
    $rush = isset($_POST['is_rush']) ? 1 : 0; $service = ($_POST['service'] ?? '') === 'layout' ? 'layout' : 'print_only';
    if ($service === 'print_only') { $theme = 'Print only'; $event = $dead; }
    $st = $pdo->prepare('SELECT price FROM sizes WHERE label = ? AND active = 1'); $st->execute([$size]); $base = $st->fetchColumn();
    if (($service !== 'print_only' && !in_array($theme, THEMES)) || $base === false) out(['error' => 'Choose a theme and an available size.'], 422);
    if (!$event || !$dead || $dead < date('Y-m-d')) out(['error' => 'Pick a deadline that is today or later.'], 422);
    if ($dead > $event) out(['error' => 'The deadline must be on or before the event date.'], 422);
    $name = $age = null;
    if ($theme === 'Birthday') {
        $name = trim($_POST['celebrant_name'] ?? ''); $age = (int)($_POST['celebrant_age'] ?? 0);
        if (!$name || $age < 1) out(['error' => "Enter the celebrant's name and age."], 422);
    }
    $files = save_images('images', 5);
    if ($service === 'print_only' && !$files) out(['error' => 'Upload the photo or design you want printed.'], 422);
    $price = ($base + ($service === 'layout' ? (float)setting($pdo, 'layout_fee') : 0)) * ($rush ? 1 + (float)setting($pdo, 'rush_rate') : 1);
    $pdo->prepare('INSERT INTO orders (user_id,theme,event_date,deadline,size,service,is_rush,celebrant_name,celebrant_age,notes,price) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $theme, $event, $dead, $size, $service, $rush, $name, $age, trim($_POST['notes'] ?? ''), $price]);
    $oid = $pdo->lastInsertId();
    foreach ($files as [$s, $n]) $pdo->prepare('INSERT INTO order_images (order_id,file,original_name) VALUES (?,?,?)')->execute([$oid, $s, $n]);
    out(['ok' => true]);
}
}

if ($a === 'mine') {
    $u = need('customer');
    $st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC'); $st->execute([$u['id']]);
    out(['orders' => attach($pdo, $st->fetchAll())]);
}

if ($a === 'list') {
    need('admin');
    $sorts = ['rush' => 'o.is_rush DESC, o.deadline ASC', 'deadline' => 'o.deadline ASC', 'newest' => 'o.created_at DESC'];
    $sort = $sorts[$_GET['sort'] ?? ''] ?? $sorts['rush'];
    $f = $_GET['status'] ?? 'active';
    $where = $f === 'active' ? "o.status <> 'Claimed'" : (in_array($f, STATUSES) ? 'o.status = ' . $pdo->quote($f) : '1');
    out(['orders' => attach($pdo, $pdo->query("SELECT o.*, u.name cname, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE $where ORDER BY $sort")->fetchAll())]);
}

if ($a === 'update') {
    need('admin'); $in = body(); $id = (int)($in['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o) out(['error' => 'Order not found.'], 404);
    if (isset($in['status'])) {
        $s = $in['status'];
        if (!in_array($s, ['Pending', 'Designing', 'Printing', 'Ready', 'Claimed'])) out(['error' => 'The shop cannot set that status. It changes when a layout is sent or answered.'], 422);
        if ($o['service'] === 'print_only' && $s === 'Designing') out(['error' => 'This order is print only, so it has no layout step.'], 422);
        if ($o['service'] === 'layout' && in_array($s, ['Printing', 'Ready', 'Claimed'])) {
            $p = latest_proof($pdo, $id);
            if (!$p || $p['response'] !== 'approved') out(['error' => 'The customer has not approved the layout yet. Send the layout for approval first.'], 422);
        }
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$s, $id]);
    }
    if (!empty($in['pay'])) $pdo->prepare('UPDATE orders SET paid_at = IF(paid_at IS NULL, NOW(), NULL) WHERE id = ?')->execute([$id]);
    out(['ok' => true]);
}

if ($a === 'proof_upload') {
    need('admin'); $id = (int)($_POST['order_id'] ?? 0);
    $st = $pdo->prepare('SELECT service FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
    if (!$o || $o['service'] !== 'layout') out(['error' => 'Only "Layout first" orders need a proof.'], 422);
    $files = save_images('image', 1);
    if (!$files) out(['error' => 'Choose the layout image to send.'], 422);
    $pdo->prepare('INSERT INTO order_proofs (order_id,file,note) VALUES (?,?,?)')->execute([$id, $files[0][0], trim($_POST['note'] ?? '')]);
    $pdo->prepare("UPDATE orders SET status = 'For approval' WHERE id = ?")->execute([$id]);
    out(['ok' => true]);
}

if ($a === 'proof_respond') {
    $u = need('customer'); $in = body();
    $st = $pdo->prepare('SELECT p.*, o.user_id FROM order_proofs p JOIN orders o ON o.id = p.order_id WHERE p.id = ?');
    $st->execute([(int)($in['proof_id'] ?? 0)]); $p = $st->fetch();
    if (!$p || $p['user_id'] != $u['id'] || $p['response'] !== 'pending') out(['error' => 'This layout was already answered.'], 422);
    if (latest_proof($pdo, $p['order_id'])['id'] != $p['id']) out(['error' => 'A newer layout was sent. Please review that one.'], 422);
    $approve = ($in['response'] ?? '') === 'approved'; $fb = trim($in['feedback'] ?? '');
    if (!$approve && !$fb) out(['error' => 'Tell us what to change.'], 422);
    $pdo->prepare('UPDATE order_proofs SET response = ?, feedback = ?, responded_at = NOW() WHERE id = ?')->execute([$approve ? 'approved' : 'changes', $fb, $p['id']]);
    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$approve ? 'Approved' : 'Designing', $p['order_id']]);
    out(['ok' => true]);
}
out(['error' => 'Unknown action.'], 400);

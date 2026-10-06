<?php require 'config.php'; need('admin');
$a = $_GET['action'] ?? '';

if ($a === 'dashboard') {
    $counts = array_fill_keys(STATUSES, 0);
    foreach ($pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
    out([
        'counts' => $counts,
        'rush' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE is_rush = 1 AND status <> 'Claimed'")->fetchColumn(),
        'today' => (float)$pdo->query('SELECT COALESCE(SUM(price),0) FROM orders WHERE DATE(paid_at) = CURDATE()')->fetchColumn(),
        'month' => (float)$pdo->query('SELECT COALESCE(SUM(price),0) FROM orders WHERE YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())')->fetchColumn(),
        'due' => $pdo->query("SELECT o.*, u.name cname FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status <> 'Claimed' ORDER BY o.deadline ASC, o.is_rush DESC LIMIT 8")->fetchAll(),
    ]);
}

if ($a === 'report') {
    $map = [
        'week'  => ['YEARWEEK(paid_at,1)', "CONCAT('Week of ', MIN(DATE(paid_at)))", 12],
        'month' => ["DATE_FORMAT(paid_at,'%Y-%m')", "DATE_FORMAT(MIN(paid_at),'%b %Y')", 12],
        'year'  => ['YEAR(paid_at)', 'YEAR(MIN(paid_at))', 10],
    ];
    [$k, $label, $lim] = $map[$_GET['period'] ?? ''] ?? $map['week'];
    out(['rows' => $pdo->query("SELECT $label AS label, COUNT(*) n, SUM(price) total FROM orders WHERE paid_at IS NOT NULL GROUP BY $k ORDER BY $k DESC LIMIT $lim")->fetchAll()]);
}

if ($a === 'pricing') out([
    'sizes' => $pdo->query('SELECT * FROM sizes ORDER BY price')->fetchAll(),
    'rush_rate' => (float)setting($pdo, 'rush_rate'), 'layout_fee' => (float)setting($pdo, 'layout_fee'),
]);

if ($a === 'size_save') {
    $in = body(); $id = (int)($in['id'] ?? 0); $label = trim($in['label'] ?? ''); $price = (float)($in['price'] ?? 0);
    if (!$label || $price <= 0) out(['error' => 'Enter a size name and a price above zero.'], 422);
    try {
        if ($id) $pdo->prepare('UPDATE sizes SET label=?, price=?, active=? WHERE id=?')->execute([$label, $price, empty($in['active']) ? 0 : 1, $id]);
        else $pdo->prepare('INSERT INTO sizes (label, price, active) VALUES (?,?,?)')->execute([$label, $price, empty($in['active']) ? 0 : 1]);
    } catch (PDOException $e) { out(['error' => 'That size name already exists.'], 422); }
    out(['ok' => true]);
}

if ($a === 'size_delete') {
    $pdo->prepare('DELETE FROM sizes WHERE id = ?')->execute([(int)(body()['id'] ?? 0)]);
    out(['ok' => true]);
}

if ($a === 'settings_save') {
    $in = body(); $rush = (float)($in['rush'] ?? -1); $fee = (float)($in['layout_fee'] ?? -1);
    if ($rush < 0 || $rush > 300 || $fee < 0) out(['error' => 'Enter a rush percent from 0 to 300 and a layout fee of 0 or more.'], 422);
    $st = $pdo->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)');
    $st->execute(['rush_rate', $rush / 100]); $st->execute(['layout_fee', $fee]);
    out(['ok' => true]);
}
out(['error' => 'Unknown action.'], 400);

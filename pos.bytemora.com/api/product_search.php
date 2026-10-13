<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

$stmt = $pdo->prepare("
    SELECT p.*, (
        CASE WHEN p.tracking_type = 'bulk' THEN p.stock_qty
        ELSE (SELECT COUNT(*) FROM serial_numbers sn WHERE sn.product_id = p.id AND sn.status = 'in_stock')
        END
    ) AS available
    FROM products p
    WHERE p.is_active = 1 AND (p.name LIKE ? OR p.category LIKE ?)
    ORDER BY p.name
    LIMIT 20
");
$stmt->execute([$like, $like]);
$rows = $stmt->fetchAll();

$results = array_map(function ($p) {
    return [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'category' => $p['category'],
        'tracking_type' => $p['tracking_type'],
        'unit_price' => (float) $p['unit_price'],
        'available' => (int) $p['available'],
    ];
}, $rows);

echo json_encode(['ok' => true, 'results' => $results]);

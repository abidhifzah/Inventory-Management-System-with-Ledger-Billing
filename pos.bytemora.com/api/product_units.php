<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$productId = (int) ($_GET['product_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, serial_barcode FROM serial_numbers WHERE product_id = ? AND status = 'in_stock' ORDER BY id LIMIT 200");
$stmt->execute([$productId]);
$rows = $stmt->fetchAll();

echo json_encode(['ok' => true, 'units' => array_map(fn($r) => ['id' => (int) $r['id'], 'barcode' => $r['serial_barcode']], $rows)]);

<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$barcode = trim($_GET['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['ok' => false, 'error' => 'No barcode given.']);
    exit;
}

// 1. Try bulk product barcode.
$stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ? AND tracking_type = 'bulk' AND is_active = 1");
$stmt->execute([$barcode]);
$product = $stmt->fetch();
if ($product) {
    if ((int) $product['stock_qty'] <= 0) {
        echo json_encode(['ok' => false, 'error' => $product['name'] . ' is out of stock.']);
        exit;
    }
    echo json_encode([
        'ok'   => true,
        'type' => 'bulk',
        'product' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'unit_price' => (float) $product['unit_price'],
            'stock_qty' => (int) $product['stock_qty'],
        ],
    ]);
    exit;
}

// 2. Try serialized unit barcode.
$stmt = $pdo->prepare("
    SELECT sn.*, p.name AS product_name, p.unit_price, p.is_active
    FROM serial_numbers sn
    JOIN products p ON p.id = sn.product_id
    WHERE sn.serial_barcode = ?
");
$stmt->execute([$barcode]);
$serial = $stmt->fetch();
if ($serial) {
    if (!$serial['is_active']) {
        echo json_encode(['ok' => false, 'error' => 'This product is inactive.']);
        exit;
    }
    if ($serial['status'] !== 'in_stock') {
        echo json_encode(['ok' => false, 'error' => 'This unit is already ' . str_replace('_', ' ', $serial['status']) . ' (not available).']);
        exit;
    }
    echo json_encode([
        'ok'   => true,
        'type' => 'serialized',
        'product' => [
            'id' => (int) $serial['product_id'],
            'name' => $serial['product_name'],
            'unit_price' => (float) $serial['unit_price'],
            'serial_number_id' => (int) $serial['id'],
            'serial_barcode' => $serial['serial_barcode'],
        ],
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Barcode not recognized: ' . $barcode]);

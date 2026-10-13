<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}

$id                 = !empty($_POST['id']) ? (int) $_POST['id'] : null;
$name               = trim($_POST['name'] ?? '');
$category           = trim($_POST['category'] ?? 'other');
$trackingType       = $_POST['tracking_type'] ?? 'bulk';
$unitPrice          = (float) ($_POST['unit_price'] ?? 0);
$lowStockThreshold  = (int) ($_POST['low_stock_threshold'] ?? 5);
$isActive           = isset($_POST['is_active']) ? 1 : 0;
$barcode            = trim($_POST['barcode'] ?? '');

if ($name === '' || !in_array($trackingType, ['bulk', 'serialized'], true)) {
    flash_set('Please fill in all required fields.', 'danger');
    redirect('products.php');
}

if ($trackingType === 'bulk') {
    if ($barcode === '') {
        $barcode = 'BC' . time() . rand(100, 999);
    }
} else {
    $barcode = null; // serialized products carry barcodes on individual units instead
}

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, unit_price=?, low_stock_threshold=?, is_active=?, barcode=IF(tracking_type='bulk', ?, barcode) WHERE id=?");
        $stmt->execute([$name, $category, $unitPrice, $lowStockThreshold, $isActive, $barcode, $id]);
        flash_set('Product updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, tracking_type, barcode, unit_price, stock_qty, low_stock_threshold, is_active) VALUES (?,?,?,?,?,0,?,?)");
        $stmt->execute([$name, $category, $trackingType, $barcode, $unitPrice, $lowStockThreshold, $isActive]);
        flash_set('Product added.');
    }
} catch (PDOException $e) {
    flash_set('Could not save product: barcode may already be in use.', 'danger');
}

redirect('products.php');

<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('stock_out.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$reason    = $_POST['reason'] ?? 'other';
$notes     = trim($_POST['notes'] ?? '');
$validReasons = ['damage', 'return', 'internal_use', 'other'];

if ($productId <= 0 || !in_array($reason, $validReasons, true)) {
    flash_set('Please fill in all required fields.', 'danger');
    redirect('stock_out.php');
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    flash_set('Invalid product.', 'danger');
    redirect('stock_out.php');
}

$pdo->beginTransaction();
try {
    if ($product['tracking_type'] === 'bulk') {
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        if ($qty > (int) $product['stock_qty']) {
            throw new RuntimeException('Only ' . $product['stock_qty'] . ' in stock.');
        }
        $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?')->execute([$qty, $productId]);
        $pdo->prepare("INSERT INTO stock_out (product_id, tracking_type, serial_number_id, quantity, reason, notes, created_by) VALUES (?, 'bulk', NULL, ?, ?, ?, ?)")
            ->execute([$productId, $qty, $reason, $notes !== '' ? $notes : null, $_SESSION['user_id']]);
    } else {
        $barcode = trim($_POST['serial_barcode'] ?? '');
        if ($barcode === '') {
            throw new RuntimeException('Please scan the unit barcode.');
        }
        $sStmt = $pdo->prepare("SELECT * FROM serial_numbers WHERE serial_barcode = ? AND product_id = ? FOR UPDATE");
        $sStmt->execute([$barcode, $productId]);
        $serial = $sStmt->fetch();
        if (!$serial) {
            throw new RuntimeException('Barcode not found for this product.');
        }
        if ($serial['status'] !== 'in_stock') {
            throw new RuntimeException('This unit is already marked ' . str_replace('_', ' ', $serial['status']) . '.');
        }
        $statusMap = ['damage' => 'damaged', 'return' => 'returned', 'internal_use' => 'internal_use', 'other' => 'other'];
        $pdo->prepare('UPDATE serial_numbers SET status = ? WHERE id = ?')->execute([$statusMap[$reason], $serial['id']]);
        $pdo->prepare("INSERT INTO stock_out (product_id, tracking_type, serial_number_id, quantity, reason, notes, created_by) VALUES (?, 'serialized', ?, 1, ?, ?, ?)")
            ->execute([$productId, $serial['id'], $reason, $notes !== '' ? $notes : null, $_SESSION['user_id']]);
    }

    $pdo->commit();
    flash_set('Stock-out recorded.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set($e->getMessage(), 'danger');
}

redirect('stock_out.php');

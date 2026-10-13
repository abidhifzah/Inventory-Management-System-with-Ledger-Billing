<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('stock_in.php');
}

$productId     = (int) ($_POST['product_id'] ?? 0);
$quantity      = (int) ($_POST['quantity'] ?? 0);
$unitPrice     = (float) ($_POST['unit_price'] ?? 0);
$purchaseDate  = $_POST['purchase_date'] ?? date('Y-m-d');
$paymentStatus = $_POST['payment_status'] ?? 'due';
$amountPaid    = (float) ($_POST['amount_paid'] ?? 0);

if ($productId <= 0 || $quantity <= 0) {
    flash_set('Please choose a product and a valid quantity.', 'danger');
    redirect('stock_in.php');
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND tracking_type='bulk'");
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    flash_set('Invalid product.', 'danger');
    redirect('stock_in.php');
}

try {
    $vendorId = resolve_vendor_id($pdo, $_POST);
} catch (RuntimeException $e) {
    flash_set($e->getMessage(), 'danger');
    redirect('stock_in.php');
}

$totalAmount = $quantity * $unitPrice;
if ($paymentStatus === 'paid') {
    $amountPaid = $totalAmount;
} elseif ($paymentStatus === 'due') {
    $amountPaid = 0;
}
$amountPaid = min($amountPaid, $totalAmount);
$amountDue  = $totalAmount - $amountPaid;
if ($amountDue <= 0) {
    $paymentStatus = 'paid';
} elseif ($amountPaid > 0) {
    $paymentStatus = 'partial';
} else {
    $paymentStatus = 'due';
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO vendor_purchases (vendor_id, product_id, tracking_type, quantity, unit_price, total_amount, amount_paid, amount_due, payment_status, purchase_date)
        VALUES (?, ?, 'bulk', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$vendorId, $productId, $quantity, $unitPrice, $totalAmount, $amountPaid, $amountDue, $paymentStatus, $purchaseDate]);

    $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$quantity, $productId]);

    $pdo->commit();
    flash_set("Received {$quantity} unit(s) of {$product['name']}.");
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('Could not save stock-in record: ' . $e->getMessage(), 'danger');
}

redirect('stock_in.php?type=bulk');

function resolve_vendor_id(PDO $pdo, array $post): int
{
    if (($post['vendor_id'] ?? '') === 'new') {
        $name = trim($post['new_vendor_name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Please enter a name for the new vendor.');
        }
        $phone = trim($post['new_vendor_phone'] ?? '');
        $stmt = $pdo->prepare('INSERT INTO vendors (name, phone) VALUES (?, ?)');
        $stmt->execute([$name, $phone !== '' ? $phone : null]);
        return (int) $pdo->lastInsertId();
    }
    $vendorId = (int) ($post['vendor_id'] ?? 0);
    if ($vendorId <= 0) {
        throw new RuntimeException('Please select a vendor.');
    }
    return $vendorId;
}

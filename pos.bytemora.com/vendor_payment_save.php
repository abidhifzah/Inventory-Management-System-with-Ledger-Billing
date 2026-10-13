<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('vendors.php');
}

$vendorId  = (int) ($_POST['vendor_id'] ?? 0);
$purchaseId = (int) ($_POST['vendor_purchase_id'] ?? 0);
$amount    = (float) ($_POST['amount'] ?? 0);
$notes     = trim($_POST['notes'] ?? '');

if ($vendorId <= 0 || $purchaseId <= 0 || $amount <= 0) {
    flash_set('Please enter a valid payment amount.', 'danger');
    redirect('vendor_view.php?id=' . $vendorId);
}

$stmt = $pdo->prepare('SELECT * FROM vendor_purchases WHERE id = ? AND vendor_id = ?');
$stmt->execute([$purchaseId, $vendorId]);
$purchase = $stmt->fetch();
if (!$purchase) {
    flash_set('Purchase record not found.', 'danger');
    redirect('vendor_view.php?id=' . $vendorId);
}

$amount = min($amount, (float) $purchase['amount_due']);
if ($amount <= 0) {
    flash_set('This purchase is already fully paid.', 'danger');
    redirect('vendor_view.php?id=' . $vendorId);
}

$newPaid = (float) $purchase['amount_paid'] + $amount;
$newDue  = round((float) $purchase['total_amount'] - $newPaid, 2);
$status  = $newDue <= 0 ? 'paid' : 'partial';

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO vendor_payments (vendor_id, vendor_purchase_id, amount, payment_date, notes) VALUES (?,?,?,CURDATE(),?)')
        ->execute([$vendorId, $purchaseId, $amount, $notes !== '' ? $notes : null]);
    $pdo->prepare('UPDATE vendor_purchases SET amount_paid=?, amount_due=?, payment_status=? WHERE id=?')
        ->execute([$newPaid, max(0, $newDue), $status, $purchaseId]);
    $pdo->commit();
    flash_set('Payment of ' . money($amount) . ' recorded.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('Could not record payment.', 'danger');
}

redirect('vendor_view.php?id=' . $vendorId);

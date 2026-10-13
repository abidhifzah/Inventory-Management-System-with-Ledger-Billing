<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customers.php');
}

$customerId = (int) ($_POST['customer_id'] ?? 0);
$saleId     = (int) ($_POST['sale_id'] ?? 0);
$amount     = (float) ($_POST['amount'] ?? 0);
$notes      = trim($_POST['notes'] ?? '');

if ($customerId <= 0 || $saleId <= 0 || $amount <= 0) {
    flash_set('Please enter a valid payment amount.', 'danger');
    redirect('customer_view.php?id=' . $customerId);
}

$stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ? AND customer_id = ?');
$stmt->execute([$saleId, $customerId]);
$sale = $stmt->fetch();
if (!$sale) {
    flash_set('Invoice not found.', 'danger');
    redirect('customer_view.php?id=' . $customerId);
}

$amount = min($amount, (float) $sale['balance_due']);
if ($amount <= 0) {
    flash_set('This invoice is already fully paid.', 'danger');
    redirect('customer_view.php?id=' . $customerId);
}

$newPaid = (float) $sale['amount_paid'] + $amount;
$newDue  = round((float) $sale['total'] - $newPaid, 2);
$status  = $newDue <= 0 ? 'paid' : 'partial';

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO customer_payments (customer_id, sale_id, amount, payment_date, notes) VALUES (?,?,?,CURDATE(),?)')
        ->execute([$customerId, $saleId, $amount, $notes !== '' ? $notes : null]);
    $pdo->prepare('UPDATE sales SET amount_paid=?, balance_due=?, payment_status=? WHERE id=?')
        ->execute([$newPaid, max(0, $newDue), $status, $saleId]);
    $pdo->commit();
    flash_set('Payment of ' . money($amount) . ' recorded.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('Could not record payment.', 'danger');
}

redirect('customer_view.php?id=' . $customerId);

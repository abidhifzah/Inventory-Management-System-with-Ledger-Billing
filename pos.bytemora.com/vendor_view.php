<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM vendors WHERE id = ?');
$stmt->execute([$id]);
$vendor = $stmt->fetch();
if (!$vendor) {
    flash_set('Vendor not found.', 'danger');
    redirect('vendors.php');
}

$pageTitle = 'Vendor: ' . $vendor['name'];

$purchases = $pdo->prepare("
    SELECT vp.*, p.name AS product_name FROM vendor_purchases vp
    JOIN products p ON p.id = vp.product_id
    WHERE vp.vendor_id = ? ORDER BY vp.id DESC
");
$purchases->execute([$id]);
$purchases = $purchases->fetchAll();

$payments = $pdo->prepare('SELECT * FROM vendor_payments WHERE vendor_id = ? ORDER BY id DESC');
$payments->execute([$id]);
$payments = $payments->fetchAll();

$outstandingPurchases = array_filter($purchases, fn($p) => $p['amount_due'] > 0);
$totalBalance = array_sum(array_column($purchases, 'amount_due'));

require __DIR__ . '/includes/header.php';
?>

<div class="card">
  <h3 class="mt-0"><?= h($vendor['name']) ?> <span class="text-muted" style="font-weight:400;">(<?= h($vendor['phone']) ?>)</span></h3>
  <p>Outstanding Balance: <strong style="color:<?= $totalBalance > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= money($totalBalance) ?></strong></p>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Record a Payment</h3>
    <?php if (!$outstandingPurchases): ?>
      <p class="text-muted">No outstanding purchases for this vendor.</p>
    <?php else: ?>
    <form method="post" action="vendor_payment_save.php">
      <input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>">
      <div class="form-group">
        <label>Purchase</label>
        <select name="vendor_purchase_id" required>
          <?php foreach ($outstandingPurchases as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= h($p['product_name']) ?> (<?= h($p['purchase_date']) ?>) &mdash; Due <?= money($p['amount_due']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Amount (PKR)</label>
        <input type="number" step="0.01" min="0.01" name="amount" required>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <input type="text" name="notes" placeholder="Optional">
      </div>
      <button type="submit" class="btn">Record Payment</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Payment History</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Amount</th><th>Notes</th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="3" class="text-muted">No payments recorded.</td></tr><?php endif; ?>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= h($p['payment_date']) ?></td><td><?= money($p['amount']) ?></td><td><?= h($p['notes']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <h3>Purchase History</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$purchases): ?><tr><td colspan="9" class="text-muted">No purchases yet.</td></tr><?php endif; ?>
      <?php foreach ($purchases as $p): ?>
        <tr>
          <td><?= h($p['purchase_date']) ?></td>
          <td><?= h($p['product_name']) ?></td>
          <td><span class="tag-pill"><?= h($p['tracking_type']) ?></span></td>
          <td><?= (int) $p['quantity'] ?></td>
          <td><?= money($p['unit_price']) ?></td>
          <td><?= money($p['total_amount']) ?></td>
          <td><?= money($p['amount_paid']) ?></td>
          <td><?= money($p['amount_due']) ?></td>
          <td><?= payment_status_badge($p['payment_status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

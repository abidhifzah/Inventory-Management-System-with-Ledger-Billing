<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash_set('Customer not found.', 'danger');
    redirect('customers.php');
}

$pageTitle = 'Customer: ' . $customer['name'];

$sales = $pdo->prepare('SELECT * FROM sales WHERE customer_id = ? ORDER BY id DESC');
$sales->execute([$id]);
$sales = $sales->fetchAll();

$itemsStmt = $pdo->prepare("
    SELECT si.*, p.name AS product_name, sn.serial_barcode
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    LEFT JOIN serial_numbers sn ON sn.id = si.serial_number_id
    WHERE si.sale_id = ?
");

$payments = $pdo->prepare('SELECT * FROM customer_payments WHERE customer_id = ? ORDER BY id DESC');
$payments->execute([$id]);
$payments = $payments->fetchAll();

$outstandingSales = array_filter($sales, fn($s) => $s['balance_due'] > 0);
$totalBalance = array_sum(array_column($sales, 'balance_due'));

require __DIR__ . '/includes/header.php';
?>

<div class="card">
  <h3 class="mt-0"><?= h($customer['name']) ?> <span class="text-muted" style="font-weight:400;">(<?= h($customer['phone']) ?>)</span></h3>
  <p>Outstanding Balance: <strong class="<?= $totalBalance > 0 ? '' : '' ?>" style="color:<?= $totalBalance > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= money($totalBalance) ?></strong></p>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Record a Payment</h3>
    <?php if (!$outstandingSales): ?>
      <p class="text-muted">No outstanding invoices for this customer.</p>
    <?php else: ?>
    <form method="post" action="customer_payment_save.php">
      <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
      <div class="form-group">
        <label>Invoice</label>
        <select name="sale_id" required>
          <?php foreach ($outstandingSales as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= h($s['invoice_no']) ?> &mdash; Balance <?= money($s['balance_due']) ?></option>
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
  <?php if (!$sales): ?><p class="text-muted">No purchases yet.</p><?php endif; ?>
  <?php foreach ($sales as $s): ?>
    <?php $itemsStmt->execute([$s['id']]); $lineItems = $itemsStmt->fetchAll(); ?>
    <div class="table-wrap" style="margin-bottom:14px;">
      <table>
        <thead>
          <tr>
            <th colspan="5">
              <a href="invoice.php?id=<?= (int) $s['id'] ?>"><?= h($s['invoice_no']) ?></a>
              &nbsp;<?= h($s['created_at']) ?>&nbsp;
              <?= payment_status_badge($s['payment_status']) ?>
              &nbsp;Total: <?= money($s['total']) ?> &nbsp; Balance: <?= money($s['balance_due']) ?>
            </th>
          </tr>
          <tr><th>Product</th><th>Serial</th><th>Qty</th><th>Price</th><th>Line Total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($lineItems as $it): ?>
            <tr>
              <td><?= h($it['product_name']) ?></td>
              <td><?= $it['serial_barcode'] ? h($it['serial_barcode']) : '&mdash;' ?></td>
              <td><?= (int) $it['quantity'] ?></td>
              <td><?= money($it['unit_price']) ?></td>
              <td><?= money($it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

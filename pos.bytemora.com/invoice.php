<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone
    FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) {
    flash_set('Invoice not found.', 'danger');
    redirect('billing.php');
}

$items = $pdo->prepare("
    SELECT si.*, p.name AS product_name, sn.serial_barcode
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    LEFT JOIN serial_numbers sn ON sn.id = si.serial_number_id
    WHERE si.sale_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();

$pageTitle = 'Invoice ' . $sale['invoice_no'];
require __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:640px;margin:0 auto;">
  <div class="no-print" style="text-align:right;margin-bottom:10px;">
    <button onclick="window.print()" class="btn btn-secondary">Print Receipt</button>
    <a href="billing.php" class="btn">New Sale</a>
  </div>

  <h2 class="mt-0"><?= h(APP_NAME) ?></h2>
  <p class="text-muted mb-0">Invoice: <strong><?= h($sale['invoice_no']) ?></strong> &nbsp;|&nbsp; <?= h(date('d M Y, h:i A', strtotime($sale['created_at']))) ?></p>
  <p><strong>Customer:</strong> <?= h($sale['customer_name'] ?? 'Walk-in') ?> &nbsp; <?= h($sale['customer_phone'] ?? '') ?></p>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Serial</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
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

  <div class="totals-box">
    <div class="row grand"><span>Total</span><span><?= money($sale['total']) ?></span></div>
    <div class="row"><span>Amount Paid</span><span><?= money($sale['amount_paid']) ?></span></div>
    <div class="row balance"><span>Balance Due</span><span><?= money($sale['balance_due']) ?></span></div>
  </div>
  <p><?= payment_status_badge($sale['payment_status']) ?></p>
  <p class="text-center text-muted" style="margin-top:20px;">Thank you for your business!</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';

$todaySales = $pdo->query("SELECT COALESCE(SUM(total),0) AS t, COUNT(*) AS c FROM sales WHERE DATE(created_at) = CURDATE()")->fetch();
$monthSales = $pdo->query("SELECT COALESCE(SUM(total),0) AS t FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch();
$customerDue = $pdo->query("SELECT COALESCE(SUM(balance_due),0) AS t FROM sales WHERE balance_due > 0")->fetch();
$vendorDue = $pdo->query("SELECT COALESCE(SUM(amount_due),0) AS t FROM vendor_purchases WHERE amount_due > 0")->fetch();

$lowStockBulk = $pdo->query("SELECT COUNT(*) FROM products WHERE tracking_type='bulk' AND is_active=1 AND stock_qty <= low_stock_threshold")->fetchColumn();
$lowStockSerialized = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT p.id, p.low_stock_threshold, COUNT(sn.id) AS in_stock
        FROM products p
        LEFT JOIN serial_numbers sn ON sn.product_id = p.id AND sn.status = 'in_stock'
        WHERE p.tracking_type = 'serialized' AND p.is_active = 1
        GROUP BY p.id
        HAVING in_stock <= p.low_stock_threshold
    ) x
")->fetchColumn();

$recentSales = $pdo->query("
    SELECT s.*, c.name AS customer_name FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    ORDER BY s.id DESC LIMIT 8
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-box">
    <div class="label">Today's Sales</div>
    <div class="value"><?= money($todaySales['t']) ?></div>
    <div class="text-muted"><?= (int)$todaySales['c'] ?> invoice(s)</div>
  </div>
  <div class="stat-box">
    <div class="label">This Month</div>
    <div class="value"><?= money($monthSales['t']) ?></div>
  </div>
  <div class="stat-box danger">
    <div class="label">Customer Dues Outstanding</div>
    <div class="value"><?= money($customerDue['t']) ?></div>
  </div>
  <div class="stat-box danger">
    <div class="label">Vendor Payments Outstanding</div>
    <div class="value"><?= money($vendorDue['t']) ?></div>
  </div>
  <div class="stat-box warn">
    <div class="label">Low Stock Products</div>
    <div class="value"><?= (int)$lowStockBulk + (int)$lowStockSerialized ?></div>
    <div class="text-muted"><a href="reports.php#stock">View stock report</a></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Quick Actions</h3>
    <div class="form-row">
      <a href="billing.php" class="btn">New Sale</a>
      <a href="stock_in.php" class="btn btn-secondary">Receive Stock</a>
      <a href="products.php" class="btn btn-secondary">Add Product</a>
    </div>
  </div>
  <div class="card">
    <h3>Recent Invoices</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$recentSales): ?>
          <tr><td colspan="4" class="text-muted">No sales yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentSales as $s): ?>
          <tr>
            <td><a href="invoice.php?id=<?= (int)$s['id'] ?>"><?= h($s['invoice_no']) ?></a></td>
            <td><?= h($s['customer_name'] ?? 'Walk-in') ?></td>
            <td><?= money($s['total']) ?></td>
            <td><?= payment_status_badge($s['payment_status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Reports & Analysis';

$range = $_GET['range'] ?? 'month';
$today = date('Y-m-d');
switch ($range) {
    case 'today':
        $from = $today; $to = $today; break;
    case 'week':
        $from = date('Y-m-d', strtotime('-6 days')); $to = $today; break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to'] ?? $today;
        break;
    case 'month':
    default:
        $from = date('Y-m-01'); $to = $today; break;
}

$salesSummary = $pdo->prepare("
    SELECT COUNT(*) AS invoice_count, COALESCE(SUM(total),0) AS total_sales,
           COALESCE(SUM(amount_paid),0) AS total_collected, COALESCE(SUM(balance_due),0) AS total_due
    FROM sales WHERE DATE(created_at) BETWEEN ? AND ?
");
$salesSummary->execute([$from, $to]);
$salesSummary = $salesSummary->fetch();

$bestSellers = $pdo->prepare("
    SELECT p.name, p.category, SUM(si.quantity) AS qty_sold, SUM(si.line_total) AS revenue
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY p.id ORDER BY qty_sold DESC LIMIT 10
");
$bestSellers->execute([$from, $to]);
$bestSellers = $bestSellers->fetchAll();

$stockLevels = $pdo->query("
    SELECT p.id, p.name, p.category, p.tracking_type, p.low_stock_threshold,
        CASE WHEN p.tracking_type = 'bulk' THEN p.stock_qty
             ELSE (SELECT COUNT(*) FROM serial_numbers sn WHERE sn.product_id = p.id AND sn.status = 'in_stock')
        END AS stock
    FROM products p
    WHERE p.is_active = 1
    ORDER BY p.name
")->fetchAll();

$vendorDues = $pdo->query("
    SELECT v.id, v.name, SUM(vp.amount_due) AS due
    FROM vendor_purchases vp JOIN vendors v ON v.id = vp.vendor_id
    WHERE vp.amount_due > 0 GROUP BY v.id ORDER BY due DESC
")->fetchAll();

$customerDues = $pdo->query("
    SELECT c.id, c.name, c.phone, SUM(s.balance_due) AS due
    FROM sales s JOIN customers c ON c.id = s.customer_id
    WHERE s.balance_due > 0 GROUP BY c.id ORDER BY due DESC
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="card">
  <form method="get" class="form-row" style="align-items:flex-end;">
    <div class="form-group" style="max-width:160px;">
      <label>Quick Range</label>
      <select name="range" onchange="this.form.submit()">
        <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom</option>
      </select>
    </div>
    <?php if ($range === 'custom'): ?>
      <div class="form-group"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
      <div class="form-group"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
      <button type="submit" class="btn">Apply</button>
    <?php endif; ?>
  </form>
</div>

<div class="stat-grid">
  <div class="stat-box">
    <div class="label">Sales (<?= h($from) ?> to <?= h($to) ?>)</div>
    <div class="value"><?= money($salesSummary['total_sales']) ?></div>
    <div class="text-muted"><?= (int) $salesSummary['invoice_count'] ?> invoice(s)</div>
  </div>
  <div class="stat-box">
    <div class="label">Collected</div>
    <div class="value"><?= money($salesSummary['total_collected']) ?></div>
  </div>
  <div class="stat-box danger">
    <div class="label">Outstanding (period)</div>
    <div class="value"><?= money($salesSummary['total_due']) ?></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Best-Selling Products</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php if (!$bestSellers): ?><tr><td colspan="3" class="text-muted">No sales in this period.</td></tr><?php endif; ?>
        <?php foreach ($bestSellers as $b): ?>
          <tr><td><?= h($b['name']) ?></td><td><?= (int) $b['qty_sold'] ?></td><td><?= money($b['revenue']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" id="stock">
    <h3>Current Stock Levels</h3>
    <div class="table-wrap" style="max-height:360px;overflow-y:auto;">
      <table>
        <thead><tr><th>Product</th><th>Type</th><th>Stock</th></tr></thead>
        <tbody>
        <?php foreach ($stockLevels as $s): ?>
          <?php $low = $s['stock'] <= $s['low_stock_threshold']; ?>
          <tr>
            <td><?= h($s['name']) ?></td>
            <td><span class="tag-pill"><?= h($s['tracking_type']) ?></span></td>
            <td><?= (int) $s['stock'] ?> <?= $low ? '<span class="badge badge-warning">Low Stock</span>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Outstanding Vendor Payments</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Vendor</th><th>Amount Due</th></tr></thead>
        <tbody>
        <?php if (!$vendorDues): ?><tr><td colspan="2" class="text-muted">No outstanding vendor payments.</td></tr><?php endif; ?>
        <?php foreach ($vendorDues as $v): ?>
          <tr><td><a href="vendor_view.php?id=<?= (int) $v['id'] ?>"><?= h($v['name']) ?></a></td><td><?= money($v['due']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Outstanding Customer Dues</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Customer</th><th>Phone</th><th>Amount Due</th></tr></thead>
        <tbody>
        <?php if (!$customerDues): ?><tr><td colspan="3" class="text-muted">No outstanding customer dues.</td></tr><?php endif; ?>
        <?php foreach ($customerDues as $c): ?>
          <tr><td><a href="customer_view.php?id=<?= (int) $c['id'] ?>"><?= h($c['name']) ?></a></td><td><?= h($c['phone']) ?></td><td><?= money($c['due']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

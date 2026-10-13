<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Vendors';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vendor'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name !== '') {
        $pdo->prepare('INSERT INTO vendors (name, phone) VALUES (?, ?)')->execute([$name, $phone !== '' ? $phone : null]);
        flash_set('Vendor added.');
    } else {
        flash_set('Vendor name is required.', 'danger');
    }
    redirect('vendors.php');
}

$vendors = $pdo->query("
    SELECT v.*,
        COALESCE(SUM(vp.total_amount),0) AS total_purchased,
        COALESCE(SUM(vp.amount_due),0) AS balance
    FROM vendors v
    LEFT JOIN vendor_purchases vp ON vp.vendor_id = v.id
    GROUP BY v.id ORDER BY v.name
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-2">
  <div class="card">
    <h3>Add Vendor</h3>
    <form method="post">
      <input type="hidden" name="add_vendor" value="1">
      <div class="form-group">
        <label>Vendor Name</label>
        <input type="text" name="name" required>
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone">
      </div>
      <button type="submit" class="btn">Add Vendor</button>
    </form>
  </div>
  <div class="card">
    <h3>All Vendors</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Total Purchased</th><th>Outstanding</th><th></th></tr></thead>
        <tbody>
        <?php if (!$vendors): ?><tr><td colspan="5" class="text-muted">No vendors yet.</td></tr><?php endif; ?>
        <?php foreach ($vendors as $v): ?>
          <tr>
            <td><?= h($v['name']) ?></td>
            <td><?= h($v['phone']) ?></td>
            <td><?= money($v['total_purchased']) ?></td>
            <td><?= $v['balance'] > 0 ? '<span class="badge badge-danger">' . money($v['balance']) . '</span>' : money(0) ?></td>
            <td><a href="vendor_view.php?id=<?= (int) $v['id'] ?>" class="btn btn-small btn-secondary">View / Pay</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

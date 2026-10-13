<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Customers';

$q = trim($_GET['q'] ?? '');
$sql = "
    SELECT c.*,
        COALESCE(SUM(s.total),0) AS total_purchased,
        COALESCE(SUM(s.balance_due),0) AS balance
    FROM customers c
    LEFT JOIN sales s ON s.customer_id = c.id
";
$params = [];
if ($q !== '') {
    $sql .= " WHERE c.name LIKE ? OR c.phone LIKE ? ";
    $params = ["%$q%", "%$q%"];
}
$sql .= " GROUP BY c.id ORDER BY c.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="card">
  <form method="get" class="form-row">
    <input type="search" name="q" placeholder="Search by name or phone" value="<?= h($q) ?>">
    <button type="submit" class="btn">Search</button>
  </form>
</div>

<div class="card">
  <h3>All Customers</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th>Total Purchased</th><th>Balance Due</th><th></th></tr></thead>
      <tbody>
      <?php if (!$customers): ?><tr><td colspan="5" class="text-muted">No customers found.</td></tr><?php endif; ?>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= h($c['name']) ?></td>
          <td><?= h($c['phone']) ?></td>
          <td><?= money($c['total_purchased']) ?></td>
          <td><?= $c['balance'] > 0 ? '<span class="badge badge-danger">' . money($c['balance']) . '</span>' : money(0) ?></td>
          <td><a href="customer_view.php?id=<?= (int) $c['id'] ?>" class="btn btn-small btn-secondary">View / Pay</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

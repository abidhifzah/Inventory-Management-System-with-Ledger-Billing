<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Products';

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editProduct = $stmt->fetch();
}

$products = $pdo->query("SELECT * FROM products ORDER BY is_active DESC, name ASC")->fetchAll();

// Attach serialized in-stock counts in one query to avoid N+1.
$serialCounts = $pdo->query("SELECT product_id, COUNT(*) AS c FROM serial_numbers WHERE status='in_stock' GROUP BY product_id")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-2">
  <div class="card">
    <h3><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h3>
    <form method="post" action="product_save.php">
      <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>"><?php endif; ?>
      <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="name" required value="<?= h($editProduct['name'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category">
            <?php foreach (['toner', 'printer', 'laptop', 'scanner', 'other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= (($editProduct['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tracking Type</label>
          <select name="tracking_type" id="trackingType" <?= $editProduct ? 'disabled' : '' ?> required>
            <option value="bulk" <?= (($editProduct['tracking_type'] ?? '') === 'bulk') ? 'selected' : '' ?>>Bulk (quantity count)</option>
            <option value="serialized" <?= (($editProduct['tracking_type'] ?? '') === 'serialized') ? 'selected' : '' ?>>Serialized (unique units)</option>
          </select>
          <?php if ($editProduct): ?>
            <input type="hidden" name="tracking_type" value="<?= h($editProduct['tracking_type']) ?>">
            <small class="text-muted">Tracking type can't change after creation.</small>
          <?php endif; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Selling Price (PKR)</label>
          <input type="number" step="0.01" min="0" name="unit_price" required value="<?= h($editProduct['unit_price'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Low Stock Alert Threshold</label>
          <input type="number" min="0" name="low_stock_threshold" value="<?= h($editProduct['low_stock_threshold'] ?? 5) ?>">
        </div>
      </div>
      <div class="form-group" id="barcodeGroup">
        <label>Barcode (bulk products only &mdash; leave blank to auto-generate)</label>
        <input type="text" name="barcode" value="<?= h($editProduct['barcode'] ?? '') ?>" placeholder="Scan or type barcode">
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="is_active" <?= (!$editProduct || $editProduct['is_active']) ? 'checked' : '' ?> style="width:auto;"> Active</label>
      </div>
      <div class="form-row">
        <button type="submit" class="btn">Save Product</button>
        <?php if ($editProduct): ?><a href="products.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>How tracking types work</h3>
    <p><strong>Bulk</strong> &mdash; one shared barcode (e.g. Toner). Stock is a quantity count. Scanning it at checkout asks how many units to sell.</p>
    <p><strong>Serialized</strong> &mdash; no product-level barcode. Every physical unit gets its own barcode/serial number when it's received via <a href="stock_in.php">Stock In</a>. Scanning a unit's barcode sells that exact unit.</p>
  </div>
</div>

<div class="card">
  <h3>All Products</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Name</th><th>Category</th><th>Type</th><th>Barcode</th><th>Price</th><th>In Stock</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <?php
          $stock = $p['tracking_type'] === 'bulk' ? (int) $p['stock_qty'] : (int) ($serialCounts[$p['id']] ?? 0);
          $low = $stock <= (int) $p['low_stock_threshold'];
        ?>
        <tr>
          <td><?= h($p['name']) ?></td>
          <td><?= h(ucfirst($p['category'])) ?></td>
          <td><span class="tag-pill"><?= $p['tracking_type'] === 'bulk' ? 'Bulk' : 'Serialized' ?></span></td>
          <td><?= $p['tracking_type'] === 'bulk' ? h($p['barcode']) : '<span class="text-muted">per-unit</span>' ?></td>
          <td><?= money($p['unit_price']) ?></td>
          <td><?= $stock ?> <?= $low ? '<span class="badge badge-warning">Low</span>' : '' ?></td>
          <td><?= $p['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge">Inactive</span>' ?></td>
          <td>
            <a href="products.php?edit=<?= (int) $p['id'] ?>" class="btn btn-small btn-secondary">Edit</a>
            <?php if ($p['tracking_type'] === 'serialized'): ?>
              <a href="stock_in.php?product=<?= (int) $p['id'] ?>&type=serialized" class="btn btn-small btn-outline" style="color:var(--primary);border-color:var(--primary);">View/Add Units</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const trackingSelect = document.getElementById('trackingType');
const barcodeGroup = document.getElementById('barcodeGroup');
function toggleBarcodeField() {
  if (!trackingSelect) return;
  barcodeGroup.style.display = trackingSelect.value === 'bulk' ? '' : 'none';
}
if (trackingSelect) {
  trackingSelect.addEventListener('change', toggleBarcodeField);
  toggleBarcodeField();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

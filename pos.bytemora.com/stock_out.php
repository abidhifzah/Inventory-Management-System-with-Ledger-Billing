<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Stock Out';

$products = $pdo->query("SELECT id, name, tracking_type FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();

$records = $pdo->query("
    SELECT so.*, p.name AS product_name, sn.serial_barcode
    FROM stock_out so
    JOIN products p ON p.id = so.product_id
    LEFT JOIN serial_numbers sn ON sn.id = so.serial_number_id
    ORDER BY so.id DESC LIMIT 100
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-2">
  <div class="card">
    <h3>Manual Stock Out</h3>
    <p class="text-muted">Use this for damaged items, returns to vendor, or internal use. Sales are logged automatically.</p>
    <form method="post" action="stock_out_save.php" id="stockOutForm">
      <div class="form-group">
        <label>Product</label>
        <select name="product_id" id="stockOutProduct" required>
          <option value="">-- Select product --</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>" data-type="<?= h($p['tracking_type']) ?>"><?= h($p['name']) ?> (<?= $p['tracking_type'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="qtyField">
        <label>Quantity</label>
        <input type="number" name="quantity" min="1" value="1">
      </div>
      <div class="form-group hidden" id="barcodeField">
        <label>Scan / Enter Unit Barcode</label>
        <input type="text" name="serial_barcode" autocomplete="off">
      </div>
      <div class="form-group">
        <label>Reason</label>
        <select name="reason" required>
          <option value="damage">Damaged</option>
          <option value="return">Returned to Vendor</option>
          <option value="internal_use">Internal Use</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" rows="2" placeholder="Optional"></textarea>
      </div>
      <button type="submit" class="btn">Record Stock Out</button>
    </form>
  </div>

  <div class="card">
    <h3>How this works</h3>
    <p><strong>Bulk products:</strong> enter how many units to remove from the stock count.</p>
    <p><strong>Serialized products:</strong> scan the exact unit's barcode &mdash; it must currently be marked "in stock".</p>
  </div>
</div>

<div class="card">
  <h3>Stock Out History</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Product</th><th>Serial</th><th>Qty</th><th>Reason</th><th>Notes</th></tr></thead>
      <tbody>
      <?php if (!$records): ?><tr><td colspan="6" class="text-muted">No stock-out records yet.</td></tr><?php endif; ?>
      <?php foreach ($records as $r): ?>
        <tr>
          <td><?= h($r['created_at']) ?></td>
          <td><?= h($r['product_name']) ?></td>
          <td><?= $r['serial_barcode'] ? h($r['serial_barcode']) : '&mdash;' ?></td>
          <td><?= (int) $r['quantity'] ?></td>
          <td><span class="tag-pill"><?= h(ucfirst(str_replace('_', ' ', $r['reason']))) ?></span></td>
          <td><?= h($r['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const productSelect = document.getElementById('stockOutProduct');
const qtyField = document.getElementById('qtyField');
const barcodeField = document.getElementById('barcodeField');
function toggleFields() {
  const opt = productSelect.options[productSelect.selectedIndex];
  const type = opt ? opt.dataset.type : '';
  qtyField.classList.toggle('hidden', type === 'serialized');
  barcodeField.classList.toggle('hidden', type !== 'serialized');
}
productSelect.addEventListener('change', toggleFields);
toggleFields();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

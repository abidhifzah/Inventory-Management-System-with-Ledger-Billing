<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Stock In';

$bulkProducts = $pdo->query("SELECT * FROM products WHERE tracking_type='bulk' AND is_active=1 ORDER BY name")->fetchAll();
$serializedProducts = $pdo->query("SELECT * FROM products WHERE tracking_type='serialized' AND is_active=1 ORDER BY name")->fetchAll();
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();

$activeTab = ($_GET['type'] ?? 'bulk') === 'serialized' ? 'serialized' : 'bulk';
$preselectProduct = (int) ($_GET['product'] ?? 0);

$recent = $pdo->query("
    SELECT vp.*, v.name AS vendor_name, p.name AS product_name
    FROM vendor_purchases vp
    JOIN vendors v ON v.id = vp.vendor_id
    JOIN products p ON p.id = vp.product_id
    ORDER BY vp.id DESC LIMIT 10
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="tabs">
  <a href="?type=bulk" class="<?= $activeTab === 'bulk' ? 'active' : '' ?>">Bulk Products</a>
  <a href="?type=serialized" class="<?= $activeTab === 'serialized' ? 'active' : '' ?>">Serialized Products</a>
</div>

<?php $vendorFieldId = 0; ?>
<?php function vendor_select_block($pdo, $vendors, $fieldName) { ?>
  <div class="form-group">
    <label>Vendor</label>
    <select name="<?= $fieldName ?>" class="vendor-select" required>
      <option value="">-- Select vendor --</option>
      <?php foreach ($vendors as $v): ?>
        <option value="<?= (int) $v['id'] ?>"><?= h($v['name']) ?></option>
      <?php endforeach; ?>
      <option value="new">+ Add new vendor</option>
    </select>
  </div>
  <div class="form-row new-vendor-fields hidden">
    <div class="form-group">
      <label>New Vendor Name</label>
      <input type="text" name="new_vendor_name">
    </div>
    <div class="form-group">
      <label>New Vendor Phone</label>
      <input type="tel" name="new_vendor_phone">
    </div>
  </div>
<?php } ?>

<?php if ($activeTab === 'bulk'): ?>
  <div class="card">
    <h3>Receive Bulk Stock</h3>
    <?php if (!$bulkProducts): ?>
      <p class="text-muted">No bulk products yet. <a href="products.php">Add one first.</a></p>
    <?php else: ?>
    <form method="post" action="stock_in_bulk_save.php" class="vendor-form">
      <div class="form-row">
        <div class="form-group">
          <label>Product</label>
          <select name="product_id" required>
            <?php foreach ($bulkProducts as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $preselectProduct === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?> (current stock: <?= (int) $p['stock_qty'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity Received</label>
          <input type="number" name="quantity" min="1" required>
        </div>
      </div>
      <div class="form-row">
        <?php vendor_select_block($pdo, $vendors, 'vendor_id'); ?>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Purchase Price (per unit, PKR)</label>
          <input type="number" step="0.01" min="0" name="unit_price" required>
        </div>
        <div class="form-group">
          <label>Purchase Date</label>
          <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Payment Status</label>
          <select name="payment_status" id="paymentStatusBulk">
            <option value="paid">Paid in full</option>
            <option value="partial">Partially paid</option>
            <option value="due" selected>Nothing paid yet (due)</option>
          </select>
        </div>
        <div class="form-group" id="amountPaidGroupBulk">
          <label>Amount Paid Now (PKR)</label>
          <input type="number" step="0.01" min="0" name="amount_paid" value="0">
        </div>
      </div>
      <button type="submit" class="btn">Add to Stock</button>
    </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <h3>Receive Serialized Units</h3>
    <?php if (!$serializedProducts): ?>
      <p class="text-muted">No serialized products yet. <a href="products.php">Add one first.</a></p>
    <?php else: ?>
    <form method="post" action="stock_in_serial_save.php" id="serialForm" class="vendor-form">
      <div class="form-row">
        <div class="form-group">
          <label>Product</label>
          <select name="product_id" required>
            <?php foreach ($serializedProducts as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $preselectProduct === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <?php vendor_select_block($pdo, $vendors, 'vendor_id'); ?>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Purchase Price (per unit, PKR)</label>
          <input type="number" step="0.01" min="0" name="unit_price" required id="serialUnitPrice">
        </div>
        <div class="form-group">
          <label>Purchase Date</label>
          <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Payment Status</label>
          <select name="payment_status">
            <option value="paid">Paid in full</option>
            <option value="partial">Partially paid</option>
            <option value="due" selected>Nothing paid yet (due)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Amount Paid Now (PKR)</label>
          <input type="number" step="0.01" min="0" name="amount_paid" value="0">
        </div>
      </div>

      <div class="form-group">
        <label>Scan / Enter Unique Barcode for Each Unit</label>
        <input type="text" id="serialScanInput" placeholder="Scan barcode and press Enter, repeat for every unit" autocomplete="off">
      </div>
      <div class="table-wrap" style="margin-bottom:14px;">
        <table>
          <thead><tr><th>#</th><th>Barcode</th><th></th></tr></thead>
          <tbody id="serialListBody">
            <tr id="serialListEmpty"><td colspan="3" class="text-muted">No units scanned yet.</td></tr>
          </tbody>
        </table>
      </div>
      <div id="serialInputsContainer"></div>
      <button type="submit" class="btn" id="serialSubmitBtn" disabled>Save <span id="serialCountLabel">0</span> Unit(s) to Stock</button>
    </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h3>Recent Stock-In Records</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Product</th><th>Vendor</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Payment</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="7" class="text-muted">No stock-in records yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= h($r['purchase_date']) ?></td>
          <td><?= h($r['product_name']) ?> <span class="tag-pill"><?= $r['tracking_type'] ?></span></td>
          <td><?= h($r['vendor_name']) ?></td>
          <td><?= (int) $r['quantity'] ?></td>
          <td><?= money($r['unit_price']) ?></td>
          <td><?= money($r['total_amount']) ?></td>
          <td><?= payment_status_badge($r['payment_status']) ?> <?php if ($r['amount_due'] > 0): ?><span class="text-muted">due <?= money($r['amount_due']) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('.vendor-form').forEach(form => {
  const select = form.querySelector('.vendor-select');
  const newFields = form.querySelector('.new-vendor-fields');
  select.addEventListener('change', () => {
    newFields.classList.toggle('hidden', select.value !== 'new');
    newFields.querySelectorAll('input').forEach(i => i.required = select.value === 'new');
  });
});

<?php if ($activeTab === 'serialized'): ?>
const scanInput = document.getElementById('serialScanInput');
const listBody = document.getElementById('serialListBody');
const emptyRow = document.getElementById('serialListEmpty');
const inputsContainer = document.getElementById('serialInputsContainer');
const submitBtn = document.getElementById('serialSubmitBtn');
const countLabel = document.getElementById('serialCountLabel');
const scannedBarcodes = [];

function renderSerialList() {
  listBody.querySelectorAll('tr[data-row]').forEach(r => r.remove());
  inputsContainer.innerHTML = '';
  scannedBarcodes.forEach((code, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.row = '1';
    tr.innerHTML = `<td>${idx + 1}</td><td>${code}</td><td><button type="button" class="btn btn-small btn-danger" data-idx="${idx}">Remove</button></td>`;
    listBody.appendChild(tr);
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'barcodes[]';
    hidden.value = code;
    inputsContainer.appendChild(hidden);
  });
  emptyRow.style.display = scannedBarcodes.length ? 'none' : '';
  countLabel.textContent = scannedBarcodes.length;
  submitBtn.disabled = scannedBarcodes.length === 0;
}

listBody.addEventListener('click', (e) => {
  if (e.target.dataset.idx !== undefined) {
    scannedBarcodes.splice(parseInt(e.target.dataset.idx, 10), 1);
    renderSerialList();
  }
});

scanInput.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const code = scanInput.value.trim();
  scanInput.value = '';
  if (!code) return;
  if (scannedBarcodes.includes(code)) {
    alert('This barcode was already scanned in this batch.');
    return;
  }
  scannedBarcodes.push(code);
  renderSerialList();
});
scanInput.focus();
<?php endif; ?>
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

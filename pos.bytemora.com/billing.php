<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Billing';
require __DIR__ . '/includes/header.php';
?>

<div class="billing-layout">
  <div>
    <div class="card scan-box">
      <h3 class="mt-0">Scan Barcode</h3>
      <input type="text" id="scanInput" placeholder="Scan or type barcode, then Enter" autocomplete="off" autofocus>
      <p id="scanError" class="text-muted" style="min-height:20px;margin-bottom:0;"></p>
    </div>
    <div class="card">
      <h3 class="mt-0">Manual Search</h3>
      <input type="search" id="productSearchInput" placeholder="Search by product name or category">
      <div class="search-results" id="productSearchResults"></div>
    </div>
  </div>

  <div class="card">
    <h3 class="mt-0">Current Sale</h3>
    <div class="table-wrap">
      <table class="cart-table">
        <thead><tr><th>Product</th><th>Detail</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
        <tbody id="cartBody">
          <tr id="cartEmptyRow"><td colspan="6" class="cart-empty">Scan a product to begin.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="grid grid-2" style="margin-top:18px;">
      <div>
        <h3>Customer</h3>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" id="customerPhone" placeholder="03xx-xxxxxxx" autocomplete="off">
          <div class="search-results" id="customerSearchResults"></div>
        </div>
        <div class="form-group">
          <label>Customer Name</label>
          <input type="text" id="customerName" placeholder="Full name">
        </div>
      </div>
      <div>
        <h3>Payment</h3>
        <div class="totals-box">
          <div class="row"><span>Subtotal</span><span id="totalSubtotal">Rs. 0.00</span></div>
          <div class="row grand"><span>Total</span><span id="totalGrand">Rs. 0.00</span></div>
        </div>
        <div class="form-group">
          <label>Amount Paid Now (PKR)</label>
          <input type="number" id="amountPaid" min="0" step="0.01" value="0">
        </div>
        <div class="totals-box">
          <div class="row balance"><span>Balance Due</span><span id="totalBalance">Rs. 0.00</span></div>
        </div>
        <button type="button" class="btn btn-block" id="checkoutBtn" disabled>Complete Sale</button>
        <p id="checkoutError" class="text-muted" style="color:var(--danger);"></p>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script src="assets/js/billing.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>

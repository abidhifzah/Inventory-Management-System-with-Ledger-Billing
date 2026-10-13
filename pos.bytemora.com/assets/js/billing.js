(function () {
  const cart = []; // { type, product_id, name, unit_price, quantity, max_stock, serial_number_id, serial_barcode }

  const scanInput = document.getElementById('scanInput');
  const scanError = document.getElementById('scanError');
  const searchInput = document.getElementById('productSearchInput');
  const searchResults = document.getElementById('productSearchResults');
  const cartBody = document.getElementById('cartBody');
  const cartEmptyRow = document.getElementById('cartEmptyRow');
  const totalSubtotal = document.getElementById('totalSubtotal');
  const totalGrand = document.getElementById('totalGrand');
  const totalBalance = document.getElementById('totalBalance');
  const amountPaidInput = document.getElementById('amountPaid');
  const checkoutBtn = document.getElementById('checkoutBtn');
  const checkoutError = document.getElementById('checkoutError');
  const customerPhone = document.getElementById('customerPhone');
  const customerName = document.getElementById('customerName');
  const customerSearchResults = document.getElementById('customerSearchResults');

  function money(n) {
    return 'Rs. ' + Number(n).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
  }

  function cartTotal() {
    return cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
  }

  function renderCart() {
    cartBody.querySelectorAll('tr[data-key]').forEach(r => r.remove());
    cartEmptyRow.style.display = cart.length ? 'none' : '';

    cart.forEach((item) => {
      const tr = document.createElement('tr');
      tr.dataset.key = item.key;
      const detail = item.type === 'serialized' ? 'S/N: ' + item.serial_barcode : '&mdash;';
      const qtyCell = item.type === 'bulk'
        ? `<input type="number" class="qty-input cart-qty" min="1" max="${item.max_stock}" value="${item.quantity}" data-key="${item.key}">`
        : `1`;
      tr.innerHTML = `
        <td>${item.name}</td>
        <td>${detail}</td>
        <td>${qtyCell}</td>
        <td>${money(item.unit_price)}</td>
        <td>${money(item.unit_price * item.quantity)}</td>
        <td><button type="button" class="btn btn-small btn-danger cart-remove" data-key="${item.key}">Remove</button></td>
      `;
      cartBody.appendChild(tr);
    });

    const total = cartTotal();
    totalSubtotal.textContent = money(total);
    totalGrand.textContent = money(total);
    if (parseFloat(amountPaidInput.value || '0') > total) {
      amountPaidInput.value = total.toFixed(2);
    }
    updateBalance();
    checkoutBtn.disabled = cart.length === 0;
  }

  function updateBalance() {
    const total = cartTotal();
    const paid = Math.max(0, Math.min(parseFloat(amountPaidInput.value || '0'), total));
    totalBalance.textContent = money(total - paid);
  }

  amountPaidInput.addEventListener('input', updateBalance);

  cartBody.addEventListener('click', (e) => {
    if (e.target.classList.contains('cart-remove')) {
      const key = e.target.dataset.key;
      const idx = cart.findIndex(i => i.key === key);
      if (idx !== -1) cart.splice(idx, 1);
      renderCart();
    }
  });

  cartBody.addEventListener('change', (e) => {
    if (e.target.classList.contains('cart-qty')) {
      const key = e.target.dataset.key;
      const item = cart.find(i => i.key === key);
      if (!item) return;
      let qty = parseInt(e.target.value, 10) || 1;
      if (qty < 1) qty = 1;
      if (qty > item.max_stock) {
        alert('Only ' + item.max_stock + ' in stock.');
        qty = item.max_stock;
      }
      item.quantity = qty;
      renderCart();
    }
  });

  function addBulkToCart(product, qty) {
    const existing = cart.find(i => i.type === 'bulk' && i.product_id === product.id);
    if (existing) {
      const newQty = Math.min(existing.quantity + qty, existing.max_stock);
      existing.quantity = newQty;
    } else {
      cart.push({
        key: 'bulk-' + product.id,
        type: 'bulk',
        product_id: product.id,
        name: product.name,
        unit_price: product.unit_price,
        quantity: Math.min(qty, product.stock_qty ?? product.available ?? qty),
        max_stock: product.stock_qty ?? product.available ?? qty,
      });
    }
    renderCart();
  }

  function addSerializedToCart(product) {
    if (cart.find(i => i.serial_number_id === product.serial_number_id)) {
      showScanError('This unit is already in the cart.');
      return;
    }
    cart.push({
      key: 'serial-' + product.serial_number_id,
      type: 'serialized',
      product_id: product.id,
      name: product.name,
      unit_price: product.unit_price,
      quantity: 1,
      serial_number_id: product.serial_number_id,
      serial_barcode: product.serial_barcode,
    });
    renderCart();
  }

  function showScanError(msg) {
    scanError.textContent = msg;
    setTimeout(() => { if (scanError.textContent === msg) scanError.textContent = ''; }, 4000);
  }

  scanInput.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = scanInput.value.trim();
    scanInput.value = '';
    if (!code) return;

    const res = await fetch('api/scan.php?barcode=' + encodeURIComponent(code)).then(r => r.json());
    if (!res.ok) {
      showScanError(res.error || 'Barcode not found.');
      return;
    }
    if (res.type === 'bulk') {
      const qty = await askQuantity(res.product.name, res.product.stock_qty, 1);
      if (qty) addBulkToCart(res.product, qty);
    } else {
      addSerializedToCart(res.product);
    }
    scanInput.focus();
  });

  const runSearch = debounce(async (q) => {
    if (!q) { searchResults.innerHTML = ''; return; }
    const res = await fetch('api/product_search.php?q=' + encodeURIComponent(q)).then(r => r.json());
    searchResults.innerHTML = '';
    if (!res.results.length) {
      searchResults.innerHTML = '<div class="text-muted" style="padding:8px;">No matches.</div>';
      return;
    }
    res.results.forEach((p) => {
      const div = document.createElement('div');
      div.className = 'search-result-item';
      div.innerHTML = `${p.name} <span class="stock">${p.available} in stock</span>`;
      div.addEventListener('click', () => handleSearchPick(p));
      searchResults.appendChild(div);
    });
  }, 300);

  searchInput.addEventListener('input', () => runSearch(searchInput.value.trim()));

  async function handleSearchPick(p) {
    if (p.available <= 0) { alert('Out of stock.'); return; }
    if (p.tracking_type === 'bulk') {
      const qty = await askQuantity(p.name, p.available, 1);
      if (qty) addBulkToCart({ id: p.id, name: p.name, unit_price: p.unit_price, stock_qty: p.available }, qty);
    } else {
      const res = await fetch('api/product_units.php?product_id=' + p.id).then(r => r.json());
      const unit = await pickUnit(p.name, res.units);
      if (unit) {
        addSerializedToCart({
          id: p.id, name: p.name, unit_price: p.unit_price,
          serial_number_id: unit.id, serial_barcode: unit.barcode,
        });
      }
    }
  }

  function pickUnit(productName, units) {
    return new Promise((resolve) => {
      if (!units.length) { alert('No units in stock.'); resolve(null); return; }
      const list = units.map(u => `<div class="search-result-item" data-id="${u.id}" data-barcode="${u.barcode}">${u.barcode}</div>`).join('');
      const overlay = showModal(`
        <h3>Select Unit</h3>
        <p>${productName}</p>
        <div class="search-results">${list}</div>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button></div>
      `);
      overlay.querySelectorAll('.search-result-item').forEach(el => {
        el.addEventListener('click', () => {
          const unit = { id: parseInt(el.dataset.id, 10), barcode: el.dataset.barcode };
          closeModal();
          resolve(unit);
        });
      });
      overlay.querySelector('#modalCancelBtn').addEventListener('click', () => { closeModal(); resolve(null); });
    });
  }

  const runCustomerSearch = debounce(async (q) => {
    if (!q || q.length < 3) { customerSearchResults.innerHTML = ''; return; }
    const res = await fetch('api/customer_search.php?q=' + encodeURIComponent(q)).then(r => r.json());
    customerSearchResults.innerHTML = '';
    res.results.forEach((c) => {
      const div = document.createElement('div');
      div.className = 'search-result-item';
      div.innerHTML = `${c.name} <span class="stock">${c.phone}</span>`;
      div.addEventListener('click', () => {
        customerName.value = c.name;
        customerPhone.value = c.phone;
        customerSearchResults.innerHTML = '';
      });
      customerSearchResults.appendChild(div);
    });
  }, 300);
  customerPhone.addEventListener('input', () => runCustomerSearch(customerPhone.value.trim()));

  checkoutBtn.addEventListener('click', async () => {
    checkoutError.textContent = '';
    if (!cart.length) return;
    if (!customerName.value.trim() || !customerPhone.value.trim()) {
      checkoutError.textContent = 'Please enter customer name and phone number.';
      return;
    }
    checkoutBtn.disabled = true;
    checkoutBtn.textContent = 'Processing...';

    const payload = {
      items: cart.map(i => ({
        type: i.type,
        product_id: i.product_id,
        quantity: i.quantity,
        serial_number_id: i.serial_number_id || null,
      })),
      customer_name: customerName.value.trim(),
      customer_phone: customerPhone.value.trim(),
      amount_paid: parseFloat(amountPaidInput.value || '0'),
    };

    try {
      const res = await fetch('api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      }).then(r => r.json());

      if (res.ok) {
        window.location.href = 'invoice.php?id=' + res.sale_id;
      } else {
        checkoutError.textContent = res.error || 'Checkout failed.';
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = 'Complete Sale';
      }
    } catch (err) {
      checkoutError.textContent = 'Network error. Please try again.';
      checkoutBtn.disabled = false;
      checkoutBtn.textContent = 'Complete Sale';
    }
  });

  renderCart();
  scanInput.focus();
})();

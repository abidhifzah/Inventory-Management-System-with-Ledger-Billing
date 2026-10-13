/**
 * Small reusable modal helper used across pages (quantity prompts, confirmations).
 */
function showModal(innerHtml) {
  closeModal();
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = 'appModalOverlay';
  overlay.innerHTML = '<div class="modal-box">' + innerHtml + '</div>';
  document.body.appendChild(overlay);
  return overlay;
}

function closeModal() {
  const existing = document.getElementById('appModalOverlay');
  if (existing) existing.remove();
}

/**
 * Prompt the user for a quantity (used for bulk-tracked products).
 * Resolves with an integer, or null if cancelled.
 */
function askQuantity(productName, maxQty, defaultQty = 1) {
  return new Promise((resolve) => {
    const overlay = showModal(`
      <h3>Quantity</h3>
      <p>${productName}${maxQty !== null ? ' <span class="text-muted">(in stock: ' + maxQty + ')</span>' : ''}</p>
      <div class="form-group">
        <input type="number" id="modalQtyInput" min="1" ${maxQty ? 'max="' + maxQty + '"' : ''} value="${defaultQty}">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button>
        <button type="button" class="btn" id="modalOkBtn">Add</button>
      </div>
    `);
    const input = overlay.querySelector('#modalQtyInput');
    input.focus();
    input.select();
    const finish = (val) => { closeModal(); resolve(val); };
    overlay.querySelector('#modalOkBtn').addEventListener('click', () => {
      const val = parseInt(input.value, 10);
      if (!val || val < 1) { input.focus(); return; }
      if (maxQty && val > maxQty) { alert('Only ' + maxQty + ' in stock.'); return; }
      finish(val);
    });
    overlay.querySelector('#modalCancelBtn').addEventListener('click', () => finish(null));
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); overlay.querySelector('#modalOkBtn').click(); }
      if (e.key === 'Escape') { finish(null); }
    });
  });
}

function confirmModal(message) {
  return new Promise((resolve) => {
    const overlay = showModal(`
      <h3>Please confirm</h3>
      <p>${message}</p>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button>
        <button type="button" class="btn btn-danger" id="modalOkBtn">Confirm</button>
      </div>
    `);
    overlay.querySelector('#modalOkBtn').addEventListener('click', () => { closeModal(); resolve(true); });
    overlay.querySelector('#modalCancelBtn').addEventListener('click', () => { closeModal(); resolve(false); });
  });
}

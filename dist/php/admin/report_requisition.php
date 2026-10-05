<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$me = currentUser();

$pageTitle='Requisition & Issue Slip — Reports'; $activeNav='reports';
require __DIR__ . '/../includes/admin_header.php';

$catList = allCategories($conn);

// All items, grouped by category, sent to the browser once. The admin
// builds the slip entirely client-side by picking items into a cart —
// the server never assumes which items belong on a requisition.
$itemsRes = $conn->query("SELECT i.id, i.item_code, i.item_name, i.unit, i.quantity, i.category_id, c.name AS category_name
                           FROM inventory_items i JOIN categories c ON c.id=i.category_id
                           ORDER BY c.name, i.item_name");
$allItems = $itemsRes->fetch_all(MYSQLI_ASSOC);
$itemsJson = json_encode($allItems, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>

<div class="page-head">
  <div><h2>Requisition &amp; Issue Slip</h2><div class="desc">Follows the PhilHealth RIS (Supplies and Materials) layout — pick items and quantities, then review and print.</div></div>
  <a href="reports.php" class="btn btn-outline no-print"><i class="fa-solid fa-arrow-left"></i> Back to Reports</a>
</div>

<!-- ===================== BUILDER (screen only) ===================== -->
<div id="slipBuilder" class="no-print">

  <div class="panel">
    <div class="panel-head"><h3>Slip Details</h3></div>
    <div class="slip-header-fields slip-header-fields-ris">
      <div class="form-group"><label for="slip_office">Office – End User</label><input type="text" id="slip_office" class="form-control" value="<?= h($me['department'] ?? '') ?>" placeholder="e.g. LHIO-SPC"></div>
      <div class="form-group"><label for="slip_ris_no">RIS No.</label><input type="text" id="slip_ris_no" class="form-control" placeholder="e.g. 2608-001"></div>
      <div class="form-group"><label for="slip_date">Date</label><input type="date" id="slip_date" class="form-control" value="<?= h(date('Y-m-d')) ?>"></div>
      <div class="form-group"><label for="slip_requested_by">Requested By</label><input type="text" id="slip_requested_by" class="form-control" value="<?= h($me['full_name'] ?? '') ?>"></div>
      <div class="form-group"><label for="slip_purpose">Purpose</label><input type="text" id="slip_purpose" class="form-control" placeholder="Purpose of this requisition"></div>
    </div>
  </div>

  <div class="slip-builder-grid">
    <div class="panel slip-item-picker">
      <div class="panel-head"><h3>1. Select Items</h3></div>
      <div class="filter-bar" style="margin-bottom:14px">
        <input type="text" id="picker_search" class="form-control search-grow" placeholder="Search item name or code...">
        <select id="picker_category" class="form-control">
          <option value="0">All Categories</option>
          <?php foreach ($catList as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="picker-list" id="pickerList"></div>
    </div>

    <div class="panel slip-cart">
      <div class="panel-head"><h3>2. Review Selected Items</h3></div>
      <div id="cartList"><div class="cart-empty">No items selected yet. Add items from the list on the left.</div></div>
    </div>
  </div>

  <div class="flex justify-between items-center" style="margin-top:18px">
    <div class="cell-muted" id="cartCount">0 item(s) selected</div>
    <button type="button" class="btn btn-primary" id="btnReview"><i class="fa-solid fa-eye"></i> Review &amp; Print Slip</button>
  </div>
</div>

<!-- ===================== PREVIEW (screen + print) ===================== -->
<div id="slipPreview" class="panel ris-slip" style="display:none">
  <?php
    $reportTitle    = 'Requisition and Issue Slip';
    $reportSubtitle = 'Supplies and Materials';
    require __DIR__ . '/../includes/report_print_head.php';
  ?>
  <div class="report-view-head">
    <div><h3>Requisition and Issue Slip</h3><div class="cell-muted">Supplies and Materials</div></div>
    <div class="report-toolbar-actions no-print">
      <button type="button" class="btn btn-outline btn-sm" id="btnBackToEdit"><i class="fa-solid fa-arrow-left"></i> Back to Edit</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>
  </div>

  <!-- Office / RIS No. / Date band — mirrors the paper RIS form's top strip -->
  <div class="ris-topband">
    <div class="ris-topband-main">
      <span class="ris-label">Office – End User:</span>
      <span class="ris-value" id="pv_office"></span>
    </div>
    <div class="ris-topband-side">
      <div><span class="ris-label">RIS No.:</span> <span class="ris-value" id="pv_ris_no"></span></div>
      <div><span class="ris-label">Date:</span> <span class="ris-value" id="pv_date"></span></div>
    </div>
  </div>
  <div class="ris-topband ris-topband-secondary">
    <div><span class="ris-label">Requested By:</span> <span class="ris-value" id="pv_requested_by"></span></div>
    <div><span class="ris-label">Purpose:</span> <span class="ris-value" id="pv_purpose"></span></div>
  </div>

  <!-- Two-tier REQUISITION / ISSUANCE table — mirrors the paper RIS form -->
  <div class="table-wrap"><table class="data-table report-table ris-table">
    <thead>
      <tr class="ris-group-row">
        <th colspan="3">Requisition</th>
        <th colspan="3">Issuance</th>
      </tr>
      <tr>
        <th>Unit</th><th>Description</th><th>Qty</th>
        <th>Qty</th><th>Unit Cost</th><th>Total Cost</th>
      </tr>
    </thead>
    <tbody id="pv_items"></tbody>
  </table></div>
  <p class="cell-muted ris-issuance-note no-print">The three Issuance columns are intentionally left blank on the printed slip — they're filled in by hand once items are actually released, since unit cost isn't tracked in the inventory system.</p>

  <div class="sign-row">
    <div class="sign-block"><div class="sign-line">Requested By</div><div class="sign-role">Signature over Printed Name</div></div>
    <div class="sign-block"><div class="sign-line">Approved By</div><div class="sign-role">Signature over Printed Name</div></div>
    <div class="sign-block"><div class="sign-line">Received By</div><div class="sign-role">Signature over Printed Name</div></div>
  </div>
</div>

<script type="application/json" id="requisitionItemsData"><?= $itemsJson ?></script>
<script>
(function () {
  const ALL_ITEMS = JSON.parse(document.getElementById('requisitionItemsData').textContent);
  const cart = new Map(); // item_id -> { item, qty }

  const pickerList   = document.getElementById('pickerList');
  const pickerSearch = document.getElementById('picker_search');
  const pickerCat    = document.getElementById('picker_category');
  const cartList     = document.getElementById('cartList');
  const cartCount    = document.getElementById('cartCount');

  function renderPicker() {
    const q = (pickerSearch.value || '').trim().toLowerCase();
    const cat = pickerCat.value;
    const rows = ALL_ITEMS.filter(function (it) {
      const matchesCat = cat === '0' || String(it.category_id) === cat;
      const matchesQ = !q || it.item_name.toLowerCase().includes(q) || it.item_code.toLowerCase().includes(q);
      return matchesCat && matchesQ;
    });
    pickerList.innerHTML = '';
    if (!rows.length) {
      pickerList.innerHTML = '<div class="cart-empty">No items match.</div>';
      return;
    }
    rows.forEach(function (it) {
      const row = document.createElement('div');
      row.className = 'picker-row';
      const inCart = cart.has(it.id);
      const nameWrap = document.createElement('div');
      nameWrap.style.flex = '1';
      const nameEl = document.createElement('div');
      nameEl.className = 'picker-name';
      nameEl.textContent = it.item_name;
      const metaEl = document.createElement('div');
      metaEl.className = 'picker-meta';
      metaEl.textContent = it.item_code + ' · ' + it.category_name + ' · ' + it.quantity + ' ' + it.unit + ' on hand';
      nameWrap.appendChild(nameEl);
      nameWrap.appendChild(metaEl);
      row.appendChild(nameWrap);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline btn-sm';
      btn.textContent = inCart ? 'Added' : '+ Add';
      btn.disabled = inCart;
      btn.addEventListener('click', function () {
        cart.set(it.id, { item: it, qty: 1 });
        renderPicker();
        renderCart();
      });
      row.appendChild(btn);
      pickerList.appendChild(row);
    });
  }

  function renderCart() {
    cartCount.textContent = cart.size + ' item(s) selected';
    if (!cart.size) {
      cartList.innerHTML = '<div class="cart-empty">No items selected yet. Add items from the list on the left.</div>';
      return;
    }
    cartList.innerHTML = '';
    cart.forEach(function (entry, id) {
      const row = document.createElement('div');
      row.className = 'cart-row';

      const nameEl = document.createElement('div');
      nameEl.className = 'cart-name';
      nameEl.textContent = entry.item.item_name;

      const unitEl = document.createElement('div');
      unitEl.className = 'cart-unit';
      unitEl.textContent = entry.item.unit;

      const qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.min = '1';
      qtyInput.value = entry.qty;
      qtyInput.addEventListener('input', function () {
        entry.qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
      });

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'icon-btn danger';
      removeBtn.title = 'Remove';
      removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
      removeBtn.addEventListener('click', function () {
        cart.delete(id);
        renderPicker();
        renderCart();
      });

      row.appendChild(nameEl);
      row.appendChild(unitEl);
      row.appendChild(qtyInput);
      row.appendChild(removeBtn);
      cartList.appendChild(row);
    });
  }

  pickerSearch.addEventListener('input', renderPicker);
  pickerCat.addEventListener('change', renderPicker);
  renderPicker();
  renderCart();

  // ---- Review / Print ----
  const builder = document.getElementById('slipBuilder');
  const preview = document.getElementById('slipPreview');

  document.getElementById('btnReview').addEventListener('click', function () {
    if (!cart.size) { alert('Please select at least one item before generating the slip.'); return; }

    document.getElementById('pv_office').textContent = document.getElementById('slip_office').value || '—';
    document.getElementById('pv_ris_no').textContent = document.getElementById('slip_ris_no').value || '—';
    document.getElementById('pv_date').textContent = document.getElementById('slip_date').value || '—';
    document.getElementById('pv_requested_by').textContent = document.getElementById('slip_requested_by').value || '—';
    document.getElementById('pv_purpose').textContent = document.getElementById('slip_purpose').value || '—';

    // REQUISITION side is filled in (Unit / Description / Qty requested).
    // ISSUANCE side (Qty / Unit Cost / Total Cost) is left blank, exactly
    // like the paper RIS form — it's completed by hand once supplies are
    // actually released, since the system does not track unit costs.
    const tbody = document.getElementById('pv_items');
    tbody.innerHTML = '';
    cart.forEach(function (entry) {
      const tr = document.createElement('tr');

      const tdUnit = document.createElement('td');
      tdUnit.textContent = entry.item.unit;

      const tdDesc = document.createElement('td');
      tdDesc.className = 'cell-strong';
      tdDesc.textContent = entry.item.item_name + ' (' + entry.item.item_code + ')';

      const tdQty = document.createElement('td');
      tdQty.textContent = entry.qty;

      const tdIssQty = document.createElement('td');
      tdIssQty.className = 'ris-blank-cell';
      const tdUnitCost = document.createElement('td');
      tdUnitCost.className = 'ris-blank-cell';
      const tdTotalCost = document.createElement('td');
      tdTotalCost.className = 'ris-blank-cell';

      tr.appendChild(tdUnit);
      tr.appendChild(tdDesc);
      tr.appendChild(tdQty);
      tr.appendChild(tdIssQty);
      tr.appendChild(tdUnitCost);
      tr.appendChild(tdTotalCost);
      tbody.appendChild(tr);
    });

    builder.style.display = 'none';
    preview.style.display = 'block';
    window.scrollTo(0, 0);
  });

  document.getElementById('btnBackToEdit').addEventListener('click', function () {
    preview.style.display = 'none';
    builder.style.display = 'block';
  });
})();
</script>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

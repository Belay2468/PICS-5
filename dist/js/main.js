/**
 * PhilTrack System — main.js
 * Located at: dist/js/main.js
 * Referenced by: admin_footer.php (../../js/main.js)
 *                user_footer.php  (../../js/main.js)
 *                index.php        (../js/main.js)
 */

// ── Modal helpers ────────────────────────────────────────────
// Some browsers mis-stack a `position: sticky` topbar/sidebar above a
// `position: fixed` backdrop despite the backdrop's higher z-index (a
// real, if uncommon, engine quirk — sticky elements can get their own
// compositing layer and stop respecting sibling z-index reliably).
// Re-parenting every modal-backdrop directly under <body> sidesteps it
// entirely: as a top-level fixed overlay with no positioned ancestors
// in between, there's no ambiguous stacking order left to get wrong.
// IDs and existing CSS selectors (#itemModal, .modal-backdrop, …) keep
// working unchanged since they don't depend on DOM nesting.
document.querySelectorAll('.modal-backdrop').forEach(function (m) {
  document.body.appendChild(m);
});

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

// Close on backdrop click
document.addEventListener('click', function (e) {
  if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
  }
});

// ── Admin sidebar toggle (off-canvas drawer on tablet/mobile) ──
(function () {
  const toggle   = document.getElementById('sidebarToggle');
  const sidebar  = document.getElementById('adminSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (!toggle || !sidebar || !backdrop) return; // not on an admin page

  function openSidebar() {
    sidebar.classList.add('open');
    backdrop.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function () {
    if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar();
  });
  backdrop.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
})();

// Close on Escape
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(function (m) {
      m.classList.remove('open');
    });
  }
});

// ── Delete confirmation ──────────────────────────────────────
function confirmDelete(message) {
  return window.confirm(message || 'Are you sure you want to delete this record? This cannot be undone.');
}

// ── Live clock (user topbar) ─────────────────────────────────
function startClock(elId) {
  const el = document.getElementById(elId);
  if (!el) return;
  function tick() {
    const now  = new Date();
    const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const date = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric'});
    const weekday = now.toLocaleDateString('en-US', {  weekday: 'long' });
    el.innerHTML = `
        <div class="clock-time">${time}</div>
      
        <div class="clock-info">
            <span class="clock-date">${date}</span>
            <span class="clock-divider"></span>
            <span class="clock-day">${weekday}</span>
        </div>
    `;
  }
  tick();
  setInterval(tick, 1000);
}

// ── Tabs ─────────────────────────────────────────────────────
function showTab(groupName, tabId) {
  document.querySelectorAll('[data-tab-group="' + groupName + '"]').forEach(function (panel) {
    panel.style.display = (panel.id === tabId) ? 'block' : 'none';
  });
  document.querySelectorAll('[data-tab-link="' + groupName + '"]').forEach(function (link) {
    link.classList.toggle('active', link.getAttribute('data-tab-target') === tabId);
  });
}

// ── Auto-dismiss alerts ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert[data-autohide]').forEach(function (a) {
    setTimeout(function () {
      a.style.transition = 'opacity .4s ease';
      a.style.opacity = '0';
      setTimeout(function () { a.remove(); }, 400);
    }, 4000);
  });
});

// ── Safe rendering helpers ───────────────────────────────────
// Modal templates are built from STATIC markup only; every untrusted
// value is written afterwards with textContent so it can never be
// parsed as HTML (stored XSS protection).
function fillFields(root, values) {
  if (!root) return;
  Object.keys(values).forEach(function (name) {
    root.querySelectorAll('[data-field="' + name + '"]').forEach(function (el) {
      el.textContent = (values[name] === null || values[name] === undefined) ? '' : String(values[name]);
    });
  });
}

// Escapes a value for the rare case where markup really has to be built.
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Builds the item preview node: an <img> for an uploaded file, or the
// "No Image" placeholder. The file name is URL-encoded, never injected.
function itemImageNode(fileName, basePath) {
  if (fileName) {
    const img = document.createElement('img');
    img.className = 'item-preview';
    img.src = basePath + encodeURIComponent(String(fileName));
    img.alt = '';
    return img;
  }
  const box = document.createElement('div');
  box.className = 'no-image';
  box.textContent = 'No Image';
  return box;
}

// ── Request form helper ──────────────────────────────────────
// Called from item cards: pre-fills the shared request modal.
function openRequestModal(itemId, itemName, available) {
  const idField  = document.getElementById('req_item_id');
  const nameField = document.getElementById('req_item_name');
  const qtyField = document.getElementById('req_quantity');
  if (idField)   idField.value   = itemId;
  if (nameField) nameField.value = itemName;
  if (qtyField)  { qtyField.max = available; qtyField.value = ''; }
  openModal('requestModal');
}

// Delegated listener for "Request Item" buttons (item cards on the
// Search Results and Category pages). Item id / label / quantity travel
// as data-* attributes rather than being built into an inline onclick
// string, so item names containing quotes, apostrophes, or other special
// characters (e.g. TAPE 1'') can never break the generated markup.
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.js-request-item');
  if (!btn) return;
  openRequestModal(
    parseInt(btn.dataset.itemId, 10) || 0,
    btn.dataset.itemLabel || '',
    parseInt(btn.dataset.itemQty, 10) || 0
  );
});

// ── Submit feedback (loading state) ──────────────────────────
// Every form in the app is a plain POST/GET that reloads the page —
// there was previously zero feedback between clicking "Save" and the
// page actually changing, so a slow request (e.g. the item form's
// image upload) looked identical to a stalled/broken button, and
// nothing stopped a double-click from double-submitting.
//
// The disabling is deferred one tick via setTimeout rather than done
// synchronously in the submit handler: some browsers determine which
// submit button's name=value pair to include in the request based on
// its state at serialization time, and disabling it too early can
// silently drop that value — which would break the Reports pages'
// "Generate Report" buttons (name="generated" value="1", read via
// isset($_GET['generated']) server-side).
document.addEventListener('submit', function (e) {
  const form = e.target;
  if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;

  const submitter = e.submitter || form.querySelector('button[type="submit"]');
  if (!submitter) return;

  setTimeout(function () {
    // If the submission was cancelled — e.g. the user backed out of a
    // delete confirmation dialog — the page isn't navigating, so don't
    // leave the button stuck disabled with a spinner that never clears.
    if (e.defaultPrevented) return;

    form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
      btn.disabled = true;
    });
    // Icon-only buttons (search, small row actions) already get a
    // clear dimmed "disabled" look from the shared .btn:disabled style;
    // buttons with visible text also get a spinner so a multi-second
    // save/upload doesn't look like it silently did nothing.
    if (submitter.textContent.trim().length > 0 && !submitter.querySelector('.btn-spinner')) {
      const spinner = document.createElement('i');
      spinner.className = 'fa-solid fa-spinner fa-spin btn-spinner';
      submitter.prepend(spinner);
    }
  }, 0);
});

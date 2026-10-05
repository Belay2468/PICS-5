<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('user');
$me = currentUser();
$flash=''; $flashType='success';

if ($_SERVER['REQUEST_METHOD']==='POST' && !csrfValid()) {
    $flash=CSRF_ERROR; $flashType='danger';
} elseif ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='request_item') {
    $itemId=(int)($_POST['item_id']??0);
    $reqName=trim($_POST['requester_name']??'');
    $dept=trim($_POST['department']??'');
    $qty=max(1,(int)($_POST['quantity']??0));
    $purpose=trim($_POST['purpose']??'');
    $ir=$conn->prepare("SELECT item_name,quantity FROM inventory_items WHERE id=?"); $ir->bind_param("i",$itemId); $ir->execute();
    $item=$ir->get_result()->fetch_assoc(); $ir->close();
    if (!$item) { $flash='Item not found.'; $flashType='danger'; }
    elseif ($reqName===''||$dept==='') { $flash='Please fill in your name and department.'; $flashType='danger'; }
    elseif ($qty>(int)$item['quantity']) { $flash="Insufficient stock — only {$item['quantity']} unit(s) available."; $flashType='danger'; }
    else {
        $s=$conn->prepare("INSERT INTO item_requests (item_id,requester_id,requester_name,department,quantity_requested,purpose) VALUES (?,?,?,?,?,?)");
        $s->bind_param("iissis",$itemId,$me['id'],$reqName,$dept,$qty,$purpose); $s->execute(); $s->close();
        logActivity($conn,$me['id'],"Requested $qty x \"{$item['item_name']}\"");
        $flash="Your request for \"{$item['item_name']}\" was submitted. The administrator has been notified.";
    }
}

$categoryId=(int)($_GET['id']??0);
$cr=$conn->prepare("SELECT * FROM categories WHERE id=?"); $cr->bind_param("i",$categoryId); $cr->execute();
$category=$cr->get_result()->fetch_assoc(); $cr->close();
if (!$category) { header("Location: dashboard.php"); exit; }

$pageTitle=$category['name'];
require __DIR__ . '/../includes/user_header.php';

$search=trim($_GET['q']??''); $statusFilter=$_GET['status']??''; $sort=$_GET['sort']??'name_asc';
$where=["i.category_id=?"]; $params=[$categoryId]; $types='i';
if($search!==''){$where[]="(i.item_name LIKE ? OR i.item_code LIKE ?)"; $like="%$search%"; $params[]=$like; $params[]=$like; $types.='ss';}
$orderSql=match($sort){'name_desc'=>'i.item_name DESC','qty_asc'=>'i.quantity ASC','qty_desc'=>'i.quantity DESC',default=>'i.item_name ASC'};
$stmt=$conn->prepare("SELECT i.*,c.name AS category_name FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE ".implode(' AND ',$where)." ORDER BY $orderSql");
$stmt->bind_param($types,...$params); $stmt->execute(); $allItems=$stmt->get_result();
$itemRows=[]; while($r=$allItems->fetch_assoc()) $itemRows[]=$r;
if($statusFilter) $itemRows=array_values(array_filter($itemRows,fn($r)=>stockStatus((int)$r['quantity'],(int)$r['reorder_level'])===$statusFilter));

$sa=$conn->prepare("SELECT quantity,reorder_level FROM inventory_items WHERE category_id=?"); $sa->bind_param("i",$categoryId); $sa->execute();
$sr=$sa->get_result(); $total=$available=$low=$out=0;
while($r=$sr->fetch_assoc()){$total++;$s=stockStatus((int)$r['quantity'],(int)$r['reorder_level']);if($s==='out')$out++;elseif($s==='low')$low++;else $available++;}
?>

<div class="user-content">
  <p><a href="dashboard.php">← Back to Dashboard</a></p>
  <?= $flash ? alertBox($flash, $flashType) : '' ?>

  <div class="panel">
    <div class="flex items-center gap-8" style="margin-bottom:6px">
      <div class="stat-icon ic-green" style="font-size:22px">
        <?= str_contains($category['icon'],'fa-')
           ? '<i class="'.h($category['icon']).'"></i>'
           : h($category['icon']); ?>
      </div>
      <div><h2 class="mt-0"><?= h($category['name']) ?></h2><div class="text-muted" style="font-size:12.5px">Browse and manage all <?= h(strtolower($category['name'])) ?>.</div></div>
    </div>
  </div>

  <div class="stat-grid">

      <div class="stat-card">
          <div class="stat-info">
              <div class="stat-label">Total Items</div>
              <div class="stat-value"><?= $total ?></div>
              <div class="stat-sub">Inventory Records</div>
          </div>

          <div class="stat-icon ic-blue">
              <i class="fa-solid fa-box"></i>
          </div>
      </div>

      <div class="stat-card">
          <div class="stat-info">
              <div class="stat-label">Available</div>
              <div class="stat-value success"><?= $available ?></div>
              <div class="stat-sub">Ready to Request</div>
          </div>

          <div class="stat-icon ic-green">
              <i class="fa-solid fa-circle-check"></i>
          </div>
      </div>

      <div class="stat-card">
          <div class="stat-info">
              <div class="stat-label">Low Stock</div>
              <div class="stat-value warning"><?= $low ?></div>
              <div class="stat-sub">Needs Restocking</div>
          </div>

          <div class="stat-icon ic-amber">
              <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
      </div>

      <div class="stat-card">
          <div class="stat-info">
              <div class="stat-label">Out of Stock</div>
              <div class="stat-value danger"><?= $out ?></div>
              <div class="stat-sub">Unavailable Items</div>
          </div>

          <div class="stat-icon ic-red">
              <i class="fa-solid fa-circle-xmark"></i>
          </div>
      </div>

  </div>

  <form method="GET" class="category-filter-bar">
    <input type="hidden" name="id" value="<?= $categoryId ?>">
    <input type="text" name="q" class="form-control search-grow" placeholder="Search item name or code..." value="<?= h($search) ?>">
    <select name="status" class="form-control" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="in" <?= $statusFilter==='in'?'selected':'' ?>>In Stock</option>
      <option value="low" <?= $statusFilter==='low'?'selected':'' ?>>Low Stock</option>
      <option value="out" <?= $statusFilter==='out'?'selected':'' ?>>Out of Stock</option>
    </select>
    <select name="sort" class="form-control" onchange="this.form.submit()">
      <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Name (A-Z)</option>
      <option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>>Name (Z-A)</option>
      <option value="qty_desc" <?= $sort==='qty_desc'?'selected':'' ?>>Quantity (High-Low)</option>
      <option value="qty_asc" <?= $sort==='qty_asc'?'selected':'' ?>>Quantity (Low-High)</option>
    </select>
    <button class="btn btn-outline" type="submit">Apply</button>
  </form>

  <div class="item-grid">
    <?php if(empty($itemRows)): ?><?= emptyStatePanel('fa-solid fa-box-open', 'No items match your search.', false, '', 'grid-span-full') ?><?php endif; ?>
    <?php foreach($itemRows as $row):
        $status=stockStatus((int)$row['quantity'],(int)$row['reorder_level']);
        $ij = h(json_encode($row));
    ?>
    <div class="item-card <?= $status === 'out' ? 'out-of-stock' : '' ?>">

        <div class="item-image">

            <?php if(itemImageExists($row['image'])): ?>

                <img
                    src="<?= h(itemsImageUrl($row['image'])) ?>"
                    alt="<?= h($row['item_name']) ?>"
                >
            
            <?php else: ?>
            
                <i class="<?= h($category['icon']) ?>"></i>
            
            <?php endif; ?>
            
        </div>
            
        <div class="item-info">
            
            <h3><?= h($row['item_name']) ?></h3>
            
            <div class="item-code">
            
                Item Code:
                <strong><?= h($row['item_code']) ?></strong>
            
            </div>
            
            <div class="item-qty-label">
            
                Available Quantity
            
            </div>
            
            <div class="item-qty">
            
                <?= (int)$row['quantity'] ?>
            
                <span><?= h($row['unit']) ?></span>
            
            </div>
            
            <?= badge(stockStatusLabel($status),stockStatusClass($status)) ?>
            
            <button
                type="button"
                class="btn btn-outline btn-sm js-view-item-details"
                data-item="<?= $ij ?>"
            >
                <i class="fa-solid fa-circle-info"></i>
                View Details
            </button>
            
        </div>
            
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Item details modal -->
<div class="modal-backdrop" id="detailsModal">
  <div class="modal modal-lg"><div class="modal-head"><h3>Item Details</h3><button class="modal-close" onclick="closeModal('detailsModal')" aria-label="Close">✕</button></div>
    <div class="modal-body" id="detailsBody"></div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('detailsModal')">Close</button><button type="button" class="btn btn-primary" id="detailsRequestBtn">Request Item</button></div>
  </div>
</div>

<!-- Request modal -->
<div class="modal-backdrop" id="requestModal">
  <div class="modal"><div class="modal-head"><h3>Request Item</h3><button class="modal-close" onclick="closeModal('requestModal')" aria-label="Close">✕</button></div>
    <form method="POST"><div class="modal-body">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="request_item">
      <input type="hidden" name="item_id" id="req_item_id">
      <div class="form-row">
        <div class="form-group"><label for="req_name">Requester Name</label><input type="text" name="requester_name" id="req_name" class="form-control" value="<?= h($me['full_name']) ?>" required></div>
        <div class="form-group"><label for="req_department">Department / Office</label><input type="text" name="department" id="req_department" class="form-control" value="<?= h($me['department']) ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="req_item_name">Item Name</label><input type="text" id="req_item_name" class="form-control" disabled></div>
        <div class="form-group"><label for="req_quantity">Quantity Requested</label><input type="number" name="quantity" id="req_quantity" class="form-control" min="1" required></div>
      </div>
      <div class="form-group"><label for="req_purpose">Purpose of Request</label><textarea name="purpose" id="req_purpose" class="form-control" maxlength="200" placeholder="Enter purpose of request..." required></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('requestModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button></div>
    </form>
  </div>
</div>

<script>
// Canonical item image folder, resolved server-side.
const ITEM_IMG_BASE = <?= json_encode(itemsImageUrl()) ?>;

function openItemDetails(item){
  const sMap={in:['In Stock','badge-success'],low:['Low Stock','badge-warning'],out:['Out of Stock','badge-danger']};
  const qty=parseInt(item.quantity,10),reorder=parseInt(item.reorder_level,10);
  const sk=qty<=0?'out':(qty<=reorder?'low':'in');
  const [label,cls]=sMap[sk];
  const body=document.getElementById('detailsBody');

// Static markup only — no item data is interpolated into HTML here.
body.innerHTML = `
    <div class="item-details-grid">

        <div class="item-details-left">

            <div class="form-group">
                <label>Item Code</label>
                <div data-field="item_code"></div>
            </div>

            <div class="form-group">
                <label>Item Name</label>
                <div class="cell-strong" data-field="item_name"></div>
            </div>

            <div class="form-group">
                <label>Category</label>
                <div data-field="category_name"></div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <div data-field="description"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Available Qty</label>
                    <div data-field="qty"></div>
                </div>

                <div class="form-group">
                    <label>Reorder Level</label>
                    <div data-field="reorder"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Storage Location</label>
                    <div data-field="storage_location"></div>
                </div>

                <div class="form-group">
                    <label>Date Added</label>
                    <div data-field="date_added"></div>
                </div>
            </div>

        </div>


        <div class="item-details-right"></div>

    </div>
`;
  // Untrusted values are injected as text, never as markup.
  fillFields(body,{
    item_code:        item.item_code,
    item_name:        item.item_name,
    category_name:    item.category_name,
    description:      item.description || '—',
    qty:              item.quantity + ' ' + item.unit,
    reorder:          item.reorder_level + ' ' + item.unit,
    storage_location: item.storage_location || '—',
    date_added:       item.date_added
  });

  body.querySelector('.item-details-right')
      .appendChild(itemImageNode(item.image,ITEM_IMG_BASE));

  const requestBtn = document.getElementById('detailsRequestBtn');
  const outOfStock = (item.quantity|0) === 0;
  requestBtn.disabled = outOfStock;
  requestBtn.textContent = outOfStock ? 'Out of Stock' : 'Request Item';
  requestBtn.onclick = outOfStock ? null : function(){closeModal('detailsModal');openRequestModal(item.id,item.item_name+' ('+item.item_code+')',item.quantity);};
  openModal('detailsModal');
}

// Event delegation: works for any number of item cards and is immune to
// special characters in item names (no data is ever built into an inline
// HTML event-handler string).
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.js-view-item-details');
  if (!btn) return;
  try {
    openItemDetails(JSON.parse(btn.dataset.item));
  } catch (err) {
    console.error('Could not read item data', err);
  }
});
</script>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>

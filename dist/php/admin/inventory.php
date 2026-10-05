<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$me = currentUser();

$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValid()) {
    $flash = CSRF_ERROR; $flashType = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $itemName   = trim($_POST['item_name'] ?? '');
        $catId      = (int)($_POST['category_id'] ?? 0);
        $desc       = trim($_POST['description'] ?? '');
        $quantity   = max(0,(int)($_POST['quantity'] ?? 0));
        $unit       = trim($_POST['unit'] ?? 'pcs');
        $reorder    = max(0,(int)($_POST['reorder_level'] ?? 10));
        $location   = trim($_POST['storage_location'] ?? '');
        $image = '';
        $uploadError = '';

        // Extension is not trusted — MIME type and image data are verified.
        if (isset($_FILES['image'])) {
            $image = saveItemImage($_FILES['image'], $uploadError);
        }

        // The category must exist — otherwise the insert/update would hit the
        // foreign key and surface as a raw database error.
        $catName = '';
        if ($catId > 0) {
            $cr = $conn->prepare("SELECT name FROM categories WHERE id=?"); $cr->bind_param("i",$catId); $cr->execute();
            $catName = $cr->get_result()->fetch_assoc()['name'] ?? ''; $cr->close();
        }

        if ($itemName==='' || $catId<=0) {
            $flash = 'Item name and category are required.'; $flashType = 'danger';
        } elseif ($catName === '') {
            $flash = 'The selected category does not exist. Please choose a valid category.'; $flashType = 'danger';
        } elseif ($uploadError !== '') {
            $flash = $uploadError; $flashType = 'danger';
        } elseif ($action === 'add') {
            $itemCode = nextItemCode($conn, categoryPrefix($catName));
            $today = date('Y-m-d');
            $stmt = $conn->prepare("INSERT INTO inventory_items (item_code,item_name,category_id,description,image,quantity,unit,reorder_level,storage_location,date_added) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisssisis", $itemCode,$itemName,$catId,$desc,$image,$quantity,$unit,$reorder,$location,$today);
            $stmt->execute(); $stmt->close();
            logActivity($conn,$me['id'],"Added item \"$itemName\" ($itemCode)");
            $flash = "Item \"$itemName\" added successfully.";
    } else {
        $itemId = (int)($_POST['item_id'] ?? 0);

        // keep old image if no new upload
        if ($image === '') {
            $stmt = $conn->prepare("
                UPDATE inventory_items
                SET item_name=?,
                    category_id=?,
                    description=?,
                    quantity=?,
                    unit=?,
                    reorder_level=?,
                    storage_location=?
                WHERE id=?
            ");

            $stmt->bind_param(
                "sisisisi",
                $itemName,
                $catId,
                $desc,
                $quantity,
                $unit,
                $reorder,
                $location,
                $itemId
            );

        } else {

            // update image also
            $stmt = $conn->prepare("
                UPDATE inventory_items
                SET item_name=?,
                    category_id=?,
                    description=?,
                    image=?,
                    quantity=?,
                    unit=?,
                    reorder_level=?,
                    storage_location=?
                WHERE id=?
            ");

            $stmt->bind_param(
                "sisssisis",
                $itemName,
                $catId,
                $desc,
                $image,
                $quantity,
                $unit,
                $reorder,
                $location,
                $itemId
            );
        }

        $stmt->execute();
        $stmt->close();

        logActivity($conn, $me['id'], "Edited item \"$itemName\" (#$itemId)");
        $flash = "Item \"$itemName\" updated successfully.";
    }
    } elseif ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $nr = $conn->prepare("SELECT item_name FROM inventory_items WHERE id=?"); $nr->bind_param("i",$itemId); $nr->execute();
        $itemName = $nr->get_result()->fetch_assoc()['item_name'] ?? "#$itemId"; $nr->close();
        $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id=?"); $stmt->bind_param("i",$itemId); $stmt->execute(); $stmt->close();
        logActivity($conn,$me['id'],"Deleted item \"$itemName\"");
        $flash = "Item \"$itemName\" deleted.";
    } elseif ($action === 'receive_stock') {
        $itemId  = (int)($_POST['item_id'] ?? 0);
        $recvQty = (int)($_POST['recv_quantity'] ?? 0);
        $source  = trim($_POST['recv_source'] ?? '') ?: 'PRO';
        $refNo   = trim($_POST['recv_reference'] ?? '');
        $recvDate = trim($_POST['recv_date'] ?? '') ?: date('Y-m-d');

        $ir = $conn->prepare("SELECT item_name FROM inventory_items WHERE id=?"); $ir->bind_param("i",$itemId); $ir->execute();
        $item = $ir->get_result()->fetch_assoc(); $ir->close();

        if (!$item) { $flash = 'Item not found.'; $flashType = 'danger'; }
        elseif ($recvQty <= 0) { $flash = 'Enter a quantity greater than zero.'; $flashType = 'danger'; }
        else {
            $conn->begin_transaction();
            try {
                $s = $conn->prepare("INSERT INTO stock_receipts (item_id,quantity,source,reference_no,received_by,date_received) VALUES (?,?,?,?,?,?)");
                $s->bind_param("iissis",$itemId,$recvQty,$source,$refNo,$me['id'],$recvDate); $s->execute(); $s->close();
                $u = $conn->prepare("UPDATE inventory_items SET quantity=quantity+? WHERE id=?");
                $u->bind_param("ii",$recvQty,$itemId); $u->execute(); $u->close();
                $conn->commit();
                logActivity($conn,$me['id'],"Received $recvQty x \"{$item['item_name']}\" from $source");
                $flash = "Received $recvQty x \"{$item['item_name']}\" from $source.";
            } catch (Exception $e) { $conn->rollback(); $flash = 'Error recording the stock receipt. Please try again.'; $flashType = 'danger'; }
        }
    }
}

$pageTitle = 'Inventory List'; $activeNav = 'inventory';
require __DIR__ . '/../includes/admin_header.php';

$catFilter = (int)($_GET['category'] ?? 0);
$search    = trim($_GET['q'] ?? '');
$where = []; $params = []; $types = '';
if ($catFilter>0) { $where[]="i.category_id=?"; $params[]=$catFilter; $types.='i'; }
if ($search!=='') { $where[]="(i.item_name LIKE ? OR i.item_code LIKE ?)"; $like="%$search%"; $params[]=$like; $params[]=$like; $types.='ss'; }
$sql = "SELECT i.*,c.name AS category_name FROM inventory_items i JOIN categories c ON c.id=i.category_id" . ($where?' WHERE '.implode(' AND ',$where):'') . " ORDER BY i.date_updated DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types,...$params);
$stmt->execute(); $items = $stmt->get_result();

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$catList = [];
while ($c = $categories->fetch_assoc()) $catList[] = $c;
?>

<div class="page-head">
  <div><h2>Inventory List</h2><div class="desc">Manage all inventory items</div></div>
  <button class="btn btn-primary" onclick="openAddModal()">+ Add New Item</button>
</div>

<?= $flash ? alertBox($flash, $flashType) : '' ?>

<form method="GET" class="filter-bar">
  <input type="text" name="q" class="form-control search-grow" placeholder="Search item name or code..." value="<?= h($search) ?>">
  <select name="category" class="form-control" onchange="this.form.submit()">
    <option value="0">All Categories</option>
    <?php foreach ($catList as $c): ?><option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-outline" type="submit">Filter</button>
  <?php if ($catFilter||$search): ?><a href="inventory.php" class="btn btn-ghost">Clear</a><?php endif; ?>
</form>

<div class="panel">
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Code</th><th>Item Name</th><th>Category</th><th>Quantity</th><th class="text-center">Status</th><th>Date Added</th><th class="text-center">Actions</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$items->fetch_assoc()): $hasRows=true;
        $status = stockStatus((int)$row['quantity'],(int)$row['reorder_level']);
        $ij = h(json_encode(['id'=>$row['id'],'item_name'=>$row['item_name'],'category_id'=>$row['category_id'],'description'=>$row['description'], 'image'=> $row['image'],'quantity'=>$row['quantity'],'unit'=>$row['unit'],'reorder_level'=>$row['reorder_level'],'storage_location'=>$row['storage_location'],'item_code'=>$row['item_code'],'category_name'=>$row['category_name'],'date_added'=>$row['date_added']]));
    ?>
      <tr>
        <td><span class="item-code-chip"><?= h($row['item_code']) ?></span></td>
        <td class="cell-strong"><?= h($row['item_name']) ?></td>
        <td><?= h($row['category_name']) ?></td>
        <td><?= (int)$row['quantity'] ?> <span class="cell-muted"><?= h($row['unit']) ?></span></td>
        <td class="text-center"><?= badge(stockStatusLabel($status),stockStatusClass($status)) ?></td>
        <td class="cell-muted"><?= formatDate($row['date_added']) ?></td>
        <td class="text-center"><div class="row-actions">
          <button class="icon-btn" title="View" onclick='openViewModal(<?= $ij ?>)'><i class="fa-solid fa-eye"></i></button>
          <button class="icon-btn" title="Edit" onclick='openEditModal(<?= $ij ?>)'><i class="fa-solid fa-pen"></i></button>
          <button class="icon-btn" title="Receive Stock" onclick='openReceiveModal(<?= $ij ?>)'><i class="fa-solid fa-truck-ramp-box"></i></button>
          <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete &quot;<?= h(addslashes($row['item_name'])) ?>&quot;?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="item_id" value="<?= $row['id'] ?>">
            <button class="icon-btn danger" title="Delete" type="submit"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div></td>
      </tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(7, 'fa-solid fa-box-open', 'No items found. Try adjusting your filters or add a new item.') ?><?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- Add/Edit modal -->
<div class="modal-backdrop" id="itemModal">
  <div class="modal">
    <div class="modal-head"><h3 id="itemModalTitle">Add New Item</h3><button class="modal-close" onclick="closeModal('itemModal')" aria-label="Close">✕</button></div>
    <form method="POST" enctype="multipart/form-data">

      <div class="modal-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" id="item_action" value="add">
        <input type="hidden" name="item_id" id="item_id">
        <div class="form-group"><label for="item_name">Item Name</label><input type="text" name="item_name" id="item_name" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label for="category_id">Category</label>
            <select name="category_id" id="category_id" class="form-control" required>
              <?php foreach ($catList as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label for="unit">Unit</label><input type="text" name="unit" id="unit" class="form-control" value="pcs" required></div>
        </div>
        <div class="form-group"><label for="description">Description</label><textarea name="description" id="description" class="form-control"></textarea></div>
        <div class="form-group">
          <label for="image">Item Image</label>

          <input
              type="file"
              name="image"
              id="image"
              class="form-file"
              accept=".jpg,.jpeg,.png,.webp"
          >

          <div class="hint">
              Leave blank to keep the current image.
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="quantity">Quantity</label><input type="number" name="quantity" id="quantity" class="form-control" min="0" required></div>
          <div class="form-group"><label for="reorder_level">Reorder Level</label><input type="number" name="reorder_level" id="reorder_level" class="form-control" min="0" value="10" required><div class="hint">Low-stock alert triggers at or below this value.</div></div>
        </div>
        <div class="form-group"><label for="storage_location">Storage Location</label><input type="text" name="storage_location" id="storage_location" class="form-control" placeholder="e.g. IT Storage Room - Shelf A2"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('itemModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- View modal -->
<div class="modal-backdrop" id="viewModal">
  <div class="modal modal-lg"><div class="modal-head"><h3>Item Details</h3><button class="modal-close" onclick="closeModal('viewModal')" aria-label="Close">✕</button></div>
    <div class="modal-body" id="viewModalBody"></div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('viewModal')">Close</button></div>
  </div>
</div>

<!-- Receive Stock modal -->
<div class="modal-backdrop" id="receiveModal">
  <div class="modal">
    <div class="modal-head"><h3 id="receiveModalTitle">Receive Stock</h3><button class="modal-close" onclick="closeModal('receiveModal')" aria-label="Close">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="receive_stock">
        <input type="hidden" name="item_id" id="recv_item_id">
        <div class="form-group"><label>Item</label><input type="text" id="recv_item_label" class="form-control" disabled></div>
        <div class="form-row">
          <div class="form-group"><label for="recv_quantity">Quantity Received</label><input type="number" name="recv_quantity" id="recv_quantity" class="form-control" min="1" required></div>
          <div class="form-group"><label for="recv_date">Date Received</label><input type="date" name="recv_date" id="recv_date" class="form-control" value="<?= h(date('Y-m-d')) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="recv_source">Source</label><input type="text" name="recv_source" id="recv_source" class="form-control" value="PRO" placeholder="e.g. PRO"></div>
          <div class="form-group"><label for="recv_reference">Reference No.</label><input type="text" name="recv_reference" id="recv_reference" class="form-control" placeholder="RIS / DR number (optional)"></div>
        </div>
        <div class="hint">This adds to the item's current stock and feeds the Monthly Report's "Received from PRO" column.</div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('receiveModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Receipt</button>
      </div>
    </form>
  </div>
</div>

<script>
// Canonical item image folder, resolved server-side.
const ITEM_IMG_BASE = <?= json_encode(itemsImageUrl()) ?>;

function openAddModal(){
  document.getElementById('itemModalTitle').textContent='Add New Item';
  document.getElementById('item_action').value='add';
  document.getElementById('item_id').value='';
  ['item_name','description','storage_location'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('quantity').value=0;
  document.getElementById('reorder_level').value=10;
  document.getElementById('unit').value='pcs';
  openModal('itemModal');
}
function openEditModal(item){
  document.getElementById('itemModalTitle').textContent='Edit Item — '+item.item_code;
  document.getElementById('item_action').value='edit';
  document.getElementById('item_id').value=item.id;
  document.getElementById('item_name').value=item.item_name;
  document.getElementById('category_id').value=item.category_id;
  document.getElementById('unit').value=item.unit;
  document.getElementById('description').value=item.description||'';
  document.getElementById('quantity').value=item.quantity;
  document.getElementById('reorder_level').value=item.reorder_level;
  document.getElementById('storage_location').value=item.storage_location||'';
  openModal('itemModal');
}
function openReceiveModal(item){
  document.getElementById('receiveModalTitle').textContent='Receive Stock — '+item.item_code;
  document.getElementById('recv_item_id').value=item.id;
  document.getElementById('recv_item_label').value=item.item_name+' ('+item.item_code+') — currently '+item.quantity+' '+item.unit;
  document.getElementById('recv_quantity').value='';
  document.getElementById('recv_source').value='PRO';
  document.getElementById('recv_reference').value='';
  document.getElementById('recv_date').valueAsDate=new Date();
  openModal('receiveModal');
}
function openViewModal(item){
    const body = document.getElementById('viewModalBody');

    // Static markup only — no interpolation of item data here.
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
    fillFields(body, {
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
        .appendChild(itemImageNode(item.image, ITEM_IMG_BASE));

    openModal('viewModal');
}
</script>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

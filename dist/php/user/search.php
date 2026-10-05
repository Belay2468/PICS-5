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
    $itemId=(int)($_POST['item_id']??0); $reqName=trim($_POST['requester_name']??'');
    $dept=trim($_POST['department']??''); $qty=max(1,(int)($_POST['quantity']??0)); $purpose=trim($_POST['purpose']??'');
    $ir=$conn->prepare("SELECT item_name,quantity FROM inventory_items WHERE id=?"); $ir->bind_param("i",$itemId); $ir->execute();
    $item=$ir->get_result()->fetch_assoc(); $ir->close();
    if (!$item) { $flash='Item not found.'; $flashType='danger'; }
    elseif ($reqName===''||$dept==='') { $flash='Please fill in your name and department.'; $flashType='danger'; }
    elseif ($qty>(int)$item['quantity']) { $flash="Insufficient stock — only {$item['quantity']} available."; $flashType='danger'; }
    else {
        $s=$conn->prepare("INSERT INTO item_requests (item_id,requester_id,requester_name,department,quantity_requested,purpose) VALUES (?,?,?,?,?,?)");
        $s->bind_param("iissis",$itemId,$me['id'],$reqName,$dept,$qty,$purpose); $s->execute(); $s->close();
        logActivity($conn,$me['id'],"Requested $qty x \"{$item['item_name']}\"");
        $flash="Your request for \"{$item['item_name']}\" was submitted.";
    }
}

$pageTitle='Search Results';
require __DIR__ . '/../includes/user_header.php';

$q=trim($_GET['q']??''); $itemRows=[];
if ($q!=='') {
    $like="%$q%";
    $stmt=$conn->prepare("SELECT i.*,c.icon cat_icon,c.name cat_name FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE i.item_name LIKE ? OR i.item_code LIKE ? OR c.name LIKE ? ORDER BY i.item_name");
    $stmt->bind_param("sss",$like,$like,$like); $stmt->execute();
    $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $itemRows[]=$r;
}
?>

<div class="user-content" style="margin-top:24px">
  <p><a href="dashboard.php">← Back to Dashboard</a></p>
  <?= $flash ? alertBox($flash, $flashType) : '' ?>

  <form class="user-search" style="margin:0 0 22px;max-width:100%" action="search.php" method="GET">
    <input type="text" name="q" placeholder="Search supplies, items, or categories..." value="<?= h($q) ?>">
    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>

  <h3 class="search-results-heading"><?= count($itemRows) ?> result<?= count($itemRows)===1?'':'s' ?> for "<?= h($q) ?>"</h3>

  <div class="item-grid">
    <?php if($q===''): ?><div class="search-prompt"><div class="prompt-icon"><i class="fa-solid fa-magnifying-glass"></i></div>Type a keyword above to search.</div><?php elseif(empty($itemRows)): ?><?= emptyStatePanel('fa-solid fa-box-open', 'No items matched your search.', false, '', 'grid-span-full') ?><?php endif; ?>
    <?php foreach($itemRows as $row):
        $status=stockStatus((int)$row['quantity'],(int)$row['reorder_level']);
    ?>
    <div class="item-card">

      <div class="item-image">

        <?php if(itemImageExists($row['image'])): ?>

          <img src="<?= h(itemsImageUrl($row['image'])) ?>"
               alt="<?= h($row['item_name']) ?>">
        
        <?php else: ?>
        
          <i class="<?= h($row['cat_icon']) ?>"></i>
        
        <?php endif; ?>
        
      </div>
        
        
      <div class="item-info">
        
        <h3>
          <?= h($row['item_name']) ?>
        </h3>
        
        
        <div class="category-crumb">
          <span class="cat-dot"></span> <?= h($row['cat_name']) ?> · <?= h($row['item_code']) ?>
        </div>
        
        
        <div class="item-qty-label">
          Available Quantity
        </div>
        
        
        <div class="item-qty">
          <?= (int)$row['quantity'] ?>
        
          <span>
            <?= h($row['unit']) ?>
          </span>
        </div>
        
        
        <?= badge(
          stockStatusLabel($status),
          stockStatusClass($status)
        ) ?>

        
        <button
          type="button"
          class="btn btn-outline btn-sm js-request-item"
          data-item-id="<?= (int)$row['id'] ?>"
          data-item-label="<?= h($row['item_name'].' ('.$row['item_code'].')') ?>"
          data-item-qty="<?= (int)$row['quantity'] ?>"
          <?= $row['quantity']==0 ? 'disabled' : '' ?>>

          <i class="fa-solid fa-paper-plane"></i>
          <?= $row['quantity']==0 ? 'Out of Stock' : 'Request Item' ?>
        
        </button>
        
        
      </div>
        
    </div>
    <?php endforeach; ?>
  </div>
</div>

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
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('requestModal')">Cancel</button><button type="submit" class="btn btn-primary">  <i class="fa-solid fa-paper-plane"></i>
Submit Request</button></div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>

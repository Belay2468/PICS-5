<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$me = currentUser();
$flash = ''; $flashType = 'success'; $activeTab = 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValid()) {
    $flash = CSRF_ERROR; $flashType = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' || $action === 'reject') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $conn->prepare("SELECT r.*,i.item_name,i.quantity AS available FROM item_requests r JOIN inventory_items i ON i.id=r.item_id WHERE r.id=? AND r.status='pending'");
        $stmt->bind_param("i",$requestId); $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if (!$req) {
            $flash = 'That request has already been processed.'; $flashType = 'danger';
        } elseif ($action === 'reject') {
            $upd = $conn->prepare("UPDATE item_requests SET status='rejected',date_processed=NOW(),processed_by=? WHERE id=?");
            $upd->bind_param("ii",$me['id'],$requestId); $upd->execute(); $upd->close();
            logActivity($conn,$me['id'],"Rejected request #$requestId for \"{$req['item_name']}\"");
            $flash = "Request for \"{$req['item_name']}\" rejected.";
        } else {
            if ((int)$req['quantity_requested'] > (int)$req['available']) {
                $flash = "Cannot approve — only {$req['available']} available."; $flashType = 'danger';
            } else {
                $conn->begin_transaction();
                try {
                    $d = $conn->prepare("UPDATE inventory_items SET quantity=quantity-? WHERE id=?");
                    $d->bind_param("ii",$req['quantity_requested'],$req['item_id']); $d->execute(); $d->close();
                    $i = $conn->prepare("INSERT INTO issuance (request_id,item_id,quantity,issued_to,department,purpose,issued_by) VALUES (?,?,?,?,?,?,?)");
                    $i->bind_param("iiisssi",$requestId,$req['item_id'],$req['quantity_requested'],$req['requester_name'],$req['department'],$req['purpose'],$me['id']); $i->execute(); $i->close();
                    $upd = $conn->prepare("UPDATE item_requests SET status='approved',date_processed=NOW(),processed_by=? WHERE id=?");
                    $upd->bind_param("ii",$me['id'],$requestId); $upd->execute(); $upd->close();
                    $conn->commit();
                    logActivity($conn,$me['id'],"Approved request #$requestId for \"{$req['item_name']}\" (qty {$req['quantity_requested']})");
                    $flash = "Request for \"{$req['item_name']}\" approved. Stock updated.";
                } catch (Exception $e) { $conn->rollback(); $flash='Error approving request. Please try again.'; $flashType='danger'; }
            }
        }
    } elseif ($action === 'manual_issue') {
        $itemId    = (int)($_POST['item_id'] ?? 0);
        $qty       = max(1,(int)($_POST['quantity'] ?? 0));
        $issuedTo  = trim($_POST['issued_to'] ?? '');
        $dept      = trim($_POST['department'] ?? '');
        $purpose   = trim($_POST['purpose'] ?? '');
        $activeTab = 'manual';
        $ir = $conn->prepare("SELECT item_name,quantity FROM inventory_items WHERE id=?"); $ir->bind_param("i",$itemId); $ir->execute();
        $item = $ir->get_result()->fetch_assoc(); $ir->close();
        if (!$item||$issuedTo==='') { $flash='Please select an item and enter who it is issued to.'; $flashType='danger'; }
        elseif ($qty>(int)$item['quantity']) { $flash="Cannot issue $qty — only {$item['quantity']} in stock."; $flashType='danger'; }
        else {
            $conn->begin_transaction();
            try {
                $d=$conn->prepare("UPDATE inventory_items SET quantity=quantity-? WHERE id=?"); $d->bind_param("ii",$qty,$itemId); $d->execute(); $d->close();
                $i=$conn->prepare("INSERT INTO issuance (item_id,quantity,issued_to,department,purpose,issued_by) VALUES (?,?,?,?,?,?)"); $i->bind_param("iisssi",$itemId,$qty,$issuedTo,$dept,$purpose,$me['id']); $i->execute(); $i->close();
                $conn->commit();
                logActivity($conn,$me['id'],"Issued {$qty} x \"{$item['item_name']}\" to $issuedTo");
                $flash = "{$qty} x \"{$item['item_name']}\" issued to $issuedTo.";
            } catch(Exception $e){ $conn->rollback(); $flash='Error processing issuance.'; $flashType='danger'; }
        }
    }
}

$pageTitle='Issuance'; $activeNav='issuance';
require __DIR__ . '/../includes/admin_header.php';

$pending = $conn->query("SELECT r.*,i.item_name,i.quantity AS available,i.unit FROM item_requests r JOIN inventory_items i ON i.id=r.item_id WHERE r.status='pending' ORDER BY r.date_requested ASC");
$pendingCount = $pending->num_rows;
$itemsForSelect = $conn->query("SELECT id,item_name,item_code,quantity,unit FROM inventory_items WHERE quantity>0 ORDER BY item_name");
$itemOptions = [];
while($r=$itemsForSelect->fetch_assoc()) $itemOptions[]=$r;
$history = $conn->query("SELECT iss.*,i.item_name,i.item_code FROM issuance iss JOIN inventory_items i ON i.id=iss.item_id ORDER BY iss.date_issued DESC LIMIT 50");
?>

<div class="page-head"><div><h2>Issuance</h2><div class="desc">Review item requests and record outgoing supplies</div></div></div>
<?= $flash ? alertBox($flash, $flashType) : '' ?>

<div class="tabs">
  <a href="#" class="tab-link <?= $activeTab==='pending'?'active':'' ?>" data-tab-link="iss" data-tab-target="tab-pending" onclick="showTab('iss','tab-pending');return false;">
    Pending Requests <?php if($pendingCount): ?><span class="badge badge-warning"><?= $pendingCount ?></span><?php endif; ?>
  </a>
  <a href="#" class="tab-link <?= $activeTab==='manual'?'active':'' ?>" data-tab-link="iss" data-tab-target="tab-manual" onclick="showTab('iss','tab-manual');return false;">Manual Issuance</a>
  <a href="#" class="tab-link" data-tab-link="iss" data-tab-target="tab-history" onclick="showTab('iss','tab-history');return false;">Issuance History</a>
</div>

<div id="tab-pending" data-tab-group="iss" style="display:<?= $activeTab==='pending'?'block':'none' ?>">
  <div class="panel"><div class="table-wrap"><table class="data-table">
    <thead><tr><th>Requester</th><th>Item</th><th>Qty</th><th>Department</th><th>Purpose</th><th>Requested</th><th class="text-center">Action</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$pending->fetch_assoc()): $hasRows=true; ?>
      <tr>
        <td class="cell-strong"><?= h($row['requester_name']) ?></td>
        <td><?= h($row['item_name']) ?> <span class="cell-muted">(<?= (int)$row['available'] ?> avail.)</span></td>
        <td><?= (int)$row['quantity_requested'] ?></td>
        <td><?= h($row['department']) ?></td>
        <td class="cell-muted"><?= h($row['purpose']) ?></td>
        <td class="cell-muted"><?= formatDateTime($row['date_requested']) ?></td>
        <td class="text-center"><div class="row-actions issuance-actions">
          <form method="POST" onsubmit="return confirm('Approve? Stock will be deducted.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= $row['id'] ?>">
            <button class="btn btn-success btn-sm" type="submit">Approve</button>
          </form>
          <form method="POST" onsubmit="return confirm('Reject this request?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= $row['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Reject</button>
          </form>
        </div></td>
      </tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(7, 'fa-solid fa-inbox', 'No pending requests right now.') ?><?php endif; ?>
    </tbody>
  </table></div></div>
</div>

<div id="tab-manual" data-tab-group="iss" style="display:<?= $activeTab==='manual'?'block':'none' ?>">
  <div class="panel" style="max-width:560px">
    <div class="panel-head"><h3>Issue New Item</h3></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="manual_issue">
      <div class="form-row">
        <div class="form-group"><label for="iss_item_id">Select Item</label>
          <select name="item_id" id="iss_item_id" class="form-control" required>
            <option value="">Choose an item...</option>
            <?php foreach($itemOptions as $opt): ?><option value="<?= $opt['id'] ?>"><?= h($opt['item_name']) ?> (<?= (int)$opt['quantity'] ?> avail.)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="iss_quantity">Quantity</label><input type="number" name="quantity" id="iss_quantity" class="form-control" min="1" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="iss_issued_to">Issued To</label><input type="text" name="issued_to" id="iss_issued_to" class="form-control" placeholder="Employee name" required></div>
        <div class="form-group"><label for="iss_department">Department</label><input type="text" name="department" id="iss_department" class="form-control" placeholder="e.g. IT, Medical, Admin"></div>
      </div>
      <div class="form-group"><label for="iss_purpose">Purpose / Notes</label><textarea name="purpose" id="iss_purpose" class="form-control" placeholder="Enter purpose or notes"></textarea></div>
      <button type="submit" class="btn btn-primary">Process Issuance</button>
    </form>
  </div>
</div>

<div id="tab-history" data-tab-group="iss" style="display:none">
  <div class="panel"><div class="table-wrap"><table class="data-table">
    <thead><tr><th>#</th><th>Item</th><th>Quantity</th><th>Issued To</th><th>Department</th><th>Date</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$history->fetch_assoc()): $hasRows=true; ?>
      <tr><td><span class="iss-id-chip">#<?= $row['id'] ?></span></td><td class="cell-strong"><?= h($row['item_name']) ?></td><td><?= (int)$row['quantity'] ?></td><td><?= h($row['issued_to']) ?></td><td><?= h($row['department']) ?></td><td class="cell-muted"><?= formatDate($row['date_issued']) ?></td></tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(6, 'fa-solid fa-clock-rotate-left', 'No issuance history yet.') ?><?php endif; ?>
    </tbody>
  </table></div></div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('user');
$me = currentUser();

$pageTitle='My Requests';
require __DIR__ . '/../includes/user_header.php';

$stmt=$conn->prepare("SELECT r.*,i.item_name,i.item_code,i.unit FROM item_requests r JOIN inventory_items i ON i.id=r.item_id WHERE r.requester_id=? ORDER BY r.date_requested DESC");
$stmt->bind_param("i",$me['id']); $stmt->execute(); $myReqs=$stmt->get_result();
$statusBadge=['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'];
?>

<div class="user-content" style="margin-top:24px">
  <p><a href="dashboard.php">← Back to Dashboard</a></p>
  <div class="panel">
    <div class="panel-head"><h3>My Item Requests</h3></div>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Item</th><th>Qty</th><th>Purpose</th><th>Status</th><th>Date Requested</th><th>Date Processed</th></tr></thead>
      <tbody>
      <?php $hasRows=false; while($row=$myReqs->fetch_assoc()): $hasRows=true; ?>
        <tr class="req-row-<?= h($row['status']) ?>">
          <td class="req-item-cell"><div class="item-name"><?= h($row['item_name']) ?></div><div class="item-code"><?= h($row['item_code']) ?></div></td>
          <td><?= (int)$row['quantity_requested'] ?> <?= h($row['unit']) ?></td>
          <td class="cell-muted"><?= h($row['purpose']) ?></td>
          <td><?= badge(ucfirst($row['status']),$statusBadge[$row['status']]) ?></td>
          <td class="cell-muted"><?= formatDateTime($row['date_requested']) ?></td>
          <td class="cell-muted"><?= $row['date_processed']?formatDateTime($row['date_processed']):'—' ?></td>
        </tr>
      <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(6, 'fa-solid fa-inbox', 'No requests yet. ', false, '<a href="dashboard.php">Browse supplies</a> to get started.') ?><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>

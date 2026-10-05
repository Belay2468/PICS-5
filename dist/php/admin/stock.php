<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$me = currentUser();
$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD']==='POST' && !csrfValid()) {
    $flash = CSRF_ERROR; $flashType = 'danger';
} elseif ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='receive_stock') {
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

$pageTitle='Stock Monitoring'; $activeNav='stock';
require __DIR__ . '/../includes/admin_header.php';

$critical = (int)($conn->query("SELECT COUNT(*) c FROM inventory_items WHERE quantity=0")->fetch_assoc()['c']);
$lowOnly  = (int)($conn->query("SELECT COUNT(*) c FROM inventory_items WHERE quantity>0 AND quantity<=reorder_level")->fetch_assoc()['c']);
$healthy  = (int)($conn->query("SELECT COUNT(*) c FROM inventory_items WHERE quantity>reorder_level")->fetch_assoc()['c']);

$months=[]; for($i=5;$i>=0;$i--) $months[date('Y-m',strtotime("-$i months"))]=date('M',strtotime("-$i months"));
$issued=array_fill_keys(array_keys($months),0);
$res=$conn->query("SELECT DATE_FORMAT(date_issued,'%Y-%m') ym,SUM(quantity) qty FROM issuance GROUP BY ym");
while($row=$res->fetch_assoc()) if(isset($issued[$row['ym']])) $issued[$row['ym']]=(int)$row['qty'];
$maxMove=max(1,max($issued));

$alerts=$conn->query("SELECT i.*,c.name AS cat FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE i.quantity<=i.reorder_level ORDER BY i.quantity ASC");
?>

<div class="page-head"><div><h2>Stock Monitoring</h2><div class="desc">Monitor inventory levels and stock movements</div></div></div>
<?= $flash ? alertBox($flash, $flashType) : '' ?>

<div class="stat-grid">
  <div class="stat-card"><div><div class="stat-label">Critical Stock</div><div class="stat-value critical"><?= $critical ?></div><div class="cell-muted">Out of stock items</div></div><div class="stat-icon ic-red"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
  <div class="stat-card"><div><div class="stat-label">Low Stock</div><div class="stat-value low-val"><?= $lowOnly ?></div><div class="cell-muted">Need reordering</div></div><div class="stat-icon ic-amber"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
  <div class="stat-card"><div><div class="stat-label">Healthy Stock</div><div class="stat-value healthy"><?= $healthy ?></div><div class="cell-muted">Sufficient quantity</div></div><div class="stat-icon ic-green"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Inventory Movement Trend</h3></div>
  <?php if (array_sum($issued) === 0): ?>
    <div class="chart-empty">No issuance recorded yet.</div>
  <?php else: ?>
  <div class="bar-chart">
    <?php foreach($months as $ym=>$label): ?>
      <div class="bar-col">
        <?php if ($issued[$ym] > 0): ?><div class="bar-value"><?= $issued[$ym] ?></div><?php endif; ?>
        <div class="bar" style="height:<?= max(4,round(($issued[$ym]/$maxMove)*100)) ?>%" title="Issued: <?= $issued[$ym] ?>"></div>
        <div class="bar-label"><?= h($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h3>Stock Alerts &amp; Recommendations</h3><span class="badge badge-warning"><?= $alerts->num_rows ?> alerts</span></div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Item</th><th>Category</th><th>Current Stock</th><th>Min. Required</th><th class="text-center">Priority</th><th class="text-center">Action</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$alerts->fetch_assoc()): $hasRows=true;
        $priority=$row['quantity']==0?'Critical':'Medium';
        $pClass=$row['quantity']==0?'badge-danger':'badge-warning';
        $stockLevelClass=$row['quantity']==0?'out':'low';
        // A 0-quantity item computes to a literal 0%-width fill, which
        // renders as nothing at all — the one status (out of stock) that
        // most needs a visible red indicator would show none. Floor it so
        // there's always a visible sliver of the status colour.
        $stockLevelPct=max(6,min(100,round(((int)$row['quantity']/max(1,(int)$row['reorder_level']))*100)));
    ?>
      <tr>
        <td class="cell-strong"><?= h($row['item_name']) ?></td>
        <td><?= h($row['cat']) ?></td>
        <td class="<?= $row['quantity']==0?'qty-out':'qty-low' ?>"><div class="stock-bar-wrap"><span class="stock-bar <?= $stockLevelClass ?>" style="width:<?= $stockLevelPct ?>%"></span></div><?= (int)$row['quantity'] ?></td>
        <td class="cell-muted"><?= (int)$row['reorder_level'] ?></td>
        <td class="text-center"><?= badge($priority,$pClass) ?></td>
        <td class="text-center"><button class="btn btn-primary btn-sm js-open-restock" type="button"
              data-item-id="<?= (int)$row['id'] ?>"
              data-item-code="<?= h($row['item_code']) ?>"
              data-item-name="<?= h($row['item_name']) ?>"
              data-item-qty="<?= (int)$row['quantity'] ?>"
              data-item-unit="<?= h($row['unit']) ?>">Reorder</button></td>
      </tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(6, 'fa-solid fa-circle-check', 'All items are sufficiently stocked.', true) ?><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-backdrop" id="restockModal">
  <div class="modal">
    <div class="modal-head"><h3 id="restockTitle">Receive Stock</h3><button class="modal-close" onclick="closeModal('restockModal')" aria-label="Close">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="receive_stock">
        <input type="hidden" name="item_id" id="restock_item_id">
        <div class="form-group"><label>Item</label><input type="text" id="restock_item_label" class="form-control" disabled></div>
        <div class="form-row">
          <div class="form-group"><label for="restock_quantity">Quantity Received</label><input type="number" name="recv_quantity" id="restock_quantity" class="form-control" min="1" required autofocus></div>
          <div class="form-group"><label for="restock_date">Date Received</label><input type="date" name="recv_date" id="restock_date" class="form-control" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="restock_source">Source</label><input type="text" name="recv_source" id="restock_source" class="form-control" value="PRO" placeholder="e.g. PRO"></div>
          <div class="form-group"><label for="restock_reference">Reference No.</label><input type="text" name="recv_reference" id="restock_reference" class="form-control" placeholder="RIS / DR number (optional)"></div>
        </div>
        <div class="hint">This adds to the item's current stock and feeds the Monthly Report's "Received from PRO" column.</div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('restockModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Receipt</button></div>
    </form>
  </div>
</div>

<script>
function openRestock(id,code,name,qty,unit){
  document.getElementById('restock_item_id').value=id;
  document.getElementById('restockTitle').textContent='Receive Stock — '+code;
  document.getElementById('restock_item_label').value=name+' ('+code+') — currently '+qty+' '+unit;
  document.getElementById('restock_quantity').value='';
  document.getElementById('restock_source').value='PRO';
  document.getElementById('restock_reference').value='';
  document.getElementById('restock_date').valueAsDate=new Date();
  openModal('restockModal');
}
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.js-open-restock');
  if (!btn) return;
  openRestock(btn.dataset.itemId, btn.dataset.itemCode, btn.dataset.itemName, btn.dataset.itemQty, btn.dataset.itemUnit);
});
</script>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

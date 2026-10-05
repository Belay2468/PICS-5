<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';

// KPI stats
$totalInStock  = (int)($conn->query("SELECT COALESCE(SUM(quantity),0) c FROM inventory_items")->fetch_assoc()['c']);
$totalIssued   = (int)($conn->query("SELECT COALESCE(SUM(quantity),0) c FROM issuance")->fetch_assoc()['c']);
$totalItems    = (int)($conn->query("SELECT COUNT(*) c FROM inventory_items")->fetch_assoc()['c']);
$lowStockCount = (int)($conn->query("SELECT COUNT(*) c FROM inventory_items WHERE quantity<=reorder_level")->fetch_assoc()['c']);
$pendingCount  = (int)($conn->query("SELECT COUNT(*) c FROM item_requests WHERE status='pending'")->fetch_assoc()['c']);

// Monthly issuance trend (last 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("-$i months"))] = date('M', strtotime("-$i months"));
}
$monthlyIssued = array_fill_keys(array_keys($months), 0);
$res = $conn->query("SELECT DATE_FORMAT(date_issued,'%Y-%m') ym, SUM(quantity) qty FROM issuance GROUP BY ym");
while ($row = $res->fetch_assoc()) {
    if (isset($monthlyIssued[$row['ym']])) $monthlyIssued[$row['ym']] = (int)$row['qty'];
}
$maxMonthly = max(1, max($monthlyIssued));

// Category distribution donut
$catRows = []; $totalQtyAll = 0;
$res = $conn->query("SELECT c.name, COALESCE(SUM(i.quantity),0) qty FROM categories c LEFT JOIN inventory_items i ON i.category_id=c.id GROUP BY c.id ORDER BY c.id");
while ($row = $res->fetch_assoc()) { $catRows[] = $row; $totalQtyAll += (int)$row['qty']; }
$colors = CHART_CATEGORY_COLORS;
$gradParts = []; $cursor = 0;
foreach ($catRows as $idx => $row) {
    $pct = $totalQtyAll > 0 ? round(($row['qty']/$totalQtyAll)*100) : 0;
    $gradParts[] = $colors[$idx%4]." $cursor% ".($cursor+$pct).'%';
    $cursor += $pct;
}
$donutCss = implode(', ', $gradParts);

// Most requested
$mostRequested = $conn->query("SELECT i.item_name, COUNT(r.id) cnt FROM item_requests r JOIN inventory_items i ON i.id=r.item_id GROUP BY r.item_id ORDER BY cnt DESC LIMIT 5");
// Low stock warnings
$lowStock = $conn->query("SELECT item_name, quantity, reorder_level, c.name cat FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE quantity<=reorder_level ORDER BY quantity ASC LIMIT 5");
// Recent issuance
$recentIssuance = $conn->query("SELECT iss.*, i.item_name FROM issuance iss JOIN inventory_items i ON i.id=iss.item_id ORDER BY iss.date_issued DESC LIMIT 6");
?>

<div class="page-head">
    <div>
        <h2>Dashboard</h2>
        <div class="desc">
            Monitor inventory status, stock levels, and recent activities.
        </div>
    </div>

    <div class="page-actions">
        <a href="inventory.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i>
            Add Item
        </a>

        <a href="reports.php" class="btn btn-outline">
            <i class="fa-solid fa-file-lines"></i>
            Reports
        </a>
    </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><div><div class="stat-label">Items In Stock</div><div class="stat-value"><?= number_format($totalInStock) ?></div></div><div class="stat-icon ic-green"><i class="fa-solid fa-boxes-stacked"></i></div></div>
  <div class="stat-card"><div><div class="stat-label">Items Issued </div><div class="stat-value"><?= number_format($totalIssued) ?></div></div><div class="stat-icon ic-blue"><i class="fa-solid fa-arrow-right-arrow-left"></i></div></div>
  <div class="stat-card"><div><div class="stat-label">Inventory Records</div><div class="stat-value"><?= number_format($totalItems) ?></div></div><div class="stat-icon ic-amber"><i class="fa-solid fa-layer-group"></i></div></div>
  <div class="stat-card">
    <div><div class="stat-label">Low Stock Items</div><div class="stat-value"><?= number_format($lowStockCount) ?></div>
    <?php if($pendingCount>0): ?><div class="stat-delta text-warning"><?= $pendingCount ?> pending request<?= $pendingCount===1?'':'s' ?></div><?php endif; ?>
    </div><div class="stat-icon ic-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
  </div>
</div>

<div class="panel-grid-2">
  <div class="panel panel-chart">
    <div class="panel-head"><h3>Monthly Issuance</h3></div>
    <?php if (array_sum($monthlyIssued) === 0): ?>
      <div class="chart-empty">No issuance recorded yet.</div>
    <?php else: ?>
    <div class="bar-chart">
      <?php foreach ($monthlyIssued as $ym => $qty): ?>
        <div class="bar-col">
          <?php if ($qty > 0): ?><div class="bar-value"><?= $qty ?></div><?php endif; ?>
          <div class="bar" style="height:<?= max(4,round(($qty/$maxMonthly)*100)) ?>%" title="<?= $qty ?> issued"></div>
          <div class="bar-label"><?= h($months[$ym]) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="panel panel-donut">
    <div class="panel-head"><h3>Inventory by Category</h3></div>
    <?php if ($totalQtyAll > 0): ?>
    <div class="donut-wrap">
      <div class="donut" style="background:conic-gradient(<?= $donutCss ?>);">
        <div class="donut-center"><span class="donut-total"><?= number_format($totalQtyAll) ?></span><span class="donut-total-label">units</span></div>
      </div>
      <div class="legend">
        <?php foreach ($catRows as $idx => $row): $pct = $totalQtyAll>0?round(($row['qty']/$totalQtyAll)*100):0; ?>
          <div class="legend-item"><span class="legend-dot" style="background:<?= $colors[$idx%4] ?>"></span><?= h($row['name']) ?><span class="pct"><?= $pct ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?><p class="text-muted">No inventory data yet.</p><?php endif; ?>
  </div>
</div>

<div class="panel-grid-2">
  <div class="panel panel-request">
      
      <div class="panel-head">
          <h3>Frequently Requested Items</h3>
      </div>
      
      <?php
      $rows = [];
      $maxReq = 1;
      
      while($row = $mostRequested->fetch_assoc()){
          $rows[] = $row;
          if($row['cnt'] > $maxReq){
              $maxReq = $row['cnt'];
          }
      }
      ?>
  
      <?php if(empty($rows)): ?>
      
          <?= emptyStatePanel('fa-solid fa-chart-simple', 'No requests submitted yet.') ?>
      
      <?php else: ?>
      
      <div class="request-list">
      
          <?php foreach($rows as $index => $row):
  
              $width = ($row['cnt'] / $maxReq) * 100;
          
          ?>
  
          <div class="request-item">
          
              <div class="request-top">
          
                  <div class="request-name">
                      <?= $index + 1 ?>.
                      <?= h($row['item_name']) ?>
                  </div>
          
                  <div class="request-count">
                      <?= (int)$row['cnt'] ?> requests
                  </div>
          
              </div>
          
              <div class="request-bar">
                  <span style="width:<?= $width ?>%"></span>
              </div>
          
          </div>
          
          <?php endforeach; ?>
          
      </div>
          
      <?php endif; ?>
          
  </div>
  <div class="panel panel-stock">
    <div class="panel-head"><h3>Low Stock Alerts</h3><?= badge($lowStockCount.' alerts','badge-warning') ?></div>
    <div class="table-wrap"><table class="data-table"><tbody>
      <?php $hasRows=false; while($row=$lowStock->fetch_assoc()): $hasRows=true; $s=stockStatus((int)$row['quantity'],(int)$row['reorder_level']); ?>
        <tr>
          <td><div class="cell-strong"><?= h($row['item_name']) ?></div><div class="cell-muted"><?= h($row['cat']) ?></div></td>
          <td><div class="<?= $s==='out'?'qty-out':'qty-low' ?>"><?= (int)$row['quantity'] ?> left</div><?= badge(stockStatusLabel($s),stockStatusClass($s)) ?></td>
        </tr>
      <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(1, 'fa-solid fa-circle-check', 'All items are well stocked.', true) ?><?php endif; ?>
    </tbody></table></div>
  </div>
</div>

<div class="panel panel-latest">
  <div class="panel-head"><h3>Latest Issuance</h3><a href="issuance.php" class="btn btn-outline btn-sm">View All</a></div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Item</th><th>Quantity</th><th>Issued To</th><th>Department</th><th>Date</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$recentIssuance->fetch_assoc()): $hasRows=true; ?>
      <tr><td class="cell-strong"><?= h($row['item_name']) ?></td><td><?= (int)$row['quantity'] ?></td><td><?= h($row['issued_to']) ?></td><td><?= h($row['department']) ?></td><td class="cell-muted"><?= formatDate($row['date_issued']) ?></td></tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(5, 'fa-solid fa-truck-ramp-box', 'No issuance recorded yet.') ?><?php endif; ?>
    </tbody>
  </table></div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

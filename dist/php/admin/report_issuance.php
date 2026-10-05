<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pageTitle='Issuance Report — Reports'; $activeNav='reports';
require __DIR__ . '/../includes/admin_header.php';

$catList   = allCategories($conn);
$catFilter = (int)($_GET['category'] ?? 0);
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$generated = isset($_GET['generated']);

$catName = 'All Categories';
foreach ($catList as $c) if ((int)$c['id'] === $catFilter) $catName = $c['name'];

$groups = []; $grandTotal = 0;
if ($generated) {
    $where = []; $params = []; $types = '';
    if ($catFilter > 0) { $where[] = 'i.category_id=?'; $params[] = $catFilter; $types .= 'i'; }
    if ($dateFrom !== '') { $where[] = 'iss.date_issued>=?'; $params[] = $dateFrom.' 00:00:00'; $types .= 's'; }
    if ($dateTo   !== '') { $where[] = 'iss.date_issued<=?'; $params[] = $dateTo.' 23:59:59';   $types .= 's'; }
    $sql = "SELECT iss.*, i.item_code, i.item_name, i.unit, c.name AS cat_name
            FROM issuance iss
            JOIN inventory_items i ON i.id=iss.item_id
            JOIN categories c ON c.id=i.category_id"
         . ($where ? ' WHERE '.implode(' AND ', $where) : '')
         . " ORDER BY c.name, iss.date_issued DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) { $groups[$row['cat_name']][] = $row; $grandTotal += (int)$row['quantity']; }
}
?>

<div class="page-head">
  <div><h2>Issuance Report</h2><div class="desc">Items issued/released, grouped by category with optional date filtering.</div></div>
  <a href="reports.php" class="btn btn-outline no-print"><i class="fa-solid fa-arrow-left"></i> Back to Reports</a>
</div>

<form method="GET" class="report-toolbar no-print">
  <div class="form-row-wrap">
    <div class="form-group">
      <label for="ri_category">Category</label>
      <select name="category" id="ri_category" class="form-control">
        <option value="0" <?= $catFilter===0?'selected':'' ?>>All Categories</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label for="ri_from">Date From</label><input type="date" name="date_from" id="ri_from" class="form-control" value="<?= h($dateFrom) ?>"></div>
    <div class="form-group"><label for="ri_to">Date To</label><input type="date" name="date_to" id="ri_to" class="form-control" value="<?= h($dateTo) ?>"></div>
    <div class="form-group"><button type="submit" name="generated" value="1" class="btn btn-primary">Generate Report</button></div>
  </div>
</form>

<?php if ($generated): ?>
<div class="panel">
  <?php
    $reportTitle    = 'Issuance Report';
    $reportSubtitle = $catName . (($dateFrom||$dateTo) ? ' — '.($dateFrom?:'earliest').' to '.($dateTo?:'today') : ' — All Dates');
    require __DIR__ . '/../includes/report_print_head.php';
  ?>
  <div class="report-view-head">
    <div>
      <h3>Issuance Report</h3>
      <div class="cell-muted"><?= h($reportSubtitle) ?> · <?= (int)$grandTotal ?> total unit(s) issued</div>
    </div>
    <div class="report-toolbar-actions no-print">
      <a href="report_issuance.php?category=<?= $catFilter ?>&date_from=<?= h($dateFrom) ?>&date_to=<?= h($dateTo) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-sliders"></i> Change Filters</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()" type="button"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <p class="text-muted">No issuance records match this filter.</p>
  <?php else: foreach ($groups as $catLabel => $items): ?>
    <div class="report-category-group">
      <h4 class="report-category-heading"><?= h($catLabel) ?></h4>
      <div class="table-wrap"><table class="data-table report-table">
        <thead><tr><th>Item</th><th>Qty Issued</th><th>Unit</th><th>Recipient</th><th>Department</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($items as $row): ?>
            <tr>
              <td class="cell-strong"><?= h($row['item_name']) ?> <span class="cell-muted">(<?= h($row['item_code']) ?>)</span></td>
              <td><?= (int)$row['quantity'] ?></td>
              <td><?= h($row['unit']) ?></td>
              <td><?= h($row['issued_to']) ?></td>
              <td><?= h($row['department'] ?: '—') ?></td>
              <td class="cell-muted"><?= formatDate($row['date_issued']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

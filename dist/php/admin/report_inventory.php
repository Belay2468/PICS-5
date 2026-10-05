<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pageTitle='Inventory List — Reports'; $activeNav='reports';
require __DIR__ . '/../includes/admin_header.php';

$catList   = allCategories($conn);
$catFilter = (int)($_GET['category'] ?? 0);
$mode      = ($_GET['mode'] ?? 'current') === 'blank' ? 'blank' : 'current';
$generated = isset($_GET['generated']);

$catName = 'All Categories';
foreach ($catList as $c) if ((int)$c['id'] === $catFilter) $catName = $c['name'];

$groups = [];
if ($generated) {
    $where = []; $params = []; $types = '';
    if ($catFilter > 0) { $where[] = 'i.category_id=?'; $params[] = $catFilter; $types .= 'i'; }
    $sql = "SELECT i.*, c.name AS cat_name FROM inventory_items i JOIN categories c ON c.id=i.category_id"
         . ($where ? ' WHERE '.implode(' AND ', $where) : '')
         . " ORDER BY c.name, i.item_name";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) $groups[$row['cat_name']][] = $row;
}
?>

<div class="page-head">
  <div><h2>Inventory List</h2><div class="desc">Stock list per category, ready for printing or physical count.</div></div>
  <a href="reports.php" class="btn btn-outline no-print"><i class="fa-solid fa-arrow-left"></i> Back to Reports</a>
</div>

<form method="GET" class="report-toolbar no-print">
  <div class="form-row-wrap">
    <div class="form-group">
      <label for="rc_category">Category</label>
      <select name="category" id="rc_category" class="form-control">
        <option value="0" <?= $catFilter===0?'selected':'' ?>>All Categories</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Stock Column</label>
      <div class="stock-mode-toggle">
        <label><input type="radio" name="mode" value="current" <?= $mode==='current'?'checked':'' ?>> Show Current Stock</label>
        <label><input type="radio" name="mode" value="blank" <?= $mode==='blank'?'checked':'' ?>> Blank Stock Column</label>
      </div>
    </div>
    <div class="form-group">
      <button type="submit" name="generated" value="1" class="btn btn-primary">Generate Report</button>
    </div>
  </div>
</form>

<?php if ($generated): ?>
<div class="panel">
  <?php
    $reportTitle    = 'Inventory List';
    $reportSubtitle = $catName . ' — ' . ($mode==='current' ? 'Current Stock' : 'Blank Stock Column (Physical Count)');
    require __DIR__ . '/../includes/report_print_head.php';
  ?>
  <div class="report-view-head">
    <div>
      <h3>Inventory List</h3>
      <div class="cell-muted"><?= h($reportSubtitle) ?></div>
    </div>
    <div class="report-toolbar-actions no-print">
      <a href="report_inventory.php?category=<?= $catFilter ?>&mode=<?= h($mode) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-sliders"></i> Change Filters</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()" type="button"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <p class="text-muted">No items match this filter.</p>
  <?php else: foreach ($groups as $catLabel => $items): ?>
    <div class="report-category-group">
      <h4 class="report-category-heading"><?= h($catLabel) ?></h4>
      <div class="table-wrap"><table class="data-table report-table">
        <thead><tr><th>Item No.</th><th>Description</th><th>Quantity</th><th>Unit</th></tr></thead>
        <tbody>
          <?php foreach ($items as $row): ?>
            <tr>
              <td><?= h($row['item_code']) ?></td>
              <td class="cell-strong"><?= h($row['item_name']) ?></td>
              <td><?= $mode==='current' ? (int)$row['quantity'] : '<span class="blank-line">&nbsp;</span>' ?></td>
              <td><?= h($row['unit']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

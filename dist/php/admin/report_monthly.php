<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pageTitle='Monthly Report — Reports'; $activeNav='reports';
require __DIR__ . '/../includes/admin_header.php';

$catList   = allCategories($conn);
$catFilter = (int)($_GET['category'] ?? 0);
$month     = (int)($_GET['month'] ?? date('n'));
$year      = (int)($_GET['year']  ?? date('Y'));
$month     = ($month >= 1 && $month <= 12) ? $month : (int)date('n');
$year      = ($year >= 2000 && $year <= 2100) ? $year : (int)date('Y');
$generated = isset($_GET['generated']);

$catName = 'All Categories';
foreach ($catList as $c) if ((int)$c['id'] === $catFilter) $catName = $c['name'];

$currentYear = (int)date('Y');
$yearOptions = range($currentYear - 3, $currentYear + 1);

$groups = [];
if ($generated) {
    $where = []; $params = []; $types = '';
    if ($catFilter > 0) { $where[] = 'i.category_id=?'; $params[] = $catFilter; $types .= 'i'; }
    $sql = "SELECT i.id, i.item_code, i.item_name, i.unit, i.quantity, c.name AS cat_name
            FROM inventory_items i JOIN categories c ON c.id=i.category_id"
         . ($where ? ' WHERE '.implode(' AND ', $where) : '')
         . " ORDER BY c.name, i.item_name";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $itemIds = array_column($items, 'id');
    $received = []; $issued = []; $outstanding = [];

    if ($itemIds) {
        $ph = implode(',', array_fill(0, count($itemIds), '?'));
        $idTypes = str_repeat('i', count($itemIds));

        $stmt = $conn->prepare("SELECT item_id, SUM(quantity) qty FROM stock_receipts
                                 WHERE item_id IN ($ph) AND YEAR(date_received)=? AND MONTH(date_received)=?
                                 GROUP BY item_id");
        $stmt->bind_param($idTypes.'ii', ...[...$itemIds, $year, $month]);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $received[(int)$row['item_id']] = (int)$row['qty'];

        $stmt = $conn->prepare("SELECT item_id, SUM(quantity) qty FROM issuance
                                 WHERE item_id IN ($ph) AND YEAR(date_issued)=? AND MONTH(date_issued)=?
                                 GROUP BY item_id");
        $stmt->bind_param($idTypes.'ii', ...[...$itemIds, $year, $month]);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $issued[(int)$row['item_id']] = (int)$row['qty'];

        // "Outstanding" = quantity still requested but not yet acted on
        // (status='pending' in item_requests) as of report generation —
        // there is no historical month-end snapshot of backorders in the
        // schema, so this reflects the current backlog, not a value frozen
        // at the end of the selected month. See the footnote below.
        $stmt = $conn->prepare("SELECT item_id, SUM(quantity_requested) qty FROM item_requests
                                 WHERE item_id IN ($ph) AND status='pending'
                                 GROUP BY item_id");
        $stmt->bind_param($idTypes, ...$itemIds);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $outstanding[(int)$row['item_id']] = (int)$row['qty'];
    }

    foreach ($items as $row) {
        $id = (int)$row['id'];
        $row['received']    = $received[$id]    ?? 0;
        $row['issued_m']    = $issued[$id]      ?? 0;
        $row['outstanding'] = $outstanding[$id] ?? 0;
        $groups[$row['cat_name']][] = $row;
    }
}
?>

<div class="page-head">
  <div><h2>Monthly Report</h2><div class="desc">Outstanding balance, receipts, issuances, and on-hand quantities per item.</div></div>
  <a href="reports.php" class="btn btn-outline no-print"><i class="fa-solid fa-arrow-left"></i> Back to Reports</a>
</div>

<form method="GET" class="report-toolbar no-print">
  <div class="form-row-wrap">
    <div class="form-group">
      <label for="rm_month">Month</label>
      <select name="month" id="rm_month" class="form-control">
        <?php foreach (REPORT_MONTH_NAMES as $num=>$name): ?>
          <option value="<?= $num ?>" <?= $month===$num?'selected':'' ?>><?= h($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="rm_year">Year</label>
      <select name="year" id="rm_year" class="form-control">
        <?php foreach ($yearOptions as $y): ?>
          <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="rm_category">Category</label>
      <select name="category" id="rm_category" class="form-control">
        <option value="0" <?= $catFilter===0?'selected':'' ?>>All Categories</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><button type="submit" name="generated" value="1" class="btn btn-primary">Generate Report</button></div>
  </div>
</form>

<?php if ($generated): ?>
<div class="panel">
  <?php
    $reportTitle    = 'Monthly Report';
    $reportSubtitle = REPORT_MONTH_NAMES[$month].' '.$year.' — '.$catName;
    require __DIR__ . '/../includes/report_print_head.php';
  ?>
  <div class="report-view-head">
    <div>
      <h3>Monthly Report — <?= h(REPORT_MONTH_NAMES[$month]) ?> <?= $year ?></h3>
      <div class="cell-muted"><?= h($catName) ?></div>
    </div>
    <div class="report-toolbar-actions no-print">
      <a href="report_monthly.php?month=<?= $month ?>&year=<?= $year ?>&category=<?= $catFilter ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-sliders"></i> Change Filters</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()" type="button"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <p class="text-muted">No items match this filter.</p>
  <?php else: foreach ($groups as $catLabel => $items): ?>
    <div class="report-category-group">
      <h4 class="report-category-heading"><?= h($catLabel) ?></h4>
      <div class="table-wrap"><table class="data-table report-table monthly-table">
        <thead><tr><th class="col-num">#</th><th>Particulars</th><th>Outstanding Balance</th><th>No. of Items Received from PRO</th><th>No. of Items Issued</th><th>On Hand</th><th>Unit</th></tr></thead>
        <tbody>
          <?php foreach ($items as $idx => $row): ?>
            <tr>
              <td class="col-num cell-muted"><?= $idx+1 ?></td>
              <td class="cell-strong"><?= h($row['item_name']) ?> <span class="cell-muted">(<?= h($row['item_code']) ?>)</span></td>
              <td><?= (int)$row['outstanding'] ?></td>
              <td><?= (int)$row['received'] ?></td>
              <td><?= (int)$row['issued_m'] ?></td>
              <td><?= (int)$row['quantity'] ?></td>
              <td><?= h($row['unit']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; endif; ?>

  <?php if (!empty($groups)): ?>
  <div class="sign-row monthly-signoff">
    <div class="sign-block sign-block-left">
      <div class="sign-line"><?= h($me['full_name'] ?? 'Prepared By') ?></div>
      <div class="sign-role">Prepared by · <?= h(ucfirst($me['role'] ?? '')) ?></div>
    </div>
    <div class="sign-block sign-block-left">
      <div class="sign-line">&nbsp;</div>
      <div class="sign-role">Noted by · Administrative Officer</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="report-footnote no-print">
    <strong>About these figures:</strong> "No. of Items Received from PRO" and "No. of Items Issued" are totals recorded in the system for
    <?= h(REPORT_MONTH_NAMES[$month]) ?> <?= $year ?> specifically. "On Hand" is the item's <em>current</em> quantity at
    the time this report was generated — the database does not keep a historical month-end snapshot, so for a past
    month this reflects stock as of today rather than as of that month's close. "Outstanding Balance" is the total
    quantity across pending item requests right now, since backorders are not stored per historical month either.
    Use <a href="../admin/inventory.php">Inventory List</a> → "Receive Stock" to log incoming PRO deliveries so future
    Monthly Reports reflect them accurately.
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

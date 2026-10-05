<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pageTitle='Reports & Analytics'; $activeNav='reports';
require __DIR__ . '/../includes/admin_header.php';

// ── Overview charts (kept from the previous dashboard-style view) ──
$months=[]; for($i=5;$i>=0;$i--) $months[date('Y-m',strtotime("-$i months"))]=date('M',strtotime("-$i months"));
$monthlyIssued=array_fill_keys(array_keys($months),0);
$res=$conn->query("SELECT DATE_FORMAT(date_issued,'%Y-%m') ym,SUM(quantity) qty FROM issuance GROUP BY ym");
while($row=$res->fetch_assoc()) if(isset($monthlyIssued[$row['ym']])) $monthlyIssued[$row['ym']]=(int)$row['qty'];
$maxMonthly=max(1,max($monthlyIssued));

$catRows=[]; $totalQty=0;
$res=$conn->query("SELECT c.name,COALESCE(SUM(i.quantity),0) qty FROM categories c LEFT JOIN inventory_items i ON i.category_id=c.id GROUP BY c.id ORDER BY c.id");
while($row=$res->fetch_assoc()){$catRows[]=$row;$totalQty+=(int)$row['qty'];}
$colors=CHART_CATEGORY_COLORS;
$gradParts=[];$cursor=0;
foreach($catRows as $idx=>$row){$pct=$totalQty>0?round(($row['qty']/$totalQty)*100):0;$gradParts[]=$colors[$idx%4]." $cursor% ".($cursor+$pct).'%';$cursor+=$pct;}
$donutCss=implode(', ',$gradParts);

$reportTypes = [
    [
        'href'  => 'report_inventory.php',
        'icon'  => 'fa-solid fa-clipboard-list',
        'title' => 'Inventory List',
        'desc'  => 'Full stock list per category, printable with current quantities or a blank column for physical counts.',
    ],
    [
        'href'  => 'report_requisition.php',
        'icon'  => 'fa-solid fa-file-signature',
        'title' => 'Requisition & Issue Slip',
        'desc'  => 'Hand-pick items and quantities to request or replenish, then print a ready-to-sign RIS-style slip.',
    ],
    [
        'href'  => 'report_issuance.php',
        'icon'  => 'fa-solid fa-truck-ramp-box',
        'title' => 'Issuance Report',
        'desc'  => 'Everything issued out, grouped by category, with optional date range and recipient details.',
    ],
    [
        'href'  => 'report_monthly.php',
        'icon'  => 'fa-solid fa-calendar-days',
        'title' => 'Monthly Report',
        'desc'  => 'Received, issued, and on-hand quantities per item for a given month, organized by category.',
    ],
    [
        'href'  => 'report_receiving.php',
        'icon'  => 'fa-solid fa-clock-rotate-left',
        'title' => 'Receiving History',
        'desc'  => 'Every stock receipt on record — item, quantity, source, RIS/reference no., and who received it.',
    ],
];
?>

<div class="page-head">
  <div><h2>Reports &amp; Analytics</h2><div class="desc">Generate the five PICS report types, or review overall trends below.</div></div>
</div>

<div class="report-type-grid">
  <?php foreach($reportTypes as $rt): ?>
    <a href="<?= h($rt['href']) ?>" class="report-type-card">
      <div class="rt-icon"><i class="<?= h($rt['icon']) ?>"></i></div>
      <div class="rt-title"><?= h($rt['title']) ?></div>
      <div class="rt-desc"><?= h($rt['desc']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel-grid-2">
  <div class="panel"><div class="panel-head"><h3>Monthly Issuance Comparison</h3></div>
    <?php if (array_sum($monthlyIssued) === 0): ?>
      <div class="chart-empty">No issuance recorded yet.</div>
    <?php else: ?>
    <div class="bar-chart">
      <?php foreach($monthlyIssued as $ym=>$qty): ?><div class="bar-col"><?php if($qty>0): ?><div class="bar-value"><?= $qty ?></div><?php endif; ?><div class="bar" style="height:<?= max(4,round(($qty/$maxMonthly)*100)) ?>%" title="<?= $qty ?>"></div><div class="bar-label"><?= h($months[$ym]) ?></div></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="panel"><div class="panel-head"><h3>Stock by Category</h3></div>
    <?php if($totalQty>0): ?>
    <div class="donut-wrap">
      <div class="donut" style="background:conic-gradient(<?= $donutCss ?>);">
        <div class="donut-center"><span class="donut-total"><?= number_format($totalQty) ?></span><span class="donut-total-label">units</span></div>
      </div>
      <div class="legend"><?php foreach($catRows as $idx=>$row): $pct=$totalQty>0?round(($row['qty']/$totalQty)*100):0; ?>
        <div class="legend-item"><span class="legend-dot" style="background:<?= $colors[$idx%4] ?>"></span><?= h($row['name']) ?><span class="pct"><?= $pct ?>%</span></div>
      <?php endforeach; ?></div>
    </div>
    <?php else: ?><p class="text-muted">No data yet.</p><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

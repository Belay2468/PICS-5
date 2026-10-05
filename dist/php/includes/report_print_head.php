<?php
// Printable letterhead shared by all Reports & Analytics report types.
// Uses PICS LOGO 1 (light-background lockup) since the printed page is
// always white. Only ever visible inside @media print — see .print-only
// and .report-letterhead in _admin-reports.scss.
?>
<div class="report-letterhead print-only">
  <img src="<?= h(picsLogoUrl(1)) ?>" alt="PICS logo" class="report-letterhead-logo">
  <div class="report-letterhead-text">
    <div class="org">PhilHealth Inventory Control System</div>
    <div class="title"><?= h($reportTitle ?? 'Report') ?></div>
    <?php if (!empty($reportSubtitle)): ?><div class="sub"><?= h($reportSubtitle) ?></div><?php endif; ?>
  </div>
  <div class="report-letterhead-meta">Generated: <?= h(date('M d, Y g:i A')) ?></div>
</div>

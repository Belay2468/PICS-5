<?php
requireRole('admin');

$me = currentUser();
$initials = strtoupper(substr($me['full_name'],0,1) . substr(strrchr($me['full_name'],' ') ?: '',1,1));

$alertCount = 0;
$r = $conn->query("SELECT COUNT(*) c FROM inventory_items WHERE quantity<=reorder_level");
if($r) $alertCount += (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) c FROM item_requests WHERE status='pending'");
if($r) $alertCount += (int)$r->fetch_assoc()['c'];

$navItems = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon'  => 'fa-solid fa-house',
        'href'  => 'dashboard.php'
    ],
    'inventory' => [
        'label' => 'Inventory List',
        'icon'  => 'fa-solid fa-boxes-stacked',
        'href'  => 'inventory.php'
    ],
    'issuance' => [
        'label' => 'Issuance',
        'icon'  => 'fa-solid fa-cart-shopping',
        'href'  => 'issuance.php'
    ],
    'stock' => [
        'label' => 'Stock Monitoring',
        'icon'  => 'fa-solid fa-chart-line',
        'href'  => 'stock.php'
    ],
    'reports' => [
        'label' => 'Reports & Analytics',
        'icon'  => 'fa-solid fa-chart-pie',
        'href'  => 'reports.php'
    ],
    'users' => [
        'label' => 'User Management',
        'icon'  => 'fa-solid fa-users',
        'href'  => 'users.php'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= h($pageTitle) ?> — PICS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= h(picsLogoUrl(1)) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="../../scss/main.css">
</head>
<body>

<div class="admin-shell">

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="sidebar" id="adminSidebar">

        <a href="dashboard.php" class="sidebar-brand">
            <img src="<?= h(picsLogoUrl(1)) ?>" alt="PICS logo" class="brand-logo">

            <div>
                <div class="name">PICS</div>
                <div class="tag">PhilHealth Inventory Control System</div>
            </div>
        </a>

        <nav class="sidebar-nav">
            <ul>

                <?php foreach ($navItems as $key => $item): ?>

                <li>
                    <a class="nav-link <?= $activeNav === $key ? 'active' : '' ?>"
                       href="<?= h($item['href']) ?>">

                        <span class="ico">
                            <i class="<?= h($item['icon']) ?>"></i>
                        </span>

                        <?= h($item['label']) ?>

                    </a>
                </li>

                <?php endforeach; ?>

            </ul>
        </nav>

        <div class="sidebar-foot">

            <a class="nav-link" href="../logout.php">

                <span class="ico">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </span>

                Logout

            </a>

        </div>

    </aside>

    <div class="main-area">

        <header class="topbar">

            <button type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="adminSidebar">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-right">

                <a class="bell"
                   href="stock.php"
                   title="<?= $alertCount ?> alert(s)">

                    <i class="fa-solid fa-bell"></i>

                    <?php if($alertCount > 0): ?>
                        <span class="dot"></span>
                    <?php endif; ?>

                </a>

                <div class="user-chip">

                    <div class="avatar">
                        <?= h($initials ?: 'A') ?>
                    </div>

                    <div class="meta">
                        <div class="name">
                            <?= h($me['full_name']) ?>
                        </div>

                        <div class="role">
                            <?= h(ucfirst($me['role'])) ?>
                        </div>
                    </div>

                </div>

            </div>

        </header>

        <main class="content">

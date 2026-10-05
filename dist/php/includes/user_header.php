<?php
requireRole('user');
$me = currentUser();
$initials = strtoupper(substr($me['full_name'],0,1) . substr(strrchr($me['full_name'],' ') ?: '',1,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= h($pageTitle) ?> — PICS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= h(picsLogoUrl(1)) ?>">
<!-- Font Awesome -->
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"> 
<link rel="stylesheet" href="../../scss/main.css">



</head>
<body>

<div class="user-shell <?= h($pageClass ?? '') ?>">

<header class="user-topbar">

    <a href="dashboard.php" class="brand">

        <img src="<?= h(picsLogoUrl(2)) ?>" alt="PICS logo" class="brand-logo">

        <div>

            PICS

            <small>PhilHealth Inventory Control System</small>

        </div>

    </a>

    <div class="user-topbar-right">

        <a href="dashboard.php" class="logout-link">
            Home
        </a>

        <a href="requests.php" class="logout-link">
            My Requests
        </a>

        <div class="divider"></div>

        <div class="clock">

            <i class="fa-regular fa-clock"></i>

            <span id="liveClock"></span>

        </div>

        <div class="user-chip">

            <div class="avatar">
                <?= h($initials ?: 'U') ?>
            </div>

            <div class="meta">

                <div class="name">
                    <?= h($me['full_name']) ?>
                </div>

                <div class="role">
                    <?= h($me['department'] ?: 'User') ?>
                </div>

            </div>

        </div>

        <a
            class="logout-link"
            href="../logout.php"
            title="Logout">

            <i class="fa-solid fa-right-from-bracket"></i>

        </a>

    </div>

</header>
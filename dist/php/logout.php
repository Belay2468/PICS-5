<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { logActivity($conn, $_SESSION['user_id'], 'Logged out'); }
clearRememberToken($conn);
$_SESSION = []; session_destroy();
header("Location: index.php"); exit;

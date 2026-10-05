<?php
/**
 * PICS — Default Account Seeder
 *
 * This script is PROTECTED and can only run once:
 *
 *   1. Create the file  dist/php/database/seed_token.txt  containing a
 *      secret of your choice (proves filesystem access to the server).
 *   2. Open  seed_users.php?token=YOUR_SECRET  in the browser.
 *   3. The seeder refuses to run again afterwards: the token file is
 *      deleted and a lock file (seed.lock) is written.
 *
 * Once the accounts exist, the seeder additionally requires an
 * authenticated administrator session — the "empty users table"
 * bootstrap path is only open on a brand-new installation.
 *
 * Deleting this file from the deployment is still recommended.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$tokenFile = __DIR__ . '/seed_token.txt';
$lockFile  = __DIR__ . '/seed.lock';

function seedDeny(string $message): void {
    http_response_code(403);
    echo "<h2 style='font-family:sans-serif'>PICS — Seeder blocked</h2>";
    echo "<p style='font-family:sans-serif'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    exit;
}

// ── 1. One-time guard ────────────────────────────────────────
if (file_exists($lockFile)) {
    seedDeny('The seeder has already been run on this installation. Delete this file from the deployment.');
}

// ── 2. One-time token verification ───────────────────────────
$expected = is_readable($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
$supplied = trim((string) ($_GET['token'] ?? ''));

if ($expected === '') {
    seedDeny('No seed token is configured. Create seed_token.txt next to this script with a secret value, then retry with ?token=YOUR_SECRET.');
}
if ($supplied === '' || !hash_equals($expected, $supplied)) {
    seedDeny('Invalid or missing seed token.');
}

// ── 3. Administrator check ───────────────────────────────────
// A fresh install (no users at all) may bootstrap; otherwise an
// authenticated administrator session is required.
$userCount = (int) ($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
if ($userCount > 0 && !(isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin')) {
    seedDeny('Accounts already exist — you must be signed in as an administrator to run the seeder.');
}

$defaultUsers = [
    ['full_name'=>'Admin User',      'email'=>'admin@philtrack.com',             'password'=>'Admin@123','role'=>'admin','department'=>'Administration'],
    ['full_name'=>'Juan Dela Cruz',  'email'=>'juan.delacruz@philtrack.com',     'password'=>'User@123', 'role'=>'user', 'department'=>'Information Technology'],
    ['full_name'=>'Sarah Johnson',   'email'=>'sarah.johnson@philtrack.com',     'password'=>'User@123', 'role'=>'user', 'department'=>'Admin Office'],
    ['full_name'=>'Aloysius Isleta', 'email'=>'aloysius.isleta@philtrack.com',   'password'=>'User@123', 'role'=>'user', 'department'=>'Records Management'],
];

echo "<h2 style='font-family:sans-serif'>PICS — Seeding default accounts</h2><ul style='font-family:monospace'>";

foreach ($defaultUsers as $u) {
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $chk->bind_param("s", $u['email']); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { echo "<li>Skipped (already exists): {$u['email']}</li>"; $chk->close(); continue; }
    $chk->close();

    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $ins  = $conn->prepare("INSERT INTO users (full_name,email,password,role,department,status) VALUES (?,?,?,?,?,'active')");
    $ins->bind_param("sssss", $u['full_name'], $u['email'], $hash, $u['role'], $u['department']);
    $ins->execute(); $ins->close();

    echo "<li>✅ Created: <strong>{$u['email']}</strong> / password: <strong>{$u['password']}</strong> (role: {$u['role']})</li>";
}

// ── 4. Burn the token so this can never run twice ────────────
@unlink($tokenFile);
@file_put_contents($lockFile, "Seeded on " . date('Y-m-d H:i:s') . "\n");

$conn->close();
echo "</ul><p style='font-family:sans-serif'>Done — <a href='../index.php'>go to login</a>. The seed token has been consumed. <strong>Delete this file now.</strong></p>";

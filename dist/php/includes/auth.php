<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return ['id'=>$_SESSION['user_id'],'full_name'=>$_SESSION['full_name'],
            'email'=>$_SESSION['email'],'role'=>$_SESSION['role'],'department'=>$_SESSION['department']];
}

function requireLogin(): void {
    if (!isLoggedIn()) { header("Location: " . relativeBasePath() . "index.php"); exit; }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header("Location: " . relativeBasePath() . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
        exit;
    }
}

function relativeBasePath(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    return preg_match('#/(admin|user)/#', $script) ? '../' : '';
}

function logActivity(mysqli $conn, ?int $userId, string $action): void {
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $action);
    $stmt->execute();
    $stmt->close();
}

// ── "Remember me" persistent login ─────────────────────────────
// Selector/validator pattern: the selector is a lookup key (safe to store
// in plain text), the validator is only ever stored as a hash, so leaking
// the DB doesn't hand out usable cookies. Rotated on every successful use
// so a copied cookie only works once before the legitimate user's next
// visit invalidates it.

const REMEMBER_COOKIE = 'pics_remember';
const REMEMBER_DAYS   = 30;

function forgetRememberCookie(): void {
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function issueRememberToken(mysqli $conn, int $userId): void {
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $userId, $selector, $hash, $expires);
    $stmt->execute();
    $stmt->close();

    setcookie(REMEMBER_COOKIE, "$selector:$validator", [
        'expires'  => time() + REMEMBER_DAYS * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberToken(mysqli $conn): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $selector = explode(':', $_COOKIE[REMEMBER_COOKIE], 2)[0] ?? '';
        if ($selector !== '') {
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector=?");
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $stmt->close();
        }
    }
    forgetRememberCookie();
}

function attemptRememberLogin(mysqli $conn): void {
    if (isLoggedIn() || empty($_COOKIE[REMEMBER_COOKIE])) return;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) { forgetRememberCookie(); return; }
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("
        SELECT rt.id, rt.user_id, rt.token_hash,
               u.full_name, u.email, u.role, u.department, u.status
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.selector=? AND rt.expires_at > NOW()
    ");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { forgetRememberCookie(); return; }

    if (!hash_equals($row['token_hash'], hash('sha256', $validator))) {
        // A known selector with a wrong validator means the cookie was
        // guessed or copied — burn every token for this account.
        $del = $conn->prepare("DELETE FROM remember_tokens WHERE user_id=?");
        $del->bind_param("i", $row['user_id']);
        $del->execute();
        $del->close();
        forgetRememberCookie();
        return;
    }

    // One-time use: this row is spent whether or not the account is
    // still active, so a deactivated account can't be silently reused.
    $del = $conn->prepare("DELETE FROM remember_tokens WHERE id=?");
    $del->bind_param("i", $row['id']);
    $del->execute();
    $del->close();

    if ($row['status'] !== 'active') { forgetRememberCookie(); return; }

    $_SESSION['user_id']    = $row['user_id'];
    $_SESSION['full_name']  = $row['full_name'];
    $_SESSION['email']      = $row['email'];
    $_SESSION['role']       = $row['role'];
    $_SESSION['department'] = $row['department'];

    issueRememberToken($conn, $row['user_id']);
    logActivity($conn, $row['user_id'], 'Logged in via remember-me');
}

// Runs on every page that includes this file (always after config/db.php,
// per the require order used throughout the app), so a "remember me" login
// transparently restores the session before any page checks isLoggedIn().
if (isset($conn) && $conn instanceof mysqli) {
    attemptRememberLogin($conn);
}

<?php
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── CSRF protection ──────────────────────────────────────────
// csrfField() prints the hidden input for every POST form;
// csrfValid() must be checked before any POST request is acted on.
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}
function csrfValid(): bool {
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}
/** True when the current request is a POST carrying a valid token. */
function csrfCheckedPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid();
}
const CSRF_ERROR = 'Your session security token was invalid or has expired. Please reload the page and try again.';

function stockStatus(int $qty, int $reorder): string {
    if ($qty <= 0) return 'out';
    if ($qty <= $reorder) return 'low';
    return 'in';
}
function stockStatusLabel(string $s): string {
    return match($s) { 'out'=>'Out of Stock', 'low'=>'Low Stock', default=>'In Stock' };
}
function stockStatusClass(string $s): string {
    return match($s) { 'out'=>'badge-danger', 'low'=>'badge-warning', default=>'badge-success' };
}
function formatDate(?string $v, string $fmt='M d, Y'): string {
    if (empty($v) || $v === '0000-00-00') return '—';
    $ts = strtotime($v); return $ts ? date($fmt, $ts) : '—';
}
function formatDateTime(?string $v): string { return formatDate($v, 'M d, Y g:i A'); }

function nextItemCode(mysqli $conn, string $prefix): string {
    $stmt = $conn->prepare("SELECT item_code FROM inventory_items WHERE item_code LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '-%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $next = 1;
    if ($row = $result->fetch_assoc()) {
        $parts = explode('-', $row['item_code']);
        $next = (int) end($parts) + 1;
    }
    $stmt->close();
    return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}
function categoryPrefix(string $name): string {
    return match(true) {
        str_contains($name,'IT')      => 'IT',
        str_contains($name,'Medical') => 'MED',
        str_contains($name,'Office')  => 'OFF',
        default                       => 'OTH',
    };
}
function badge(string $label, string $class): string {
    return '<span class="badge ' . h($class) . '">' . h($label) . '</span>';
}

/** True when $userId is the only remaining active administrator account. */
function isLastActiveAdmin(mysqli $conn, int $userId): bool {
    if ($userId <= 0) return false;

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active' AND id!=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
    $others = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    if ($others > 0) return false;

    // Only relevant if this account itself is an active administrator.
    $stmt = $conn->prepare("SELECT role,status FROM users WHERE id=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && $row['role'] === 'admin' && $row['status'] === 'active';
}

// ── Password policy (enforced server-side) ───────────────────
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_RULE_TEXT  = 'At least 8 characters, including an uppercase letter, a lowercase letter and a number.';

/** Returns an error message, or '' when the password satisfies the policy. */
function passwordPolicyError(string $password): string {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    return '';
}

// ── PICS branding — canonical logo URLs ───────────────────────
// A simple geometric mark (isometric box + verification checkmark) in
// two variants:
//   variant 1 — light-background: green badge, white icon. Used on
//               white/light surfaces (sidebar, print letterheads).
//   variant 2 — dark-background: white icon with no plate, for
//               placement directly on a solid dark-green surface
//               (login's left panel, the green topbar).
// Resolved through relativeBasePath() so it works from php/index.php as
// well as php/admin/*.php and php/user/*.php without hard-coded depth.
function picsLogoUrl(int $variant = 1): string {
    $file = $variant === 2 ? 'pics-mark-dark.svg' : 'pics-mark-light.svg';
    return relativeBasePath() . '../img/' . $file;
}

// Full icon+wordmark lockup (used where there's room for the full
// product name, e.g. the login page), same light/dark variant logic.
function picsLockupUrl(int $variant = 1): string {
    $file = $variant === 2 ? 'pics-lockup-dark.svg' : 'pics-lockup-light.svg';
    return relativeBasePath() . '../img/' . $file;
}

// ── Item image location (single canonical path) ──────────────
// Uploaded item images live in exactly one folder: dist/img/items/
// Every page must go through these helpers so the folder name can
// never drift (e.g. "img/Items") and break on case-sensitive servers.
function itemsImageDir(): string {
    return __DIR__ . '/../../img/items/';
}
function itemsImageUrl(?string $file = ''): string {
    $base = relativeBasePath() . '../img/items/';
    return ($file === null || $file === '') ? $base : $base . rawurlencode(basename($file));
}
function itemImageExists(?string $file): bool {
    return !empty($file) && is_file(itemsImageDir() . basename($file));
}

// ── Item image upload validation ─────────────────────────────
const ITEM_IMAGE_MAX_BYTES = 3145728; // 3 MB

/**
 * Validates and stores an uploaded item image.
 * The file extension supplied by the browser is never trusted: the real
 * content type is detected with finfo_file() and confirmed with
 * getimagesize(), and the stored name is derived from that.
 * Returns the stored file name, or '' when nothing was stored
 * (in which case $error explains a rejection).
 */
function saveItemImage(array $file, string &$error = ''): string {
    $error = '';
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'The image could not be uploaded. Please try again.'; return ''; }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) { $error = 'Invalid image upload.'; return ''; }
    if ((int)$file['size'] <= 0 || (int)$file['size'] > ITEM_IMAGE_MAX_BYTES) {
        $error = 'The image must be a non-empty file smaller than 3 MB.'; return '';
    }

    // Real content type → canonical extension.
    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string)finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    if (!isset($allowed[$mime])) { $error = 'Only JPG, PNG or WEBP images are allowed.'; return ''; }

    // Independent second check: the file must actually decode as an image
    // and the decoded type must agree with the detected MIME type.
    $info    = @getimagesize($file['tmp_name']);
    $typeMap = [IMAGETYPE_JPEG=>'image/jpeg', IMAGETYPE_PNG=>'image/png', IMAGETYPE_WEBP=>'image/webp'];
    if (!$info || empty($info[0]) || empty($info[1])
        || !isset($typeMap[$info[2]]) || $typeMap[$info[2]] !== $mime) {
        $error = 'That file is not a valid image.'; return '';
    }

    $dir = itemsImageDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { $error = 'The image folder could not be created.'; return ''; }

    $name = uniqid('item_', true) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        $error = 'The image could not be saved.'; return '';
    }
    return $name;
}

// ── Private storage directory ────────────────────────────────
// Small server-side state (login throttling) lives here. The folder is
// created on demand and shipped with a deny rule so it is never served.
function storagePath(string $file): string {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $deny = $dir . '/.htaccess';
    if (!file_exists($deny)) { @file_put_contents($deny, "Require all denied\nDeny from all\n"); }
    return $dir . '/' . $file;
}

// ── Login attempt tracking / temporary lockout ───────────────
const LOGIN_MAX_ATTEMPTS   = 5;   // failures allowed before lockout
const LOGIN_LOCKOUT_SECS   = 900; // lockout duration (15 minutes)
const LOGIN_ATTEMPT_WINDOW = 900; // failures older than this are forgotten

function loginAttemptKey(string $email): string {
    return hash('sha256', strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function loadLoginAttempts(): array {
    $raw = @file_get_contents(storagePath('login_attempts.json'));
    $data = $raw ? json_decode($raw, true) : [];
    if (!is_array($data)) $data = [];
    // Drop stale entries so the file cannot grow without bound.
    $now = time();
    foreach ($data as $k => $rec) {
        $last = (int)($rec['last'] ?? 0);
        if ($now - $last > max(LOGIN_LOCKOUT_SECS, LOGIN_ATTEMPT_WINDOW)) unset($data[$k]);
    }
    return $data;
}

function saveLoginAttempts(array $data): void {
    @file_put_contents(storagePath('login_attempts.json'), json_encode($data), LOCK_EX);
}

/** Seconds remaining on a temporary lockout, or 0 when not locked out. */
function loginLockRemaining(string $email): int {
    $data = loadLoginAttempts();
    $rec  = $data[loginAttemptKey($email)] ?? null;
    if (!$rec || (int)($rec['count'] ?? 0) < LOGIN_MAX_ATTEMPTS) return 0;
    $left = ((int)$rec['last'] + LOGIN_LOCKOUT_SECS) - time();
    return $left > 0 ? $left : 0;
}

/** Records a failed sign-in and returns how many attempts are left. */
function recordFailedLogin(string $email): int {
    $data = loadLoginAttempts();
    $key  = loginAttemptKey($email);
    $now  = time();
    $rec  = $data[$key] ?? ['count'=>0,'last'=>$now];
    // A quiet period longer than the window resets the counter.
    if ($now - (int)$rec['last'] > LOGIN_ATTEMPT_WINDOW) $rec['count'] = 0;
    $rec['count'] = (int)$rec['count'] + 1;
    $rec['last']  = $now;
    $data[$key]   = $rec;
    saveLoginAttempts($data);
    return max(0, LOGIN_MAX_ATTEMPTS - $rec['count']);
}

function clearLoginAttempts(string $email): void {
    $data = loadLoginAttempts();
    unset($data[loginAttemptKey($email)]);
    saveLoginAttempts($data);
}

function formatLockoutWait(int $seconds): string {
    $mins = (int)ceil($seconds / 60);
    return $mins <= 1 ? 'a minute' : "$mins minutes";
}

// ── Flash / status alert ───────────────────────────────────────
// Every flash message across the app goes through here so the visual
// treatment — including a status icon — stays identical everywhere,
// and so status is never conveyed by background colour alone.
function alertBox(string $message, string $type = 'success', bool $autohide = true): string {
    $icon = match ($type) {
        'danger' => 'fa-solid fa-circle-exclamation',
        'info'   => 'fa-solid fa-circle-info',
        default  => 'fa-solid fa-circle-check',
    };
    $attr = $autohide ? ' data-autohide' : '';
    return '<div class="alert alert-' . h($type) . '"' . $attr . '>'
         . '<i class="' . $icon . '" aria-hidden="true"></i>'
         . '<span>' . h($message) . '</span>'
         . '</div>';
}

// ── Empty states ────────────────────────────────────────────
// $extraHtml lets a caller append trusted markup (e.g. a link) after
// the escaped message — message itself is always escaped, extraHtml
// is never built from user input.
function emptyStatePanel(string $icon, string $message, bool $positive = false, string $extraHtml = '', string $extraClass = ''): string {
    $cls = trim('table-empty-state ' . ($positive ? 'is-positive ' : '') . $extraClass);
    return '<div class="' . h($cls) . '">'
         . '<div class="empty-icon"><i class="' . h($icon) . '" aria-hidden="true"></i></div>'
         . '<p>' . h($message) . $extraHtml . '</p>'
         . '</div>';
}

function emptyStateRow(int $colspan, string $icon, string $message, bool $positive = false, string $extraHtml = ''): string {
    return '<tr><td colspan="' . $colspan . '">'
         . emptyStatePanel($icon, $message, $positive, $extraHtml)
         . '</td></tr>';
}

// ── Reports & Analytics helpers ───────────────────────────────
/** All supply categories, ordered by name. Cached per-request. */
function allCategories(mysqli $conn): array {
    static $cats = null;
    if ($cats === null) {
        $cats = [];
        $res = $conn->query("SELECT id,name,icon FROM categories ORDER BY name");
        while ($row = $res->fetch_assoc()) $cats[] = $row;
    }
    return $cats;
}

const REPORT_MONTH_NAMES = [
    1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
    7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
];

// Categorical colour set for charts (category breakdowns, legends).
// Deliberately built as a family around the green brand — teal (cool,
// harmonious with green), amber (warm accent), slate (neutral anchor) —
// rather than a generic blue/orange/purple default palette that doesn't
// relate to the rest of the interface.
const CHART_CATEGORY_COLORS = ['#16a34a', '#0891b2', '#d97706', '#64748b'];

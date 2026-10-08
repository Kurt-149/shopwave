<?php
/**
 * debug.php — SHOPWAVE developer diagnostics.
 *
 * - Credentials come from .env (never hardcoded here).
 * - Access: localhost always; any other visitor must be a logged-in admin.
 *   Everyone else gets a plain 404 so the page's existence isn't revealed.
 * - Never prints passwords, CSRF tokens or full session contents.
 */

require_once __DIR__ . '/core/init.php';
require_once __DIR__ . '/core/session-handler.php';
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/authentication/database.php';
require_once __DIR__ . '/core/security.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

// ---- Access gate -----------------------------------------------------------
$isAdmin = isUserLoggedIn() && (($_SESSION['role'] ?? '') === 'admin');
if (!isLocalhost() && !$isAdmin) {
    http_response_code(404);
    exit('Not found.');
}

function dbgEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dbgRow(string $label, $value, ?bool $ok = null): string
{
    $badge = '';
    if ($ok !== null) {
        $badge = $ok
            ? '<span class="ok">OK</span>'
            : '<span class="bad">FAIL</span>';
    }
    return '<tr><th>' . dbgEsc($label) . '</th><td>' . dbgEsc($value) . '</td><td>' . $badge . '</td></tr>';
}

// ---- Gather data -----------------------------------------------------------
$expectedTables = [
    'users', 'products', 'categories', 'orders', 'order_items', 'cart',
    'reviews', 'review_votes', 'notifications', 'password_resets', 'user_sessions',
];

$dbOk          = false;
$dbVersion     = '—';
$dbError       = '';
$existingTables = [];
$tableCounts   = [];

try {
    $pdo = getPdo();
    if ($pdo) {
        $dbOk      = true;
        $dbVersion = $pdo->query('SELECT VERSION()')->fetchColumn();
        $existingTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $table) {
            if (!in_array($table, $existingTables, true)) {
                $tableCounts[$table] = null; // missing
                continue;
            }
            // $table comes from the fixed list above, never from user input.
            $tableCounts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        }
    } else {
        $dbError = 'getPdo() returned null';
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    error_log('[debug.php] ' . $dbError);
}

$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$requiredFiles = [
    '.env'            => __DIR__ . '/.env',
    '.htaccess'       => __DIR__ . '/.htaccess',
    'images/products' => __DIR__ . '/images/products',
    'images/profiles' => __DIR__ . '/images/profiles',
];

$user = $isAdmin ? (getCurrentUser() ?: null) : null;

// Admin-only items that show whether the production warnings are fixed
$leftoverDebugFiles = array_filter([
    'session-debug.php', 'session-test.php', 'test-db.php',
    'public/test.php', 'public/TEST-REVIEWS.php', 'backend/debug-session.php',
], fn ($f) => file_exists(__DIR__ . '/' . $f));

?>
<!doctype html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
    <script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || !meta.content || !window.fetch) return;
        var token = meta.content, origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            if ((init.method || 'GET').toUpperCase() === 'POST' && init.body !== undefined) {
                var b = init.body;
                if (typeof FormData !== 'undefined' && b instanceof FormData) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof URLSearchParams !== 'undefined' && b instanceof URLSearchParams) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof b === 'string') {
                    if (b.indexOf('csrf_token=') === -1) {
                        init.body = b + (b ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                    }
                }
            }
            return origFetch.call(this, input, init);
        };
    })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SHOPWAVE — Debug</title>
    <style>
        body { font-family: system-ui, Arial, sans-serif; margin: 0; padding: 16px; background: #f6f7f9; color: #222; }
        h1 { font-size: 1.4rem; margin: 0 0 12px; }
        h2 { font-size: 1.05rem; margin: 24px 0 8px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e3e5e8; font-size: 0.9rem; word-break: break-word; }
        th { width: 40%; color: #555; font-weight: 600; }
        .ok  { color: #0a7d32; font-weight: 700; }
        .bad { color: #b00020; font-weight: 700; }
        .warn { background: #fff4e5; border-left: 4px solid #f59e0b; padding: 10px; margin: 12px 0; font-size: 0.9rem; }
        @media (max-width: 600px) { th { width: 45%; } }
    </style>
</head>
<body>
<h1>SHOPWAVE debug</h1>
<p style="font-size:.85rem;color:#666;">
    Access: <?= isLocalhost() ? 'localhost' : 'admin session' ?> ·
    Generated <?= dbgEsc(date('Y-m-d H:i:s T')) ?>
</p>

<h2>Environment</h2>
<table>
    <?= dbgRow('PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=')) ?>
    <?= dbgRow('APP_ENV', env('APP_ENV', '(not set)')) ?>
    <?= dbgRow('BASE_URL', defined('BASE_URL') ? BASE_URL : '(not defined)') ?>
    <?= dbgRow('HTTPS detected', $httpsOn ? 'yes' : 'no', $httpsOn) ?>
    <?= dbgRow('Timezone', date_default_timezone_get()) ?>
    <?= dbgRow('APCu loaded (rate limits)', function_exists('apcu_fetch') ? 'yes' : 'no — using session fallback', function_exists('apcu_fetch')) ?>
</table>

<h2>Required files</h2>
<table>
    <?php foreach ($requiredFiles as $label => $path): ?>
        <?= dbgRow($label, file_exists($path) ? (is_writable($path) ? 'exists, writable' : 'exists') : 'missing', file_exists($path)) ?>
    <?php endforeach; ?>
</table>

<h2>Database</h2>
<table>
    <?= dbgRow('Host', env('DB_HOST', '(not set)')) ?>
    <?= dbgRow('Database name', env('DB_NAME', '(not set)')) ?>
    <?= dbgRow('Connection', $dbOk ? 'connected' : ('failed' . ($dbError ? ': ' . $dbError : '')), $dbOk) ?>
    <?= dbgRow('Server version', $dbVersion) ?>
</table>

<h2>Tables</h2>
<table>
    <?php foreach ($expectedTables as $table): ?>
        <?php $count = $tableCounts[$table] ?? null; ?>
        <?php if ($count === null): ?>
            <?= dbgRow($table, $dbOk ? 'missing' : 'not checked', false) ?>
        <?php else: ?>
            <?= dbgRow($table, $count . ' row(s)', true) ?>
        <?php endif; ?>
    <?php endforeach; ?>
</table>

<h2>Session</h2>
<table>
    <?= dbgRow('Logged in', isUserLoggedIn() ? 'yes' : 'no') ?>
    <?= dbgRow('Role', $_SESSION['role'] ?? 'guest') ?>
    <?= dbgRow('Username', $user['username'] ?? '—') ?>
    <?= dbgRow('Session name', session_name()) ?>
</table>

<?php if (!empty($leftoverDebugFiles)): ?>
    <div class="warn">
        <strong>Delete before going live:</strong>
        <?= dbgEsc(implode(', ', $leftoverDebugFiles)) ?>
        — these expose phpinfo() or session data publicly.
    </div>
<?php endif; ?>

</body>
</html>

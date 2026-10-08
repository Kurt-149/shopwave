<?php
if (session_status() !== PHP_SESSION_NONE) {
    return;
}

class ShopwaveDbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime)
    {
        $this->pdo      = $pdo;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT payload FROM user_sessions WHERE id = ? AND last_activity > ?"
            );
            $stmt->execute([$id, time() - $this->lifetime]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['payload'] : '';
        } catch (Exception $e) {
            error_log('[SessionHandler::read] ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $userId = null;
            if (preg_match('/user_id\|i:(\d+);/', $data, $m)) {
                $userId = (int)$m[1];
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     user_id       = VALUES(user_id),
                     ip_address    = VALUES(ip_address),
                     user_agent    = VALUES(user_agent),
                     payload       = VALUES(payload),
                     last_activity = VALUES(last_activity)"
            );
            $stmt->execute([
                $id,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $data,
                time(),
            ]);
            return true;
        } catch (Exception $e) {
            error_log('[SessionHandler::write] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$id]);
            return true;
        } catch (Exception $e) {
            error_log('[SessionHandler::destroy] ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM user_sessions WHERE last_activity < ?"
            );
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log('[SessionHandler::gc] ' . $e->getMessage());
            return false;
        }
    }
}

$_swDbPath = __DIR__ . '/../authentication/database.php';
if (!isset($pdo)) {
    require_once $_swDbPath;
}

$_swIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$_swIsLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])
               || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

$_swLifetime          = 604800;
$_swInactivityTimeout = 1800;

$_swHandler = new ShopwaveDbSessionHandler($pdo, $_swLifetime);
session_set_save_handler($_swHandler, true);

session_name('SHOPWAVE_SESSID');

session_set_cookie_params([
    'lifetime' => $_swLifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $_swIsHttps && !$_swIsLocalhost,
    'httponly' => true,
    'samesite' => 'Lax',
]);

ini_set('session.use_strict_mode',   1);
ini_set('session.use_only_cookies',  1);
ini_set('session.cookie_httponly',   1);
ini_set('session.cookie_secure',     ($_swIsHttps && !$_swIsLocalhost) ? '1' : '0');
ini_set('session.gc_maxlifetime',    $_swLifetime);
ini_set('session.gc_probability',    1);
ini_set('session.gc_divisor',        100);

session_start();

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $_swInactivityTimeout)) {
    $wasLoggedIn = isLoggedIn();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], 
            $params['secure'], $params['httponly']);
    }

    session_destroy();

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!$isAjax && $wasLoggedIn) {
        $currentFile  = basename($_SERVER['PHP_SELF']);
        $publicPages  = [
            'index.php', 'shop.php', 'product-details.php',
            'login-page.php', 'sign-up.php', 'forgot-password.php',
            'reset-password.php',
        ];
        if (!in_array($currentFile, $publicPages)) {
            header('Location: /authentication/login-page.php?error=' . urlencode('Session expired. Please login again.'));
            exit;
        }
    }
} else {
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

function regenerateUserSession(int $userId, string $username, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']      = $userId;
    $_SESSION['username']     = $username;
    $_SESSION['role']         = $role;
    $_SESSION['logged_in']    = true;
    $_SESSION['CREATED']      = time();
    $_SESSION['LAST_ACTIVITY'] = time();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['logged_in'])
        && $_SESSION['logged_in'] === true;
}

function getCurrentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function getCurrentUserRole(): string
{
    return $_SESSION['role'] ?? 'guest';
}
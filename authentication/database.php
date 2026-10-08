<?php
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    http_response_code(403);
    die('Access denied.');
}

// env.php and .env live in the project root (one level above /authentication)
require_once __DIR__ . '/../env.php';
loadEnv(__DIR__ . '/../.env');

$isLocalhost   = env('APP_ENV') === 'local';
$host          = env('DB_HOST');
$port          = (int) env('DB_PORT', 3306);
$database_name = env('DB_NAME');
$user          = env('DB_USER');
$password      = env('DB_PASS');
$charset       = "utf8mb4";
$dsn = "mysql:host=$host;port=$port;dbname=$database_name;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => 15,
];

$maxRetries = 3;
$retryCount = 0;
$pdo = null;

while (!$pdo && $retryCount < $maxRetries) {
    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        $pdo->exec("SET SESSION wait_timeout = 300");
        $pdo->exec("SET SESSION interactive_timeout = 300");
    } catch (PDOException $e) {
        $retryCount++;
        error_log("DB connection attempt $retryCount failed: " . $e->getMessage());

        if ($retryCount >= $maxRetries) {
            if ($isLocalhost) {
                die("Database connection failed after $maxRetries attempts: " . $e->getMessage());
            } else {
                die("Site under maintenance. Please try again later.");
            }
        }
        sleep(1);
    }
}

function getPdo() {
    global $pdo, $dsn, $user, $password, $options, $isLocalhost;

    if ($pdo) {
        try {
            $pdo->query("SELECT 1");
            return $pdo;
        } catch (Exception $e) {
            error_log("Connection lost, reconnecting: " . $e->getMessage());
        }
    }

    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Reconnection failed: " . $e->getMessage());
        if ($isLocalhost) {
            die("Reconnection failed: " . $e->getMessage());
        } else {
            return null;
        }
    }
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id'], $_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getCurrentUser() {
    if (!isUserLoggedIn()) {
        return null;
    }

    try {
        $pdo = getPdo();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("SELECT id, username, email, role, profile_picture FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

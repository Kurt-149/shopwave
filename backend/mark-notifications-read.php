<?php
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$id     = isset($_POST['id']) ? (int) $_POST['id'] : null;

try {
    $pdo = getPdo();

    if ($id) {
        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);

} catch (PDOException $e) {
    error_log('[mark-notifications-read] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
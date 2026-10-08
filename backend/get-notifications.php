<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$page    = max(1, (int) ($_GET['page']    ?? 1));
// FIX: raised cap from 50 → 100 so the hash-highlight fetch (per_page=100) gets all notifications
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 10)));
$offset  = ($page - 1) * $perPage;

$statusLabels = [
    'pending'    => 'Pending',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
    'completed'  => 'Completed',
    'cancelled'  => 'Cancelled',
];

$statusColors = [
    'pending'    => '#f59e0b',
    'processing' => '#3b82f6',
    'shipped'    => '#8b5cf6',
    'delivered'  => '#10b981',
    'completed'  => '#10b981',
    'cancelled'  => '#ef4444',
];

$typeIcons = [
    'order'  => 'order',
    'promo'  => 'promo',
    'review' => 'review',
    'alert'  => 'alert',
];

$typeColors = [
    'order'  => '#3b82f6',
    'promo'  => '#f59e0b',
    'review' => '#10b981',
    'alert'  => '#ef4444',
];

try {
    $pdo = getPdo();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, type, message, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$userId, $perPage, $offset]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = array_map(function ($n) use ($statusLabels, $statusColors, $typeIcons, $typeColors) {
        $diff = (new DateTime())->diff(new DateTime($n['created_at']));
        if ($diff->y)     $ago = $diff->y . ' year'  . ($diff->y  > 1 ? 's' : '') . ' ago';
        elseif ($diff->m) $ago = $diff->m . ' month' . ($diff->m  > 1 ? 's' : '') . ' ago';
        elseif ($diff->d) $ago = $diff->d . ' day'   . ($diff->d  > 1 ? 's' : '') . ' ago';
        elseif ($diff->h) $ago = $diff->h . ' hour'  . ($diff->h  > 1 ? 's' : '') . ' ago';
        elseif ($diff->i) $ago = $diff->i . ' min'   . ($diff->i  > 1 ? 's' : '') . ' ago';
        else              $ago = 'Just now';

        $orderStatus    = null;
        $displayMessage = $n['message'];

        if ($n['type'] === 'order' && strpos($n['message'], '|') !== false) {
            $parts          = explode('|', $n['message'], 2);
            $orderStatus    = $parts[0];
            $displayMessage = $parts[1] ?? $n['message'];
        }

        $statusLabel = $orderStatus ? ($statusLabels[$orderStatus] ?? ucfirst($orderStatus)) : null;
        $statusColor = $orderStatus ? ($statusColors[$orderStatus] ?? '#6b7280') : null;
        $icon        = $typeIcons[$n['type']] ?? 'order';
        $iconColor   = $typeColors[$n['type']] ?? '#6b7280';

        if ($orderStatus === 'pending') {
            $icon = 'pending'; $iconColor = '#f59e0b';
        } elseif ($orderStatus === 'processing') {
            $icon = 'processing'; $iconColor = '#3b82f6';
        } elseif ($orderStatus === 'shipped') {
            $icon = 'shipped'; $iconColor = '#8b5cf6';
        } elseif ($orderStatus === 'delivered' || $orderStatus === 'completed') {
            $icon = 'delivered'; $iconColor = '#10b981';
        } elseif ($orderStatus === 'cancelled') {
            $icon = 'cancelled'; $iconColor = '#ef4444';
        }

        return [
            'id'           => (int) $n['id'],
            'type'         => $n['type'],
            'message'      => $displayMessage,
            'raw_message'  => $n['message'],
            'is_read'      => (bool) $n['is_read'],
            'time_ago'     => $ago,
            'created_at'   => $n['created_at'],
            'order_status' => $orderStatus,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'icon'         => $icon,
            'icon_color'   => $iconColor,
        ];
    }, $rows);

    echo json_encode([
        'success'       => true,
        'notifications' => $notifications,
        'pagination'    => [
            'current_page'        => $page,
            'per_page'            => $perPage,
            'total_notifications' => $total,
            'total_pages'         => (int) ceil($total / $perPage),
        ],
    ]);

} catch (PDOException $e) {
    error_log('[get-notifications] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
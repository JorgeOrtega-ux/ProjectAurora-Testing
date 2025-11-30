<?php
// api/notifications_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/notifications_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => translation('global.session_expired')]);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => translation('global.action_invalid')];

try {
    if ($action === 'get_notifications') {
        // [MODIFICADO] profile_picture
        $sql = "SELECT n.*, u.profile_picture as sender_profile_picture, u.role as sender_role
                FROM notifications n 
                LEFT JOIN users u ON n.related_id = u.id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$currentUserId]);
        $unreadCount = $stmtCount->fetchColumn();

        $response = [
            'success' => true, 
            'notifications' => $notifs,
            'unread_count' => (int)$unreadCount
        ];

    } elseif ($action === 'mark_read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$currentUserId]);
        $response = ['success' => true, 'message' => translation('header.mark_read')];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
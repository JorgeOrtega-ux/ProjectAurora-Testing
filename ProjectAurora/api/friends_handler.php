<?php
// api/friends_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/friends_error.log';
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
    $uSt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $uSt->execute([$currentUserId]);
    $currentUserData = $uSt->fetch();
    $myUsername = $currentUserData['username'];
    $myProfilePic = $currentUserData['profile_picture'];

    // --- [NUEVO] INICIAR CHAT ---
    if ($action === 'start_chat') {
        $targetUid = (int)($data['target_id'] ?? 0);
        
        // 1. Verificar si existe el usuario y obtener su UUID
        $stmt = $pdo->prepare("SELECT uuid FROM users WHERE id = ?");
        $stmt->execute([$targetUid]);
        $uuid = $stmt->fetchColumn();

        if (!$uuid) throw new Exception(translation('admin.error.user_not_exist'));

        // --- VALIDACIÓN DE PRIVACIDAD Y BLOQUEO ---
        // Verificar bloqueo
        $stmtBlock = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $stmtBlock->execute([$currentUserId, $targetUid, $targetUid, $currentUserId]);
        if ($stmtBlock->rowCount() > 0) throw new Exception(translation('chat.error.privacy_block'));

        $stmtPriv = $pdo->prepare("
            SELECT COALESCE(up.message_privacy, 'friends') as privacy,
                   (SELECT status FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) as status
            FROM users u
            LEFT JOIN user_preferences up ON u.id = up.user_id
            WHERE u.id = ?
        ");
        $stmtPriv->execute([$currentUserId, $targetUid, $targetUid, $currentUserId, $targetUid]);
        $res = $stmtPriv->fetch(PDO::FETCH_ASSOC);

        $privacy = $res['privacy'] ?? 'friends';
        $status = $res['status'];

        if ($privacy === 'nobody') {
            throw new Exception("La configuración de privacidad de este usuario impide enviar mensajes.");
        }
        if ($privacy === 'friends' && $status !== 'accepted') {
            throw new Exception("Solo los amigos pueden enviar mensajes a este usuario.");
        }
        // ----------------------------------------

        // Retornamos el UUID para que el frontend redirija
        echo json_encode(['success' => true, 'uuid' => $uuid]);
        exit;

    } elseif ($action === 'send_request') {
        if (checkActionRateLimit($pdo, $currentUserId, 'friend_request_limit', 10, 1)) {
            throw new Exception(translation('auth.errors.too_many_attempts'));
        }
        logSecurityAction($pdo, $currentUserId, 'friend_request_limit');

        $targetId = (int)($data['target_id'] ?? 0);
        if ($targetId === 0 || $targetId === $currentUserId) throw new Exception(translation('admin.error.user_not_exist'));

        // [NUEVO] Verificar bloqueo antes de enviar solicitud
        $stmtBlock = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $stmtBlock->execute([$currentUserId, $targetId, $targetId, $currentUserId]);
        if ($stmtBlock->rowCount() > 0) throw new Exception(translation('chat.error.privacy_block'));

        $sql = "SELECT id FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId, $targetId, $currentUserId]);
        
        if ($stmt->rowCount() > 0) throw new Exception(translation('friends.error.exists') ?? 'Ya existe solicitud');

        $stmt = $pdo->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$currentUserId, $targetId]);

        $msg = translation('notifications.friend_request', ['username' => $myUsername]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_request', ?, ?)")
            ->execute([$targetId, $msg, $currentUserId]);

        send_live_notification($targetId, 'friend_request', [
            'message' => $msg,
            'sender_id' => $currentUserId,
            'sender_username' => $myUsername,
            'sender_profile_picture' => $myProfilePic 
        ]);

        $response = ['success' => true, 'message' => translation('notifications.request_sent')];

    } elseif ($action === 'cancel_request') {
        $targetId = (int)($data['target_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
                ->execute([$targetId, $currentUserId]);

            send_live_notification($targetId, 'request_cancelled', [
                'sender_id' => $currentUserId
            ]);

            $response = ['success' => true, 'message' => translation('notifications.request_cancelled')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'accept_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "UPDATE friendships SET status = 'accepted' 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
                ->execute([$currentUserId, $senderId]);

            $msg = translation('notifications.friend_accepted', ['username' => $myUsername]);
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_accepted', ?, ?)")
                ->execute([$senderId, $msg, $currentUserId]);

            send_live_notification($senderId, 'friend_accepted', [
                'message' => $msg,
                'accepter_id' => $currentUserId,
                'accepter_username' => $myUsername
            ]);

            $response = ['success' => true, 'message' => translation('notifications.now_friends')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'decline_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
            ->execute([$currentUserId, $senderId]);
        
        send_live_notification($senderId, 'request_declined', ['sender_id' => $currentUserId]);

        $response = ['success' => true, 'message' => translation('notifications.request_declined')];

    } elseif ($action === 'remove_friend') {
        $friendId = (int)($data['target_id'] ?? 0);
        
        $sql = "DELETE FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $friendId, $friendId, $currentUserId]);

        $pdo->prepare("DELETE FROM notifications 
                       WHERE user_id = ? AND related_id = ? 
                       AND type IN ('friend_request', 'friend_accepted')")
            ->execute([$currentUserId, $friendId]);

        send_live_notification($friendId, 'friend_removed', ['sender_id' => $currentUserId]);

        $response = ['success' => true, 'message' => translation('notifications.friend_removed')];

    // [NUEVO] Acción para bloquear usuario
    } elseif ($action === 'block_user') {
        $targetId = (int)($data['target_id'] ?? 0);
        if ($targetId === 0 || $targetId === $currentUserId) throw new Exception(translation('global.action_invalid'));

        // 1. Insertar en user_blocks
        $sql = "INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$currentUserId, $targetId])) {
            
            // 2. Eliminar amistad si existe
            $sqlDelFriend = "DELETE FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
            $pdo->prepare($sqlDelFriend)->execute([$currentUserId, $targetId, $targetId, $currentUserId]);

            // 3. Eliminar notificaciones pendientes de amistad
            $pdo->prepare("DELETE FROM notifications WHERE (user_id = ? AND related_id = ?) OR (user_id = ? AND related_id = ?) AND type IN ('friend_request', 'friend_accepted')")
                ->execute([$currentUserId, $targetId, $targetId, $currentUserId]);

            // Notificar al frontend para refrescar UI (opcional, el bloqueado no recibe notif específica de bloqueo)
            $response = ['success' => true, 'message' => translation('friends.blocked_success')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    // [NUEVO] Acción para desbloquear usuario
    } elseif ($action === 'unblock_user') {
        $targetId = (int)($data['target_id'] ?? 0);
        if ($targetId === 0) throw new Exception(translation('global.action_invalid'));

        $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        if ($stmt->execute([$currentUserId, $targetId])) {
            $response = ['success' => true, 'message' => translation('friends.unblocked_success')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
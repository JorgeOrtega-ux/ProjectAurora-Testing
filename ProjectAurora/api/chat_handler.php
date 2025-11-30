<?php
// api/chat_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/chat_error.log';
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

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

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

$userId = $_SESSION['user_id'];

try {
    // --- ENVIAR MENSAJE ---
    if ($action === 'send_message') {
        
        // Anti-Spam Check
        if (checkChatSpam($pdo, $userId)) {
            $config = getServerConfig($pdo);
            throw new Exception(translation('chat.error.spam_limit', ['limit' => $config['chat_msg_limit'], 'seconds' => $config['chat_time_window']]));
        }

        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? ''; 
        // [NUEVO] Recibir channel_uuid para contextos de comunidad
        $channelUuid = $data['channel_uuid'] ?? null;
        
        $context = $data['context'] ?? 'community'; 
        $messageText = trim($data['message'] ?? '');
        $replyToUuid = !empty($data['reply_to_uuid']) ? $data['reply_to_uuid'] : null;
        
        if (empty($uuid)) throw new Exception("UUID destino requerido");

        $targetId = null;
        $channelId = null;

        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetId = $stmtU->fetchColumn();
            if (!$targetId || $targetId == $userId) throw new Exception("Usuario inválido.");

            // Validaciones
            $stmtBlock = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
            $stmtBlock->execute([$userId, $targetId, $targetId, $userId]);
            if ($stmtBlock->rowCount() > 0) throw new Exception(translation('chat.error.privacy_block'));

            $stmtPriv = $pdo->prepare("SELECT COALESCE(up.message_privacy, 'friends') as privacy, (SELECT status FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) as status FROM users u LEFT JOIN user_preferences up ON u.id = up.user_id WHERE u.id = ?");
            $stmtPriv->execute([$userId, $targetId, $targetId, $userId, $targetId]);
            $res = $stmtPriv->fetch(PDO::FETCH_ASSOC);
            $privacy = $res['privacy'] ?? 'friends';
            $status = $res['status']; 
            if ($privacy === 'nobody') {
                throw new Exception(translation('chat.error.privacy_block'));
            }
            if ($privacy === 'friends' && $status !== 'accepted') {
                throw new Exception(translation('chat.error.privacy_block'));
            }

        } else {
            // Contexto Comunidad
            $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $targetId = $stmtC->fetchColumn();
            if (!$targetId) throw new Exception("Acceso denegado a la comunidad.");

            // [NUEVO] Validar y resolver Canal
            if (empty($channelUuid)) throw new Exception("Canal requerido.");
            
            $stmtCh = $pdo->prepare("SELECT id FROM community_channels WHERE uuid = ? AND community_id = ?");
            $stmtCh->execute([$channelUuid, $targetId]);
            $channelId = $stmtCh->fetchColumn();
            
            if (!$channelId) throw new Exception("Canal no válido o no pertenece a esta comunidad.");
        }

        // Lógica de Respuesta (Reply)
        $reply_data = [];
        if ($replyToUuid) {
            $table = ($context === 'private') ? 'private_messages' : 'community_messages';
            $joinCol = ($context === 'private') ? 'sender_id' : 'user_id';
            
            $parent_query = "SELECT m.message, m.type, u.username FROM $table m JOIN users u ON m.$joinCol = u.id WHERE m.uuid = ?";
            $stmtReply = $pdo->prepare($parent_query);
            $stmtReply->execute([$replyToUuid]);
            $parent_row = $stmtReply->fetch(PDO::FETCH_ASSOC);

            if ($parent_row) {
                $reply_data = [
                    'message' => $parent_row['message'], 
                    'sender_username' => $parent_row['username'],
                    'type' => $parent_row['type']
                ];
            } elseif (isset($redis) && $redis) {
                // Buscar en Redis usando la nueva estructura de claves
                $searchRedisKey = ($context === 'private') 
                    ? "chat:buffer:private:".min($userId, $targetId).":".max($userId, $targetId) 
                    : "chat:buffer:channel:$channelId"; // [MODIFICADO] Usar channelId
                
                $cachedMessages = $redis->lRange($searchRedisKey, 0, -1);
                foreach ($cachedMessages as $jsonMsg) {
                    $item = json_decode($jsonMsg, true);
                    if ($item && isset($item['uuid']) && $item['uuid'] === $replyToUuid) {
                        $reply_data = [
                            'message' => $item['message'],
                            'sender_username' => $item['sender_username'], 
                            'type' => $item['type']
                        ];
                        break; 
                    }
                }
            }
        }

        // Subida de archivos
        $uploadedFiles = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $files = $_FILES['attachments'];
            $count = count($files['name']);
            if ($count > 4) throw new Exception("Máximo 4 imágenes permitidas.");
            $uploadDir = __DIR__ . '/../public/assets/uploads/chat/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $name = basename($files['name'][$i]);
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpName);
                    if (!str_starts_with($mime, 'image/')) continue;
                    $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'png';
                    $newFileName = generate_uuid() . '.' . $ext;
                    $targetPath = $uploadDir . $newFileName;
                    $dbPath = 'assets/uploads/chat/' . $newFileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $fileUuid = generate_uuid();
                        $stmtFile = $pdo->prepare("INSERT INTO community_files (uuid, uploader_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
                        $stmtFile->execute([$fileUuid, $userId, $dbPath, $name, $mime]);
                        $fileId = $pdo->lastInsertId();
                        $uploadedFiles[] = ['name' => $name, 'path' => $dbPath, 'mime' => $mime, 'type' => 'image', 'db_id' => $fileId];
                    }
                }
            }
        }

        if (empty($messageText) && empty($uploadedFiles)) throw new Exception("El mensaje no puede estar vacío.");

        $msgType = (!empty($uploadedFiles)) ? (empty($messageText) ? 'image' : 'mixed') : 'text';
        $messageUuid = generate_uuid();
        $createdAt = date('c');

        $stmtUser = $pdo->prepare("SELECT uuid, username, profile_picture, role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // --- CONSTRUCCIÓN DEL PAYLOAD ---
        $messagePayload = [
            'id' => null, 
            'uuid' => $messageUuid,
            'target_uuid' => $uuid, 
            'context' => $context,
            'message' => $messageText,
            'sender_id' => $userId,
            'sender_uuid' => $userData['uuid'],
            'sender_username' => $userData['username'],
            'sender_profile_picture' => $userData['profile_picture'],
            'sender_role' => $userData['role'],
            'created_at' => $createdAt,
            'type' => $msgType,
            'status' => 'active',
            'reply_to_uuid' => $replyToUuid,
            'attachments' => $uploadedFiles,
            'reply_message' => $reply_data['message'] ?? null,
            'reply_sender_username' => $reply_data['sender_username'] ?? null,
            'reply_type' => $reply_data['type'] ?? null,
            
            // Identificadores para JS
            'community_uuid' => ($context === 'community') ? $uuid : null,
            'channel_uuid' => $channelUuid, // [NUEVO]
            
            // Campos para Worker Python/DB
            'community_id' => ($context === 'community') ? $targetId : null,
            'channel_id' => ($context === 'community') ? $channelId : null, // [NUEVO]
            'user_id' => ($context === 'community') ? $userId : null,
            'receiver_id' => ($context === 'private') ? $targetId : null
        ];

        $savedToRedis = false;
        
        $isFirstMessage = false;
        if ($context === 'private') {
            $stmtCheckHistory = $pdo->prepare("SELECT id FROM private_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1");
            $stmtCheckHistory->execute([$userId, $targetId, $targetId, $userId]);
            if ($stmtCheckHistory->rowCount() === 0) {
                $isFirstMessage = true;
            }
        }

        // Usar Redis solo si NO es el primer mensaje
        if (isset($redis) && $redis && !$isFirstMessage) {
            try {
                if ($context === 'private') {
                    $minId = min($userId, $targetId);
                    $maxId = max($userId, $targetId);
                    $redisKey = "chat:buffer:private:$minId:$maxId";
                } else {
                    // [MODIFICADO] Usar channel_id para separar canales
                    $redisKey = "chat:buffer:channel:$channelId";
                }
                $redis->rPush($redisKey, json_encode($messagePayload));
                $savedToRedis = true;
            } catch (Exception $e) {
                error_log("Redis Push Failed: " . $e->getMessage());
                $savedToRedis = false;
            }
        }

        if (!$savedToRedis) {
            // FALLBACK / PRIMER MENSAJE: Guardar directo en MySQL
            if ($context === 'private') {
                $replyToId = null;
                if ($replyToUuid) {
                    $stmtRid = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                    $stmtRid->execute([$replyToUuid]);
                    $replyToId = $stmtRid->fetchColumn() ?: null;
                }
                $sql = "INSERT INTO private_messages (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute([$messageUuid, $userId, $targetId, $messageText, $msgType, $replyToId, $replyToUuid]);
                $msgDbId = $pdo->lastInsertId();
                if (!empty($uploadedFiles)) {
                    $stmtAtt = $pdo->prepare("INSERT INTO private_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) $stmtAtt->execute([$msgDbId, $file['db_id']]);
                }
            } else {
                $replyToId = null;
                if ($replyToUuid) {
                    $stmtRid = $pdo->prepare("SELECT id FROM community_messages WHERE uuid = ?");
                    $stmtRid->execute([$replyToUuid]);
                    $replyToId = $stmtRid->fetchColumn() ?: null;
                }
                // [MODIFICADO] Incluir channel_id
                $sql = "INSERT INTO community_messages (uuid, community_id, channel_id, user_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute([$messageUuid, $targetId, $channelId, $userId, $messageText, $msgType, $replyToId, $replyToUuid]);
                $msgDbId = $pdo->lastInsertId();
                if (!empty($uploadedFiles)) {
                    $stmtAtt = $pdo->prepare("INSERT INTO community_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) $stmtAtt->execute([$msgDbId, $file['db_id']]);
                }
            }
            $messagePayload['id'] = $msgDbId; 
        }
        
        // NOTIFICACIÓN SOCKET
        $socketType = ($context === 'private') ? 'private_message' : 'new_chat_message';
        $socketTarget = ($context === 'private') ? $targetId : 'community_broadcast';
        
        $extraData = ['message_data' => $messagePayload];
        if ($context === 'community') $extraData['community_id'] = $targetId;
        if ($context === 'private') $extraData['sender_id'] = $userId;

        $sent = send_live_notification($socketTarget, $socketType, $extraData);
        
        if (!$sent) {
            error_log("Fallo al contactar Socket Bridge (8081).");
        }

        echo json_encode(['success' => true, 'message' => 'Enviado']);
        exit;

    // --- OBTENER MENSAJES (MERGE REDIS + MYSQL) ---
    } elseif ($action === 'get_messages') {
        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? '';
        $context = $data['context'] ?? 'community';
        $channelUuid = $data['channel_uuid'] ?? null; // [NUEVO]

        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
        
        $targetId = null;
        $channelId = null;
        $redisKey = '';

        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetId = $stmtU->fetchColumn();
            if (!$targetId) throw new Exception("Usuario no encontrado");

            $stmtRead = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $stmtRead->execute([$targetId, $userId]);

            if (isset($redis) && $redis) {
                $minId = min($userId, $targetId);
                $maxId = max($userId, $targetId);
                $redisKey = "chat:buffer:private:$minId:$maxId";
            }

        } else {
            // Contexto Comunidad
            $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $targetId = $stmtC->fetchColumn();
            if (!$targetId) throw new Exception("Acceso denegado");

            // [NUEVO] Resolver Channel ID
            if (!empty($channelUuid)) {
                $stmtCh = $pdo->prepare("SELECT id FROM community_channels WHERE uuid = ? AND community_id = ?");
                $stmtCh->execute([$channelUuid, $targetId]);
                $channelId = $stmtCh->fetchColumn();
            }
            
            // Si no se envía channel_uuid, intentamos usar el 'General' por defecto o fallamos
            if (!$channelId && $context === 'community') {
                // Fallback: obtener el primer canal disponible
                 $stmtDef = $pdo->prepare("SELECT id FROM community_channels WHERE community_id = ? ORDER BY created_at ASC LIMIT 1");
                 $stmtDef->execute([$targetId]);
                 $channelId = $stmtDef->fetchColumn();
            }

            if (!$channelId) throw new Exception("Canal no encontrado.");

            $pdo->prepare("UPDATE community_members SET last_read_at = NOW() WHERE community_id = ? AND user_id = ?")->execute([$targetId, $userId]);
            if (isset($redis) && $redis) {
                // [MODIFICADO] Key por canal
                $redisKey = "chat:buffer:channel:$channelId";
            }
        }

        // 1. Redis
        $redisMessages = [];
        if (isset($redis) && $redis && !empty($redisKey)) {
            try {
                $rawRedis = $redis->lRange($redisKey, 0, -1);
                foreach ($rawRedis as $json) {
                    $m = json_decode($json, true);
                    if ($m) $redisMessages[] = $m;
                }
                $redisMessages = array_reverse($redisMessages);
            } catch (Exception $e) { error_log("Redis Read Error: " . $e->getMessage()); }
        }

        // 2. MySQL
        $sqlMessages = [];
        if ($context === 'private') {
            $stmtClear = $pdo->prepare("SELECT cleared_at FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtClear->execute([$userId, $targetId]);
            $clearedAt = $stmtClear->fetchColumn() ?: '1970-01-01 00:00:00';

            $sql = "
                SELECT m.id, m.uuid, m.message, m.created_at, m.type, m.reply_to_id, m.reply_to_uuid, m.status, m.sender_id,
                       u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                       p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                       (SELECT COUNT(*) FROM private_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                       (SELECT GROUP_CONCAT(CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}') SEPARATOR ',') FROM private_message_attachments cma JOIN community_files f ON cma.file_id = f.id WHERE cma.message_id = m.id) as attachments_json
                FROM private_messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN private_messages p ON m.reply_to_id = p.id
                LEFT JOIN users pu ON p.sender_id = pu.id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                AND m.created_at > ?
                ORDER BY m.created_at DESC 
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $targetId, $targetId, $userId, $clearedAt]);
        } else {
            // [MODIFICADO] Filtrar por channel_id
            $sql = "
                SELECT m.id, m.uuid, m.message, m.created_at, m.type, m.reply_to_id, m.reply_to_uuid, m.status, m.user_id as sender_id,
                       u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                       p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                       (SELECT COUNT(*) FROM community_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                       (SELECT GROUP_CONCAT(CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}') SEPARATOR ',') FROM community_message_attachments cma JOIN community_files f ON cma.file_id = f.id WHERE cma.message_id = m.id) as attachments_json
                FROM community_messages m
                JOIN users u ON m.user_id = u.id
                LEFT JOIN community_messages p ON m.reply_to_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                WHERE m.community_id = ? AND m.channel_id = ?
                ORDER BY m.created_at DESC 
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId, $channelId]);
        }
        
        $dbMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dbMessages as &$msg) {
            if ($msg['status'] === 'deleted') {
                $msg['message'] = null; $msg['attachments'] = [];
            } else {
                if (!empty($msg['attachments_json'])) {
                    $jsonString = '[' . $msg['attachments_json'] . ']';
                    $msg['attachments'] = json_decode($jsonString, true);
                } else {
                    $msg['attachments'] = [];
                }
            }
            unset($msg['attachments_json']);
        }

        $finalList = [];
        if ($offset === 0) {
            $finalList = array_merge($redisMessages, $dbMessages);
        } else {
            $finalList = $dbMessages;
        }

        // Deduplicar
        $uniqueMessages = [];
        $seenUuids = [];
        foreach ($finalList as $m) {
            if (!in_array($m['uuid'], $seenUuids)) {
                $uniqueMessages[] = $m;
                $seenUuids[] = $m['uuid'];
            }
        }

        usort($uniqueMessages, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        echo json_encode(['success' => true, 'messages' => array_reverse($uniqueMessages), 'has_more' => (count($dbMessages) >= $limit)]);
        exit;

    } elseif ($action === 'mark_as_read') {
        $targetUuid = $data['target_uuid'] ?? ''; 
        $context = $data['context'] ?? 'private';
        if (empty($targetUuid)) throw new Exception("UUID requerido");
        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$targetUuid]);
            $senderId = $stmtU->fetchColumn();
            if ($senderId) {
                $stmtUpd = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                $stmtUpd->execute([$senderId, $userId]);
                if ($stmtUpd->rowCount() > 0) {
                    send_live_notification($senderId, 'messages_read', ['reader_id' => $userId, 'context' => 'private']);
                }
            }
        } else {
            $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtC->execute([$targetUuid]);
            $commId = $stmtC->fetchColumn();
            if ($commId) {
                $pdo->prepare("UPDATE community_members SET last_read_at = NOW() WHERE community_id = ? AND user_id = ?")->execute([$commId, $userId]);
            }
        }
        echo json_encode(['success' => true]);
        exit;

    } elseif ($action === 'delete_message') {
        $msgUuid = $data['message_id'] ?? '';
        $context = $data['context'] ?? 'community';
        if (empty($msgUuid)) throw new Exception(translation('global.action_invalid'));
        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $userCol = ($context === 'private') ? 'sender_id' : 'user_id';
        $stmt = $pdo->prepare("SELECT id, created_at, status FROM $table WHERE uuid = ? AND $userCol = ?");
        $stmt->execute([$msgUuid, $userId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) throw new Exception("El mensaje no se puede eliminar (aún no guardado en base de datos).");
        if ($msg['status'] === 'deleted') throw new Exception("Ya eliminado.");
        $msgTime = strtotime($msg['created_at']);
        if (time() - $msgTime > (24 * 3600)) throw new Exception(translation('chat.error.delete_timeout') ?? 'Tiempo expirado');
        $pdo->prepare("UPDATE $table SET status = 'deleted', message = '', type = 'text' WHERE id = ?")->execute([$msg['id']]);
        $eventType = ($context === 'private') ? 'private_message_deleted' : 'message_deleted';
        $notifPayload = ['message_id' => $msgUuid, 'sender_id' => $userId];
        if ($context === 'private') {
            $stmtRec = $pdo->prepare("SELECT receiver_id FROM private_messages WHERE id = ?");
            $stmtRec->execute([$msg['id']]);
            $receiverId = $stmtRec->fetchColumn();
            send_live_notification($receiverId, $eventType, $notifPayload);
            send_live_notification($userId, $eventType, $notifPayload);
        } else {
            $stmtComm = $pdo->prepare("SELECT community_id FROM community_messages WHERE id = ?");
            $stmtComm->execute([$msg['id']]);
            $commId = $stmtComm->fetchColumn();
            send_live_notification('community_broadcast', $eventType, ['message_data' => $notifPayload, 'community_id' => $commId]);
        }
        echo json_encode(['success' => true, 'message' => translation('chat.message_deleted')]);
        exit;

    } elseif ($action === 'delete_conversation') {
        $uuid = $data['target_uuid'] ?? '';
        if (empty($uuid)) throw new Exception(translation('global.action_invalid'));
        
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$uuid]);
        $partnerId = $stmtU->fetchColumn();
        
        if (!$partnerId) throw new Exception("Usuario no encontrado.");

        // --- INICIO: EMERGENCY FLUSH (REDIS -> MYSQL) ---
        if (isset($redis) && $redis) {
            $minId = min($userId, $partnerId);
            $maxId = max($userId, $partnerId);
            $redisKey = "chat:buffer:private:$minId:$maxId";

            try {
                $cachedMessages = $redis->lRange($redisKey, 0, -1);
                
                if (!empty($cachedMessages)) {
                    $pdo->beginTransaction();
                    
                    foreach ($cachedMessages as $jsonMsg) {
                        $msg = json_decode($jsonMsg, true);
                        if (!$msg) continue;

                        $stmtCheck = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                        $stmtCheck->execute([$msg['uuid']]);
                        if ($stmtCheck->fetch()) continue;

                        $replyId = null;
                        if (!empty($msg['reply_to_uuid'])) {
                            $stmtRep = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                            $stmtRep->execute([$msg['reply_to_uuid']]);
                            $replyId = $stmtRep->fetchColumn() ?: null;
                        }

                        $sqlInsert = "INSERT INTO private_messages 
                            (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at, is_read) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"; 
                        
                        $stmtIns = $pdo->prepare($sqlInsert);
                        $stmtIns->execute([
                            $msg['uuid'],
                            $msg['sender_id'], 
                            $msg['receiver_id'] ?? $partnerId, 
                            $msg['message'],
                            $msg['type'],
                            $replyId,
                            $msg['reply_to_uuid'] ?? null,
                            $msg['created_at']
                        ]);
                        
                        $newMsgId = $pdo->lastInsertId();

                        if (!empty($msg['attachments'])) {
                            $stmtAtt = $pdo->prepare("INSERT INTO private_message_attachments (message_id, file_id) VALUES (?, ?)");
                            $stmtFileSearch = $pdo->prepare("SELECT id FROM community_files WHERE file_path = ? LIMIT 1");
                            
                            foreach ($msg['attachments'] as $att) {
                                if (isset($att['db_id'])) {
                                    $stmtAtt->execute([$newMsgId, $att['db_id']]);
                                } else {
                                    $stmtFileSearch->execute([$att['path']]);
                                    $fId = $stmtFileSearch->fetchColumn();
                                    if ($fId) $stmtAtt->execute([$newMsgId, $fId]);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $redis->del($redisKey);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Emergency Flush Error: " . $e->getMessage());
            }
        }
        // --- FIN: EMERGENCY FLUSH ---

        $sql = "INSERT INTO private_chat_clearance (user_id, partner_id, cleared_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cleared_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $partnerId]);
        echo json_encode(['success' => true, 'message' => 'Chat eliminado de tu lista.']);
        exit;

    } elseif ($action === 'report_message') {
        $msgUuid = $data['message_id'] ?? '';
        $reason = trim($data['reason'] ?? '');
        $context = $data['context'] ?? 'community';
        if (empty($msgUuid) || empty($reason)) throw new Exception(translation('admin.error.reason_required'));
        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $stmtCheck = $pdo->prepare("SELECT id FROM $table WHERE uuid = ?");
        $stmtCheck->execute([$msgUuid]);
        $msgId = $stmtCheck->fetchColumn();
        if (!$msgId) throw new Exception("Mensaje no encontrado o aún en proceso de guardado.");
        if ($context === 'private') {
            $stmtRep = $pdo->prepare("SELECT id FROM private_message_reports WHERE message_id = ? AND reporter_id = ?");
            $stmtRep->execute([$msgId, $userId]);
            if ($stmtRep->rowCount() > 0) throw new Exception(translation('chat.error.already_reported') ?? 'Ya reportado');
            $stmtIns = $pdo->prepare("INSERT INTO private_message_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW())");
            $stmtIns->execute([$msgId, $userId, $reason]);
        } else {
            $stmtRep = $pdo->prepare("SELECT id FROM community_message_reports WHERE message_id = ? AND reporter_id = ?");
            $stmtRep->execute([$msgId, $userId]);
            if ($stmtRep->rowCount() > 0) throw new Exception(translation('chat.error.already_reported') ?? 'Ya reportado');
            $stmtIns = $pdo->prepare("INSERT INTO community_message_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW())");
            $stmtIns->execute([$msgId, $userId, $reason]);
        }
        echo json_encode(['success' => true, 'message' => translation('chat.report_success')]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
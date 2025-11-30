<?php
// api/communities_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/communities_error.log';
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

$userId = $_SESSION['user_id'];

try {

    // --- UNIRSE POR CÓDIGO ---
    if ($action === 'join_by_code') {
        $code = trim($data['access_code'] ?? '');
        
        if (empty($code) || strlen($code) !== 14) {
            throw new Exception("El código debe tener el formato XXXX-XXXX-XXXX.");
        }

        $stmt = $pdo->prepare("SELECT id, community_name, privacy FROM communities WHERE access_code = ?");
        $stmt->execute([$code]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            throw new Exception("Código de acceso inválido o comunidad no encontrada.");
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$community['id'], $userId]);
        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Ya eres miembro de <strong>" . htmlspecialchars($community['community_name']) . "</strong>.");
        }

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
            $stmtInsert->execute([$community['id'], $userId]);

            $stmtCount = $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?");
            $stmtCount->execute([$community['id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Te has unido a <strong>" . htmlspecialchars($community['community_name']) . "</strong> correctamente."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception(translation('global.error_connection'));
        }

    // --- OBTENER SIDEBAR LIST (FUSIÓN SQL + REDIS) ---
    } elseif ($action === 'get_sidebar_list') {
        
        // 1. Obtener Comunidades desde SQL
        $sqlCommunities = "SELECT 
                    'community' as type,
                    c.id, c.uuid, c.community_name as name, 
                    c.profile_picture, c.community_type, cm.is_pinned, cm.is_favorite,
                    (SELECT message FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
                    (SELECT COUNT(*) FROM community_messages WHERE community_id = c.id AND created_at > cm.last_read_at AND user_id != cm.user_id) as unread_count,
                    0 as is_blocked_by_me,
                    'member' as role 
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE cm.user_id = ?";
        
        $stmtComm = $pdo->prepare($sqlCommunities);
        $stmtComm->execute([$userId]);
        $communities = $stmtComm->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener Chats Privados Activos desde SQL
        $sqlDMs = "SELECT 
                    'private' as type,
                    u.id, u.uuid, u.username as name, 
                    u.profile_picture, u.role,
                    COALESCE(pcc.is_pinned, 0) as is_pinned,
                    COALESCE(pcc.is_favorite, 0) as is_favorite,
                    m.message as last_message,
                    m.created_at as last_message_at,
                    (SELECT COUNT(*) FROM private_messages pm WHERE pm.sender_id = u.id AND pm.receiver_id = ? AND pm.is_read = 0) as unread_count,
                    COALESCE(up.message_privacy, 'friends') as message_privacy,
                    (SELECT status FROM friendships f WHERE (f.sender_id = u.id AND f.receiver_id = ?) OR (f.sender_id = ? AND f.receiver_id = u.id)) as friend_status,
                    (SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked_by_me
                   FROM users u
                   LEFT JOIN user_preferences up ON u.id = up.user_id
                   JOIN (
                       SELECT 
                           CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id,
                           message, created_at
                       FROM private_messages
                       WHERE id IN (
                           SELECT MAX(id) 
                           FROM private_messages 
                           WHERE sender_id = ? OR receiver_id = ? 
                           GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
                       )
                   ) m ON u.id = m.partner_id
                   LEFT JOIN private_chat_clearance pcc ON (pcc.user_id = ? AND pcc.partner_id = u.id)
                   WHERE u.account_status = 'active'
                   AND (pcc.cleared_at IS NULL OR m.created_at > pcc.cleared_at)";

        $stmtDMs = $pdo->prepare($sqlDMs);
        $params = array_fill(0, 9, $userId);
        $stmtDMs->execute($params);
        $dms = $stmtDMs->fetchAll(PDO::FETCH_ASSOC);

        // Procesar privacidad en DMs
        foreach ($dms as &$dm) {
            $privacy = $dm['message_privacy'];
            $status = $dm['friend_status'];
            $dm['can_message'] = true;
            if ($privacy === 'nobody') $dm['can_message'] = false;
            elseif ($privacy === 'friends' && $status !== 'accepted') $dm['can_message'] = false;
            unset($dm['message_privacy']);
            unset($dm['friend_status']);
        }

        // Combinar listas iniciales
        $fullList = array_merge($communities, $dms);
        
        // --- 3. FUSIÓN CON REDIS ---
        if (isset($redis) && $redis) {
            try {
                $existingMap = [];
                foreach ($fullList as $index => $item) {
                    $key = $item['type'] . '_' . $item['id'];
                    $existingMap[$key] = $index;
                }

                $keys1 = $redis->keys("chat:buffer:private:$userId:*");
                $keys2 = $redis->keys("chat:buffer:private:*:$userId");
                $allBufferKeys = array_unique(array_merge($keys1, $keys2));

                foreach ($allBufferKeys as $key) {
                    $lastMsgJson = $redis->lIndex($key, -1);
                    if (!$lastMsgJson) continue;
                    
                    $msgData = json_decode($lastMsgJson, true);
                    if (!$msgData) continue;

                    $parts = explode(':', $key);
                    $id1 = $parts[3];
                    $id2 = $parts[4];
                    $partnerId = ($id1 == $userId) ? $id2 : $id1;
                    
                    $mapKey = 'private_' . $partnerId;
                    
                    $previewMsg = $msgData['message'];
                    if (empty($previewMsg) && !empty($msgData['attachments'])) {
                        $previewMsg = '📷 Imagen';
                    }
                    if ($msgData['sender_id'] == $userId) {
                        $previewMsg = 'Tú: ' . $previewMsg;
                    }

                    $redisTimestamp = $msgData['created_at'];

                    if (isset($existingMap[$mapKey])) {
                        $index = $existingMap[$mapKey];
                        $currentSqlTime = $fullList[$index]['last_message_at'];
                        
                        if (!$currentSqlTime || strtotime($redisTimestamp) > strtotime($currentSqlTime)) {
                            $fullList[$index]['last_message'] = $previewMsg;
                            $fullList[$index]['last_message_at'] = $redisTimestamp;
                            
                            if ($msgData['sender_id'] != $userId) {
                                $bufferLen = $redis->lLen($key);
                                $fullList[$index]['unread_count'] += $bufferLen; 
                            }
                        }
                    } else {
                        $stmtUser = $pdo->prepare("SELECT id, uuid, username, profile_picture, role FROM users WHERE id = ?");
                        $stmtUser->execute([$partnerId]);
                        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
                        
                        if ($uData) {
                            $newChat = [
                                'type' => 'private',
                                'id' => $uData['id'],
                                'uuid' => $uData['uuid'],
                                'name' => $uData['username'],
                                'profile_picture' => $uData['profile_picture'],
                                'role' => $uData['role'],
                                'is_pinned' => 0,
                                'is_favorite' => 0,
                                'last_message' => $previewMsg,
                                'last_message_at' => $redisTimestamp,
                                'unread_count' => ($msgData['sender_id'] != $userId) ? 1 : 0,
                                'can_message' => true,
                                'is_blocked_by_me' => 0
                            ];
                            $fullList[] = $newChat;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Redis Sidebar Merge Error: " . $e->getMessage());
            }
        }

        usort($fullList, function($a, $b) {
            $pinA = (int)($a['is_pinned'] ?? 0);
            $pinB = (int)($b['is_pinned'] ?? 0);
            
            if ($pinA !== $pinB) return $pinB - $pinA; 

            $t1 = $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
            $t2 = $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
            return $t2 - $t1;
        });

        echo json_encode(['success' => true, 'list' => $fullList]);

    // --- TOGGLE PIN CHAT ---
    } elseif ($action === 'toggle_pin') {
        $uuid = $data['uuid'] ?? '';
        $type = $data['type'] ?? ''; 
        
        if (!$uuid || !$type) throw new Exception(translation('global.action_invalid'));

        $sqlCount = "SELECT 
            (SELECT COUNT(*) FROM community_members WHERE user_id = ? AND is_pinned = 1) + 
            (SELECT COUNT(*) FROM private_chat_clearance WHERE user_id = ? AND is_pinned = 1) as total_pinned";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$userId, $userId]);
        $totalPinned = (int)$stmtCount->fetchColumn();

        $newState = 0;

        if ($type === 'community') {
            $stmtCurr = $pdo->prepare("SELECT is_pinned, id FROM community_members WHERE user_id = ? AND community_id = (SELECT id FROM communities WHERE uuid = ?)");
            $stmtCurr->execute([$userId, $uuid]);
            $row = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) throw new Exception("Comunidad no encontrada.");
            
            $currentPinned = (int)$row['is_pinned'];
            $newState = $currentPinned ? 0 : 1;

            if ($newState === 1 && $totalPinned >= 3) throw new Exception("Máximo 3 chats fijados.");

            $pdo->prepare("UPDATE community_members SET is_pinned = ? WHERE id = ?")->execute([$newState, $row['id']]);

        } elseif ($type === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $partnerId = $stmtU->fetchColumn();
            if (!$partnerId) throw new Exception("Usuario no encontrado.");

            $stmtCurr = $pdo->prepare("SELECT is_pinned FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtCurr->execute([$userId, $partnerId]);
            $currentPinned = (int)$stmtCurr->fetchColumn();

            $newState = $currentPinned ? 0 : 1;

            if ($newState === 1 && $totalPinned >= 3) throw new Exception("Máximo 3 chats fijados.");

            $sqlUpd = "INSERT INTO private_chat_clearance (user_id, partner_id, is_pinned) VALUES (?, ?, ?) 
                       ON DUPLICATE KEY UPDATE is_pinned = VALUES(is_pinned)";
            $pdo->prepare($sqlUpd)->execute([$userId, $partnerId, $newState]);
        }

        echo json_encode(['success' => true, 'is_pinned' => $newState, 'message' => $newState ? 'Chat fijado' : 'Chat desfijado']);

    // --- TOGGLE FAVORITE CHAT ---
    } elseif ($action === 'toggle_favorite') {
        $uuid = $data['uuid'] ?? '';
        $type = $data['type'] ?? ''; 
        
        if (!$uuid || !$type) throw new Exception(translation('global.action_invalid'));

        $newState = 0;

        if ($type === 'community') {
            $stmtCurr = $pdo->prepare("SELECT is_favorite, id FROM community_members WHERE user_id = ? AND community_id = (SELECT id FROM communities WHERE uuid = ?)");
            $stmtCurr->execute([$userId, $uuid]);
            $row = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) throw new Exception("Error.");
            
            $newState = ((int)$row['is_favorite']) ? 0 : 1;
            $pdo->prepare("UPDATE community_members SET is_favorite = ? WHERE id = ?")->execute([$newState, $row['id']]);

        } elseif ($type === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $partnerId = $stmtU->fetchColumn();
            if (!$partnerId) throw new Exception("Error.");

            $stmtCurr = $pdo->prepare("SELECT is_favorite FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtCurr->execute([$userId, $partnerId]);
            $currentFav = (int)$stmtCurr->fetchColumn();

            $newState = $currentFav ? 0 : 1;

            $sqlUpd = "INSERT INTO private_chat_clearance (user_id, partner_id, is_favorite) VALUES (?, ?, ?) 
                       ON DUPLICATE KEY UPDATE is_favorite = VALUES(is_favorite)";
            $pdo->prepare($sqlUpd)->execute([$userId, $partnerId, $newState]);
        }

        echo json_encode(['success' => true, 'is_favorite' => $newState, 'message' => $newState ? 'Marcado como favorito' : 'Quitado de favoritos']);

    // --- OBTENER COMUNIDADES PÚBLICAS ---
    } elseif ($action === 'get_public_communities') {
        $sql = "SELECT c.id, c.uuid, c.community_name, c.community_type, c.member_count, c.profile_picture, c.banner_picture
                FROM communities c
                WHERE c.privacy = 'public'
                AND c.id NOT IN (SELECT community_id FROM community_members WHERE user_id = ?)
                ORDER BY c.member_count DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'communities' => $communities]);

    // --- UNIRSE A PÚBLICA ---
    } elseif ($action === 'join_public') {
        $communityId = (int)($data['community_id'] ?? 0);
        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$communityId, $userId]);
        if ($stmtCheck->rowCount() > 0) throw new Exception("Ya eres miembro.");
        
        // Obtener el ID del canal general por defecto
        $stmtChannel = $pdo->prepare("SELECT id FROM community_channels WHERE community_id = ? AND name = 'General' LIMIT 1");
        $stmtChannel->execute([$communityId]);
        $channelId = $stmtChannel->fetchColumn();

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)")->execute([$communityId, $userId]);
        $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?")->execute([$communityId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Te has unido al grupo.']);

    // --- ABANDONAR ---
    } elseif ($action === 'leave_community') {
        $uuid = $data['uuid'] ?? '';
        $communityId = (int)($data['community_id'] ?? 0);

        if ($communityId === 0 && !empty($uuid)) {
            $stmtId = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtId->execute([$uuid]);
            $communityId = $stmtId->fetchColumn();
        }

        if (empty($communityId)) throw new Exception(translation('global.action_invalid'));

        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$communityId, $userId]);

        if ($stmtDel->rowCount() > 0) {
            $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$communityId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Has salido del grupo.']);
        } else {
            $pdo->rollBack();
            throw new Exception("No eres miembro de este grupo.");
        }

    // --- OBTENER DETALLES COMUNIDAD POR UUID ---
    } elseif ($action === 'get_community_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        $sql = "SELECT c.id, c.uuid, c.community_name, c.community_type, c.profile_picture, c.banner_picture, cm.role FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?";
        $stmt = $pdo->prepare($sql); $stmt->execute([$uuid, $userId]); $comm = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($comm) { $comm['type'] = 'community'; echo json_encode(['success' => true, 'data' => $comm]); } 
        else echo json_encode(['success' => false, 'message' => 'Error']);

    // --- OBTENER DETALLES USUARIO (DM) POR UUID ---
    } elseif ($action === 'get_user_chat_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        
        $sql = "SELECT u.id, u.uuid, u.username as community_name, u.profile_picture, u.role,
                       COALESCE(up.message_privacy, 'friends') as message_privacy,
                       (SELECT status FROM friendships f WHERE (f.sender_id = u.id AND f.receiver_id = ?) OR (f.sender_id = ? AND f.receiver_id = u.id)) as friend_status
                FROM users u 
                LEFT JOIN user_preferences up ON u.id = up.user_id
                WHERE u.uuid = ? AND u.id != ?";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $uuid, $userId]); 
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) { 
            $user['type'] = 'private'; 
            $user['banner_picture'] = null; 
            if(empty($user['role'])) $user['role'] = 'member'; 
            
            $privacy = $user['message_privacy'];
            $status = $user['friend_status'];
            $user['can_message'] = true;

            if ($privacy === 'nobody') {
                $user['can_message'] = false;
            } elseif ($privacy === 'friends' && $status !== 'accepted') {
                $user['can_message'] = false;
            }
            
            unset($user['message_privacy']);
            unset($user['friend_status']);
            
            echo json_encode(['success' => true, 'data' => $user]); 
        }
        else echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);

    // --- OBTENER DETALLES COMPLETOS DE COMUNIDAD + CANALES ---
    } elseif ($action === 'get_community_details') {
        $uuid = trim($data['uuid'] ?? '');
        
        // Info Básica y Rol
        $stmtC = $pdo->prepare("SELECT c.id, c.community_name, c.community_type, c.profile_picture, c.access_code, c.member_count, cm.role 
                                FROM communities c 
                                JOIN community_members cm ON c.id = cm.community_id 
                                WHERE c.uuid = ? AND cm.user_id = ?");
        $stmtC->execute([$uuid, $userId]);
        $info = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        if (!$info) throw new Exception("Error o no tienes acceso.");
        
        // Miembros
        $sqlMembers = "SELECT u.id, u.username, u.profile_picture, cm.role 
                       FROM community_members cm 
                       JOIN users u ON cm.user_id = u.id 
                       WHERE cm.community_id = ? 
                       ORDER BY FIELD(cm.role, 'admin', 'moderator', 'member'), u.username ASC";
        $stmtM = $pdo->prepare($sqlMembers); 
        $stmtM->execute([$info['id']]); 
        $members = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        
        // Archivos Recientes
        $sqlFiles = "SELECT f.file_path, f.file_type, f.created_at, u.username, u.profile_picture 
                     FROM community_files f 
                     JOIN users u ON f.uploader_id = u.id 
                     JOIN community_message_attachments cma ON f.id = cma.file_id 
                     JOIN community_messages m ON cma.message_id = m.id 
                     WHERE m.community_id = ? AND m.status = 'active' AND f.file_type LIKE 'image/%' 
                     ORDER BY f.created_at DESC LIMIT 12";
        $stmtF = $pdo->prepare($sqlFiles); 
        $stmtF->execute([$info['id']]); 
        $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        // [NUEVO] Obtener Canales
        $sqlChannels = "SELECT id, uuid, name, type FROM community_channels WHERE community_id = ? ORDER BY created_at ASC";
        $stmtChannels = $pdo->prepare($sqlChannels);
        $stmtChannels->execute([$info['id']]);
        $channels = $stmtChannels->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay canales (legacy support), creamos "General" on the fly o lo simulamos
        if (empty($channels)) {
            // Crear canal general por defecto si no existe
            $newUuid = generate_uuid();
            $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type) VALUES (?, ?, 'General', 'text')")
                ->execute([$newUuid, $info['id']]);
            $channels[] = ['id' => $pdo->lastInsertId(), 'uuid' => $newUuid, 'name' => 'General', 'type' => 'text'];
        }

        echo json_encode([
            'success' => true, 
            'info' => $info, 
            'members' => $members, 
            'files' => $files,
            'channels' => $channels
        ]);

    // --- OBTENER DETALLES DE CHAT PRIVADO ---
    } elseif ($action === 'get_private_chat_details') {
        $uuid = trim($data['uuid'] ?? '');
        $stmtU = $pdo->prepare("SELECT id, username, profile_picture, role FROM users WHERE uuid = ?");
        $stmtU->execute([$uuid]);
        $user = $stmtU->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new Exception("Usuario no encontrado.");
        $targetId = $user['id'];
        $sqlFiles = "
            SELECT f.file_path, f.file_type, f.created_at, u.username 
            FROM community_files f 
            JOIN users u ON f.uploader_id = u.id 
            JOIN private_message_attachments pma ON f.id = pma.file_id 
            JOIN private_messages pm ON pma.message_id = pm.id 
            WHERE 
                ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?))
                AND pm.status = 'active' 
                AND f.file_type LIKE 'image/%' 
            ORDER BY f.created_at DESC 
            LIMIT 12";
        $stmtF = $pdo->prepare($sqlFiles);
        $stmtF->execute([$userId, $targetId, $targetId, $userId]);
        $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true, 
            'info' => [
                'community_name' => $user['username'], 
                'profile_picture' => $user['profile_picture'],
                'role' => $user['role'],
                'member_count' => null, 
                'access_code' => 'Chat Directo' 
            ], 
            'members' => [], 
            'files' => $files,
            'channels' => [] // Privado no tiene canales
        ]);

    // --- [NUEVO] CREAR CANAL ---
    } elseif ($action === 'create_channel') {
        $communityUuid = $data['community_uuid'] ?? '';
        $name = trim($data['name'] ?? '');
        $type = $data['type'] ?? 'text';

        if (empty($communityUuid) || empty($name)) throw new Exception("Faltan datos.");

        // Obtener ID de comunidad y verificar rol
        $stmt = $pdo->prepare("SELECT c.id, cm.role FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
        $stmt->execute([$communityUuid, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Comunidad no encontrada.");
        if (!in_array($row['role'], ['admin', 'moderator'])) throw new Exception("No tienes permisos para crear canales.");

        $newUuid = generate_uuid();
        $stmtIns = $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type) VALUES (?, ?, ?, ?)");
        
        if ($stmtIns->execute([$newUuid, $row['id'], $name, $type])) {
            echo json_encode(['success' => true, 'message' => "Canal creado.", 'channel' => ['uuid' => $newUuid, 'name' => $name, 'type' => $type]]);
        } else {
            throw new Exception("Error al crear el canal.");
        }

    // --- [NUEVO] ELIMINAR CANAL ---
    } elseif ($action === 'delete_channel') {
        $channelUuid = $data['channel_uuid'] ?? '';
        if (empty($channelUuid)) throw new Exception("UUID requerido.");

        // Verificar permisos y obtener community_id
        $stmt = $pdo->prepare("
            SELECT cc.id, cc.community_id, cm.role 
            FROM community_channels cc 
            JOIN community_members cm ON cc.community_id = cm.community_id 
            WHERE cc.uuid = ? AND cm.user_id = ?
        ");
        $stmt->execute([$channelUuid, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Canal no encontrado o sin permisos.");
        if (!in_array($row['role'], ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        // Verificar que no sea el último canal (opcional, pero recomendable)
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM community_channels WHERE community_id = ?");
        $stmtCount->execute([$row['community_id']]);
        if ($stmtCount->fetchColumn() <= 1) {
            throw new Exception("No puedes eliminar el único canal de la comunidad.");
        }

        $stmtDel = $pdo->prepare("DELETE FROM community_channels WHERE id = ?");
        if ($stmtDel->execute([$row['id']])) {
            echo json_encode(['success' => true, 'message' => "Canal eliminado."]);
        } else {
            throw new Exception("Error al eliminar.");
        }

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
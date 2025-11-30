<?php
// api/settings_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/settings_error.log';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/GoogleAuthenticator.php';
require_once '../includes/logic/i18n_server.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$data = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => translation('global.session_expired')]);
    exit;
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => translation('global.action_invalid')];

$serverConfig = getServerConfig($pdo);

function check_cooldown($pdo, $userId, $type, $daysLimit) {
    if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT changed_at FROM user_audit_logs 
                           WHERE user_id = ? AND change_type = ? 
                           ORDER BY changed_at DESC LIMIT 1");
    $stmt->execute([$userId, $type]);
    $lastChange = $stmt->fetchColumn();

    if ($lastChange) {
        $lastDate = new DateTime($lastChange);
        $now = new DateTime();
        $diff = $now->diff($lastDate);
        $daysPassed = $diff->days;

        if ($daysPassed < $daysLimit) {
            throw new Exception(translation('settings.cooldown_error') . " ($daysLimit " . translation('global.days') . ")");
        }
    }
}

function audit_log($pdo, $userId, $type, $oldValue, $newValue) {
    try {
        $ip = get_client_ip();
        $stmt = $pdo->prepare("INSERT INTO user_audit_logs (user_id, performed_by, change_type, old_value, new_value, changed_by_ip, changed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $userId, $type, $oldValue, $newValue, $ip]);
    } catch (Exception $e) {
        error_log("Error al crear audit_log: " . $e->getMessage());
    }
}

function parse_user_agent($userAgent) {
    $os = 'Desconocido';
    $browser = 'Desconocido';
    $icon = 'devices_other'; 

    if (preg_match('/windows|win32/i', $userAgent)) { $os = 'Windows'; $icon = 'desktop_windows'; }
    elseif (preg_match('/macintosh|mac os/i', $userAgent)) { $os = 'macOS'; $icon = 'laptop_mac'; }
    elseif (preg_match('/linux/i', $userAgent)) { $os = 'Linux'; $icon = 'terminal'; }
    elseif (preg_match('/android/i', $userAgent)) { $os = 'Android'; $icon = 'phone_android'; }
    elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) { $os = 'iOS'; $icon = 'phone_iphone'; }

    if (preg_match('/msie|trident/i', $userAgent)) $browser = 'Internet Explorer';
    elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/opera|opr/i', $userAgent)) $browser = 'Opera';

    return ['os' => $os, 'browser' => $browser, 'icon' => $icon];
}

try {

    if ($action === 'update_profile_picture') {
        check_cooldown($pdo, $userId, 'profile_picture', 1);
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(translation('settings.profile.error_format'));
        }
        $file = $_FILES['profile_picture'];
        
        $maxMB = (int)($serverConfig['profile_picture_max_size'] ?? 2);
        $maxSize = $maxMB * 1024 * 1024;
        
        if ($file['size'] > $maxSize) throw new Exception(translation('settings.profile.error_size', ['size' => $maxMB]));
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) throw new Exception(translation('settings.profile.error_format'));
        
        $uploadDir = __DIR__ . '/../public/assets/uploads/profile_pictures/custom/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        
        $newFileName = generate_uuid() . '.' . $extension;
        
        $destination = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/profile_pictures/custom/' . $newFileName;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception(translation('global.error_connection'));
        }
        
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldPic = $stmt->fetchColumn();
        if ($oldPic && file_exists(__DIR__ . '/../public/' . $oldPic)) {
            if (strpos($oldPic, 'custom/') !== false) {
                @unlink(__DIR__ . '/../public/' . $oldPic);
            }
        }
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_profile_picture'] = $dbPath;
            audit_log($pdo, $userId, 'profile_picture', $oldPic, $dbPath);
            $response = ['success' => true, 'message' => translation('settings.profile.success'), 'avatar_url' => '/ProjectAurora/' . $dbPath];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'remove_profile_picture') {
        $stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) throw new Exception(translation('admin.error.user_not_found'));
        
        $oldPic = $user['profile_picture'];
        $username = $user['username'];
        
        if ($oldPic && strpos($oldPic, '/default/') !== false) {
            echo json_encode([
                'success' => true, 
                'message' => translation('settings.profile.reset'), 
                'avatar_url' => '/ProjectAurora/' . $oldPic
            ]);
            exit;
        }
        
        $color = get_random_color();
        
        $uuid = generate_uuid();
        $apiUrl = "https://ui-avatars.com/api/?name={$username}&size=256&background={$color}&color=ffffff&bold=true&length=1";
        $newFileName = $uuid . '.png';
        $uploadDir = __DIR__ . '/../public/assets/uploads/profile_pictures/default/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $destPath = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/profile_pictures/default/' . $newFileName;
        
        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        
        if ($oldPic && file_exists(__DIR__ . '/../public/' . $oldPic)) {
            if (strpos($oldPic, 'custom/') !== false) {
                @unlink(__DIR__ . '/../public/' . $oldPic);
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_profile_picture'] = $dbPath;
            audit_log($pdo, $userId, 'profile_picture', $oldPic, $dbPath);
            $response = ['success' => true, 'message' => translation('settings.profile.reset'), 'avatar_url' => '/ProjectAurora/' . $dbPath];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'update_username') {
        $cooldownDays = (int)($serverConfig['username_cooldown'] ?? 30);
        check_cooldown($pdo, $userId, 'username', $cooldownDays);
        
        $newUsername = trim($data['username'] ?? '');
        
        $minLen = (int)($serverConfig['min_username_length'] ?? 6);
        $maxLen = (int)($serverConfig['max_username_length'] ?? 32);
        
        if (strlen($newUsername) < $minLen || strlen($newUsername) > $maxLen) {
            throw new Exception(translation('auth.errors.username_invalid', ['min' => $minLen, 'max' => $maxLen]));
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) throw new Exception(translation('auth.errors.username_invalid', ['min' => $minLen, 'max' => $maxLen]));
        
        $stmtGet = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtGet->execute([$userId]);
        $oldUsername = $stmtGet->fetchColumn();
        
        if ($oldUsername === $newUsername) throw new Exception(translation('settings.username.same'));
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->rowCount() > 0) throw new Exception(translation('settings.username.taken'));
        
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        if ($stmt->execute([$newUsername, $userId])) {
            audit_log($pdo, $userId, 'username', $oldUsername, $newUsername);
            $response = ['success' => true, 'message' => translation('global.save_status'), 'new_username' => $newUsername];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'update_email') {
        $cooldownDays = (int)($serverConfig['email_cooldown'] ?? 12);
        check_cooldown($pdo, $userId, 'email', $cooldownDays);
        
        $newEmail = strtolower(trim($data['email'] ?? ''));
        
        $maxLen = (int)($serverConfig['max_email_length'] ?? 255);
        if (strlen($newEmail) > $maxLen) throw new Exception(translation('auth.errors.email_long', ['max' => $maxLen]));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) throw new Exception(translation('auth.errors.email_invalid_domain'));
        
        if (!is_allowed_domain($newEmail, $pdo)) throw new Exception(translation('auth.errors.email_domain_restricted'));
        
        $stmtGet = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtGet->execute([$userId]);
        $oldEmail = $stmtGet->fetchColumn();
        
        if ($oldEmail === $newEmail) throw new Exception(translation('settings.email.same'));
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->rowCount() > 0) throw new Exception(translation('auth.errors.email_exists'));
        
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt->execute([$newEmail, $userId])) {
            audit_log($pdo, $userId, 'email', $oldEmail, $newEmail);
            $response = ['success' => true, 'message' => translation('global.save_status'), 'new_email' => $newEmail];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'verify_current_password') {
        $currentPassword = $data['password'] ?? '';
        if (empty($currentPassword)) throw new Exception(translation('auth.errors.all_required'));
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($currentPassword, $hash)) {
            $response = ['success' => true, 'message' => translation('settings.password.verified')];
        } else {
            throw new Exception(translation('settings.password.invalid_current'));
        }

    } elseif ($action === 'update_password') {
        check_cooldown($pdo, $userId, 'password', 1);
        $newPassword = $data['new_password'] ?? '';
        $logoutOthers = isset($data['logout_others']) ? (bool)$data['logout_others'] : false;
        
        $minPass = (int)($serverConfig['min_password_length'] ?? 8);
        $maxPass = (int)($serverConfig['max_password_length'] ?? 72);
        
        if (strlen($newPassword) < $minPass) {
            throw new Exception(translation('auth.errors.password_short', ['min' => $minPass]));
        }
        if (strlen($newPassword) > $maxPass) {
            throw new Exception("La contraseña es demasiado larga (Máximo $maxPass caracteres).");
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldHash = $stmt->fetchColumn();
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$newHash, $userId])) {
            audit_log($pdo, $userId, 'password', $oldHash, $newHash);
            if ($logoutOthers) {
                $currentSessionId = session_id();
                $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?")
                    ->execute([$userId, $currentSessionId]);
                send_live_notification($userId, 'force_logout_others', ['exclude_session_id' => $currentSessionId]);
            }
            $response = ['success' => true, 'message' => translation('settings.change_password.success_msg')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'update_usage') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) throw new Exception(translation('auth.errors.too_many_attempts'));
        logSecurityAction($pdo, $userId, 'pref_update_limit');
        $usage = $data['usage'] ?? 'personal';
        $allowed = ['personal', 'student', 'teacher', 'small_business', 'large_business'];
        if (!in_array($usage, $allowed)) throw new Exception(translation('global.action_invalid'));
        $sql = "INSERT INTO user_preferences (user_id, usage_intent) VALUES (?, ?) ON DUPLICATE KEY UPDATE usage_intent = VALUES(usage_intent)";
        if ($pdo->prepare($sql)->execute([$userId, $usage])) {
            $response = ['success' => true, 'message' => translation('global.save_status')];
        } else throw new Exception(translation('global.error_connection'));

    } elseif ($action === 'update_language') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) throw new Exception(translation('auth.errors.too_many_attempts'));
        logSecurityAction($pdo, $userId, 'pref_update_limit');
        $lang = $data['language'] ?? 'en-us';
        $allowed = ['es-latam', 'es-mx', 'en-us', 'en-gb'];
        if (!in_array($lang, $allowed)) throw new Exception(translation('global.action_invalid'));
        $sql = "INSERT INTO user_preferences (user_id, language) VALUES (?, ?) ON DUPLICATE KEY UPDATE language = VALUES(language)";
        if ($pdo->prepare($sql)->execute([$userId, $lang])) {
            $_SESSION['user_lang'] = $lang;
            $response = ['success' => true, 'message' => translation('global.save_status')];
        } else throw new Exception(translation('global.error_connection'));

    } elseif ($action === 'update_theme') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) throw new Exception(translation('auth.errors.too_many_attempts'));
        logSecurityAction($pdo, $userId, 'pref_update_limit');
        $theme = $data['theme'] ?? 'system';
        $allowed = ['system', 'light', 'dark'];
        if (!in_array($theme, $allowed)) throw new Exception(translation('global.action_invalid'));
        $sql = "INSERT INTO user_preferences (user_id, theme) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme = VALUES(theme)";
        if ($pdo->prepare($sql)->execute([$userId, $theme])) {
            $_SESSION['user_theme'] = $theme;
            $response = ['success' => true, 'message' => translation('global.save_status')];
        } else throw new Exception(translation('global.error_connection'));

    // [NUEVO] Actualizar Privacidad de Mensajes
    } elseif ($action === 'update_privacy') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) throw new Exception(translation('auth.errors.too_many_attempts'));
        logSecurityAction($pdo, $userId, 'pref_update_limit');
        
        $privacy = $data['privacy'] ?? 'friends';
        $allowed = ['everyone', 'friends', 'nobody'];
        
        if (!in_array($privacy, $allowed)) throw new Exception(translation('global.action_invalid'));
        
        // Obtenemos el valor anterior para el log
        $stmtOld = $pdo->prepare("SELECT message_privacy FROM user_preferences WHERE user_id = ?");
        $stmtOld->execute([$userId]);
        $oldVal = $stmtOld->fetchColumn() ?: 'friends'; // Default si es null

        $sql = "INSERT INTO user_preferences (user_id, message_privacy) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE message_privacy = VALUES(message_privacy)";
                
        if ($pdo->prepare($sql)->execute([$userId, $privacy])) {
            // Registrar cambio en auditoría
            if ($oldVal !== $privacy) {
                audit_log($pdo, $userId, 'privacy_update', $oldVal, $privacy);
            }
            $response = ['success' => true, 'message' => translation('global.save_status')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'update_boolean_preference') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) throw new Exception(translation('auth.errors.too_many_attempts'));
        logSecurityAction($pdo, $userId, 'pref_update_limit');
        $field = $data['field'] ?? '';
        $value = isset($data['value']) && $data['value'] ? 1 : 0;
        $allowedFields = ['open_links_in_new_tab', 'extended_message_time'];
        if (!in_array($field, $allowedFields)) throw new Exception(translation('global.action_invalid'));
        $sql = "INSERT INTO user_preferences (user_id, $field) VALUES (?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)";
        if ($pdo->prepare($sql)->execute([$userId, $value])) {
            if ($field === 'extended_message_time') $_SESSION['user_extended_msg'] = $value;
            if ($field === 'open_links_in_new_tab') $_SESSION['user_new_tab'] = $value;
            $response = ['success' => true, 'message' => translation('global.save_status')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'generate_2fa_secret') {
        $ga = new PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret(); 
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn();
        $response = ['success' => true, 'secret' => $secret, 'username' => $username];

    } elseif ($action === 'enable_2fa_confirm') {
        $secret = $data['secret'] ?? '';
        $code = $data['code'] ?? '';
        if (empty($secret) || empty($code)) throw new Exception(translation('auth.errors.all_required'));
        $ga = new PHPGangsta_GoogleAuthenticator();
        $checkResult = $ga->verifyCode($secret, $code, 1);
        if ($checkResult) {
            $backupCodes = [];
            for ($i = 0; $i < 5; $i++) { $backupCodes[] = rand(1000, 9999) . '-' . rand(1000, 9999); }
            $backupCodesJson = json_encode($backupCodes);
            $stmt = $pdo->prepare("UPDATE users SET is_2fa_enabled = 1, two_factor_secret = ?, backup_codes = ? WHERE id = ?");
            if ($stmt->execute([$secret, $backupCodesJson, $userId])) {
                $response = ['success' => true, 'message' => translation('settings.2fa.activated'), 'backup_codes' => $backupCodes];
            } else {
                throw new Exception(translation('global.error_connection'));
            }
        } else {
            throw new Exception(translation('settings.2fa.invalid_code'));
        }

    } elseif ($action === 'disable_2fa') {
        $password = $data['password'] ?? '';
        if (empty($password)) throw new Exception(translation('auth.errors.all_required'));

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($password, $hash)) {
            $stmtUpd = $pdo->prepare("UPDATE users SET is_2fa_enabled = 0, two_factor_secret = NULL, backup_codes = NULL WHERE id = ?");
            if ($stmtUpd->execute([$userId])) {
                audit_log($pdo, $userId, '2fa_disabled', 'enabled', 'disabled');
                $response = ['success' => true, 'message' => translation('global.save_status')];
            } else {
                throw new Exception(translation('global.error_connection'));
            }
        } else {
            throw new Exception(translation('settings.password.invalid_current'));
        }

    } elseif ($action === 'get_sessions') {
        $stmt = $pdo->prepare("SELECT id, session_id, ip_address, user_agent, last_activity, created_at FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->execute([$userId]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $currentSessionId = session_id();
        $formattedSessions = [];

        foreach ($sessions as $sess) {
            $info = parse_user_agent($sess['user_agent']);
            $isCurrent = ($sess['session_id'] === $currentSessionId);
            
            $formattedSessions[] = [
                'id' => $sess['id'], 
                'ip' => $sess['ip_address'],
                'os' => $info['os'],
                'browser' => $info['browser'],
                'icon' => $info['icon'],
                'is_current' => $isCurrent,
                'last_active' => $sess['last_activity']
            ];
        }

        $response = ['success' => true, 'sessions' => $formattedSessions];

    } elseif ($action === 'revoke_session') {
        $sessionIdDb = $data['session_id_db'] ?? 0;
        if (!$sessionIdDb) throw new Exception(translation('global.action_invalid'));
        $stmtGet = $pdo->prepare("SELECT session_id FROM user_sessions WHERE id = ? AND user_id = ?");
        $stmtGet->execute([$sessionIdDb, $userId]);
        $targetSessionId = $stmtGet->fetchColumn();
        if ($targetSessionId) {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE id = ?");
            if ($stmt->execute([$sessionIdDb])) {
                send_live_notification($userId, 'force_logout', ['target_session_id' => $targetSessionId]);
                $response = ['success' => true, 'message' => translation('settings.sessions.session_revoked')];
            } else {
                throw new Exception(translation('global.error_connection'));
            }
        } else {
            throw new Exception(translation('global.action_invalid'));
        }

    } elseif ($action === 'revoke_all_sessions') {
        $currentSessionId = session_id();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
        if ($stmt->execute([$userId, $currentSessionId])) {
            send_live_notification($userId, 'force_logout_others', ['exclude_session_id' => $currentSessionId]);
            $response = ['success' => true, 'message' => translation('settings.sessions.all_revoked')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'delete_account') {
        $password = $data['password'] ?? '';
        if (empty($password)) throw new Exception(translation('auth.errors.all_required'));

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($password, $hash)) {
            $stmtUpdate = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE id = ?");
            
            if ($stmtUpdate->execute([$userId])) {
                $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);

                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                
                send_live_notification($userId, 'force_logout_others', ['exclude_session_id' => 'none']);

                $response = ['success' => true, 'message' => translation('admin.success.account_deleted')];
            } else {
                throw new Exception(translation('global.error_connection'));
            }
        } else {
            throw new Exception(translation('settings.password.invalid_current'));
        }
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
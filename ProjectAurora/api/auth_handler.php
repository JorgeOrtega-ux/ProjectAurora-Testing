<?php
// api/auth_handler.php

// --- CONFIGURACIÓN DE LOGS ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_httponly', 1);
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/GoogleAuthenticator.php';
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

try {
    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
    $pdo->exec("SET time_zone='$offset';");
} catch (Exception $e) {
    logger("Warning: No se pudo sincronizar time_zone SQL: " . $e->getMessage());
}

// --- FUNCIONES AUXILIARES ---

function generate_verification_code() {
    return strtoupper(bin2hex(random_bytes(6)));
}

function set_user_session($pdo, $user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_profile_picture'] = $user['profile_picture'];
    $_SESSION['user_role'] = $user['role'];

    $sessionId = session_id();
    $ip = get_client_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")->execute([$sessionId]);

    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $sessionId, $ip, $userAgent]);
}

function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        return substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
    return $email;
}

$response = ['success' => false, 'message' => translation('global.action_invalid')];

try {
    $serverConfig = getServerConfig($pdo);

    if ($action === 'register_step_1') {
        if ((int)$serverConfig['allow_registrations'] === 0) {
            throw new Exception(translation('auth.register.closed_error') ?? 'El registro de nuevos usuarios está cerrado temporalmente.');
        }

        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';
        
        $minPass = (int)($serverConfig['min_password_length'] ?? 8);
        $maxPass = (int)($serverConfig['max_password_length'] ?? 72);
        $maxEmail = (int)($serverConfig['max_email_length'] ?? 255);

        if (empty($email) || empty($password)) throw new Exception(translation('auth.errors.all_required'));
        if (strlen($email) < 4) throw new Exception(translation('auth.errors.email_short'));
        if (strlen($email) > $maxEmail) throw new Exception(translation('auth.errors.email_long', ['max' => $maxEmail]));
        
        if (!is_allowed_domain($email, $pdo)) throw new Exception(translation('auth.errors.email_domain_restricted'));
        
        if (strlen($password) < $minPass) {
            throw new Exception(translation('auth.errors.password_short', ['min' => $minPass]));
        }
        if (strlen($password) > $maxPass) {
            throw new Exception("La contraseña es demasiado larga (Máximo $maxPass caracteres).");
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) throw new Exception(translation('auth.errors.email_exists'));
        
        if (!isset($_SESSION['temp_register'])) $_SESSION['temp_register'] = [];
        $_SESSION['temp_register']['email'] = $email;
        $_SESSION['temp_register']['password'] = $password;
        $response = ['success' => true, 'message' => 'Paso 1 OK'];

    } elseif ($action === 'register_step_2') {
        if ((int)$serverConfig['allow_registrations'] === 0) throw new Exception(translation('auth.register.closed_error'));

        $username = trim($data['username'] ?? '');
        $email = $_SESSION['temp_register']['email'] ?? '';
        $rawPassword = $_SESSION['temp_register']['password'] ?? '';
        
        if (empty($email) || empty($rawPassword)) throw new Exception(translation('global.session_expired'));
        
        $minUser = (int)($serverConfig['min_username_length'] ?? 6);
        $maxUser = (int)($serverConfig['max_username_length'] ?? 32);

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username) || strlen($username) < $minUser || strlen($username) > $maxUser) {
            throw new Exception(translation('auth.errors.username_invalid', ['min' => $minUser, 'max' => $maxUser]));
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception(translation('settings.username.taken'));
        
        $cooldownSecs = (int)($serverConfig['code_resend_cooldown'] ?? 60);
        $stmtLastCode = $pdo->prepare("SELECT created_at FROM verification_codes WHERE identifier = ? AND code_type = 'registration' ORDER BY created_at DESC LIMIT 1");
        $stmtLastCode->execute([$email]);
        $lastCreated = $stmtLastCode->fetchColumn();
        
        if ($lastCreated) {
            $diff = time() - strtotime($lastCreated);
            if ($diff < $cooldownSecs) {
                $wait = $cooldownSecs - $diff;
                echo json_encode(['success' => false, 'message' => translation('auth.errors.too_many_attempts'), 'remaining_time' => $wait]);
                exit;
            }
        }

        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $payload = json_encode(['username' => $username, 'password_hash' => $passwordHash]);
        $code = generate_verification_code();
        $codeHash = hash('sha256', $code);
        
        $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'")->execute([$email]);
        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at, created_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $codeHash, $payload]);
        
        unset($_SESSION['temp_register']['password']);
        $_SESSION['temp_register']['username'] = $username;
        logger("[REGISTRO] Code para $email: $code"); 
        $response = ['success' => true, 'message' => translation('auth.code_sent')];

    } elseif ($action === 'resend_code') {
        $type = $data['type'] ?? '';
        $email = '';
        
        if ($type === 'register') $email = $_SESSION['temp_register']['email'] ?? '';
        elseif ($type === 'login') $email = $_SESSION['temp_login_2fa']['email'] ?? '';
        
        if (empty($email)) throw new Exception(translation('global.session_expired'));

        $cooldownSecs = (int)($serverConfig['code_resend_cooldown'] ?? 60);

        if ($type === 'register') {
             $stmtLast = $pdo->prepare("SELECT created_at, payload FROM verification_codes WHERE identifier = ? AND code_type = 'registration' ORDER BY created_at DESC LIMIT 1");
             $stmtLast->execute([$email]);
             $row = $stmtLast->fetch(PDO::FETCH_ASSOC);
             
             if ($row) {
                 $diff = time() - strtotime($row['created_at']);
                 if ($diff < $cooldownSecs) {
                     echo json_encode(['success' => false, 'message' => translation('auth.errors.too_many_attempts'), 'remaining_time' => ($cooldownSecs - $diff)]);
                     exit;
                 }
                 $payload = $row['payload']; 
             } else {
                 throw new Exception(translation('global.error_connection'));
             }

             $code = generate_verification_code();
             $codeHash = hash('sha256', $code);
             $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'")->execute([$email]);
             $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at, created_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())")->execute([$email, $codeHash, $payload]);
             
             logger("[RESEND REGISTRO] Code para $email: $code");
             $response = ['success' => true, 'message' => translation('auth.code_sent')];
        } else {
             $response = ['success' => false, 'message' => 'Operation not supported'];
        }

    } elseif ($action === 'register_final') {
        if ((int)$serverConfig['allow_registrations'] === 0) throw new Exception(translation('auth.register.closed_error'));

        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';
        if (empty($email)) throw new Exception(translation('global.session_expired'));
        
        $inputHash = hash('sha256', $inputCode);
        $sql = "SELECT id, payload FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'registration' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $inputHash]);
        $row = $stmt->fetch();
        
        if (!$row) throw new Exception(translation('auth.errors.invalid_code'));
        
        $payloadData = json_decode($row['payload'], true);
        $finalUsername = $payloadData['username'];
        $finalPassHash = $payloadData['password_hash'];
        
        $uuid = generate_uuid();
        
        // [CORREGIDO] Se usa get_random_color() centralizada
        $selectedColor = get_random_color();
        
        $apiUrl = "https://ui-avatars.com/api/?name={$finalUsername}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        
        $fileName = $uuid . '.png';
        $uploadDir = __DIR__ . '/../public/assets/uploads/profile_pictures/default/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $destPath = $uploadDir . $fileName;
        $dbPath = 'assets/uploads/profile_pictures/default/' . $fileName;
        
        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        else $dbPath = null;
        
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, profile_picture, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $finalUsername, $finalPassHash, $dbPath])) {
            $newUserId = $pdo->lastInsertId();
            $detectedLang = detect_browser_language();
            $prefsSql = "INSERT INTO user_preferences (user_id, usage_intent, language) VALUES (?, 'personal', ?)";
            $prefsStmt = $pdo->prepare($prefsSql);
            $prefsStmt->execute([$newUserId, $detectedLang]);
            
            $newUser = ['id' => $newUserId, 'uuid' => $uuid, 'email' => $email, 'profile_picture' => $dbPath, 'role' => 'user'];
            session_regenerate_id(true);
            set_user_session($pdo, $newUser); 

            $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);
            unset($_SESSION['temp_register']);
            $response = ['success' => true, 'message' => translation('auth.welcome')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'login') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';
        
        if (checkLockStatus($pdo, $email, 'login_fail')) {
            $mins = $serverConfig['lockout_time_minutes'] ?? 5;
            throw new Exception(translation('auth.errors.too_many_attempts') . " " . $mins . " mins.");
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            
            $role = $user['role'];
            $isVip = in_array($role, ['founder', 'administrator', 'moderator']);
            $isMaintenance = (int)$serverConfig['maintenance_mode'] === 1;

            if ($user['account_status'] === 'suspended' && !empty($user['suspension_end_date'])) {
                $endDate = new DateTime($user['suspension_end_date']);
                $now = new DateTime();
                if ($now > $endDate) {
                    $stmtReactivate = $pdo->prepare("UPDATE users SET account_status = 'active', suspension_reason = NULL, suspension_end_date = NULL WHERE id = ?");
                    $stmtReactivate->execute([$user['id']]);
                    $user['account_status'] = 'active';
                }
            }

            if (isset($user['account_status']) && $user['account_status'] !== 'active') {
                $responseData = [
                    'success' => false, 
                    'is_account_issue' => true, 
                    'status_type' => $user['account_status']
                ];
                if ($user['account_status'] === 'suspended') {
                    $responseData['reason'] = $user['suspension_reason'];
                    if ($user['suspension_end_date']) {
                        $end = new DateTime($user['suspension_end_date']);
                        $responseData['until'] = $end->format('d/m/Y');
                    }
                }
                echo json_encode($responseData);
                exit;
            }

            if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
                if (!isset($_SESSION['temp_login_2fa'])) $_SESSION['temp_login_2fa'] = [];
                $_SESSION['temp_login_2fa']['user_id'] = $user['id'];
                $_SESSION['temp_login_2fa']['email'] = $user['email'];
                $response = ['success' => true, 'require_2fa' => true, 'message' => translation('auth.2fa_required'), 'masked_email' => 'tu aplicación de autenticación'];
            } else {
                clearFailedAttempts($pdo, $email);
                session_regenerate_id(true);
                set_user_session($pdo, $user); 
                
                $shouldRedirectMaintenance = ($isMaintenance && !$isVip);

                $response = [
                    'success' => true, 
                    'message' => translation('auth.login_success'),
                    'redirect_maintenance' => $shouldRedirectMaintenance
                ];
            }
        } else {
            logFailedAttempt($pdo, $email, 'login_fail');
            throw new Exception(translation('auth.errors.invalid_credentials'));
        }

    } elseif ($action === 'login_2fa_verify') {
        $inputCode = trim($data['code'] ?? '');
        $cleanCode = str_replace(['-', ' '], '', $inputCode);
        
        if (empty($_SESSION['temp_login_2fa']['user_id'])) throw new Exception(translation('global.session_expired'));
        $userId = $_SESSION['temp_login_2fa']['user_id'];
        $email = $_SESSION['temp_login_2fa']['email'];
        
        if (checkLockStatus($pdo, $email, 'login_2fa_fail')) throw new Exception(translation('auth.errors.too_many_attempts'));
        
        $stmt = $pdo->prepare("SELECT id, uuid, username, email, profile_picture, role, two_factor_secret, backup_codes FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) throw new Exception(translation('admin.error.user_not_found'));
        
        $role = $user['role'];
        $isVip = in_array($role, ['founder', 'administrator', 'moderator']);
        $isMaintenance = (int)$serverConfig['maintenance_mode'] === 1;
        
        $secret = $user['two_factor_secret'];
        $backupCodes = json_decode($user['backup_codes'] ?? '[]', true);
        if (!is_array($backupCodes)) $backupCodes = [];
        
        $isAuthenticated = false;
        $usedBackupCode = false;
        
        if (strlen($cleanCode) === 6) {
            $ga = new PHPGangsta_GoogleAuthenticator();
            if ($ga->verifyCode($secret, $cleanCode, 1)) $isAuthenticated = true;
        }
        
        if (!$isAuthenticated) {
            $index = array_search($inputCode, $backupCodes); 
            if ($index !== false) {
                $isAuthenticated = true;
                $usedBackupCode = true;
                unset($backupCodes[$index]);
                $backupCodes = array_values($backupCodes);
            }
        }
        
        if ($isAuthenticated) {
            if ($usedBackupCode) {
                $newBackups = json_encode($backupCodes);
                $pdo->prepare("UPDATE users SET backup_codes = ? WHERE id = ?")->execute([$newBackups, $userId]);
            }
            clearFailedAttempts($pdo, $email);
            session_regenerate_id(true);
            unset($user['password']);
            unset($user['two_factor_secret']);
            unset($user['backup_codes']);
            set_user_session($pdo, $user); 
            unset($_SESSION['temp_login_2fa']);
            
            $shouldRedirectMaintenance = ($isMaintenance && !$isVip);

            $response = [
                'success' => true, 
                'message' => translation('auth.2fa.success_alert'),
                'redirect_maintenance' => $shouldRedirectMaintenance
            ];
        } else {
            logFailedAttempt($pdo, $email, 'login_2fa_fail');
            throw new Exception(translation('auth.2fa.invalid_code'));
        }

    } elseif ($action === 'logout') {
        $sessionId = session_id();
        $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")->execute([$sessionId]);

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        $response = ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    
    } elseif ($action === 'recovery_step_1') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        
        if (empty($email) || !is_allowed_domain($email, $pdo)) throw new Exception(translation('auth.errors.email_domain_restricted'));
        
        if (checkLockStatus($pdo, $email, 'recovery_fail')) {
            $mins = $serverConfig['lockout_time_minutes'] ?? 5;
            throw new Exception(translation('auth.errors.too_many_attempts') . " " . $mins . " m.");
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $token = bin2hex(random_bytes(32)); 
            $tokenHash = hash('sha256', $token);
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
            $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'recovery', ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
            $stmt->execute([$email, $tokenHash]);
            $response = ['success' => true, 'message' => translation('auth.recovery.link_sent_alert')];
        } else {
            logFailedAttempt($pdo, $email, 'recovery_fail');
            throw new Exception(translation('auth.errors.invalid_credentials'));
        }

    } elseif ($action === 'recovery_final') {
        $token = trim($data['token'] ?? '');
        $newPass = $data['password'] ?? '';
        $confirmPass = $data['password_confirm'] ?? '';
        
        if (empty($token) || empty($newPass)) throw new Exception(translation('auth.errors.all_required'));
        if ($newPass !== $confirmPass) throw new Exception(translation('auth.errors.pass_mismatch'));
        
        $minPass = (int)($serverConfig['min_password_length'] ?? 8);
        $maxPass = (int)($serverConfig['max_password_length'] ?? 72);
        
        if (strlen($newPass) < $minPass) {
            throw new Exception(translation('auth.errors.password_short', ['min' => $minPass]));
        }
        if (strlen($newPass) > $maxPass) {
            throw new Exception("La contraseña es demasiado larga (Máximo $maxPass caracteres).");
        }
        
        $tokenHash = hash('sha256', $token);
        $sql = "SELECT identifier FROM verification_codes WHERE code = ? AND code_type = 'recovery' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        
        if (!$row) throw new Exception(translation('auth.recovery.invalid_link_title'));
        
        $email = $row['identifier'];
        $stmtUser = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmtUser->execute([$email]);
        $userData = $stmtUser->fetch();
        $oldHash = $userData['password'] ?? 'UNKNOWN';
        $userIdForLog = $userData['id'] ?? null;
        
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($upd->execute([$newHash, $email])) {
            if ($userIdForLog) {
                $ip = get_client_ip();
                $stmtLog = $pdo->prepare("INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip, changed_at) VALUES (?, 'password', ?, ?, ?, NOW())");
                $stmtLog->execute([$userIdForLog, $oldHash, $newHash, $ip]);
            }
            $pdo->prepare("DELETE FROM verification_codes WHERE code = ?")->execute([$tokenHash]);
            clearFailedAttempts($pdo, $email);
            $response = ['success' => true, 'message' => translation('auth.recovery.pass_updated')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }
    }

} catch (Exception $e) {
    $realErrorMessage = $e->getMessage();
    $response['message'] = $realErrorMessage;
}

echo json_encode($response);
exit;
?>
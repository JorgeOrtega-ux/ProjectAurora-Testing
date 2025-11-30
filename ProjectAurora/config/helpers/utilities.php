<?php
// config/helpers/utilities.php

date_default_timezone_set('America/Matamoros');

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function checkLockStatus($pdo, $identifier, $specificAction = null) {
    $config = getServerConfig($pdo);
    $limit = (int)($config['max_login_attempts'] ?? 5);
    $minutes = (int)($config['lockout_time_minutes'] ?? 5);
    
    $ip = get_client_ip();

    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE (user_identifier = ? OR ip_address = ?) 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    $params = [$identifier, $ip];

    if ($specificAction !== null) {
        $sql .= " AND action_type = ?";
        $params[] = $specificAction;
    }
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

/**
 * [NUEVO] Verifica si el usuario está haciendo spam.
 * Consulta la configuración de la BD para obtener el límite y la ventana de tiempo.
 * Retorna true si debe ser bloqueado (spam detectado).
 */
function checkChatSpam($pdo, $userId) {
    // 1. Obtener configuración dinámica de la BD
    $config = getServerConfig($pdo);
    $limit = (int)($config['chat_msg_limit'] ?? 5);      // Default: 5 mensajes
    $seconds = (int)($config['chat_time_window'] ?? 10); // Default: 10 segundos

    // 2. Contar mensajes enviados en comunidades + privados dentro de la ventana de tiempo
    // Usamos los segundos configurados en el INTERVAL
    $sql = "SELECT 
            (SELECT COUNT(*) FROM community_messages WHERE user_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)) +
            (SELECT COUNT(*) FROM private_messages WHERE sender_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)) 
            as total_recent";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $seconds, $userId, $seconds]);
    $count = (int)$stmt->fetchColumn();

    // 3. Si supera el límite, es spam
    if ($count >= $limit) {
        return true; 
    }
    return false;
}

function logFailedAttempt($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier, $actionType, $ip]);
}

function clearFailedAttempts($pdo, $identifier) {
    $sql = "DELETE FROM security_logs WHERE user_identifier = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier]);
}

function checkActionRateLimit($pdo, $identifier, $actionType, $limit, $minutes) {
    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE user_identifier = ? 
            AND action_type = ? 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(string)$identifier, $actionType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

function logSecurityAction($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(string)$identifier, $actionType, $ip]);
}

function generate_ws_auth_token($pdo, $userId, $sessionId) {
    $stmtCleanup = $pdo->prepare("DELETE FROM ws_auth_tokens WHERE expires_at < NOW()");
    $stmtCleanup->execute();

    $rawToken = bin2hex(random_bytes(32)); 
    $tokenHash = hash('sha256', $rawToken);
    
    $stmt = $pdo->prepare("INSERT INTO ws_auth_tokens (user_id, session_id, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))");
    $stmt->execute([$userId, $sessionId, $tokenHash]);

    return $rawToken;
}

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function send_live_notification($targetUserId, $type, $data = []) {
    $host = '127.0.0.1';
    $port = 8081; 
    $timeout = 2; 

    $payload = json_encode([
        'target_id' => (string)$targetUserId, 
        'type' => $type, 
        'payload' => $data
    ]);

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout); 
    if ($fp) {
        stream_set_timeout($fp, $timeout);
        fwrite($fp, $payload);
        fclose($fp);
        return true;
    }
    return false; 
}

function detect_browser_language() {
    $availableLanguages = ['es-latam', 'es-mx', 'en-us', 'en-gb'];
    $familyFallbacks = ['es' => 'es-latam', 'en' => 'en-us'];
    $default = 'en-us';

    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return $default;

    $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($langs as $lang) {
        $lang = trim(explode(';', $lang)[0]); 
        $lang = strtolower($lang); 

        if (in_array($lang, $availableLanguages)) return $lang;

        $langPrefix = substr($lang, 0, 2);
        if (array_key_exists($langPrefix, $familyFallbacks)) return $familyFallbacks[$langPrefix];
    }
    return $default;
}

function getServerConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM server_config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // [ACTUALIZADO] Valores por defecto incluyendo las nuevas variables de anti-spam
        if (!$config) {
            return [
                'maintenance_mode' => 0, 
                'allow_registrations' => 1,
                'min_password_length' => 8, 
                'max_password_length' => 72,
                'min_username_length' => 6, 
                'max_username_length' => 32,
                'max_email_length' => 255, 
                'max_login_attempts' => 5,
                'lockout_time_minutes' => 5, 
                'code_resend_cooldown' => 60,
                'username_cooldown' => 30, 
                'email_cooldown' => 12, 
                'profile_picture_max_size' => 2,
                'allowed_email_domains' => NULL,
                'chat_msg_limit' => 5, // Límite por defecto
                'chat_time_window' => 10 // Segundos por defecto
            ];
        }
        return $config;
    } catch (Exception $e) {
        return ['maintenance_mode' => 0, 'allow_registrations' => 1];
    }
}

function countActiveSessions($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_sessions");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function is_allowed_domain($email, $pdo) {
    $config = getServerConfig($pdo);
    $allowedJson = $config['allowed_email_domains'] ?? '[]';
    $allowedDomains = json_decode($allowedJson, true);

    if (empty($allowedDomains) || !is_array($allowedDomains)) {
        return true;
    }

    $parts = explode('@', $email);
    $domain = array_pop($parts); 

    return in_array(strtolower($domain), array_map('strtolower', $allowedDomains));
}

function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_random_color() {
    $colors = ['C84F4F', '4F7AC8', '8C4FC8', 'C87A4F', '4FC8C8'];
    return $colors[array_rand($colors)];
}
?>
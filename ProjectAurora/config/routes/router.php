<?php
// config/routes/router.php

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

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../helpers/utilities.php';
require_once __DIR__ . '/../../includes/logic/i18n_server.php';

$langRouter = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($langRouter);

$basePath = '/ProjectAurora/';

$serverConfig = getServerConfig($pdo);
$isMaintenanceMode = (int)$serverConfig['maintenance_mode'] === 1;

// --- LOGICA DE SESIÓN Y SEGURIDAD (Sin cambios) ---
if (isset($_SESSION['user_id'])) {
    $currentSessionId = session_id();
    $stmtCheck = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $stmtCheck->execute([$currentSessionId, $_SESSION['user_id']]);

    if ($stmtCheck->rowCount() === 0) {
        $isSuspended = false;
        $statusRedirect = '';
        try {
            $stmtStatus = $pdo->prepare("SELECT account_status, suspension_reason, suspension_end_date FROM users WHERE id = ?");
            $stmtStatus->execute([$_SESSION['user_id']]);
            $userStatusData = $stmtStatus->fetch();
            if ($userStatusData && in_array($userStatusData['account_status'], ['suspended', 'deleted'])) {
                $isSuspended = true;
                $statusRedirect = "status-page?status=" . $userStatusData['account_status'];
                if ($userStatusData['account_status'] === 'suspended') {
                    if (!empty($userStatusData['suspension_reason'])) $statusRedirect .= "&reason=" . urlencode($userStatusData['suspension_reason']);
                    if (!empty($userStatusData['suspension_end_date'])) {
                        $endDate = new DateTime($userStatusData['suspension_end_date']);
                        $statusRedirect .= "&until=" . urlencode($endDate->format('d/m/Y'));
                    }
                }
            }
        } catch (Exception $e) {
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();

        if ($isSuspended) header("Location: " . $basePath . $statusRedirect);
        else header("Location: " . $basePath . "login");
        exit;
    } else {
        $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?")->execute([$currentSessionId]);
    }
}

$freshUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT u.role, u.profile_picture, u.account_status, p.language, p.theme, p.extended_message_time, p.open_links_in_new_tab FROM users u LEFT JOIN user_preferences p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['user_role'] = $freshUser['role'] ?? 'user';
            $_SESSION['user_profile_picture'] = $freshUser['profile_picture'];
            $_SESSION['user_lang'] = $freshUser['language'] ?? 'es-latam';
            $_SESSION['user_theme'] = $freshUser['theme'] ?? 'system';
            $_SESSION['user_extended_msg'] = $freshUser['extended_message_time'] ?? 0;
            $_SESSION['user_new_tab'] = $freshUser['open_links_in_new_tab'] ?? 1;

            if ($freshUser['account_status'] !== 'active') {
                $_SESSION = [];
                session_destroy();
                header("Location: " . $basePath . "status-page?status=" . $freshUser['account_status']);
                exit;
            }
        } else {
            $_SESSION = [];
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
    }
}

// --- ENRUTAMIENTO ---
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, $basePath) === 0) $requestUri = substr($requestUri, strlen($basePath));
$requestUri = strtok($requestUri, '?');
$requestUri = rtrim($requestUri, '/');

if (strpos($requestUri, 'api/') === 0) {
    $apiFilePath = __DIR__ . '/../../' . $requestUri;
    if (file_exists($apiFilePath)) {
        require_once $apiFilePath;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API not found']);
        exit;
    }
}

$DEFAULT_SECTION = 'main';
$allowedSections = [
    'main',
    'login',
    'register',
    'explorer',
    'join-community',
    'register/additional-data',
    'register/verification-account',
    'forgot-password',
    'reset-password',
    'status-page',
    'login/verification-additional',
    'search',
    'settings',
    'settings/your-profile',
    'settings/login-security',
    'settings/accessibility',
    'settings/change-password',
    'settings/2fa-setup',
    'settings/sessions',
    'settings/delete-account',
    'admin',
    'admin/dashboard',
    'admin/users',
    'admin/user-status',
    'admin/user-manage',
    'admin/user-history',
    'admin/user-role',
    'admin/communities', 'admin/community-edit',
    'admin/backups',
    'admin/server'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;

// [MODIFICADO] Variables de contexto para el Chat (Comunidad o Privado)
$activeContextUuid = null;
$activeContextType = null; // 'community' o 'private'

// 1. Detectar Comunidad (/c/uuid)
if (preg_match('/^c\/([a-zA-Z0-9-]+)$/', $requestUri, $matches)) {
    $CURRENT_SECTION = 'main';
    $activeContextType = 'community';
    $activeContextUuid = $matches[1];
}

// 2. Detectar Chat Directo (/dm/uuid)
if (preg_match('/^dm\/([a-zA-Z0-9-]+)$/', $requestUri, $matches)) {
    $CURRENT_SECTION = 'main';
    $activeContextType = 'private';
    $activeContextUuid = $matches[1];
}

if (!in_array($CURRENT_SECTION, $allowedSections) && $CURRENT_SECTION !== 'main') $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri) && !$activeContextUuid) $CURRENT_SECTION = 'main';

if ($CURRENT_SECTION === 'settings') {
    header("Location: " . $basePath . "settings/your-profile");
    exit;
}
if ($CURRENT_SECTION === 'admin') {
    header("Location: " . $basePath . "admin/dashboard");
    exit;
}

$isUserRole = ($_SESSION['user_role'] ?? 'user') === 'user';
$maintenanceAllowed = ['status-page', 'login', 'register', 'forgot-password', 'reset-password', 'login/verification-additional'];

if ($isMaintenanceMode && $isUserRole && !in_array($CURRENT_SECTION, $maintenanceAllowed)) {
    header("Location: " . $basePath . "status-page?status=maintenance");
    exit;
}

if ($CURRENT_SECTION === 'status-page') {
    $statusParam = $_GET['status'] ?? '';
    if ($statusParam === 'maintenance') {
        if (!$isMaintenanceMode || !$isUserRole) {
            header("Location: " . $basePath);
            exit;
        }
    }
    if (in_array($statusParam, ['suspended', 'deleted']) && $freshUser && $freshUser['account_status'] === 'active') {
        header("Location: " . $basePath);
        exit;
    }
}

$isLoggedIn = isset($_SESSION['user_id']);

if (strpos($CURRENT_SECTION, 'admin/') === 0) {
    $userRole = $_SESSION['user_role'] ?? 'user';
    $allowedAdminRoles = ['founder', 'administrator'];
    if (!in_array($userRole, $allowedAdminRoles)) $CURRENT_SECTION = '404';
}

$appSections = ['main', 'explorer', 'join-community'];
$systemSections = ['status-page', '404', 'error-missing-data'];

if ($CURRENT_SECTION === 'login/verification-additional') {
    $SECTION_FILE_NAME = 'auth/login';
} elseif ($CURRENT_SECTION === 'search') {
    $SECTION_FILE_NAME = 'app/search-results';
} elseif (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'auth/register';
} elseif ($CURRENT_SECTION === 'forgot-password') {
    $SECTION_FILE_NAME = 'auth/forgot-password';
} elseif ($CURRENT_SECTION === 'reset-password') {
    $SECTION_FILE_NAME = 'auth/reset-password';
} elseif ($CURRENT_SECTION === 'login') {
    $SECTION_FILE_NAME = 'auth/login';
} elseif (strpos($CURRENT_SECTION, 'settings/') === 0) {
    $SECTION_FILE_NAME = $CURRENT_SECTION;
} elseif (strpos($CURRENT_SECTION, 'admin/') === 0) {
    $SECTION_FILE_NAME = $CURRENT_SECTION;
} elseif (in_array($CURRENT_SECTION, $appSections)) {
    $SECTION_FILE_NAME = 'app/' . $CURRENT_SECTION;
} elseif (in_array($CURRENT_SECTION, $systemSections)) {
    $SECTION_FILE_NAME = 'system/' . $CURRENT_SECTION;
} else {
    $SECTION_FILE_NAME = 'system/404';
}

$publicSections = ['login', 'register', 'register/additional-data', 'register/verification-account', 'forgot-password', 'reset-password', 'status-page', 'login/verification-additional'];

if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections) && $CURRENT_SECTION !== '404') {
    header("Location: " . $basePath . "login");
    exit;
}
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections) && $CURRENT_SECTION !== 'status-page') {
    header("Location: " . $basePath);
    exit;
}

$requirements = [
    'register/additional-data' => ['key' => 'temp_register', 'sub' => 'email'],
    'register/verification-account' => ['key' => 'temp_register', 'sub' => 'username'],
    'login/verification-additional' => ['key' => 'temp_login_2fa', 'sub' => 'user_id']
];

if (array_key_exists($CURRENT_SECTION, $requirements)) {
    $req = $requirements[$CURRENT_SECTION];
    $hasData = isset($_SESSION[$req['key']][$req['sub']]) && !empty($_SESSION[$req['key']][$req['sub']]);
    if (!$hasData) {
        if ($CURRENT_SECTION === 'login/verification-additional') {
            header("Location: " . $basePath . "login");
            exit;
        }
        $SECTION_FILE_NAME = 'system/error-missing-data';
        $missingDataMessage = translation('system.missing_data_context') . " <strong>$CURRENT_SECTION</strong>.";
    }
}

if ($isLoggedIn) {
    $statusParam = $_GET['status'] ?? '';
    if ($isMaintenanceMode && $isUserRole) {
        $showNavigation = false;
    } else {
        $showNavigation = ($SECTION_FILE_NAME !== 'system/error-missing-data');
    }
} else {
    $showNavigation = !($SECTION_FILE_NAME === 'system/error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
}
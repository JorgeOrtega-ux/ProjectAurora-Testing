<?php
// public/loader.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once __DIR__ . '/../includes/logic/i18n_server.php'; 

// 1. Cargar idioma
$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

// 2. Seguridad básica
$publicSections = [
    'login', 'register', 'register/additional-data', 'register/verification-account', 
    'forgot-password', 'reset-password', 'status-page', 'login/verification-additional'
];

$section = $_GET['section'] ?? 'main';
// Limpieza básica de path traversal
$section = str_replace(['..', '.php'], '', $section);

if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    exit('<div style="padding:20px; text-align:center">' . translation('global.session_expired') . '</div>');
}

// 3. [FIX] Manejo de rutas dinámicas de chat (DM y Comunidad)
if (preg_match('/^c\/([a-zA-Z0-9-]+)$/', $section, $matches)) {
    $section = 'main';
    $activeContextType = 'community';
    $activeContextUuid = $matches[1];
} elseif (preg_match('/^dm\/([a-zA-Z0-9-]+)$/', $section, $matches)) {
    $section = 'main';
    $activeContextType = 'private';
    $activeContextUuid = $matches[1];
}

// 4. Mapa COMPLETO de rutas estáticas
$fileMap = [
    // App
    'main'           => 'app/main',
    'explorer'       => 'app/explorer',
    'search'         => 'app/search-results',
    'join-community' => 'app/join-community',

    // Auth
    'login'                         => 'auth/login',
    'login/verification-additional' => 'auth/login',
    'register'                      => 'auth/register',
    'register/additional-data'      => 'auth/register',
    'register/verification-account' => 'auth/register',
    'forgot-password'               => 'auth/forgot-password',
    'reset-password'                => 'auth/reset-password',

    // Settings
    'settings'                  => 'settings/your-profile',
    'settings/your-profile'     => 'settings/your-profile',
    'settings/login-security'   => 'settings/login-security',
    'settings/accessibility'    => 'settings/accessibility',
    'settings/change-password'  => 'settings/change-password',
    'settings/2fa-setup'        => 'settings/2fa-setup',
    'settings/sessions'         => 'settings/sessions',
    'settings/delete-account'   => 'settings/delete-account',

    // Admin
    'admin'             => 'admin/dashboard',
    'admin/dashboard'   => 'admin/dashboard',
    'admin/users'       => 'admin/users',
    'admin/user-status' => 'admin/user-status', 
    'admin/user-manage' => 'admin/user-manage',
    'admin/user-history'=> 'admin/user-history',
    'admin/user-role'   => 'admin/user-role',
    'admin/user-edit'   => 'admin/user-edit',
    'admin/backups'     => 'admin/backups',
    'admin/server'      => 'admin/server',
    'admin/alerts'      => 'admin/alerts',
    
    // [AGREGADO] Rutas de Comunidades que faltaban
    'admin/communities'    => 'admin/communities',
    'admin/community-edit' => 'admin/community-edit',
    
    // System
    'status-page' => 'system/status-page',
    '404'         => 'system/404'
];

if (array_key_exists($section, $fileMap)) {
    $realFile = __DIR__ . '/../includes/sections/' . $fileMap[$section] . '.php';
} else {
    $realFile = __DIR__ . '/../includes/sections/system/404.php';
    $section = '404'; 
}

$CURRENT_SECTION = $section;
$basePath = '/ProjectAurora/'; 

if ($realFile && file_exists($realFile)) {
    include $realFile;
} else {
    http_response_code(404);
    echo '<div style="padding:20px; text-align:center;">' . translation('system.file_not_found') . '</div>';
}
?>
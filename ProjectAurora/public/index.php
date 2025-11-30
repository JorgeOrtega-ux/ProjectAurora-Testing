<?php
require_once __DIR__ . '/../config/routes/router.php';
require_once __DIR__ . '/../config/core/database.php';
require_once __DIR__ . '/../config/helpers/utilities.php';
require_once __DIR__ . '/../includes/logic/i18n_server.php'; 

$jsUserId = 'null';
$wsToken = 'null';

if (isset($_SESSION['user_lang'])) {
    $userLang = $_SESSION['user_lang'];
} else {
    $userLang = detect_browser_language(); 
}

$userTheme = $_SESSION['user_theme'] ?? 'system';

// [NUEVO] Obtener preferencias de sesión para inyectar en JS
$userExtendedMsg = isset($_SESSION['user_extended_msg']) ? (int)$_SESSION['user_extended_msg'] : 0;
$openLinksNewTab = isset($_SESSION['user_new_tab']) ? (int)$_SESSION['user_new_tab'] : 1;

// [CORRECCIÓN CRÍTICA] Obtener configuración del servidor para validaciones dinámicas
$serverConfigData = getServerConfig($pdo);

I18n::load($userLang);

if (isset($_SESSION['user_id'])) {
    $jsUserId = $_SESSION['user_id'];
    $currentSessionId = session_id();
    $token = generate_ws_auth_token($pdo, $jsUserId, $currentSessionId);
    $wsToken = "'$token'";
} 
?>
<!DOCTYPE html>
<html lang="<?php echo substr($userLang, 0, 2); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_ID = <?php echo $jsUserId; ?>; 
        window.WS_TOKEN = <?php echo $wsToken; ?>;
        window.USER_LANG = '<?php echo $userLang; ?>';
        window.USER_THEME = '<?php echo $userTheme; ?>';
        
        // [NUEVO] Variables globales de preferencias
        window.USER_EXTENDED_MSG = <?php echo $userExtendedMsg; ?>; 
        window.OPEN_NEW_TAB = <?php echo $openLinksNewTab; ?>; 
        
        // [CORRECCIÓN CRÍTICA] Inyección de configuración del servidor
        window.SERVER_CONFIG = <?php echo json_encode($serverConfigData); ?>;
    </script>

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/chat.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/componnents.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>
<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                <?php if ($showNavigation): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../includes/layouts/header.php'; ?>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>

                    <div class="loader-wrapper">
                        <div class="loader-spinner"></div>
                    </div>

                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
                        <?php
                        $sectionFile = __DIR__ . "/../includes/sections/{$SECTION_FILE_NAME}.php";
                        if (file_exists($sectionFile)) {
                            include $sectionFile;
                        } else {
                            include __DIR__ . "/../includes/sections/system/404.php";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>
</html>
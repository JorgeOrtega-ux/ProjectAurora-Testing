<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/communities">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Gestión de Comunidades</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" data-nav="admin/community-edit" 
                        data-i18n-tooltip="admin.communities.create" 
                        data-tooltip="Nueva Comunidad">
                    <span class="material-symbols-rounded">add_circle</span>
                </button>
            </div>
        </div>
        
        <div class="component-toolbar search-toolbar-panel" style="margin-top: 8px;">
            <div class="search-container full-width-search">
                <span class="material-symbols-rounded search-icon">search</span>
                <input type="text" id="admin-communities-search" class="search-input" placeholder="Buscar por nombre o código..." autocomplete="off"> 
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar" style="padding-top: 140px !important;">

        <div class="component-header-card">
            <h1 class="component-page-title">Comunidades</h1>
            <p class="component-page-description">Administra los grupos, su privacidad y accesos.</p>
        </div>

        <div id="communities-list-container" class="capsule-list-container mt-16">
            <div class="small-spinner" style="margin: 20px auto;"></div>
        </div>

    </div>
</div>
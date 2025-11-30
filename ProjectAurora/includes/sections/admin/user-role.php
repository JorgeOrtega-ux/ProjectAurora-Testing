<?php
// includes/sections/admin/user-role.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-role">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Gestión de Roles</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" id="btn-save-role" 
                        data-i18n-tooltip="global.save" 
                        data-tooltip="<?php echo translation('global.save'); ?>">
                    <span class="material-symbols-rounded">save</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title">Asignar Rol de Usuario</h1>
            <p class="component-page-description">Modifica los permisos y el nivel de acceso de este usuario en la plataforma.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="role-pfp-container">
                        <img src="" id="role-user-avatar" class="component-card__avatar-image hidden-avatar" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="role-user-icon">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="role-username" data-i18n="global.loading"><?php echo translation('global.loading'); ?></h2>
                        <p class="component-card__description" id="role-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped mt-16">
            <input type="hidden" id="role-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            <input type="hidden" id="role-input-value" value="user">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Seleccionar Rol</h2>
                        <p class="component-card__description">El rol define qué acciones puede realizar el usuario.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-roles">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="current-role-icon">person</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="current-role-text">Usuario</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-roles">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link" 
                                         data-action="select-role-option" 
                                         data-value="user" 
                                         data-label="Usuario" 
                                         data-icon="person">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                                        <div class="menu-link-text">Usuario</div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                    <div class="menu-link" 
                                         data-action="select-role-option" 
                                         data-value="moderator" 
                                         data-label="Moderador" 
                                         data-icon="security">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">security</span></div>
                                        <div class="menu-link-text">Moderador</div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                    <div class="menu-link" 
                                         data-action="select-role-option" 
                                         data-value="administrator" 
                                         data-label="Administrador" 
                                         data-icon="admin_panel_settings">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                                        <div class="menu-link-text">Administrador</div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="security-notice">
            <span class="material-symbols-rounded security-notice__icon">info</span>
            <div>
                <h4 class="security-notice__title">Aviso de Seguridad</h4>
                <p class="security-notice__text">
                    Esta es una acción administrativa sensible. Todos los cambios de roles son <strong>auditados y registrados</strong> permanentemente para su revisión. Asegúrate de tener la autorización correspondiente.
                </p>
            </div>
        </div>

    </div>
</div>
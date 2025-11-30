<?php
// includes/sections/admin/user-edit.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
$sConfig = isset($GLOBALS['serverConfig']) ? $GLOBALS['serverConfig'] : getServerConfig($pdo);
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-edit">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Editar Usuario</span>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title">Editar Perfil de Usuario</h1>
            <p class="component-page-description">Modifica la información básica de la cuenta. Los cambios se registran en auditoría.</p>
        </div>

        <input type="hidden" id="edit-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">

        <div class="component-card component-card--grouped">
            <div class="component-group-item" data-component="admin-profile-picture-section">
                <input type="file" class="visually-hidden" data-element="admin-pfp-input" accept="image/png, image/jpeg, image/gif, image/webp">

                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="admin-edit-pfp-container">
                        <img src="" class="component-card__avatar-image" id="admin-edit-pfp-img" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="admin-edit-pfp-icon" style="font-size: 24px;">person</span>
                        
                        <div class="component-card__avatar-overlay" data-action="admin-trigger-pfp-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.profile.profile_picture_title"><?php echo translation('settings.profile.profile_picture_title'); ?></h2>
                        <p class="component-card__description">Cambiar avatar del usuario.</p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <div id="admin-pfp-actions-default" class="disabled">
                        <button type="button" class="component-button" data-action="admin-upload-pfp" data-i18n="settings.profile.upload_btn">Subir foto</button>
                    </div>

                    <div id="admin-pfp-actions-custom" class="disabled">
                        <button type="button" class="component-button danger" data-action="admin-remove-pfp" data-i18n="global.delete">Restablecer</button>
                        <button type="button" class="component-button" data-action="admin-upload-pfp" data-i18n="settings.profile.change_btn">Cambiar foto</button>
                    </div>

                    <div id="admin-pfp-actions-save" class="disabled">
                        <button type="button" class="component-button" data-action="admin-cancel-pfp" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="admin-save-pfp" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="admin-username-section">
                <div class="component-card__content">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title" data-i18n="settings.profile.username_title"><?php echo translation('settings.profile.username_title'); ?></h2>
                        
                        <div id="admin-username-view" class="active">
                            <p class="component-card__description" id="admin-username-display">Cargando...</p>
                        </div>
                        
                        <div id="admin-username-edit" class="disabled">
                            <div class="input-with-actions">
                                <input type="text" class="component-text-input" id="admin-username-input" required>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="component-button" data-action="admin-cancel-username" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                                    <button type="button" class="component-button primary" data-action="admin-save-username" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                                </div>
                            </div>
                            <p class="component-card__meta">Sin cooldown para administradores.</p>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" id="admin-username-actions">
                    <button type="button" class="component-button" data-action="admin-edit-username" data-i18n="global.edit"><?php echo translation('global.edit'); ?></button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="admin-email-section">
                <div class="component-card__content">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title" data-i18n="settings.profile.email_title"><?php echo translation('settings.profile.email_title'); ?></h2>
                        
                        <div id="admin-email-view" class="active">
                            <p class="component-card__description" id="admin-email-display">Cargando...</p>
                        </div>
                        
                        <div id="admin-email-edit" class="disabled">
                            <div class="input-with-actions">
                                <input type="email" class="component-text-input" id="admin-email-input" required>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="component-button" data-action="admin-cancel-email" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                                    <button type="button" class="component-button primary" data-action="admin-save-email" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" id="admin-email-actions">
                    <button type="button" class="component-button" data-action="admin-edit-email" data-i18n="global.edit"><?php echo translation('global.edit'); ?></button>
                </div>
            </div>

        </div>

        <div class="security-notice">
            <span class="material-symbols-rounded security-notice__icon">admin_panel_settings</span>
            <div>
                <h4 class="security-notice__title">Modo Administrador</h4>
                <p class="security-notice__text">
                    Estás editando la información de otro usuario. Todas las acciones quedarán registradas. No se aplican tiempos de espera (cooldowns) para estos cambios.
                </p>
            </div>
        </div>

    </div>
</div>
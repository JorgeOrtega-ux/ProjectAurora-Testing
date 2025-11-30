<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// [CORRECCIÓN] Configuración
$sConfig = isset($GLOBALS['serverConfig']) ? $GLOBALS['serverConfig'] : getServerConfig($pdo);
?>
<div class="section-content active" data-section="settings/change-password">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.change_password.title"><?php echo translation('settings.change_password.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.change_password.description"><?php echo translation('settings.change_password.description'); ?></p>
        </div>

        <div class="component-card component-card--grouped active" data-step="password-step-1">
            <div class="component-group-item component-group-item--stacked-right">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.current_label"><?php echo translation('settings.change_password.current_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.current_desc"><?php echo translation('settings.change_password.current_desc'); ?></p>
                    </div>
                </div>
                
                <div class="component-input-wrapper w-100">
                    <input type="password" 
                           class="component-text-input full-width" 
                           data-element="current-password" 
                           data-i18n-placeholder="settings.change_password.current_label"
                           placeholder="<?php echo translation('settings.change_password.current_label'); ?>">
                </div>

                <div class="component-card__actions">
                    <button type="button" class="component-button primary" data-action="verify-current-password" data-i18n="settings.change_password.next_btn">
                        <?php echo translation('settings.change_password.next_btn'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped disabled" data-step="password-step-2">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">vpn_key</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.new_label"><?php echo translation('settings.change_password.new_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.new_desc">
                            <?php echo translation('settings.change_password.new_desc', ['min' => $sConfig['min_password_length']]); ?>
                        </p>
                    </div>
                </div>
                <div class="component-input-wrapper w-100">
                    <input type="password" 
                           class="component-text-input full-width" 
                           data-element="new-password" 
                           data-i18n-placeholder="settings.change_password.new_label"
                           placeholder="<?php echo translation('settings.change_password.new_label'); ?>">
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">check_circle</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.confirm_label"><?php echo translation('settings.change_password.confirm_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.confirm_desc"><?php echo translation('settings.change_password.confirm_desc'); ?></p>
                    </div>
                </div>
                <div class="component-input-wrapper w-100">
                    <input type="password" 
                           class="component-text-input full-width" 
                           data-element="confirm-password" 
                           data-i18n-placeholder="settings.change_password.confirm_label"
                           placeholder="<?php echo translation('settings.change_password.confirm_label'); ?>">
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item disabled" data-step="password-step-2-sessions">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">devices_other</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.sessions_check_title"><?php echo translation('settings.change_password.sessions_check_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.sessions_check_desc"><?php echo translation('settings.change_password.sessions_check_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" data-element="logout-others-check">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item disabled justify-end" data-step="password-step-2-actions">
                <div class="component-card__actions actions-right w-100">
                    <button type="button" class="component-button primary" data-action="save-new-password" data-i18n="global.save">
                        <?php echo translation('global.save'); ?>
                    </button>
                </div>
            </div>

        </div>

    </div>
</div>
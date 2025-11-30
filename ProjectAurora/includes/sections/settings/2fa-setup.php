<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$is2faEnabled = false;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT is_2fa_enabled FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is2faEnabled = (bool)$stmt->fetchColumn();
}
?>

<script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="auth.2fa.title"><?php echo translation('auth.2fa.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.security.2fa_desc"><?php echo translation('settings.security.2fa_desc'); ?></p>
        </div>

        <?php if ($is2faEnabled): ?>
            <div class="component-card component-card--danger active" data-step="2fa-status-enabled">
                <div class="component-card__content" style="flex-direction: column; align-items: flex-start; gap: 16px;">
                    
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span class="material-symbols-rounded" style="font-size: 32px; color: #2e7d32;">verified_user</span>
                        <h2 class="component-card__title" style="font-size: 18px; color: #2e7d32;" data-i18n="settings.2fa.status_active_title">
                            <?php echo translation('settings.2fa.status_active_title'); ?>
                        </h2>
                    </div>

                    <p class="component-card__description" style="color: #000;" data-i18n="settings.2fa.status_active_desc">
                        <?php echo translation('settings.2fa.status_active_desc'); ?>
                    </p>

                    <hr class="component-separator">

                    <p style="font-size:14px; color:#d32f2f; margin-top:10px;" data-i18n="settings.2fa.disable_warning">
                        <?php echo translation('settings.2fa.disable_warning'); ?>
                    </p>

                    <div class="component-input-wrapper w-100">
                        <input type="password" class="component-text-input full-width" 
                               data-element="2fa-disable-password" 
                               data-i18n-placeholder="settings.change_password.current_label"
                               placeholder="<?php echo translation('settings.change_password.current_label'); ?>">
                    </div>

                    <div class="component-card__actions w-100" style="justify-content: flex-end; margin-top: 10px;">
                        <button type="button" class="component-button danger" data-action="disable-2fa-btn" data-i18n="settings.2fa.disable_btn">
                            <?php echo translation('settings.2fa.disable_btn'); ?>
                        </button>
                    </div>

                </div>
            </div>

        <?php else: ?>
            <div class="component-card component-card--grouped active" data-step="2fa-step-1">
                <div class="component-group-item component-group-item--stacked-right">
                    <div class="component-card__content">
                        <div class="component-icon-container">
                            <span class="material-symbols-rounded">lock</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="settings.2fa.step1_title"><?php echo translation('settings.2fa.step1_title'); ?></h2>
                            <p class="component-card__description" data-i18n="settings.change_password.current_desc"><?php echo translation('settings.change_password.current_desc'); ?></p>
                        </div>
                    </div>
                    
                    <div class="component-input-wrapper w-100">
                        <input type="password" class="component-text-input full-width" 
                               data-element="2fa-current-password" 
                               data-i18n-placeholder="settings.change_password.current_label"
                               placeholder="<?php echo translation('settings.change_password.current_label'); ?>">
                    </div>

                    <div class="component-card__actions">
                        <button type="button" class="component-button primary" data-action="verify-pass-2fa" data-i18n="global.continue">
                            <?php echo translation('global.continue'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--grouped disabled" data-step="2fa-step-2">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-icon-container">
                            <span class="material-symbols-rounded">qr_code_scanner</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="settings.2fa.step2_title"><?php echo translation('settings.2fa.step2_title'); ?></h2>
                            <p class="component-card__description" data-i18n="settings.2fa.step2_desc"><?php echo translation('settings.2fa.step2_desc'); ?></p>
                        </div>
                    </div>

                    <div style="width: 100%; display: flex; justify-content: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                        <div id="qrcode-display"></div>
                    </div>

                    <div style="text-align: center; width: 100%;">
                        <p style="font-size: 13px; color: #666; margin-bottom: 5px;" data-i18n="settings.2fa.manual_entry"><?php echo translation('settings.2fa.manual_entry'); ?></p>
                        <div style="background: #eee; padding: 8px; border-radius: 4px; display: inline-block; font-family: monospace; letter-spacing: 1px;">
                            <strong id="manual-secret-text" data-i18n="global.loading"><?php echo translation('global.loading'); ?></strong>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-icon-container">
                            <span class="material-symbols-rounded">pin</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="settings.2fa.verify_code_title"><?php echo translation('settings.2fa.verify_code_title'); ?></h2>
                            <p class="component-card__description" data-i18n="settings.2fa.verify_code_desc"><?php echo translation('settings.2fa.verify_code_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-input-wrapper w-100">
                        <input type="text" class="component-text-input full-width" 
                            data-element="2fa-verify-code" 
                            placeholder="000000" 
                            maxlength="6" 
                            style="letter-spacing: 4px; font-size: 18px; text-align: center;">
                    </div>
                    <div class="component-card__actions actions-right w-100">
                        <button type="button" class="component-button" data-action="reload-page" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="confirm-enable-2fa" data-i18n="settings.2fa.activate_btn">
                            <?php echo translation('settings.2fa.activate_btn'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--grouped disabled" data-step="2fa-step-3">
                <div class="component-group-item component-group-item--stacked">
                    <div style="text-align: center; width: 100%; padding: 20px 0;">
                        <span class="material-symbols-rounded" style="font-size: 64px; color: #4caf50;">check_circle</span>
                        <h2 style="margin: 10px 0; font-size: 24px;" data-i18n="settings.2fa.success_title"><?php echo translation('settings.2fa.success_title'); ?></h2>
                        <p style="color: #666;" data-i18n="settings.2fa.success_desc"><?php echo translation('settings.2fa.success_desc'); ?></p>
                    </div>
                </div>
                
                <hr class="component-divider">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-icon-container" style="background: #fff3e0; border-color: #ffe0b2;">
                            <span class="material-symbols-rounded" style="color: #f57c00;">warning</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" style="color: #e65100;" data-i18n="settings.2fa.backup_title"><?php echo translation('settings.2fa.backup_title'); ?></h2>
                            <p class="component-card__description" data-i18n="settings.2fa.backup_desc"><?php echo translation('settings.2fa.backup_desc'); ?></p>
                        </div>
                    </div>

                    <div id="backup-codes-list" style="
                        background: #333; 
                        color: #fff; 
                        padding: 20px; 
                        border-radius: 8px; 
                        font-family: monospace; 
                        display: grid; 
                        grid-template-columns: 1fr 1fr; 
                        gap: 10px; 
                        text-align: center;
                        width: 100%;
                    ">
                        </div>

                    <div class="component-card__actions actions-right w-100">
                        <button type="button" class="component-button primary" data-nav="settings/login-security" data-i18n="settings.2fa.backup_saved_btn">
                            <?php echo translation('settings.2fa.backup_saved_btn'); ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
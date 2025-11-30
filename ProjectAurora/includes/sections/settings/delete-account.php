<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="settings/delete-account">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" style="color: #d32f2f;" data-i18n="settings.delete_account.title"><?php echo translation('settings.delete_account.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.delete_account.description"><?php echo translation('settings.delete_account.description'); ?></p>
        </div>

        <div class="component-card component-card--danger">
            <div class="component-card__content" style="flex-direction: column; align-items: flex-start; gap: 16px;">
                
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="material-symbols-rounded" style="font-size: 32px; color: #d32f2f;">warning</span>
                    <h2 class="component-card__title" style="font-size: 18px; color: #d32f2f;" data-i18n="settings.delete_account.warning_title">
                        <?php echo translation('settings.delete_account.warning_title'); ?>
                    </h2>
                </div>

                <p class="component-card__description" style="color: #000;" data-i18n="settings.delete_account.warning_text">
                    <?php echo translation('settings.delete_account.warning_text'); ?>
                </p>

                <hr class="component-separator" style="border-color: #ffcdd2;">

                <div class="component-input-wrapper w-100">
                    <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 8px;" data-i18n="settings.delete_account.password_label">
                        <?php echo translation('settings.delete_account.password_label'); ?>
                    </label>
                    <input type="password" 
                           class="component-text-input full-width" 
                           data-element="delete-confirm-password" 
                           placeholder=" "
                           style="border-color: #ffcdd2;">
                </div>

                <div class="component-card__actions w-100" style="justify-content: space-between; margin-top: 10px;">
                    <button type="button" class="component-button" data-nav="settings/login-security" data-i18n="settings.delete_account.cancel_btn">
                        <?php echo translation('settings.delete_account.cancel_btn'); ?>
                    </button>
                    
                    <button type="button" class="component-button danger" data-action="confirm-account-deletion" data-i18n="settings.delete_account.confirm_btn" style="background-color: #ffebee;">
                        <?php echo translation('settings.delete_account.confirm_btn'); ?>
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>
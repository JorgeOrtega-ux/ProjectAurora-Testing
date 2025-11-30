<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<div class="section-content active" data-section="forgot-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div class="auth-back-link">
                <a href="#" data-nav="login" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> 
                    <span data-i18n="global.back"><?php echo translation('global.back'); ?></span>
                </a>
            </div>

            <div data-step="rec-1" class="auth-step-container active">
                <h1 data-i18n="auth.recovery.title"><?php echo translation('auth.recovery.title'); ?></h1>
                <p data-i18n="auth.recovery.subtitle"><?php echo translation('auth.recovery.subtitle'); ?></p>
                
                <div class="floating-label-group">
                    <input type="email" data-input="rec-email" class="floating-input" required placeholder=" ">
                    <label class="floating-label" data-i18n="auth.login.email_label"><?php echo translation('auth.login.email_label'); ?></label>
                </div>

                <button class="form-button" data-action="rec-step1" data-i18n="auth.recovery.send_btn"><?php echo translation('auth.recovery.send_btn'); ?></button>
                <div data-error="rec-1" class="form-error-message"></div>
            </div>

            <div data-step="rec-success" class="auth-step-container">
                <div style="text-align:center; padding:20px 0;">
                    <span class="material-symbols-rounded" style="font-size:64px; color:#4caf50;">mark_email_read</span>
                </div>
                <h1 data-i18n="auth.recovery.success_title"><?php echo translation('auth.recovery.success_title'); ?></h1>
                <p>
                    <span data-i18n="auth.recovery.check_email"><?php echo translation('auth.recovery.check_email'); ?></span> 
                    <strong data-display="rec-email"></strong> 
                    <span data-i18n="auth.recovery.spam_hint"><?php echo translation('auth.recovery.spam_hint'); ?></span>
                </p>
                <p style="font-size:14px; color:#888; margin-top:10px;" data-i18n="auth.recovery.click_hint">
                    <?php echo translation('auth.recovery.click_hint'); ?>
                </p>
            </div>

        </div>
    </div>
</div>
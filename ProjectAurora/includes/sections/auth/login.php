<?php
if (!isset($CURRENT_SECTION)) {
    $CURRENT_SECTION = 'login';
}

$isStep2 = ($CURRENT_SECTION === 'login/verification-additional');
$maskedEmailDisplay = 'tu correo';

if ($isStep2 && isset($_SESSION['temp_login_2fa']['email'])) {
    $rawEmail = $_SESSION['temp_login_2fa']['email'];
    $parts = explode('@', $rawEmail);
    if(count($parts) == 2){
        $maskedEmailDisplay = substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
}
?>
<div class="section-content active" data-section="login">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div data-step="login-1" class="auth-step-container <?php echo $isStep2 ? '' : 'active'; ?>">
                <h1 data-i18n="auth.login.title"><?php echo translation('auth.login.title'); ?></h1>
                <p data-i18n="auth.login.subtitle"><?php echo translation('auth.login.subtitle'); ?></p>

                <div class="floating-label-group">
                    <input 
                        type="email" 
                        data-input="login-email" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label class="floating-label" data-i18n="auth.login.email_label"><?php echo translation('auth.login.email_label'); ?></label>
                </div>

                <div class="floating-label-group">
                    <input 
                        type="password" 
                        data-input="login-password" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label class="floating-label" data-i18n="auth.login.password_label"><?php echo translation('auth.login.password_label'); ?></label>
                    
                    <button type="button" class="floating-input-btn">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                <div class="auth-link-wrapper">
                    <a href="#" data-nav="forgot-password" style="color:#666; text-decoration:none; font-size:14px; font-weight:500;" data-i18n="auth.login.forgot_password">
                        <?php echo translation('auth.login.forgot_password'); ?>
                    </a>
                </div>

                <button class="form-button" data-action="login-submit" data-i18n="global.continue"><?php echo translation('global.continue'); ?></button>

                <div data-error="login-error" class="form-error-message"></div>

                <div class="form-footer-link">
                    <span data-i18n="auth.login.no_account"><?php echo translation('auth.login.no_account'); ?></span> 
                    <a href="#" data-nav="register" data-i18n="auth.login.register_link"><?php echo translation('auth.login.register_link'); ?></a>
                </div>
            </div>

            <div data-step="login-2" class="auth-step-container <?php echo $isStep2 ? 'active' : ''; ?>">
                <div class="auth-back-link">
                    <a href="#" data-action="login-2fa-back" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> 
                        <span data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></span>
                    </a>
                </div>

                <h1 data-i18n="auth.2fa.title"><?php echo translation('auth.2fa.title'); ?></h1>
                <p data-i18n="auth.2fa.subtitle"><?php echo translation('auth.2fa.subtitle'); ?></p>
                
                <p style="font-size:14px; margin-top:10px;">
                    <span data-i18n="auth.2fa.instruction"><?php echo translation('auth.2fa.instruction'); ?></span> 
                    <strong data-display="login-2fa-email"><?php echo htmlspecialchars($maskedEmailDisplay); ?></strong>
                </p>

                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="login-2fa-code" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                        maxlength="14" 
                        style="letter-spacing: 2px; text-transform: uppercase;"
                    >
                    <label class="floating-label" data-i18n="auth.2fa.label"><?php echo translation('auth.2fa.label'); ?></label>
                </div>

                <button class="form-button" data-action="login-2fa-submit" data-i18n="auth.2fa.verify_btn"><?php echo translation('auth.2fa.verify_btn'); ?></button>

                <div data-error="login-2fa" class="form-error-message"></div>
                
                <div class="form-footer-link">
                    <a href="#" data-action="resend-login" class="disabled-link" data-i18n="auth.register.resend_code"><?php echo translation('auth.register.resend_code'); ?></a> (60)
                </div>
            </div>

        </div>
    </div>
</div>
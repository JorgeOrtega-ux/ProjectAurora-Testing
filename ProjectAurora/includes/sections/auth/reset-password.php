<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$tokenIsValid = false;

if (!empty($token) && isset($pdo)) {
    try {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE code = ? AND code_type = 'recovery' AND expires_at > NOW()");
        $stmt->execute([$tokenHash]);
        if ($stmt->fetch()) {
            $tokenIsValid = true;
        }
    } catch (Exception $e) {
        $tokenIsValid = false;
    }
}
?>

<div class="section-content active" data-section="reset-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <?php if ($tokenIsValid): ?>
                <div class="auth-step-container active">
                    <h1 data-i18n="auth.recovery.new_pass_title"><?php echo translation('auth.recovery.new_pass_title'); ?></h1>
                    <p data-i18n="auth.recovery.new_pass_subtitle"><?php echo translation('auth.recovery.new_pass_subtitle'); ?></p>
                    
                    <input type="hidden" data-input="reset-token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="floating-label-group">
                        <input type="password" data-input="reset-pass" class="floating-input" required placeholder=" " minlength="8">
                        <label class="floating-label" data-i18n="auth.recovery.new_pass_label"><?php echo translation('auth.recovery.new_pass_label'); ?></label>
                        <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                    </div>

                    <div class="floating-label-group">
                        <input type="password" data-input="reset-pass-confirm" class="floating-input" required placeholder=" " minlength="8">
                        <label class="floating-label" data-i18n="auth.recovery.repeat_pass_label"><?php echo translation('auth.recovery.repeat_pass_label'); ?></label>
                        <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                    </div>

                    <button class="form-button" data-action="reset-final-submit" data-i18n="auth.recovery.change_btn"><?php echo translation('auth.recovery.change_btn'); ?></button>
                    <div data-error="reset-error" class="form-error-message"></div>
                </div>

            <?php else: ?>
                <div style="text-align: center; margin-bottom: 20px;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f;">link_off</span>
                </div>

                <div style="
                    border: 1px solid #e0e0e0; 
                    border-radius: 8px; 
                    padding: 20px; 
                    text-align: left; 
                    background-color: #fff;
                ">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #d32f2f;" data-i18n="auth.recovery.invalid_link_title">
                        <?php echo translation('auth.recovery.invalid_link_title'); ?>
                    </h3>
                    <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">
                        <span data-i18n="auth.recovery.invalid_link_desc"><?php echo translation('auth.recovery.invalid_link_desc'); ?></span>
                        <br><br>
                        
                        <span data-i18n="global.please"><?php echo translation('global.please'); ?></span>, 
                        <a href="#" data-nav="forgot-password" style="color: #000; font-weight: 600; text-decoration: underline;" data-i18n="auth.recovery.request_new">
                            <?php echo translation('auth.recovery.request_new'); ?>
                        </a>.
                    </p>
                </div>

                <div style="margin-top: 25px; text-align: center;">
                    <a href="#" data-nav="login" style="color:#666; text-decoration:none; font-size:14px; font-weight:500;">
                        <span class="material-symbols-rounded" style="font-size:16px; vertical-align: text-bottom;">arrow_back</span> 
                        <span data-i18n="global.back_home"><?php echo translation('global.back_home'); ?></span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
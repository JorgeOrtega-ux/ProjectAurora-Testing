<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT created_at, is_2fa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$createdAt = $userData['created_at'] ?? null;
$is2faEnabled = !empty($userData['is_2fa_enabled']) && $userData['is_2fa_enabled'] == 1;

$dateStr = $createdAt ? date("d/m/Y", strtotime($createdAt)) : date("d/m/Y");

$stmtPass = $pdo->prepare("SELECT changed_at FROM user_audit_logs WHERE user_id = ? AND change_type = 'password' ORDER BY changed_at DESC LIMIT 1");
$stmtPass->execute([$userId]);
$lastPassChange = $stmtPass->fetchColumn();

$passwordDesc = translation('settings.security.password_desc');
if ($lastPassChange) {
    $ts = strtotime($lastPassChange);
    $passwordDesc = translation('settings.security.last_update') . date("d/m/Y \a \l\a\s H:i", $ts);
}

$btn2faKey = $is2faEnabled ? 'settings.2fa.disable_btn' : 'settings.security.2fa_btn';
?>

<div class="section-content active" data-section="settings/login-security">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.security.title"><?php echo translation('settings.security.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.security.description"><?php echo translation('settings.security.description'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item" data-component="password-section">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.security.password_title"><?php echo translation('settings.security.password_title'); ?></h2>
                        <p class="component-card__description">
                            <?php echo htmlspecialchars($passwordDesc); ?>
                        </p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="settings/change-password" data-i18n="settings.security.password_btn">
                        <?php echo translation('settings.security.password_btn'); ?>
                    </button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="2fa-section">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">shield_lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.security.2fa_title"><?php echo translation('settings.security.2fa_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.security.2fa_desc"><?php echo translation('settings.security.2fa_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="settings/2fa-setup" data-i18n="<?php echo $btn2faKey; ?>">
                        <?php echo translation($btn2faKey); ?>
                    </button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked-right" data-component="sessions-section">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">devices</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.security.sessions_title"><?php echo translation('settings.security.sessions_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.security.sessions_desc"><?php echo translation('settings.security.sessions_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-action="trigger-sessions-manage" data-i18n="settings.security.sessions_btn">
                        <?php echo translation('settings.security.sessions_btn'); ?>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped component-card--danger" data-component="delete-account-section">
            <div class="component-group-item component-group-item--stacked-right">
                <div class="component-card__content">
                    <div class="component-icon-container" style="color: #d32f2f; border-color: #ffcdd2; background-color: transparent;">
                        <span class="material-symbols-rounded" style="color: #d32f2f;">no_accounts</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.security.delete_title"><?php echo translation('settings.security.delete_title'); ?></h2>
                        <p class="component-card__description">
                            <span data-i18n="settings.security.delete_desc"><?php echo translation('settings.security.delete_desc'); ?></span> 
                            <?php echo $dateStr; ?>
                        </p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button danger" data-action="trigger-account-delete" data-i18n="settings.security.delete_btn">
                        <?php echo translation('settings.security.delete_btn'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
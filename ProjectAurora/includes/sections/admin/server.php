<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$serverConfig = getServerConfig($pdo);
$maintMode = (int)$serverConfig['maintenance_mode'];
$regMode = (int)$serverConfig['allow_registrations'];

// Función helper para renderizar los steppers
function renderStepper($titleKey, $descKey, $action, $value, $min, $max, $step1=1, $step10=10) {
    $transVal = $value; 
    $title = translation($titleKey, ['val' => $transVal]);
    $desc = translation($descKey, ['val' => $transVal]);
    $jsonVars = htmlspecialchars(json_encode(['val' => $transVal]), ENT_QUOTES, 'UTF-8');
    $valueId = 'stepper-value-' . str_replace('update-', '', $action);
    
    echo <<<HTML
    <div class="component-card component-card--column active">
        <div class="component-card__content">
            <div class="component-card__text">
                <h2 class="component-card__title" data-i18n="$titleKey" data-i18n-vars='$jsonVars'>$title</h2>
                <p class="component-card__description" data-i18n="$descKey" data-i18n-vars='$jsonVars'>$desc</p>
            </div>
        </div>
        <div class="component-card__actions">
            <div class="component-stepper component-stepper--multi" style="max-width: 265px;" 
                 data-action="$action" 
                 data-current-value="$value" 
                 data-min="$min" 
                 data-max="$max"
                 data-step-1="$step1"
                 data-step-10="$step10">
                <button type="button" class="stepper-button" data-step-action="decrement-10">
                    <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                </button>
                <button type="button" class="stepper-button" data-step-action="decrement-1">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
                <div class="stepper-value" id="$valueId">$value</div>
                <button type="button" class="stepper-button" data-step-action="increment-1">
                    <span class="material-symbols-rounded">chevron_right</span>
                </button>
                <button type="button" class="stepper-button" data-step-action="increment-10">
                    <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                </button>
            </div>
        </div>
    </div>
HTML;
}
?>

<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.server_title"><?php echo translation('admin.server_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.server_desc"><?php echo translation('admin.server_desc'); ?></p>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded">settings</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title">Configuración General</h2>
                    <p class="component-accordion__description">Estado del sitio y gestión de usuarios.</p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                
                <div class="component-card component-card--edit-mode active">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.server.maintenanceTitle">
                                <?php echo translation('admin.server.maintenanceTitle'); ?>
                            </h2>
                            <p class="component-card__description" data-i18n="admin.server.maintenanceDesc">
                                <?php echo translation('admin.server.maintenanceDesc'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="toggle-maintenance-mode" data-action="update-maintenance-mode" 
                                   <?php echo ($maintMode === 1) ? 'checked' : ''; ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="component-card component-card--edit-mode active">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.server.registrationTitle">
                                <?php echo translation('admin.server.registrationTitle'); ?>
                            </h2>
                            <p class="component-card__description" data-i18n="admin.server.registrationDesc">
                                <?php echo translation('admin.server.registrationDesc'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="toggle-allow-registration" data-action="update-registration-mode" 
                                   <?php echo ($regMode === 1) ? 'checked' : ''; ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded">person</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title">Reglas de Cuentas y Contraseñas</h2>
                    <p class="component-accordion__description">Límites para nombres de usuario, emails y contraseñas.</p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                <?php
                renderStepper('admin.server.minPasswordLengthTitle', 'admin.server.minPasswordLengthDesc', 'update-min-password-length', (int)$serverConfig['min_password_length'], 8, 72);
                renderStepper('admin.server.maxPasswordLengthTitle', 'admin.server.maxPasswordLengthDesc', 'update-max-password-length', (int)$serverConfig['max_password_length'], 8, 72);
                renderStepper('admin.server.minUsernameLengthTitle', 'admin.server.minUsernameLengthDesc', 'update-min-username-length', (int)$serverConfig['min_username_length'], 6, 32);
                renderStepper('admin.server.maxUsernameLengthTitle', 'admin.server.maxUsernameLengthDesc', 'update-max-username-length', (int)$serverConfig['max_username_length'], 6, 32);
                renderStepper('admin.server.maxEmailLengthTitle', 'admin.server.maxEmailLengthDesc', 'update-max-email-length', (int)$serverConfig['max_email_length'], 64, 255);
                ?>
            </div>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded">security</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title">Seguridad y Límites</h2>
                    <p class="component-accordion__description">Protección contra fuerza bruta y tiempos de espera.</p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                <?php
                // [NUEVO] Sección Anti-Spam
                renderStepper('admin.server.chatMsgLimitTitle', 'admin.server.chatMsgLimitDesc', 'update-chat-limit-count', (int)($serverConfig['chat_msg_limit'] ?? 5), 1, 50);
                renderStepper('admin.server.chatTimeWindowTitle', 'admin.server.chatTimeWindowDesc', 'update-chat-limit-time', (int)($serverConfig['chat_time_window'] ?? 10), 1, 60);

                renderStepper('admin.server.maxLoginAttemptsTitle', 'admin.server.maxLoginAttemptsDesc', 'update-max-login-attempts', (int)$serverConfig['max_login_attempts'], 3, 20);
                renderStepper('admin.server.lockoutTimeMinutesTitle', 'admin.server.lockoutTimeMinutesDesc', 'update-lockout-time-minutes', (int)$serverConfig['lockout_time_minutes'], 1, 60);
                renderStepper('admin.server.codeResendCooldownTitle', 'admin.server.codeResendCooldownDesc', 'update-code-resend-cooldown', (int)$serverConfig['code_resend_cooldown'], 30, 300, 5, 15);
                
                renderStepper('admin.server.usernameCooldownTitle', 'admin.server.usernameCooldownDesc', 'update-username-cooldown', (int)$serverConfig['username_cooldown'], 1, 365);
                renderStepper('admin.server.emailCooldownTitle', 'admin.server.emailCooldownDesc', 'update-email-cooldown', (int)$serverConfig['email_cooldown'], 1, 365);
                
                renderStepper('admin.server.profilePictureMaxSizeTitle', 'admin.server.profilePictureMaxSizeDesc', 'update-avatar-max-size', (int)$serverConfig['profile_picture_max_size'], 1, 20);
                ?>
            </div>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded">mail</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title" data-i18n="admin.server.domainsTitle"><?php echo translation('admin.server.domainsTitle'); ?></h2>
                    <p class="component-card__description" data-i18n="admin.server.domainsDesc"><?php echo translation('admin.server.domainsDesc'); ?></p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                
                <div class="component-card component-card--column active">
                    <div class="component-card__content" style="align-items: flex-start; width:100%;">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.server.domainsListTitle"><?php echo translation('admin.server.domainsListTitle'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.server.domainsListDesc" style="margin-bottom: 16px;">
                                <?php echo translation('admin.server.domainsListDesc'); ?>
                            </p>
                            
                            <?php 
                                $domains = json_decode($serverConfig['allowed_email_domains'] ?? '[]', true);
                                if(!is_array($domains)) $domains = [];
                            ?>
                            <div id="domain-list-container" class="domain-chips-container">
                                <?php foreach($domains as $dom): ?>
                                    <div class="domain-chip">
                                        <span class="material-symbols-rounded chip-icon">language</span>
                                        <span><?php echo htmlspecialchars($dom); ?></span>
                                        <span class="material-symbols-rounded chip-remove" data-action="remove-domain" data-domain="<?php echo htmlspecialchars($dom); ?>">close</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="add-domain-btn-wrapper">
                                <button class="component-button primary" data-action="show-add-domain-form">
                                    <span data-i18n="admin.server.add_domain_btn"><?php echo translation('admin.server.add_domain_btn'); ?></span>
                                </button>
                            </div>

                            <div id="add-domain-form-wrapper" class="add-domain-form d-none">
                                <div class="component-input-wrapper w-100">
                                    <input type="text" id="new-domain-input" class="component-text-input full-width" 
                                           placeholder="ej. gmail.com" style="background:#fff;">
                                </div>
                                <div class="add-domain-actions">
                                    <button class="component-button" data-action="cancel-add-domain" data-i18n="admin.server.cancel_domain">
                                        <?php echo translation('admin.server.cancel_domain'); ?>
                                    </button>
                                    <button class="component-button primary" data-action="save-new-domain" data-i18n="admin.server.save_domain">
                                        <?php echo translation('admin.server.save_domain'); ?>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
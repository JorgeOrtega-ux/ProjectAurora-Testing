<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

$sConfig = isset($GLOBALS['serverConfig']) ? $GLOBALS['serverConfig'] : getServerConfig($pdo);

// [MODIFICADO] profile_picture
$stmt = $pdo->prepare("SELECT username, email, profile_picture, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$currentEmail = $currentUser['email'] ?? 'correo@ejemplo.com';
$userProfilePic = $currentUser['profile_picture'] ?? null;
$userRole = $currentUser['role'] ?? 'user';

// [MODIFICADO] Agregamos message_privacy a la consulta
$stmtPrefs = $pdo->prepare("SELECT usage_intent, language, open_links_in_new_tab, message_privacy FROM user_preferences WHERE user_id = ?");
$stmtPrefs->execute([$userId]);
$prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

$currentUsage = $prefs['usage_intent'] ?? 'personal';
$currentLang = $prefs['language'] ?? 'en-us';
$openLinksInNewTab = isset($prefs['open_links_in_new_tab']) ? (int)$prefs['open_links_in_new_tab'] : 1;
// [NUEVO] Obtener privacidad
$currentMsgPrivacy = $prefs['message_privacy'] ?? 'friends';

$usageIcons = [
    'personal' => 'person',
    'student' => 'school',
    'teacher' => 'history_edu',
    'small_business' => 'storefront',
    'large_business' => 'domain'
];
$usageDisplayIcon = $usageIcons[$currentUsage] ?? 'person'; 

$langDisplayText = translation('languages.en_us');
if($currentLang == 'es-latam') $langDisplayText = translation('languages.es_latam');
if($currentLang == 'es-mx') $langDisplayText = translation('languages.es_mx');
if($currentLang == 'en-gb') $langDisplayText = translation('languages.en_gb');

$pfpUrl = null;
if ($userProfilePic && !empty($userProfilePic)) {
    $pfpUrl = $basePath . $userProfilePic . '?t=' . time();
}

$isDefaultPfp = false;
if (empty($userProfilePic) || strpos($userProfilePic, '/default/') !== false) {
    $isDefaultPfp = true;
}
$hasCustomPfp = !$isDefaultPfp && ($pfpUrl !== null);

// [NUEVO] Mapa de iconos para privacidad
$privacyIcons = [
    'everyone' => 'public',
    'friends' => 'group',
    'nobody' => 'lock'
];
$currentPrivacyIcon = $privacyIcons[$currentMsgPrivacy];
?>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.profile.title"><?php echo translation('settings.profile.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.profile.description"><?php echo translation('settings.profile.description'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item" data-component="profile-picture-section">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="file" class="visually-hidden" data-element="profile-picture-upload-input" name="profile_picture" accept="image/png, image/jpeg, image/gif, image/webp">

                <div class="component-card__content">
                    <div class="component-card__profile-picture" data-element="profile-picture-preview-container" data-role="<?php echo htmlspecialchars($userRole); ?>">
                        <?php if ($pfpUrl): ?>
                            <img src="<?php echo htmlspecialchars($pfpUrl); ?>" alt="<?php echo translation('settings.profile.alt_avatar'); ?>" class="component-card__avatar-image" data-element="profile-picture-preview-image">
                        <?php else: ?>
                            <img src="" alt="<?php echo translation('settings.profile.alt_no_avatar'); ?>" class="component-card__avatar-image d-none" data-element="profile-picture-preview-image">
                            <span class="material-symbols-rounded default-avatar-icon avatar-placeholder-icon">person</span>
                        <?php endif; ?>

                        <div class="component-card__avatar-overlay" data-action="trigger-profile-picture-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.profile.profile_picture_title"><?php echo translation('settings.profile.profile_picture_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.profile.profile_picture_desc"><?php echo translation('settings.profile.profile_picture_desc'); ?></p>
                        <p class="component-card__meta" data-i18n="settings.profile.profile_picture_meta">
                            <?php echo translation('settings.profile.profile_picture_meta', ['size' => $sConfig['profile_picture_max_size']]); ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <div data-state="profile-picture-actions-default" class="<?php echo !$hasCustomPfp ? 'active' : 'disabled'; ?>">
                        <button type="button" class="component-button" data-action="profile-picture-upload-trigger" data-i18n="settings.profile.upload_btn"><?php echo translation('settings.profile.upload_btn'); ?></button>
                    </div>

                    <div data-state="profile-picture-actions-custom" class="<?php echo $hasCustomPfp ? 'active' : 'disabled'; ?>">
                        <button type="button" class="component-button" data-action="profile-picture-remove-trigger" data-i18n="global.delete"><?php echo translation('global.delete'); ?></button>
                        <button type="button" class="component-button" data-action="profile-picture-change-trigger" data-i18n="settings.profile.change_btn"><?php echo translation('settings.profile.change_btn'); ?></button>
                    </div>

                    <div data-state="profile-picture-actions-preview" class="disabled">
                        <button type="button" class="component-button" data-action="profile-picture-cancel-trigger" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save-trigger-btn" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title" data-i18n="settings.profile.username_title"><?php echo translation('settings.profile.username_title'); ?></h2>
                        <div data-state="username-view-state" class="active">
                            <p class="component-card__description" data-element="username-display-text">
                                <?php echo htmlspecialchars($currentUsername); ?>
                            </p>
                        </div>
                        <div data-state="username-edit-state" class="disabled">
                            <div class="input-with-actions">
                                <input type="text" class="component-text-input" data-element="username-input"
                                    value="<?php echo htmlspecialchars($currentUsername); ?>"
                                    required 
                                    minlength="<?php echo $sConfig['min_username_length']; ?>" 
                                    maxlength="<?php echo $sConfig['max_username_length']; ?>">
                                <div data-state="username-actions-edit" class="disabled">
                                    <button type="button" class="component-button" data-action="username-cancel-trigger" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                                    <button type="button" class="component-button primary" data-action="username-save-trigger-btn" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                                </div>
                            </div>
                            <p class="component-card__meta" data-i18n="settings.profile.username_meta">
                                <?php echo translation('settings.profile.username_meta', ['min' => $sConfig['min_username_length'], 'max' => $sConfig['max_username_length']]); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="username-edit-trigger" data-i18n="global.edit"><?php echo translation('global.edit'); ?></button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title" data-i18n="settings.profile.email_title"><?php echo translation('settings.profile.email_title'); ?></h2>
                        <div data-state="email-view-state" class="active">
                            <p class="component-card__description" data-element="email-display-text">
                                <?php echo htmlspecialchars($currentEmail); ?>
                            </p>
                        </div>
                        <div data-state="email-edit-state" class="disabled">
                            <div class="input-with-actions">
                                <input type="email" class="component-text-input" data-element="email-input"
                                    value="<?php echo htmlspecialchars($currentEmail); ?>"
                                    required>
                                <div data-state="email-actions-edit" class="disabled">
                                    <button type="button" class="component-button" data-action="email-cancel-trigger" data-i18n="global.cancel"><?php echo translation('global.cancel'); ?></button>
                                    <button type="button" class="component-button primary" data-action="email-save-trigger-btn" data-i18n="global.save"><?php echo translation('global.save'); ?></button>
                                </div>
                            </div>
                            <p class="component-card__meta" data-i18n="settings.profile.email_meta"><?php echo translation('settings.profile.email_meta'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="email-edit-trigger" data-i18n="global.edit"><?php echo translation('global.edit'); ?></button>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.profile.usage_title"><?php echo translation('settings.profile.usage_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.profile.usage_desc"><?php echo translation('settings.profile.usage_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper">
                        <div class="trigger-selector" data-action="toggleModuleUsageSelect">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded"><?php echo htmlspecialchars($usageDisplayIcon); ?></span>
                            </div>
                            <div class="trigger-select-text">
                                <span data-i18n="settings.usage_options.<?php echo $currentUsage; ?>"><?php echo translation("settings.usage_options.{$currentUsage}"); ?></span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleUsageSelect" data-preference-type="usage">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <?php
                                    $usageOptions = [
                                        ['val' => 'personal', 'icon' => $usageIcons['personal'], 'i18n' => 'settings.usage_options.personal'],
                                        ['val' => 'student', 'icon' => $usageIcons['student'], 'i18n' => 'settings.usage_options.student'],
                                        ['val' => 'teacher', 'icon' => $usageIcons['teacher'], 'i18n' => 'settings.usage_options.teacher'],
                                        ['val' => 'small_business', 'icon' => $usageIcons['small_business'], 'i18n' => 'settings.usage_options.small_business'],
                                        ['val' => 'large_business', 'icon' => $usageIcons['large_business'], 'i18n' => 'settings.usage_options.large_business'],
                                    ];
                                    foreach ($usageOptions as $opt): 
                                        $isActive = ($currentUsage === $opt['val']) ? 'active' : '';
                                        $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                    ?>
                                    <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                        <div class="menu-link-text"><span data-i18n="<?php echo $opt['i18n']; ?>"><?php echo translation($opt['i18n']); ?></span></div>
                                        <div class="menu-link-icon"><?php echo $check; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.profile.lang_title"><?php echo translation('settings.profile.lang_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.profile.lang_desc"><?php echo translation('settings.profile.lang_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper">
                        <div class="trigger-selector" data-action="toggleModuleLanguageSelect">
                            <div class="trigger-select-icon"><span class="material-symbols-rounded">language</span></div>
                            <div class="trigger-select-text"><span><?php echo htmlspecialchars($langDisplayText); ?></span></div>
                            <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
                        </div>
                        <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleLanguageSelect" data-preference-type="language">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <?php 
                                    $langOptions = [
                                        ['val' => 'es-latam', 'icon' => 'language', 'label_key' => 'languages.es_latam'],
                                        ['val' => 'es-mx', 'icon' => 'language', 'label_key' => 'languages.es_mx'],
                                        ['val' => 'en-us', 'icon' => 'language', 'label_key' => 'languages.en_us'],
                                        ['val' => 'en-gb', 'icon' => 'language', 'label_key' => 'languages.en_gb'],
                                    ];
                                    foreach ($langOptions as $opt): 
                                        $isActive = ($currentLang === $opt['val']) ? 'active' : '';
                                        $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                        $label = translation($opt['label_key']);
                                    ?>
                                    <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                        <div class="menu-link-text"><span data-i18n="<?php echo $opt['label_key']; ?>"><?php echo $label; ?></span></div>
                                        <div class="menu-link-icon"><?php echo $check; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.profile.privacy_msg_title">
                            <?php echo translation('settings.profile.privacy_msg_title'); ?>
                        </h2>
                        <p class="component-card__description" data-i18n="settings.profile.privacy_msg_desc">
                            <?php echo translation('settings.profile.privacy_msg_desc'); ?>
                        </p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper">
                        <div class="trigger-selector" data-action="toggleModulePrivacySelect">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded"><?php echo $currentPrivacyIcon; ?></span>
                            </div>
                            <div class="trigger-select-text">
                                <span data-i18n="settings.profile.privacy_options.<?php echo $currentMsgPrivacy; ?>">
                                    <?php echo translation("settings.profile.privacy_options.{$currentMsgPrivacy}"); ?>
                                </span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" 
                             data-module="modulePrivacySelect" 
                             data-preference-type="message_privacy">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <?php
                                    $privOptions = [
                                        ['val' => 'everyone', 'icon' => 'public'],
                                        ['val' => 'friends', 'icon' => 'group'],
                                        ['val' => 'nobody', 'icon' => 'lock']
                                    ];
                                    foreach ($privOptions as $opt): 
                                        $isActive = ($currentMsgPrivacy === $opt['val']) ? 'active' : '';
                                        $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                    ?>
                                    <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span>
                                        </div>
                                        <div class="menu-link-text">
                                            <span data-i18n="settings.profile.privacy_options.<?php echo $opt['val']; ?>">
                                                <?php echo translation("settings.profile.privacy_options.{$opt['val']}"); ?>
                                            </span>
                                        </div>
                                        <div class="menu-link-icon"><?php echo $check; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="component-card component-card--edit-mode" data-component="toggle-new-tab-card">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.new_tab_title"><?php echo translation('settings.profile.new_tab_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.profile.new_tab_desc"><?php echo translation('settings.profile.new_tab_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" 
                           data-element="toggle-new-tab" 
                           data-preference-type="boolean" 
                           data-field-name="open_links_in_new_tab" 
                           <?php echo ($openLinksInNewTab == 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$currentTheme = $_SESSION['user_theme'] ?? 'system';
$extendedMsg = isset($_SESSION['user_extended_msg']) ? (int)$_SESSION['user_extended_msg'] : 0;

$themeLabelMap = [
    'system' => 'settings.accessibility.theme_options.system',
    'light' => 'settings.accessibility.theme_options.light',
    'dark' => 'settings.accessibility.theme_options.dark'
];
$currentThemeLabel = $themeLabelMap[$currentTheme];
$currentThemeIcon = ($currentTheme === 'light') ? 'light_mode' : (($currentTheme === 'dark') ? 'dark_mode' : 'desktop_windows');
?>
<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.accessibility.title"><?php echo translation('settings.accessibility.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.accessibility.description"><?php echo translation('settings.accessibility.description'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.accessibility.theme_title"><?php echo translation('settings.accessibility.theme_title'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.accessibility.theme_desc"><?php echo translation('settings.accessibility.theme_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper">
                        <div class="trigger-selector" data-action="toggleModuleThemeSelect">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded"><?php echo $currentThemeIcon; ?></span>
                            </div>
                            <div class="trigger-select-text">
                                <span data-i18n="<?php echo $currentThemeLabel; ?>"><?php echo translation($currentThemeLabel); ?></span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleThemeSelect" data-preference-type="theme">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <?php 
                                    $options = [
                                        ['val' => 'system', 'icon' => 'desktop_windows', 'key' => 'settings.accessibility.theme_options.system'],
                                        ['val' => 'light',  'icon' => 'light_mode',      'key' => 'settings.accessibility.theme_options.light'],
                                        ['val' => 'dark',   'icon' => 'dark_mode',       'key' => 'settings.accessibility.theme_options.dark']
                                    ];
                                    foreach($options as $opt): 
                                        $isActive = ($currentTheme === $opt['val']) ? 'active' : '';
                                        $check = $isActive ? '<span class="material-symbols-rounded">check</span>' : '';
                                    ?>
                                    <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                        <div class="menu-link-text"><span data-i18n="<?php echo $opt['key']; ?>"><?php echo translation($opt['key']); ?></span></div>
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

        <div class="component-card component-card--edit-mode">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.accessibility.msg_time_title"><?php echo translation('settings.accessibility.msg_time_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.accessibility.msg_time_desc"><?php echo translation('settings.accessibility.msg_time_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" 
                           data-element="toggle-msg-persistence" 
                           data-preference-type="boolean" 
                           data-field-name="extended_message_time"
                           <?php echo ($extendedMsg === 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>
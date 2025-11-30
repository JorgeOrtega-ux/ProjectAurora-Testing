<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-status">

    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions" data-i18n="global.actions"><?php echo translation('global.actions'); ?></span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button d-none" id="btn-lift-ban" 
                        data-i18n-tooltip="admin.status.lift_ban" 
                        data-tooltip="<?php echo translation('admin.status.lift_ban'); ?>">
                    <span class="material-symbols-rounded">lock_open</span>
                </button>
                
                <button class="component-icon-button" id="btn-save-status" 
                        data-i18n-tooltip="admin.status.apply_ban" 
                        data-tooltip="<?php echo translation('admin.status.apply_ban'); ?>">
                    <span class="material-symbols-rounded">save</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.status.page_title"><?php echo translation('admin.status.page_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.status.page_desc"><?php echo translation('admin.status.page_desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="status-pfp-container">
                        <img src="" id="status-user-avatar" class="component-card__avatar-image hidden-avatar" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="status-user-icon">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="status-username" data-i18n="global.loading"><?php echo translation('global.loading'); ?></h2>
                        <p class="component-card__description" id="status-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped mt-16">
            <input type="hidden" id="target-user-id" value="<?php echo htmlspecialchars($targetUid); ?>">

            <input type="hidden" id="input-status-value" value="">
            <input type="hidden" id="input-duration-value" value="">
            <input type="hidden" id="input-reason-value" value="">

            <div id="active-sanction-alert" class="component-group-item d-none" style="background-color: #f5f5f5; border-bottom: 1px solid #e0e0e0;">
                <div class="component-card__content">
                    <div class="component-icon-container" style="border-color: #e0e0e0; background: #fff;">
                        <span class="material-symbols-rounded">warning</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.status.already_suspended"><?php echo translation('admin.status.already_suspended'); ?></h2>
                        <p class="component-card__description" id="active-sanction-desc">...</p>
                    </div>
                </div>
            </div>

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.status.type_label"><?php echo translation('admin.status.type_label'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.status.type_desc"><?php echo translation('admin.status.type_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-status-options">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="current-status-icon">gavel</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="current-status-text" data-i18n="admin.status.select_type"><?php echo translation('admin.status.select_type'); ?></span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-status-options">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link"
                                        data-action="select-status-option"
                                        data-value="suspended_temp"
                                        data-label="<?php echo translation('admin.status.temp_ban'); ?>"
                                        data-icon="timer">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded status-temp">timer</span>
                                        </div>
                                        <div class="menu-link-text" data-i18n="admin.status.temp_ban"><?php echo translation('admin.status.temp_ban'); ?></div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                    <div class="menu-link"
                                        data-action="select-status-option"
                                        data-value="suspended_perm"
                                        data-label="<?php echo translation('admin.status.perm_ban'); ?>"
                                        data-icon="block">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded status-perm">block</span>
                                        </div>
                                        <div class="menu-link-text" data-i18n="admin.status.perm_ban"><?php echo translation('admin.status.perm_ban'); ?></div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-duration" class="w-100 d-none">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.status.duration_label"><?php echo translation('admin.status.duration_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.status.duration_desc"><?php echo translation('admin.status.duration_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-duration">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">calendar_today</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-duration-text" data-i18n="admin.status.select_duration"><?php echo translation('admin.status.select_duration'); ?></span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-duration">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $daysOptions = [2, 4, 6, 8, 12, 30];
                                        foreach ($daysOptions as $d) {
                                            $daysLabel = translation('global.days');
                                            echo "
                                            <div class='menu-link' data-action='select-duration-option' data-value='$d'>
                                                <div class='menu-link-icon'>
                                                    <span class='material-symbols-rounded'>schedule</span>
                                                </div>
                                                <div class='menu-link-text'>{$d} {$daysLabel}</div>
                                                <div class='menu-link-icon'></div>
                                            </div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-reason" class="w-100 d-none">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.status.reason_label"><?php echo translation('admin.status.reason_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.status.reason_desc"><?php echo translation('admin.status.reason_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-reasons">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">gavel</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-reason-text" data-i18n="admin.status.select_reason"><?php echo translation('admin.status.select_reason'); ?></span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-reasons">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $reasons = [
                                            ['key' => 'admin.reasons.tos', 'val' => translation('admin.reasons.tos')],
                                            ['key' => 'admin.reasons.harassment', 'val' => translation('admin.reasons.harassment')],
                                            ['key' => 'admin.reasons.spam', 'val' => translation('admin.reasons.spam')],
                                            ['key' => 'admin.reasons.security', 'val' => translation('admin.reasons.security')],
                                            ['key' => 'admin.reasons.verification', 'val' => translation('admin.reasons.verification')]
                                        ];
                                        foreach ($reasons as $r) {
                                            $val = htmlspecialchars($r['val']);
                                            $key = $r['key'];
                                            echo "<div class='menu-link' data-action=\"select-reason-option\" data-value=\"$val\">
                                                    <div class='menu-link-icon'><span class='material-symbols-rounded'>gavel</span></div>
                                                    <div class='menu-link-text' data-i18n='$key'>$val</div>
                                                    <div class='menu-link-icon'></div>
                                                  </div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

       <div class="component-card component-card--grouped mt-16">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">history</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.history.card_title"><?php echo translation('admin.history.card_title'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.history.card_desc"><?php echo translation('admin.history.card_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" 
                            data-nav="admin/user-history?uid=<?php echo htmlspecialchars($targetUid); ?>" 
                            data-i18n="admin.history.view_btn">
                        <?php echo translation('admin.history.view_btn'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
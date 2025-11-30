<?php
// includes/sections/admin/user-manage.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-manage">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;" data-i18n="global.status"><?php echo translation('global.status'); ?></span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" id="btn-save-manage" 
                        data-i18n-tooltip="global.save_status" 
                        data-tooltip="<?php echo translation('global.save_status'); ?>">
                    <span class="material-symbols-rounded">save</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.manage.title"><?php echo translation('admin.manage.title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.manage.desc"><?php echo translation('admin.manage.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="manage-pfp-container">
                        <img src="" id="manage-user-avatar" class="component-card__avatar-image" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="manage-user-icon" style="font-size: 24px;">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="manage-username" data-i18n="global.loading"><?php echo translation('global.loading'); ?></h2>
                        <p class="component-card__description" id="manage-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped" style="margin-top: 16px;">
            <input type="hidden" id="manage-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            
            <input type="hidden" id="manage-status-value" value="active">
            <input type="hidden" id="manage-deletion-type" value="admin_decision">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.manage.status_label"><?php echo translation('admin.manage.status_label'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.manage.status_desc"><?php echo translation('admin.manage.status_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-manage-status">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="manage-status-icon" style="color: #2e7d32;">check_circle</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="manage-status-text" data-i18n="global.active"><?php echo translation('global.active'); ?></span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        
                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-manage-status">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link active" 
                                         data-action="select-manage-status" 
                                         data-value="active" 
                                         data-label="<?php echo translation('global.active'); ?>" 
                                         data-icon="check_circle" 
                                         data-color="#2e7d32">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#2e7d32">check_circle</span></div>
                                        <div class="menu-link-text" data-i18n="global.active"><?php echo translation('global.active'); ?></div>
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                    </div>
                                    <div class="menu-link" 
                                         data-action="select-manage-status" 
                                         data-value="deleted" 
                                         data-label="<?php echo translation('global.deleted'); ?>" 
                                         data-icon="delete_forever" 
                                         data-color="#616161">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#616161">delete_forever</span></div>
                                        <div class="menu-link-text" data-i18n="global.deleted"><?php echo translation('global.deleted'); ?></div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-deletion-details" class="w-100 d-none">
                <hr class="component-divider">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.manage.decision_label"><?php echo translation('admin.manage.decision_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.manage.decision_desc"><?php echo translation('admin.manage.decision_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-deletion-type">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">gavel</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="text-deletion-type" data-i18n="admin.manage.admin_dec"><?php echo translation('admin.manage.admin_dec'); ?></span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>
                            
                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-deletion-type">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link active" 
                                             data-action="select-deletion-type" 
                                             data-value="admin_decision" 
                                             data-label="<?php echo translation('admin.manage.admin_dec'); ?>">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                                            <div class="menu-link-text" data-i18n="admin.manage.admin_dec"><?php echo translation('admin.manage.admin_dec'); ?></div>
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                        </div>
                                        <div class="menu-link" 
                                             data-action="select-deletion-type" 
                                             data-value="user_decision" 
                                             data-label="<?php echo translation('admin.manage.user_dec'); ?>">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                                            <div class="menu-link-text" data-i18n="admin.manage.user_dec"><?php echo translation('admin.manage.user_dec'); ?></div>
                                            <div class="menu-link-icon"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-user-reason" class="d-none">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.manage.user_reason_label"><?php echo translation('admin.manage.user_reason_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.manage.user_reason_desc"><?php echo translation('admin.manage.user_reason_desc'); ?></p>
                        </div>
                        <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                            <textarea id="input-user-reason" 
                                      class="component-text-input full-width" 
                                      style="height: 80px; padding: 10px;" 
                                      data-i18n-placeholder="admin.manage.user_reason_placeholder"
                                      placeholder="<?php echo translation('admin.manage.user_reason_placeholder'); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.manage.admin_comments_label"><?php echo translation('admin.manage.admin_comments_label'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.manage.admin_comments_desc"><?php echo translation('admin.manage.admin_comments_desc'); ?></p>
                    </div>
                    <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                        <textarea id="input-admin-comments" 
                                  class="component-text-input full-width" 
                                  style="height: 80px; padding: 10px;" 
                                  data-i18n-placeholder="admin.manage.admin_comments_placeholder"
                                  placeholder="<?php echo translation('admin.manage.admin_comments_placeholder'); ?>"></textarea>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
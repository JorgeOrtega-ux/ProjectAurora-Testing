<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}
?>
<link rel="stylesheet" href="<?php echo $basePath ?? '/ProjectAurora/'; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/dashboard">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <span class="toolbar-title-actions" style="margin-left: 8px;" data-i18n="nav.admin_dashboard"><?php echo translation('nav.admin_dashboard'); ?></span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" data-nav="admin/alerts" 
                        data-i18n-tooltip="admin.alerts_title" 
                        data-tooltip="<?php echo translation('admin.alerts_title'); ?>">
                    <span class="material-symbols-rounded">campaign</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar" style="max-width: 100%;">

        <div class="dashboard-stats-grid">
            
            <div class="component-card component-card--column">
                <div class="component-card__content" style="width:100%; gap:16px;">
                    <div class="component-icon-container" style="background-color: #e3f2fd; border-color: #bbdefb;">
                        <span class="material-symbols-rounded" style="color: #1976d2;">group</span>
                    </div>
                    <div class="component-card__text">
                        <span class="dashboard-stat-label" data-i18n="admin.dashboard.total_users"><?php echo translation('admin.dashboard.total_users'); ?></span>
                        <h2 class="dashboard-stat-value" id="stat-total-users">...</h2>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--column">
                <div class="component-card__content" style="width:100%; gap:16px;">
                    <div class="component-icon-container" style="background-color: #e8f5e9; border-color: #c8e6c9;">
                        <span class="material-symbols-rounded" style="color: #2e7d32;">wifi</span>
                    </div>
                    <div class="component-card__text">
                        <span class="dashboard-stat-label" data-i18n="admin.dashboard.online_users"><?php echo translation('admin.dashboard.online_users'); ?></span>
                        <h2 class="dashboard-stat-value" id="stat-online-users" style="color: #2e7d32;">...</h2>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--column">
                <div class="component-card__content" style="width:100%; gap:16px;">
                    <div class="component-icon-container" style="background-color: #fff3e0; border-color: #ffe0b2;">
                        <span class="material-symbols-rounded" style="color: #f57c00;">person_add</span>
                    </div>
                    <div class="component-card__text">
                        <span class="dashboard-stat-label" data-i18n="admin.dashboard.new_users_today"><?php echo translation('admin.dashboard.new_users_today'); ?></span>
                        <h2 class="dashboard-stat-value" id="stat-new-users">...</h2>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--column">
                <div class="component-card__content" style="width:100%; gap:16px;">
                    <div class="component-icon-container" style="background-color: #f3e5f5; border-color: #e1bee7;">
                        <span class="material-symbols-rounded" style="color: #7b1fa2;">devices</span>
                    </div>
                    <div class="component-card__text">
                        <span class="dashboard-stat-label" data-i18n="admin.dashboard.active_sessions"><?php echo translation('admin.dashboard.active_sessions'); ?></span>
                        <h2 class="dashboard-stat-value" id="stat-active-sessions">...</h2>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
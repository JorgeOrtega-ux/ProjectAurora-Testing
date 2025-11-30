<?php
// includes/modules/module-surface.php
if (session_status() === PHP_SESSION_NONE) session_start();

$CURRENT_SECTION = $CURRENT_SECTION ?? 'main';

$isSettings = (strpos($CURRENT_SECTION, 'settings/') === 0);
$isAdminSection = (strpos($CURRENT_SECTION, 'admin/') === 0);

$userRole = $_SESSION['user_role'] ?? 'user';
$canSeeAdmin = in_array($userRole, ['founder', 'administrator']);
?>
<div class="module-content module-surface body-title disabled" data-module="moduleSurface">
    <div class="menu-content">
        
        <div id="sidebar-menu-app" class="menu-content-layout" style="display: <?php echo (!$isSettings && !$isAdminSection) ? 'flex' : 'none'; ?>;">
            <div class="menu-content-group-top menu-list">
                
                <div class="menu-link <?php echo ($CURRENT_SECTION === 'main') ? 'active' : ''; ?>"
                    data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">home</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.home"><?php echo translation('nav.home'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'explorer') ? 'active' : ''; ?>"
                    data-nav="explorer">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">explore</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.explore"><?php echo translation('nav.explore'); ?></span>
                    </div>
                </div>

            </div>
        </div>

        <div id="sidebar-menu-settings" class="menu-content-layout" style="display: <?php echo $isSettings ? 'flex' : 'none'; ?>;">
            <div class="menu-content-group-top menu-list">
                
                <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 5px;">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="global.back_home"><?php echo translation('global.back_home'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/your-profile') ? 'active' : ''; ?>"
                    data-nav="settings/your-profile">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">person</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.profile"><?php echo translation('nav.profile'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/login-security') ? 'active' : ''; ?>"
                    data-nav="settings/login-security">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.security"><?php echo translation('nav.security'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/accessibility') ? 'active' : ''; ?>"
                    data-nav="settings/accessibility">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">accessibility_new</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.accessibility"><?php echo translation('nav.accessibility'); ?></span>
                    </div>
                </div>

            </div>
        </div>

        <?php if ($canSeeAdmin): ?>
        <div id="sidebar-menu-admin" class="menu-content-layout" style="display: <?php echo $isAdminSection ? 'flex' : 'none'; ?>;">
            
            <div class="menu-content-group-top menu-list">
                
                <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 5px;">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="global.back_home"><?php echo translation('global.back_home'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/dashboard') ? 'active' : ''; ?>"
                    data-nav="admin/dashboard">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">dashboard</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.admin_dashboard"><?php echo translation('nav.admin_dashboard'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/users') ? 'active' : ''; ?>"
                    data-nav="admin/users">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">group</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.admin_users"><?php echo translation('nav.admin_users'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/communities' || $CURRENT_SECTION === 'admin/community-edit') ? 'active' : ''; ?>"
                    data-nav="admin/communities">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">groups</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Comunidades</span>
                    </div>
                </div>

            </div>

            <div class="menu-content-group-bottom menu-list">
                
                <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/backups') ? 'active' : ''; ?>"
                    data-nav="admin/backups">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">backup</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.admin_backups"><?php echo translation('nav.admin_backups'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/server') ? 'active' : ''; ?>"
                    data-nav="admin/server">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">dns</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-i18n="nav.admin_server"><?php echo translation('nav.admin_server'); ?></span>
                    </div>
                </div>

            </div>

        </div>
        <?php endif; ?>

    </div>
</div>
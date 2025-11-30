<div class="header">
    <div class="header-left">
        <div class="header-item">
            <div class="header-button" 
                 data-action="toggleModuleSurface" 
                 data-i18n-tooltip="header.menu_tooltip"
                 data-tooltip="<?php echo translation('header.menu_tooltip'); ?>">
                <span class="material-symbols-rounded">menu</span>
            </div>
        </div>
    </div>

    <div class="header-center">
        <div class="search-container">
            <span class="material-symbols-rounded search-icon">search</span>
            <input type="text" class="search-input" 
                   data-i18n-placeholder="header.search_placeholder" 
                   placeholder="<?php echo translation('header.search_placeholder'); ?>"
                   spellcheck="false">
        </div>
    </div>

    <div class="header-right">
        <div class="header-item">
            
            <div class="header-button"
                 data-nav="join-community"
                 data-i18n-tooltip="nav.join_community_tooltip"
                 data-tooltip="Unirse con código">
                <span class="material-symbols-rounded">vpn_key</span>
            </div>

            <div class="header-button"
                 data-action="toggleModuleNotifications"
                 data-i18n-tooltip="header.notifications_tooltip"
                 data-tooltip="<?php echo translation('header.notifications_tooltip'); ?>">
                <span class="material-symbols-rounded">notifications</span>
            </div>

            <?php
            $userRole = $_SESSION['user_role'] ?? 'user';
            ?>
            <div class="header-button profile-button"
                data-action="toggleModuleOptions"
                data-role="<?php echo htmlspecialchars($userRole); ?>"
                data-i18n-tooltip="header.profile_tooltip"
                data-tooltip="<?php echo translation('header.profile_tooltip'); ?>"> 
                <?php
                if (isset($_SESSION['user_profile_picture']) && !empty($_SESSION['user_profile_picture'])) {
                    $pfpUrl = $basePath . $_SESSION['user_profile_picture'];
                    echo '<img src="' . htmlspecialchars($pfpUrl) . '" alt="' . translation('header.alt_profile') . '" class="profile-img">';
                } else {
                    echo '<span class="material-symbols-rounded">person</span>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="popover-module popover-notifications disabled" data-module="moduleNotifications">
        <div class="menu-content">
            <div class="pill-container"><div class="drag-handle"></div></div>
            <div class="menu-content-top">
                <span class="menu-title" data-i18n="header.notifications_title"><?php echo translation('header.notifications_title'); ?></span>
                <div class="notifications-action" data-i18n-title="header.mark_read" title="<?php echo translation('header.mark_read'); ?>">
                    <span data-i18n="header.mark_read"><?php echo translation('header.mark_read'); ?></span>
                </div>
            </div>
            <div class="menu-content-bottom">
                <div class="notifications-empty">
                    <span class="material-symbols-rounded empty-icon">notifications_off</span>
                    <p data-i18n="header.no_notifications"><?php echo translation('header.no_notifications'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="popover-module popover-profile body-title disabled" data-module="moduleOptions">
        <div class="menu-content">
            <div class="pill-container"><div class="drag-handle"></div></div>
            <div class="menu-list">
                <?php if (in_array($userRole, ['founder', 'administrator'])): ?>
                <div class="menu-link" data-nav="admin">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                    <div class="menu-link-text"><span data-i18n="nav.admin_panel"><?php echo translation('nav.admin_panel'); ?></span></div>
                </div>
                <?php endif; ?>
                <div class="menu-link" data-nav="settings/your-profile">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                    <div class="menu-link-text"><span data-i18n="nav.settings"><?php echo translation('nav.settings'); ?></span></div>
                </div>
                <div class="menu-link">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                    <div class="menu-link-text"><span data-i18n="nav.help"><?php echo translation('nav.help'); ?></span></div>
                </div>
                <div class="menu-link menu-link-logout">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">logout</span></div>
                    <div class="menu-link-text"><span data-i18n="nav.logout"><?php echo translation('nav.logout'); ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>
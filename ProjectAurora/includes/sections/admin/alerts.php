<?php
// includes/sections/admin/alerts.php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/alerts">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions" data-i18n="admin.alerts_title"><?php echo translation('admin.alerts_title'); ?></span>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.alerts_title"><?php echo translation('admin.alerts_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.alerts_desc"><?php echo translation('admin.alerts_desc'); ?></p>
        </div>

        <div class="mt-16">
            <div id="active-alert-indicator" class="component-card component-card--danger d-none mb-16" style="border-color: #2e7d32; background-color: #e8f5e9;">
                <div class="component-card__content" style="align-items: center;">
                    <span class="material-symbols-rounded" style="color: #2e7d32; font-size: 32px;">broadcast_on_personal</span>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #2e7d32;">
                            <span data-i18n="admin.alerts.active_label"><?php echo translation('admin.alerts.active_label'); ?></span>: 
                            <span id="active-alert-name">...</span>
                        </h2>
                        <p class="component-card__description" style="color: #1b5e20;">
                            Esta alerta está siendo mostrada a todos los usuarios conectados.
                        </p>
                        <div id="active-alert-meta" style="margin-top: 8px; font-size: 13px; color: #1b5e20;"></div>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button class="component-button danger" data-action="stop-alert" data-i18n="admin.alerts.stop_btn">
                        <?php echo translation('admin.alerts.stop_btn'); ?>
                    </button>
                </div>
            </div>

            <div class="component-card component-card--grouped">
                <input type="hidden" id="input-alert-type" value="">
                <input type="hidden" id="input-alert-date" value="">
                <input type="hidden" id="input-alert-time" value="">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.alerts.select_title">Seleccionar Alerta</h2>
                            <p class="component-card__description" data-i18n="admin.alerts.select_desc">Elige el tipo de mensaje global que verán los usuarios.</p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-alert-types">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded" id="current-alert-icon">campaign</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-alert-text">Selecciona una alerta...</span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-alert-types">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $templates = [
                                            ['id' => 'maintenance_warning', 'icon' => 'engineering', 'color' => '#f57c00'],
                                            ['id' => 'high_traffic', 'icon' => 'dns', 'color' => '#212121'],
                                            ['id' => 'critical_issue', 'icon' => 'report', 'color' => '#d32f2f'],
                                            ['id' => 'update_info', 'icon' => 'update', 'color' => '#1976d2'],
                                            ['id' => 'terms_update', 'icon' => 'gavel', 'color' => '#616161'],
                                            ['id' => 'privacy_update', 'icon' => 'policy', 'color' => '#616161'],
                                            ['id' => 'cookie_update', 'icon' => 'cookie', 'color' => '#616161']
                                        ];

                                        foreach ($templates as $tpl) {
                                            $titleKey = "admin.alerts.templates.{$tpl['id']}.title";
                                            $titleVal = translation($titleKey);
                                            ?>
                                            <div class="menu-link" 
                                                 data-action="select-alert-option" 
                                                 data-value="<?php echo $tpl['id']; ?>"
                                                 data-label="<?php echo $titleVal; ?>"
                                                 data-icon="<?php echo $tpl['icon']; ?>"
                                                 data-color="<?php echo $tpl['color']; ?>">
                                                <div class="menu-link-icon">
                                                    <span class="material-symbols-rounded" style="color: <?php echo $tpl['color']; ?>"><?php echo $tpl['icon']; ?></span>
                                                </div>
                                                <div class="menu-link-text">
                                                    <?php echo $titleVal; ?>
                                                </div>
                                                <div class="menu-link-icon"></div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-date-picker" class="w-100 d-none">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title" data-i18n="admin.alerts.date_title">Fecha y Hora</h2>
                                <p class="component-card__description" data-i18n="admin.alerts.date_desc">Define cuándo se activará o mostrará esta información.</p>
                            </div>
                        </div>
                        <div class="component-card__actions w-100">
                            <div class="trigger-select-wrapper w-100">
                                <div class="trigger-selector" data-action="toggle-dropdown" data-target="popover-datetime-picker">
                                    <div class="trigger-select-icon">
                                        <span class="material-symbols-rounded">calendar_month</span>
                                    </div>
                                    <div class="trigger-select-text">
                                        <span id="selected-datetime-text" data-i18n="admin.alerts.select_date_placeholder">
                                            <?php echo translation('admin.alerts.select_date_placeholder'); ?>
                                        </span>
                                    </div>
                                    <div class="trigger-select-arrow">
                                        <span class="material-symbols-rounded">edit_calendar</span>
                                    </div>
                                </div>

                                <div class="popover-module popover-module--anchor-width body-title disabled" id="popover-datetime-picker" style="padding: 16px;">
                                    <div class="menu-content" style="padding: 16px;">
                                        <div style="display:flex; flex-direction:column; gap: 12px;">
                                            <label style="font-size:12px; font-weight:600; color:#666;">Fecha</label>
                                            <input type="date" id="picker-date-input" class="component-text-input full-width" style="height: 40px;">
                                            
                                            <label style="font-size:12px; font-weight:600; color:#666;">Hora</label>
                                            <input type="time" id="picker-time-input" class="component-text-input full-width" style="height: 40px;">
                                            
                                            <button type="button" class="component-button primary w-100" data-action="confirm-datetime" style="margin-top: 8px;">
                                                <span class="material-symbols-rounded">check</span>
                                                <span data-i18n="admin.alerts.calendar.confirm_btn">
                                                    <?php echo translation('admin.alerts.calendar.confirm_btn'); ?>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-link-container" class="w-100 d-none">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title" data-i18n="admin.alerts.link_label">Enlace de información</h2>
                                <p class="component-card__description" data-i18n="admin.alerts.link_desc">Proporciona una URL para que los usuarios obtengan más detalles.</p>
                            </div>
                        </div>
                        <div class="component-card__actions w-100">
                            <div class="component-input-wrapper w-100">
                                <div class="input-with-actions">
                                    <span class="material-symbols-rounded" style="color:#666; margin-right: 4px;">link</span>
                                    <input type="text" id="input-alert-link" class="component-text-input full-width" 
                                           data-i18n-placeholder="admin.alerts.link_placeholder"
                                           placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-preview" class="w-100">
                    <hr class="component-divider">
                    <div class="component-group-item">
                        <div class="component-card__content">
                             <div class="component-card__text">
                                <h2 class="component-card__title">Vista Previa</h2>
                                <p class="component-card__description" id="alert-preview-desc">Selecciona un tipo para ver la descripción.</p>
                            </div>
                        </div>
                        <div class="component-card__actions actions-right">
                            <button class="component-button primary" id="btn-emit-selected-alert" disabled>
                                <span data-i18n="admin.alerts.emit_btn"><?php echo translation('admin.alerts.emit_btn'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
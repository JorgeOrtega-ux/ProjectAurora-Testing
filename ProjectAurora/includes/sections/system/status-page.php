<style>
    .status-page-container { text-align: center; padding-top: 0; }
    .status-icon-wrapper { margin-bottom: 20px; }
    .status-icon { font-size: 80px; }
    .status-title { margin-bottom: 15px; font-size: 28px; }
    .status-message { color: #555; line-height: 1.6; font-size: 16px; margin-bottom: 40px; }
    .status-back-link { color: #888; text-decoration: none; font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; transition: color 0.2s ease; }
    .status-back-link:hover { color: #333; }
    .status-back-icon { font-size: 16px; }
    
    .status-theme-suspended { color: #d32f2f; }
    .status-theme-deleted { color: #616161; }
    .status-theme-maintenance { color: #f57c00; }
    .status-theme-server-full { color: #1976d2; }

    /* Spinner para la cola */
    .queue-spinner {
        display: none; /* Oculto por defecto */
        width: 40px;
        height: 40px;
        border: 4px solid #e3f2fd;
        border-top: 4px solid #1976d2;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px auto;
    }
</style>
<?php
// includes/sections/system/status-page.php

$status = $_GET['status'] ?? 'suspended';
$reason = $_GET['reason'] ?? null;
$until = $_GET['until'] ?? null;

$icon = "block";
$themeClass = "status-theme-suspended"; 
$titleKey = "status.suspended_title";
$msgKey = "status.suspended_msg";

if ($status === 'deleted') {
    $titleKey = "status.deleted_title";
    $msgKey = "status.deleted_msg";
    $icon = "delete_forever";
    $themeClass = "status-theme-deleted";
} elseif ($status === 'maintenance') {
    $titleKey = "status.maintenance_title";
    $msgKey = "status.maintenance_msg";
    $icon = "engineering"; 
    $themeClass = "status-theme-maintenance";
} elseif ($status === 'server_full') {
    $titleKey = "status.server_full_title";
    $msgKey = "status.server_full_msg";
    $icon = "cloud_off"; 
    $themeClass = "status-theme-server-full";
}
?>

<div class="section-content active" data-section="status-page">
    <div class="section-center-wrapper">
        <div class="form-container status-page-container">
            
            <div class="status-icon-wrapper">
                <span class="material-symbols-rounded status-icon <?php echo $themeClass; ?>">
                    <?php echo $icon; ?>
                </span>
            </div>

            <div id="queue-spinner" class="queue-spinner"></div>

            <h1 id="status-title-text" class="status-title <?php echo $themeClass; ?>" data-i18n="<?php echo $titleKey; ?>">
                <?php echo translation($titleKey); ?>
            </h1>
            
            <p id="status-message-text" class="status-message" data-i18n="<?php echo $msgKey; ?>">
                <?php echo translation($msgKey); ?>
            </p>

            <?php if ($status === 'suspended' && ($reason || $until)): ?>
                <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 1px solid #ffcdd2; margin-bottom: 30px; text-align: left;">
                    <?php if ($reason): ?>
                        <p style="margin: 0 0 8px 0; color: #d32f2f; font-size: 14px;">
                            <strong>Razón:</strong> <?php echo htmlspecialchars(urldecode($reason)); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($until): ?>
                        <p style="margin: 0; color: #d32f2f; font-size: 14px;">
                            <strong>Hasta:</strong> <?php echo htmlspecialchars(urldecode($until)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($status !== 'maintenance' && $status !== 'server_full'): ?>
                <div>
                    <a href="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>login" class="status-back-link">
                        <span class="material-symbols-rounded status-back-icon">arrow_back</span> 
                        <span data-i18n="global.back_home"><?php echo translation('global.back_home'); ?></span>
                    </a>
                </div>
            <?php elseif ($status === 'server_full'): ?>
                <div>
                    <a href="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>" class="status-back-link">
                        <span class="material-symbols-rounded status-back-icon">refresh</span> 
                        <span>Reintentar ahora</span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
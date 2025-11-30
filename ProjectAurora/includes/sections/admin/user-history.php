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

<div class="section-content active" data-section="admin/user-history">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/user-status?uid=<?php echo htmlspecialchars($targetUid); ?>" data-i18n-tooltip="admin.history.back_status" data-tooltip="<?php echo translation('admin.history.back_status'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions" data-i18n="admin.history.title"><?php echo translation('admin.history.title'); ?></span>
            </div>
        </div>
    </div>

    <div class="section-center-wrapper section-with-toolbar admin-users-wrapper">
        
        <input type="hidden" id="history-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">

        <div class="component-header-card w-100" style="margin-bottom: 16px; display: flex; align-items: center; gap: 16px; text-align: left; padding: 16px;">
            <div class="user-table-pfp" id="history-pfp-container" style="width: 48px; height: 48px;">
                <img src="" id="history-user-avatar" style="display: none;">
                <div class="user-avatar-placeholder" id="history-user-icon">
                    <span class="material-symbols-rounded avatar-icon" style="font-size: 24px;">person</span>
                </div>
            </div>
            <div>
                <h1 class="component-page-title" id="history-username" style="font-size: 20px; margin: 0;" data-i18n="global.loading"><?php echo translation('global.loading'); ?></h1>
                <p class="component-page-description" id="history-email" style="font-size: 13px;">...</p>
            </div>
        </div>

        <div class="component-table-container">
            <table class="component-table">
                <thead>
                    <tr>
                        <th data-i18n="admin.history.table.start"><?php echo translation('admin.history.table.start'); ?></th>
                        <th data-i18n="admin.history.table.reason"><?php echo translation('admin.history.table.reason'); ?></th>
                        <th data-i18n="admin.history.table.duration"><?php echo translation('admin.history.table.duration'); ?></th>
                        <th data-i18n="admin.history.table.end_original"><?php echo translation('admin.history.table.end_original'); ?></th>
                        <th data-i18n="admin.history.table.admin"><?php echo translation('admin.history.table.admin'); ?></th>
                        <th data-i18n="admin.history.table.final_status"><?php echo translation('admin.history.table.final_status'); ?></th>
                    </tr>
                </thead>
                <tbody id="full-history-body">
                    <tr>
                        <td colspan="6" class="component-table-empty">
                            <div class="loader-spinner" style="margin: 20px auto; width: 30px; height: 30px; border-width: 3px;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>
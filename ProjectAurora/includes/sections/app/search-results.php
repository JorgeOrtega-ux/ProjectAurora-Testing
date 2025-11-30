<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../../../config/core/database.php';
require_once __DIR__ . '/../../logic/search_fetcher.php'; 
require_once __DIR__ . '/../../logic/i18n_server.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$isAjaxPartial = isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1';
$currentUserId = $_SESSION['user_id'];
$limit = 5; 

if ($isAjaxPartial) {
    $lang = $_SESSION['user_lang'] ?? 'es-latam';
    I18n::load($lang);
}

$searchData = SearchFetcher::searchUsers($pdo, $currentUserId, $q, $offset, $limit);
$results = $searchData['results'];
$hasMore = $searchData['hasMore'];

$renderUserCard = function ($user) use ($currentUserId) {
    $avatarPath = !empty($user['profile_picture']) ? '/ProjectAurora/' . $user['profile_picture'] : null;
    $uid = $user['id'];
    $role = $user['role'] ?? 'user';
    $mutualCount = $user['mutual_friends'];
    
    // [MODIFICADO] Verificar si está bloqueado
    $isBlocked = (isset($user['is_blocked_by_me']) && $user['is_blocked_by_me'] > 0);

    // Lógica de Privacidad
    $privacy = $user['message_privacy'] ?? 'friends';
    $canMessage = false;

    if ($privacy === 'everyone') {
        $canMessage = true;
    } elseif ($privacy === 'friends' && $user['friend_status'] === 'accepted') {
        $canMessage = true;
    } elseif ($privacy === 'nobody') {
        $canMessage = false;
    }

    $actionsHtml = '';

    if ($isBlocked) {
        // [NUEVO] Si está bloqueado, mostrar botón de desbloquear
        $actionsHtml = '
            <button class="btn-add-friend" data-action="unblock-user" data-uid="' . $uid . '" style="color:#d32f2f; border-color:#ffcdd2; background:#ffebee;">
                <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">lock_open</span> Desbloquear
            </button>';
    } else {
        // Botón de Bloqueo (Icono Rojo pequeño) para agregar a las otras acciones
        $blockBtnHtml = '
            <button class="btn-add-friend btn-remove-friend" data-action="block-user" data-uid="' . $uid . '" title="Bloquear usuario" style="margin-left:4px; padding: 8px 10px;">
                <span class="material-symbols-rounded" style="font-size:16px;">block</span>
            </button>';

        if ($user['friend_status'] === 'accepted') {
            $chatBtnHtml = '';
            if ($canMessage) {
                $chatBtnHtml = '
                <button class="btn-add-friend" data-action="send-dm" data-uid="' . $uid . '" style="margin-right:4px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">chat</span>
                </button>';
            }

            $actionsHtml = $chatBtnHtml . '
                <button class="btn-add-friend btn-remove-friend" data-uid="' . $uid . '" data-i18n="search.actions.remove">' . translation('search.actions.remove') . '</button>
                ' . $blockBtnHtml;

        } elseif ($user['friend_status'] === 'pending') {
            if ($user['sender_id'] == $currentUserId) {
                $actionsHtml = '<button class="btn-add-friend btn-cancel-request" data-uid="' . $uid . '" data-i18n="search.actions.cancel">' . translation('search.actions.cancel') . '</button>' . $blockBtnHtml;
            } else {
                $actionsHtml = '<button class="btn-accept-request" data-uid="' . $uid . '" data-i18n="search.actions.accept">' . translation('search.actions.accept') . '</button>
                                <button class="btn-decline-request" data-uid="' . $uid . '" data-i18n="search.actions.decline">' . translation('search.actions.decline') . '</button>' . $blockBtnHtml;
            }
        } else {
            $actionsHtml = '<button class="btn-add-friend" data-uid="' . $uid . '" data-i18n="search.actions.add">' . translation('search.actions.add') . '</button>' . $blockBtnHtml;
        }
    }
?>
    <div class="user-card-item">
        <div class="user-info-group">
            <div class="user-pfp-container" data-role="<?php echo htmlspecialchars($role); ?>">
                <?php if ($avatarPath): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo translation('global.alt_avatar'); ?>">
                <?php else: ?>
                    <span class="material-symbols-rounded default-avatar">person</span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="user-meta-text" data-i18n="search.user_subtitle"><?php echo translation('search.user_subtitle'); ?></span>
                <span class="user-meta-text" style="font-size: 12px; color: #888; margin-top: 2px;">
                    <?php echo $mutualCount; ?> <span data-i18n="search.mutual_friends"><?php echo translation('search.mutual_friends'); ?></span>
                </span>
            </div>
        </div>
        <div class="user-action-group" id="actions-<?php echo $uid; ?>">
            <?php echo $actionsHtml; ?>
        </div>
    </div>
<?php
};

if ($isAjaxPartial) {
    foreach ($results as $user) {
        $renderUserCard($user);
    }
    if ($hasMore) {
        echo '<div id="ajax-has-more-flag" style="display:none;"></div>';
    }
    echo '<script>if(window.translateDocument) window.translateDocument(document.getElementById("search-results-list"));</script>';
    exit;
}
?>
<div class="section-content active" data-section="search">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="explorer" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                
                <div class="component-toolbar__separator"></div>
                
                <span style="font-size: 14px; font-weight: 600; color: #666;">
                    <?php echo empty($q) ? translation('global.search') : '"' . htmlspecialchars($q) . '"'; ?>
                </span>
            </div>

            <div class="component-toolbar__right">
                <div style="position: relative;">
                    
                    <button class="component-icon-button" 
                            data-action="toggle-dropdown" 
                            data-target="dropdown-search-filter"
                            data-i18n-tooltip="search.filter_tooltip" 
                            data-tooltip="<?php echo translation('search.filter_tooltip'); ?>">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>

                    <div class="popover-module popover-module--anchor-left disabled" 
                         id="dropdown-search-filter" 
                         style="width: 200px; right: 0; left: auto; top: calc(100% + 8px);">
                        
                        <div class="menu-content">
                            <div class="menu-list">
                                <div class="menu-link active">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                    <div class="menu-link-text">Relevancia</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon"></div>
                                    <div class="menu-link-text">Más recientes</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon"></div>
                                    <div class="menu-link-text">Amigos en común</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <div class="section-center-wrapper section-with-toolbar" style="justify-content: flex-start; align-items: center; flex-direction: column;">

        <div class="search-results-card">
            <?php if (empty($q)): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search</span>
                    <p data-i18n="search.empty_state"><?php echo translation('search.empty_state'); ?></p>
                </div>
            <?php elseif (count($results) === 0 && $offset === 0): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">person_off</span>
                    <p><span data-i18n="search.no_results"><?php echo translation('search.no_results'); ?></span> "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php else: ?>

                <div class="results-list" id="search-results-list">
                    <?php
                    foreach ($results as $user) {
                        $renderUserCard($user);
                    }
                    ?>
                </div>

                <?php if ($hasMore): ?>
                    <div class="load-more-container" style="text-align: center; padding: 20px;">
                        <button class="btn-load-more"
                            data-query="<?php echo htmlspecialchars($q); ?>"
                            data-offset="<?php echo $limit; ?>"
                            data-i18n="search.load_more">
                            <?php echo translation('search.load_more'); ?>
                        </button>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>
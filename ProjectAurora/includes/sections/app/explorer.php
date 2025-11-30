<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="section-content active" data-section="explorer">
    <div class="component-wrapper full-width">
        
        <div id="public-communities-list" class="mt-16 communities-grid">
            <div class="small-spinner" style="margin: 40px auto; grid-column: 1 / -1;"></div>
        </div>

    </div>
</div>
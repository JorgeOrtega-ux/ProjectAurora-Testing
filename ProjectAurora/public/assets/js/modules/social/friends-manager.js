// public/assets/js/modules/social/friends-manager.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';

function triggerNotificationReload() {
    document.dispatchEvent(new CustomEvent('reload-notifications'));
}

function updateUIButtons(userId, state) {
    const container = document.getElementById(`actions-${userId}`);
    if (!container) return;

    let html = '';
    
    // Botón de Bloqueo (Común para todos)
    const blockBtn = `
        <button class="btn-add-friend btn-remove-friend" data-action="block-user" data-uid="${userId}" title="Bloquear usuario" style="margin-left:4px; padding: 8px 10px;">
            <span class="material-symbols-rounded" style="font-size:16px;">block</span>
        </button>`;

    switch (state) {
        case 'friends':
            html = `
                <button class="btn-add-friend" data-action="send-dm" data-uid="${userId}" style="margin-right:4px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">chat</span>
                </button>
                <button class="btn-add-friend btn-remove-friend" data-uid="${userId}">${t('search.actions.remove')}</button>
                ${blockBtn}
            `;
            break;
        case 'request_sent':
            html = `<button class="btn-add-friend btn-cancel-request" data-uid="${userId}">${t('search.actions.cancel')}</button>${blockBtn}`;
            break;
        case 'request_received':
            html = `
                <button class="btn-accept-request" data-uid="${userId}">${t('search.actions.accept')}</button>
                <button class="btn-decline-request" data-uid="${userId}">${t('search.actions.decline')}</button>
                ${blockBtn}
            `;
            break;
        case 'blocked': // [NUEVO ESTADO]
            html = `
                <button class="btn-add-friend" data-action="unblock-user" data-uid="${userId}" style="color:#d32f2f; border-color:#ffcdd2; background:#ffebee;">
                    <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">lock_open</span> Desbloquear
                </button>
            `;
            break;
        case 'none':
        default:
            html = `<button class="btn-add-friend" data-uid="${userId}">${t('search.actions.add')}</button>${blockBtn}`;
            break;
    }
    container.innerHTML = html;
}

// Bloquear Usuario (Sin eliminar tarjeta)
async function blockUser(targetId, btn) {
    if (!confirm(t('friends.confirm_block') || '¿Seguro que quieres bloquear a este usuario?')) return;
    
    setButtonLoading(btn, true);
    
    const res = await postJson('api/friends_handler.php', { 
        action: 'block_user', 
        target_id: targetId 
    });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        updateUIButtons(targetId, 'blocked'); // Cambiar UI a "Desbloquear"
        triggerNotificationReload();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        setButtonLoading(btn, false, '<span class="material-symbols-rounded" style="font-size:16px;">block</span>');
    }
}

// Desbloquear Usuario
async function unblockUser(targetId, btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="small-spinner"></div>';
    btn.disabled = true;

    const res = await postJson('api/friends_handler.php', { 
        action: 'unblock_user', 
        target_id: targetId 
    });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        updateUIButtons(targetId, 'none'); // Volver a estado normal (Agregar amigo)
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function sendFriendRequest(targetId, btn) { 
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'send_request', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.request_sent'), 'success');
            updateUIButtons(targetId, 'request_sent');
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function cancelRequest(targetId, btn) { 
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'cancel_request', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.request_cancelled'), 'info');
            updateUIButtons(targetId, 'none');
            triggerNotificationReload(); 
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function removeFriend(targetId, btn) {
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'remove_friend', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.friend_removed'), 'info');
            updateUIButtons(targetId, 'none');
            triggerNotificationReload(); 
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function respondRequest(actionType, btn, senderId) { 
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="small-spinner"></div>';
    btn.disabled = true;
    try {
        const res = await postJson('api/friends_handler.php', { action: actionType, sender_id: senderId });
        if (res.success) {
            triggerNotificationReload(); 
            if (actionType === 'accept_request') {
                if (window.alertManager) window.alertManager.showAlert(t('notifications.now_friends'), 'success');
                updateUIButtons(senderId, 'friends');
            } else {
                if (window.alertManager) window.alertManager.showAlert(t('notifications.request_declined'), 'info');
                updateUIButtons(senderId, 'none');
            }
        } else {
            btn.innerHTML = originalContent;
            btn.disabled = false;
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    } catch (e) { btn.innerHTML = originalContent; btn.disabled = false; }
}

async function startDirectChat(targetUid) {
    const res = await postJson('api/friends_handler.php', { action: 'start_chat', target_id: targetUid });
    if (res.success && res.uuid) {
        if(window.navigateTo) window.navigateTo(`dm/${res.uuid}`);
        else window.location.href = `${window.BASE_PATH}dm/${res.uuid}`;
    } else {
        if (window.alertManager) window.alertManager.showAlert(res.message || 'Error iniciando chat', 'error');
    }
}

function initSocketListener() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        if (type === 'friend_request') updateUIButtons(payload.sender_id, 'request_received');
        if (type === 'friend_accepted') updateUIButtons(payload.accepter_id, 'friends');
        if (type === 'request_cancelled' || type === 'request_declined' || type === 'friend_removed') updateUIButtons(payload.sender_id, 'none');
    });
}

function initClickListeners() {
    document.body.addEventListener('click', async (e) => {
        const target = e.target;
        
        // Listener para desbloquear
        const unblockBtn = target.closest('[data-action="unblock-user"]');
        if (unblockBtn) {
            e.preventDefault();
            await unblockUser(unblockBtn.dataset.uid, unblockBtn);
            return;
        }

        // Listener para bloquear
        const blockBtn = target.closest('[data-action="block-user"]');
        if (blockBtn) {
            e.preventDefault();
            await blockUser(blockBtn.dataset.uid, blockBtn);
            return;
        }

        const dmBtn = target.closest('[data-action="send-dm"]');
        if (dmBtn) {
            e.preventDefault();
            await startDirectChat(dmBtn.dataset.uid);
            return;
        }

        const addBtn = target.closest('.btn-add-friend');
        if (addBtn && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !target.closest('[data-action="send-dm"]') && !target.closest('[data-action="block-user"]') && !target.closest('[data-action="unblock-user"]') && !addBtn.disabled) {
            e.preventDefault();
            await sendFriendRequest(addBtn.dataset.uid, addBtn);
            return; 
        }

        const cancelBtn = target.closest('.btn-cancel-request');
        if (cancelBtn) {
            e.preventDefault();
            await cancelRequest(cancelBtn.dataset.uid, cancelBtn);
            return; 
        }

        const removeBtn = target.closest('.btn-remove-friend');
        if (removeBtn && !blockBtn && !unblockBtn) { 
            e.preventDefault();
            if(confirm(t('search.actions.remove_confirm') || '¿Seguro que quieres eliminar a este amigo?')) {
                await removeFriend(removeBtn.dataset.uid, removeBtn);
            }
            return;
        }

        const acceptBtn = target.closest('.btn-accept-request');
        if (acceptBtn) {
            e.preventDefault();
            await respondRequest('accept_request', acceptBtn, acceptBtn.dataset.uid);
            return;
        }

        const declineBtn = target.closest('.btn-decline-request');
        if (declineBtn) {
            e.preventDefault();
            await respondRequest('decline_request', declineBtn, declineBtn.dataset.uid);
            return;
        }
    });
}

export function initFriendsManager() {
    initClickListeners();
    initSocketListener();
}
// public/assets/js/modules/notifications-manager.js

import { t } from '../../core/i18n-manager.js';
import { postJson } from '../../core/utilities.js';

function renderEmptyState(container) {
    container.innerHTML = `<div class="notifications-empty"><span class="material-symbols-rounded empty-icon">notifications_off</span><p>${t('header.no_notifications')}</p></div>`;
}

function renderNotifications(notifs) {
    const container = document.querySelector('.menu-content-bottom'); 
    if (!container || !container.closest('[data-module="moduleNotifications"]')) return;
    
    if (notifs.length === 0) { 
        renderEmptyState(container); 
        return; 
    }
    
    let html = '';
    notifs.forEach(n => {
        const role = n.sender_role || 'user'; 
        const avatar = n.sender_profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + n.sender_profile_picture : null;
        
        // Manejo especial para notificaciones de sistema/admin que no tienen sender_profile_picture
        let avatarHtml;
        if (n.type === 'admin_alert' || n.type === 'system') {
             avatarHtml = `<span class="material-symbols-rounded notif-default-icon" style="color: #1976d2;">admin_panel_settings</span>`;
        } else {
             avatarHtml = avatar ? `<img src="${avatar}" class="notif-avatar">` : `<span class="material-symbols-rounded notif-default-icon">person</span>`;
        }
        
        let actionsHtml = '';
        if (n.type === 'friend_request') {
            // [CORREGIDO] Se añaden las clases 'btn-accept-request' / 'btn-decline-request'
            // y el atributo 'data-uid' para que friends-manager.js pueda capturar el clic.
            actionsHtml = `
                <div class="notif-actions">
                    <button class="notif-btn accept btn-accept-request" data-uid="${n.related_id}">${t('search.actions.accept')}</button>
                    <button class="notif-btn decline btn-decline-request" data-uid="${n.related_id}">${t('search.actions.decline')}</button>
                </div>
            `;
        }

        const unreadDot = (parseInt(n.is_read) === 0) ? '<div class="unread-dot"></div>' : '';

        html += `
            <div class="notification-item" data-nid="${n.id}" data-sid="${n.related_id}">
                <div class="notif-left">
                    <div class="notif-img-container" data-role="${role}">
                        ${avatarHtml}
                    </div>
                </div>
                <div class="notif-content">
                    <p class="notif-text">${n.message}</p>
                    ${actionsHtml}
                    <span class="notif-time">${new Date(n.created_at).toLocaleDateString()} ${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                </div>
                ${unreadDot}
            </div>`;
    });
    container.innerHTML = `<div class="notif-list-wrapper">${html}</div>`;
}

function updateBadge(count) {
    const btn = document.querySelector('[data-action="toggleModuleNotifications"]');
    if (!btn) return;

    let badge = btn.querySelector('.notification-badge');
    if (!badge) {
        badge = document.createElement('div');
        badge.className = 'notification-badge';
        btn.appendChild(badge);
    }

    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

async function loadNotifications() {
    const res = await postJson('api/notifications_handler.php', { action: 'get_notifications' });
    if (res.success) { 
        renderNotifications(res.notifications);
        updateBadge(res.unread_count); 
    }
}

async function markAllRead() {
    const dots = document.querySelectorAll('.unread-dot');
    dots.forEach(d => d.remove());
    updateBadge(0);

    await postJson('api/notifications_handler.php', { action: 'mark_read_all' });
    loadNotifications(); 
}

function initSocketListener() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        const alertMgr = window.alertManager;

        if (type === 'friend_request') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'info');
            loadNotifications();
        }

        if (type === 'friend_accepted') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'success');
            loadNotifications();
        }

        if (type === 'request_cancelled' || type === 'friend_removed') {
            loadNotifications();
        }

        // Escuchar notificaciones de admin
        if (type === 'admin_notification') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'info');
            loadNotifications();
        }
    });
    
    document.addEventListener('reload-notifications', () => {
        loadNotifications();
    });
}

function initGlobalListeners() {
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('.notifications-action')) {
            markAllRead();
        }
    });
}

export function initNotificationsManager() {
    loadNotifications();
    initSocketListener();
    initGlobalListeners();
}
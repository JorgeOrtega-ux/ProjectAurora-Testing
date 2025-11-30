// public/assets/js/modules/admin/admin-dashboard.js

import { postJson } from '../../core/utilities.js';

let isInitialized = false;
let refreshInterval = null;

async function fetchStats() {
    const res = await postJson('api/admin_handler.php', { action: 'get_dashboard_stats' });

    if (res.success && res.stats) {
        updateUI(res.stats);
    }
}

function updateUI(stats) {
    const elTotal = document.getElementById('stat-total-users');
    const elOnline = document.getElementById('stat-online-users');
    const elNew = document.getElementById('stat-new-users');
    const elSessions = document.getElementById('stat-active-sessions');

    if (elTotal) elTotal.textContent = stats.total_users;
    if (elOnline && elOnline.textContent === '...') elOnline.textContent = stats.online_users;
    if (elNew) elNew.textContent = '+' + stats.new_users_today;
    if (elSessions) elSessions.textContent = stats.active_sessions;
}

function initSocketListener() {
    const socket = window.socketService ? window.socketService.socket : null;
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ type: 'get_online_users' }));
    }

    if (!isInitialized) {
        document.addEventListener('socket-message', (e) => {
            const { type, payload } = e.detail;
            const elOnline = document.getElementById('stat-online-users');

            if (type === 'online_users_list' && elOnline) {
                elOnline.textContent = payload.length;
            }

            if (type === 'user_status_change' && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'get_online_users' }));
            }
        });
    }
}

export function initAdminDashboard() {
    fetchStats();
    initSocketListener();

    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(fetchStats, 30000);

    isInitialized = true;
}
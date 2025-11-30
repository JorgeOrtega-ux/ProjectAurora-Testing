// public/assets/js/modules/admin/admin-communities.js

import { postJson } from '../../core/utilities.js';

let searchTimer = null;

export function initAdminCommunities() {
    loadCommunities();
    initListeners();
}

function initListeners() {
    const searchInput = document.getElementById('admin-communities-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadCommunities(e.target.value);
            }, 400);
        });
    }

    document.getElementById('communities-list-container')?.addEventListener('click', (e) => {
        const row = e.target.closest('.user-capsule-row');
        if (row && row.dataset.id) {
            if (window.navigateTo) window.navigateTo('admin/community-edit?id=' + row.dataset.id);
        }
    });
}

async function loadCommunities(query = '') {
    const container = document.getElementById('communities-list-container');
    if (!container) return;

    container.innerHTML = `<div class="small-spinner" style="margin: 20px auto;"></div>`;

    const res = await postJson('api/admin_handler.php', { action: 'list_communities', q: query });

    if (res.success) {
        renderList(res.communities);
    } else {
        container.innerHTML = `<div class="empty-capsule-state" style="color: #d32f2f;"><p>${res.message}</p></div>`;
    }
}

function renderList(list) {
    const container = document.getElementById('communities-list-container');
    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="empty-capsule-state">
                <span class="material-symbols-rounded icon">groups</span>
                <p>No se encontraron comunidades.</p>
            </div>`;
        return;
    }

    let html = '';
    list.forEach(c => {
        const isPrivate = c.privacy === 'private';
        const icon = isPrivate ? 'lock' : 'public';
        const avatar = c.profile_picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(c.community_name)}`;
        
        // Estilo especial para el avatar en la lista
        const imgStyle = `background-image: url('${avatar}'); background-size: cover; background-position: center;`;

        html += `
            <div class="user-capsule-row" data-id="${c.id}" style="cursor: pointer;">
                
                <div class="capsule-avatar" style="${imgStyle}"></div>

                <div class="info-pill primary-pill">
                    <span class="pill-content strong">${c.community_name}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">${icon}</span>
                    <span class="pill-content" style="text-transform: capitalize;">${c.privacy}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">group</span>
                    <span class="pill-content">${c.member_count}</span>
                </div>
                
                <div class="info-pill" style="margin-left: auto; border:none; background:transparent;">
                    <span class="material-symbols-rounded" style="color:#999;">chevron_right</span>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}
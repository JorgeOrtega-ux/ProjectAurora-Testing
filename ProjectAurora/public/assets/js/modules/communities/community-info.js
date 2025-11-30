// public/assets/js/modules/communities/community-info.js

import { postJson } from '../../core/utilities.js';
import { t } from '../../core/i18n-manager.js';

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

export function initInfoPanelListener() {
    document.addEventListener('reload-group-info', (e) => {
        if (e.detail && e.detail.uuid) {
            loadGroupDetails(e.detail.uuid);
        }
    });
}

async function loadGroupDetails(uuid) {
    const els = {
        img: document.getElementById('info-group-img'),
        name: document.getElementById('info-group-name'),
        desc: document.getElementById('info-group-desc'),
        count: document.getElementById('info-member-count'),
        membersList: document.getElementById('info-members-list'),
        filesGrid: document.getElementById('info-files-grid'),
        membersSection: document.querySelector('.info-section:has(#info-members-list)') || document.getElementById('info-members-list')?.parentNode
    };

    if (!els.name) return;

    if (els.membersList) els.membersList.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';
    els.filesGrid.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';
    els.name.textContent = 'Cargando...';

    const activeType = window.ACTIVE_CHAT_TYPE || 'community';
    const action = (activeType === 'private') ? 'get_private_chat_details' : 'get_community_details';

    const res = await postJson('api/communities_handler.php', { 
        action: action, 
        uuid: uuid 
    });

    if (res.success) {
        const info = res.info;
        const avatarSrc = info.profile_picture 
            ? (window.BASE_PATH || '/ProjectAurora/') + info.profile_picture 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(info.community_name)}`;
        els.img.src = avatarSrc;
        els.name.textContent = info.community_name;
        
        if (activeType === 'private') {
            els.desc.innerHTML = `<span class="comm-badge" style="margin-bottom:4px;">${t('nav.profile')}</span>`;
            if (els.count) els.count.textContent = ''; 
            if (els.membersSection) els.membersSection.style.display = 'none';
        } else {
            const typeKey = info.community_type || 'other';
            const typeText = t(`communities.types.${typeKey}`);
            els.desc.innerHTML = `
                <span class="comm-badge" style="margin-bottom:4px;">${typeText}</span><br>
                <span style="font-size:12px; color:#888;">Código: <strong style="user-select:all;">${info.access_code}</strong></span>
            `;
            if (els.count) els.count.textContent = `(${info.member_count})`;
            if (els.membersSection) els.membersSection.style.display = 'flex';
            renderGroupMembers(res.members, els.membersList);
        }
        renderGroupFiles(res.files, els.filesGrid);
    } else {
        els.filesGrid.innerHTML = `<p style="color:red; text-align:center;">Error al cargar</p>`;
    }
}

function renderGroupMembers(members, container) {
    if (!container) return;
    if (!members || members.length === 0) {
        container.innerHTML = '<p class="info-no-files">No hay miembros visibles.</p>';
        return;
    }
    let html = '';
    members.forEach(m => {
        const avatar = m.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + m.profile_picture : `https://ui-avatars.com/api/?name=${encodeURIComponent(m.username)}`;
        const roleColor = (m.role === 'admin' || m.role === 'founder') ? '#d32f2f' : ((m.role === 'moderator') ? '#1976d2' : '#888');
        const roleText = m.role === 'founder' ? 'Fundador' : (m.role === 'admin' ? 'Admin' : (m.role === 'moderator' ? 'Mod' : 'Miembro'));
        html += `<div class="info-member-item"><img src="${avatar}" class="info-member-avatar" alt="${m.username}"><div class="info-member-details"><span class="info-member-name">${escapeHtml(m.username)}</span><span class="info-member-role" style="color:${roleColor}; font-size:10px; font-weight:700;">${roleText}</span></div></div>`;
    });
    container.innerHTML = html;
}

function renderGroupFiles(files, container) {
    if (!files || files.length === 0) {
        container.innerHTML = '<p class="info-no-files">No hay archivos multimedia compartidos.</p>';
        return;
    }
    const viewerItems = files.map(f => ({
        src: (window.BASE_PATH || '/ProjectAurora/') + f.file_path,
        type: 'image', 
        user: { name: f.username, avatar: '' }, 
        date: new Date(f.created_at).toLocaleDateString()
    }));
    const jsonStr = JSON.stringify(viewerItems).replace(/'/g, "&apos;").replace(/"/g, '&quot;');
    container.setAttribute('data-media-items', jsonStr);
    let html = '';
    files.forEach((f, index) => {
        const src = (window.BASE_PATH || '/ProjectAurora/') + f.file_path;
        html += `<img src="${src}" class="info-file-thumb" data-action="view-media" data-index="${index}" loading="lazy">`;
    });
    container.innerHTML = html;
}
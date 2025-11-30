// public/assets/js/modules/communities/channels-manager.js

import { postJson } from '../../core/utilities.js';

// Cache para evitar re-fetching constante de canales
const channelsCache = {};

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

export function getCachedChannels(uuid) {
    return channelsCache[uuid] || null;
}

export function clearChannelsCache(uuid) {
    if (channelsCache[uuid]) delete channelsCache[uuid];
}

export async function loadChannels(communityUuid) {
    const res = await postJson('api/communities_handler.php', { 
        action: 'get_community_details', 
        uuid: communityUuid 
    });

    if (res.success) {
        channelsCache[communityUuid] = res.channels;
        return {
            success: true,
            channels: res.channels,
            role: res.info.role || 'member'
        };
    } else {
        channelsCache[communityUuid] = []; // Fallback vacío
        return { success: false, channels: [], role: 'member' };
    }
}

export function renderChannelList(container, communityUuid, channels, userRole) {
    if (!container) return;

    const isAdmin = ['admin', 'founder'].includes(userRole);
    let html = '';

    if (channels && channels.length > 0) {
        // Cabecera de sección "CANALES"
        html += `<div style="padding: 12px 16px; font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">Canales</div>`;

        channels.forEach(ch => {
            const isChActive = (ch.uuid === window.ACTIVE_CHANNEL_UUID) ? 'active' : '';
            const icon = (ch.type === 'announcement') ? 'campaign' : 'tag';
            
            let deleteBtn = '';
            if (isAdmin) {
                deleteBtn = `
                <button class="channel-action-btn delete" data-action="delete-channel" data-uuid="${ch.uuid}" title="Eliminar canal">
                    <span class="material-symbols-rounded">close</span>
                </button>`;
            }

            html += `
            <div class="channel-item ${isChActive}" data-action="select-channel" data-uuid="${ch.uuid}" data-community="${communityUuid}" style="margin: 0 8px 4px 8px;">
                <span class="material-symbols-rounded channel-icon">${icon}</span>
                <span class="channel-name">${escapeHtml(ch.name)}</span>
                ${deleteBtn}
            </div>`;
        });
    } else {
        html = `<div style="padding:20px; font-size:13px; color:#999; text-align:center;">No hay canales disponibles.</div>`;
    }

    // Botón Crear Canal (Solo Admins)
    if (isAdmin) {
        html += `
        <div class="channel-create-btn" data-action="create-channel-prompt" data-community="${communityUuid}" style="margin: 8px 16px;">
            <span class="material-symbols-rounded">add</span> Crear canal
        </div>`;
    }

    container.innerHTML = html;
}

export async function handleCreateChannel(communityUuid) {
    const name = prompt("Nombre del nuevo canal:");
    if (!name) return { success: false };
    
    const cleanName = name.trim().substring(0, 20);
    if (cleanName.length < 1) return { success: false };

    const btn = document.querySelector(`[data-action="create-channel-prompt"]`);
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<div class="small-spinner" style="width:14px; height:14px; border-width:2px;"></div> Creando...';
        btn.style.pointerEvents = 'none';
    }

    const res = await postJson('api/communities_handler.php', { 
        action: 'create_channel', 
        community_uuid: communityUuid,
        name: cleanName,
        type: 'text' 
    });

    if (btn) {
        btn.innerHTML = originalHtml;
        btn.style.pointerEvents = 'auto';
    }

    if (res.success) {
        if (!channelsCache[communityUuid]) channelsCache[communityUuid] = [];
        channelsCache[communityUuid].push(res.channel);
        return { success: true, channels: channelsCache[communityUuid], message: res.message };
    } else {
        alert(res.message || "Error al crear canal");
        return { success: false, message: res.message };
    }
}

export async function handleDeleteChannel(channelUuid, communityUuid) {
    if (!confirm("¿Eliminar este canal y todos sus mensajes?")) return { success: false };

    const res = await postJson('api/communities_handler.php', { 
        action: 'delete_channel', 
        channel_uuid: channelUuid 
    });

    if (res.success) {
        if (channelsCache[communityUuid]) {
            channelsCache[communityUuid] = channelsCache[communityUuid].filter(c => c.uuid !== channelUuid);
        }
        return { success: true, channels: channelsCache[communityUuid], message: res.message };
    } else {
        alert(res.message || "Error al eliminar");
        return { success: false, message: res.message };
    }
}
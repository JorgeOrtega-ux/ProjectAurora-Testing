// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js';

let sidebarItems = []; 
let currentFilter = 'all'; 
let currentSearchQuery = ''; 
// Cache para evitar re-fetching constante de canales al colapsar/expandir
const channelsCache = {}; 

function formatChatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const isToday = date.getDate() === now.getDate() && date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
    return isToday ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : date.toLocaleDateString();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function renderChatListItem(item) {
    const isPrivate = (item.type === 'private');
    
    const avatarSrc = item.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + item.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.name);
        
    // En comunidades, la clase active se gestiona a nivel de canal o cabecera
    const isActive = (!isPrivate && item.uuid === window.ACTIVE_CHAT_UUID && !window.ACTIVE_CHANNEL_UUID) ? 'active' : '';
    
    let lastMsg = item.last_message ? escapeHtml(item.last_message) : (item.last_message_at ? 'Imagen' : (isPrivate ? "Nuevo chat" : "Toca para ver canales"));
    if (isPrivate && !item.last_message && !item.last_message_at) lastMsg = "Iniciar conversación";

    const time = item.last_message_at ? formatChatTime(item.last_message_at) : '';
    const unreadCount = parseInt(item.unread_count || 0);
    
    const badgeHtml = (unreadCount > 0) 
        ? `<div class="unread-counter">${unreadCount > 99 ? '99+' : unreadCount}</div>` 
        : '';

    const previewStyle = (unreadCount > 0) ? 'font-weight: 700; color: #000;' : '';
    
    const role = item.role || 'user'; 
    const shapeClass = isPrivate ? '' : 'community-shape'; 

    const isPinned = parseInt(item.is_pinned) === 1;
    const isFavorite = parseInt(item.is_favorite) === 1;
    const isBlocked = parseInt(item.is_blocked_by_me) > 0;
    
    let indicatorsIcons = '';
    if (isFavorite) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator favorite">star</span>';
    if (isPinned) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator pinned">push_pin</span>';
   
    const pinnedAttr = isPinned ? 'true' : 'false';
    const favAttr = isFavorite ? 'true' : 'false';
    const blockedAttr = isBlocked ? 'true' : 'false';

    // Contenedor de canales (Oculto por defecto)
    let channelsContainer = '';
    let expandIcon = '';
    
    if (!isPrivate) {
        expandIcon = `<span class="material-symbols-rounded expand-icon" style="font-size:18px; color:#999; transition: transform 0.2s;">expand_more</span>`;
        channelsContainer = `
        <div class="channels-list-container d-none" id="channels-${item.uuid}" data-loaded="false">
            <div class="small-spinner" style="margin: 10px auto;"></div>
        </div>`;
    }

    return `
    <div class="chat-item-wrapper" data-uuid="${item.uuid}">
        <div class="chat-item ${isActive}" 
             id="sidebar-item-${item.uuid}" 
             data-action="${isPrivate ? 'select-chat' : 'toggle-community'}" 
             data-uuid="${item.uuid}" 
             data-type="${item.type}"
             data-pinned="${pinnedAttr}" 
             data-fav="${favAttr}" 
             data-blocked="${blockedAttr}">
            
            <div class="chat-item-avatar-wrapper ${shapeClass}" data-role="${role}">
                <img src="${avatarSrc}" alt="Avatar">
            </div>

            <div class="chat-item-info">
                <div class="chat-item-top">
                    <span class="chat-item-name">${escapeHtml(item.name)}</span>
                    <span class="chat-item-time">${time}</span>
                </div>
                
                <div class="chat-item-bottom">
                    <span class="chat-item-preview" style="${previewStyle}">${lastMsg}</span>
                    
                    <div class="chat-item-actions">
                        ${badgeHtml}
                        ${indicatorsIcons}
                        ${expandIcon}
                    </div>
                </div>
            </div>
        </div>
        ${channelsContainer}
    </div>`;
}

function renderSidebarList() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    // Guardar estado de expansión actual antes de redibujar
    const expandedUuids = [];
    container.querySelectorAll('.channels-list-container:not(.d-none)').forEach(el => {
        expandedUuids.push(el.id.replace('channels-', ''));
    });

    container.innerHTML = '';

    const filteredItems = sidebarItems.filter(item => {
        let passesBadge = true;
        if (currentFilter === 'unread') passesBadge = parseInt(item.unread_count) > 0;
        else if (currentFilter === 'community') passesBadge = (item.type === 'community');
        else if (currentFilter === 'private') passesBadge = (item.type === 'private');
        else if (currentFilter === 'favorites') passesBadge = (parseInt(item.is_favorite) === 1);

        let passesSearch = true;
        if (currentSearchQuery) passesSearch = item.name.toLowerCase().includes(currentSearchQuery.toLowerCase());

        return passesBadge && passesSearch;
    });

    if (filteredItems.length > 0) {
        container.innerHTML = filteredItems.map(c => renderChatListItem(c)).join('');
        
        // Restaurar expansiones
        expandedUuids.forEach(uuid => {
            const chContainer = document.getElementById(`channels-${uuid}`);
            if (chContainer) {
                chContainer.classList.remove('d-none');
                // Rotar icono
                const header = document.getElementById(`sidebar-item-${uuid}`);
                const icon = header?.querySelector('.expand-icon');
                if (icon) icon.style.transform = 'rotate(180deg)';
                
                // Si ya teníamos caché, renderizar de inmediato
                if (channelsCache[uuid]) {
                    renderChannelList(uuid, channelsCache[uuid], getRoleFromCache(uuid));
                } else {
                    loadChannels(uuid);
                }
            }
        });

    } else {
        let msg = 'No hay chats que mostrar.';
        if (currentFilter === 'unread') msg = 'No tienes mensajes sin leer.';
        if (currentFilter === 'favorites') msg = 'No tienes favoritos aún.';
        if (currentSearchQuery) msg = 'No se encontraron resultados.';
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px; font-size:13px;">${msg}</p>`;
    }
}

function getRoleFromCache(uuid) {
    const item = sidebarItems.find(i => i.uuid === uuid);
    return item ? item.role : 'member';
}

async function loadChannels(communityUuid) {
    const container = document.getElementById(`channels-${communityUuid}`);
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { 
        action: 'get_community_details', 
        uuid: communityUuid 
    });

    if (res.success) {
        const role = res.info.role || 'member';
        // Guardar rol en el item local por si acaso
        const item = sidebarItems.find(i => i.uuid === communityUuid);
        if (item) item.role = role;

        channelsCache[communityUuid] = res.channels;
        renderChannelList(communityUuid, res.channels, role);
        container.dataset.loaded = "true";
    } else {
        container.innerHTML = `<div style="padding:10px; color:red; font-size:12px; text-align:center;">Error cargando canales</div>`;
    }
}

function renderChannelList(communityUuid, channels, userRole) {
    const container = document.getElementById(`channels-${communityUuid}`);
    if (!container) return;

    const isAdmin = ['admin', 'founder'].includes(userRole);
    let html = '';

    if (channels && channels.length > 0) {
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
            <div class="channel-item ${isChActive}" data-action="select-channel" data-uuid="${ch.uuid}" data-community="${communityUuid}">
                <span class="material-symbols-rounded channel-icon">${icon}</span>
                <span class="channel-name">${escapeHtml(ch.name)}</span>
                ${deleteBtn}
            </div>`;
        });
    } else {
        html = `<div style="padding:8px 12px; font-size:12px; color:#999; font-style:italic;">No hay canales</div>`;
    }

    // Botón Crear Canal (Solo Admins)
    if (isAdmin) {
        html += `
        <div class="channel-create-btn" data-action="create-channel-prompt" data-community="${communityUuid}">
            <span class="material-symbols-rounded">add</span> Crear canal
        </div>`;
    }

    container.innerHTML = html;
}

async function handleCreateChannel(communityUuid) {
    const name = prompt("Nombre del nuevo canal:");
    if (!name) return;
    
    // Limpieza básica
    const cleanName = name.trim().substring(0, 20);
    if (cleanName.length < 1) return;

    // Spinner temporal en el botón de crear
    const btn = document.querySelector(`[data-action="create-channel-prompt"][data-community="${communityUuid}"]`);
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

    if (res.success) {
        if (!channelsCache[communityUuid]) channelsCache[communityUuid] = [];
        channelsCache[communityUuid].push(res.channel);
        renderChannelList(communityUuid, channelsCache[communityUuid], getRoleFromCache(communityUuid));
    } else {
        alert(res.message || "Error al crear canal");
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.style.pointerEvents = 'auto';
        }
    }
}

async function handleDeleteChannel(channelUuid, communityUuid) {
    if (!confirm("¿Eliminar este canal y todos sus mensajes?")) return;

    const res = await postJson('api/communities_handler.php', { 
        action: 'delete_channel', 
        channel_uuid: channelUuid 
    });

    if (res.success) {
        if (channelsCache[communityUuid]) {
            channelsCache[communityUuid] = channelsCache[communityUuid].filter(c => c.uuid !== channelUuid);
            renderChannelList(communityUuid, channelsCache[communityUuid], getRoleFromCache(communityUuid));
        }
        // Si estábamos en ese canal, volver al default
        if (window.ACTIVE_CHANNEL_UUID === channelUuid) {
             const firstCh = channelsCache[communityUuid][0];
             if(firstCh) {
                 // Re-abrir comunidad (seleccionará el primer canal)
                 const item = sidebarItems.find(i => i.uuid === communityUuid);
                 openChat(communityUuid, item);
             }
        }
    } else {
        alert(res.message || "Error al eliminar");
    }
}

// ======================================================

async function handleSidebarUpdate(payload) {
    // Determinar el UUID correcto del chat
    let uuid = payload.community_uuid;
    
    // Si no es comunidad, es privado.
    if (!uuid) {
         if (parseInt(payload.sender_id) === parseInt(window.USER_ID)) {
             uuid = payload.target_uuid; 
         } else {
             uuid = payload.sender_uuid; 
         }
    }

    const container = document.getElementById('my-communities-list');
    
    // Buscar si ya existe la tarjeta
    let existingItemWrapper = null;
    if (uuid) {
        existingItemWrapper = container.querySelector(`.chat-item-wrapper[data-uuid="${uuid}"]`);
    }

    let messageText = payload.message;
    if (payload.type === 'image' || (payload.attachments && payload.attachments.length > 0)) {
        messageText = '📷 Imagen';
    }
    
    // Formato del texto de preview
    if (payload.context === 'community' && payload.sender_id != window.USER_ID) {
        messageText = `${payload.sender_username}: ${messageText}`;
    } else if (payload.sender_id == window.USER_ID) {
        messageText = `Tú: ${messageText}`;
    }

    // --- CASO 1: El chat YA existe visualmente ---
    if (existingItemWrapper) {
        const existingItem = existingItemWrapper.querySelector('.chat-item');
        const previewEl = existingItem.querySelector('.chat-item-preview');
        const timeEl = existingItem.querySelector('.chat-item-time');
        
        if (previewEl) {
            previewEl.textContent = messageText;
            if (uuid !== window.ACTIVE_CHAT_UUID) {
                previewEl.style.fontWeight = '700';
                previewEl.style.color = '#000';
            }
        }

        if (timeEl) {
            timeEl.textContent = formatChatTime(new Date());
        }

        const isActiveChat = (uuid === window.ACTIVE_CHAT_UUID);
        
        // Verificar si es el canal activo (si aplica)
        let isChannelActive = true;
        if (payload.channel_uuid && window.ACTIVE_CHANNEL_UUID && payload.channel_uuid !== window.ACTIVE_CHANNEL_UUID) {
            isChannelActive = false;
        }

        const windowHasFocus = document.hasFocus();

        // Actualizar contador si no estamos viendo ese chat/canal
        if (!isActiveChat || (isActiveChat && !isChannelActive) || !windowHasFocus) {
            let badge = existingItem.querySelector('.unread-counter');
            if (!badge) {
                const actionsContainer = existingItem.querySelector('.chat-item-actions');
                if (actionsContainer) {
                    badge = document.createElement('div');
                    badge.className = 'unread-counter';
                    badge.textContent = '1';
                    actionsContainer.prepend(badge);
                }
            } else {
                let count = parseInt(badge.textContent) || 0;
                badge.textContent = count + 1 > 99 ? '99+' : count + 1;
            }
        }

        // Mover arriba visualmente
        if (existingItemWrapper.style.display !== 'none') {
            container.prepend(existingItemWrapper);
        }
        
        // Actualizar datos en memoria
        const dataItem = sidebarItems.find(i => i.uuid === uuid);
        if (dataItem) {
            dataItem.last_message = messageText;
            dataItem.last_message_at = new Date().toISOString();
            if (!isActiveChat || !isChannelActive || !windowHasFocus) {
                dataItem.unread_count = (parseInt(dataItem.unread_count) || 0) + 1;
            }
            sidebarItems = sidebarItems.filter(i => i.uuid !== uuid);
            sidebarItems.unshift(dataItem);
        }

    } else {
        // --- CASO 2: Nuevo ---
        if (uuid) {
            let displayName, displayPfp, displayRole;
            let needsFetch = false;

            if (payload.context === 'community') {
                needsFetch = true;
            } else {
                if (parseInt(payload.sender_id) !== parseInt(window.USER_ID)) {
                    displayName = payload.sender_username;
                    displayPfp = payload.sender_profile_picture;
                    displayRole = payload.sender_role;
                } else {
                    needsFetch = true;
                }
            }

            if (needsFetch) {
                 fetchCommunityInfo(uuid, payload, messageText);
                 return; 
            }

            const newItem = {
                uuid: uuid,
                name: displayName || 'Usuario',
                profile_picture: displayPfp,
                type: payload.context,
                role: displayRole || 'user',
                last_message: messageText,
                last_message_at: payload.created_at || new Date().toISOString(),
                unread_count: (parseInt(payload.sender_id) !== parseInt(window.USER_ID)) ? 1 : 0,
                is_pinned: 0,
                is_favorite: 0,
                is_blocked_by_me: 0
            };

            sidebarItems.unshift(newItem);
            const html = renderChatListItem(newItem);
            container.insertAdjacentHTML('afterbegin', html);
        }
    }
}

async function fetchCommunityInfo(uuid, payload, messageText) {
    const action = (payload.context === 'private') ? 'get_user_chat_by_uuid' : 'get_community_by_uuid';
    try {
        const res = await postJson('api/communities_handler.php', { action: action, uuid: uuid });
        if (res.success) {
            const data = res.data || res.community;
            const newItem = {
                uuid: data.uuid,
                name: data.name || data.community_name || data.username,
                profile_picture: data.profile_picture,
                type: payload.context,
                role: data.role || 'member',
                last_message: messageText,
                last_message_at: payload.created_at || new Date().toISOString(),
                unread_count: 0,
                is_pinned: 0,
                is_favorite: 0,
                is_blocked_by_me: 0
            };
            
            if (!sidebarItems.find(i => i.uuid === newItem.uuid)) {
                sidebarItems.unshift(newItem);
                const html = renderChatListItem(newItem);
                const container = document.getElementById('my-communities-list');
                if (container) container.insertAdjacentHTML('afterbegin', html);
                
                if (window.ACTIVE_CHAT_UUID === newItem.uuid) {
                    const newEl = document.getElementById(`sidebar-item-${newItem.uuid}`);
                    if(newEl) newEl.classList.add('active');
                }
            }
        }
    } catch (e) { console.error(e); }
}

async function loadSidebarList(shouldOpenActive = false) {
    const res = await postJson('api/communities_handler.php', { action: 'get_sidebar_list' });
    
    if (res.success) {
        sidebarItems = res.list;
        renderSidebarList();
    } else {
        sidebarItems = [];
        renderSidebarList();
    }

    if (window.ACTIVE_CHAT_UUID) {
        // Marcar comunidad como activa
        const activeEl = document.getElementById(`sidebar-item-${window.ACTIVE_CHAT_UUID}`);
        if (activeEl) {
            activeEl.classList.add('active');
            activeEl.querySelector('.unread-counter')?.remove();
            
            // Si es comunidad, expandir
            if (activeEl.dataset.type === 'community') {
                const channelsList = document.getElementById(`channels-${window.ACTIVE_CHAT_UUID}`);
                if (channelsList) {
                    channelsList.classList.remove('d-none');
                    const icon = activeEl.querySelector('.expand-icon');
                    if(icon) icon.style.transform = 'rotate(180deg)';
                    
                    if (!channelsCache[window.ACTIVE_CHAT_UUID]) {
                        await loadChannels(window.ACTIVE_CHAT_UUID);
                    } else {
                        renderChannelList(window.ACTIVE_CHAT_UUID, channelsCache[window.ACTIVE_CHAT_UUID], getRoleFromCache(window.ACTIVE_CHAT_UUID));
                    }
                    
                    // Marcar canal activo
                    if (window.ACTIVE_CHANNEL_UUID) {
                        const chEl = channelsList.querySelector(`.channel-item[data-uuid="${window.ACTIVE_CHANNEL_UUID}"]`);
                        if(chEl) chEl.classList.add('active');
                    }
                }
            }
        }

        const itemData = sidebarItems.find(c => c.uuid === window.ACTIVE_CHAT_UUID);
        if (shouldOpenActive && itemData) {
            openChat(window.ACTIVE_CHAT_UUID, itemData || null);
        }
    }
}

function renderCommunityCard(comm, isJoined) {
    const avatar = comm.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
    const bannerStyle = comm.banner_picture ? `background-image: url('${(window.BASE_PATH || '/ProjectAurora/') + comm.banner_picture}');` : 'background-color: #eee;';
    let actionBtn = isJoined ? `<button class="component-button" disabled>Unido</button>` : `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;
    
    const typeKey = comm.community_type || 'other';
    const descriptionText = t('communities.descriptions.' + typeKey);

    return `<div class="comm-card"><div class="comm-banner" style="${bannerStyle}"></div><div class="comm-content"><div class="comm-header-row"><div class="comm-avatar-container"><img src="${avatar}" class="comm-avatar-img" alt="${escapeHtml(comm.community_name)}"></div><div class="comm-actions">${actionBtn}</div></div><div class="comm-info"><h3 class="comm-title">${escapeHtml(comm.community_name)}</h3><div class="comm-badges"><span class="comm-badge"><span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">group</span>${comm.member_count} miembros</span><span class="comm-badge">Publico</span></div><p class="comm-desc" style="margin-top:8px;">${escapeHtml(descriptionText)}</p></div></div></div>`;
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;
    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    if (res.success && res.communities.length > 0) container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    else container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles.</p>`;
}

function showChatMenu(btn, uuid, type, isPinned, isFav, isBlocked) {
    document.querySelector('.dynamic-popover')?.remove();

    const pinText = isPinned ? 'Desfijar chat' : 'Fijar chat';
    const pinIconStyle = isPinned ? 'color:#1976d2;' : '';
    
    const favText = isFav ? 'Quitar favorito' : 'Marcar favorito';
    const favIconStyle = isFav ? 'color:#fbc02d;' : '';

    let specificOptions = '';
    let deleteOption = '';

    const createItem = (action, icon, text, style = '', danger = false) => {
        const textColor = danger ? 'color: #d32f2f;' : '';
        const iconColor = danger ? 'color: #d32f2f;' : 'color: #333;';
        const finalIconStyle = style ? style : iconColor;

        return `
        <div class="menu-link" data-action="${action}" data-uuid="${uuid}" data-type="${type}">
            <div class="menu-link-icon">
                <span class="material-symbols-rounded" style="${finalIconStyle}">${icon}</span>
            </div>
            <div class="menu-link-text" style="${textColor}">${text}</div>
        </div>`;
    };

    if (type === 'private') {
        if (isBlocked) {
            specificOptions = `
                ${createItem('unblock-user-chat', 'lock_open', 'Desbloquear', '', true)}
                ${createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true)}
            `;
        } else {
            specificOptions = `
                ${createItem('block-user-chat', 'block', t('friends.block_user') || 'Bloquear', '', true)}
                ${createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true)}
            `;
        }
        deleteOption = createItem('delete-chat-conversation', 'delete', 'Eliminar chat', '', true);
    } else {
        specificOptions = createItem('leave-community', 'logout', 'Abandonar grupo', '', true);
    }

    const menu = document.createElement('div');
    menu.className = 'popover-module dynamic-popover body-title active';
    
    menu.innerHTML = `
        <div class="menu-content">
            <div class="menu-list">
                ${createItem('toggle-pin-chat', 'push_pin', pinText, pinIconStyle)}
                ${createItem('toggle-fav-chat', 'star', favText, favIconStyle)}
                ${deleteOption}
                <div class="component-divider" style="margin: 4px 0;"></div>
                ${specificOptions}
            </div>
        </div>
    `;

    const container = btn.parentElement; 
    container.appendChild(menu);
    btn.classList.add('active'); 

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                menu.remove();
                btn.classList.remove('active');
                if (!btn.matches(':hover') && !btn.closest('.chat-item:hover')) {
                    btn.remove();
                }
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);
}

async function togglePinChat(uuid, type) {
    const res = await postJson('api/communities_handler.php', { action: 'toggle_pin', uuid, type });
    if (res.success) loadSidebarList(); 
    else if(window.alertManager) window.alertManager.showAlert(res.message, 'warning');
}

async function toggleFavChat(uuid, type) {
    const res = await postJson('api/communities_handler.php', { action: 'toggle_favorite', uuid, type });
    if (res.success) loadSidebarList(); 
    else if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
}

async function leaveCommunity(uuid) {
    if (!confirm('¿Estás seguro de que quieres salir de este grupo?')) return;
    const res = await postJson('api/communities_handler.php', { action: 'leave_community', uuid: uuid });
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (window.ACTIVE_CHAT_UUID === uuid) {
             window.ACTIVE_CHAT_UUID = null;
             if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
        } else {
            loadSidebarList();
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

async function blockUserFromChat(uuid) {
    if (!confirm(t('friends.confirm_block') || '¿Seguro que quieres bloquear a este usuario?')) return;
    const resInfo = await postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid: uuid });
    
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resBlock = await postJson('api/friends_handler.php', { action: 'block_user', target_id: targetId });
        
        if (resBlock.success) {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'error');
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert("No se pudo identificar al usuario.", 'error');
    }
}

async function unblockUserFromChat(uuid) {
    const resInfo = await postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid: uuid });
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resUnblock = await postJson('api/friends_handler.php', { action: 'unblock_user', target_id: targetId });
        
        if (resUnblock.success) {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'error');
        }
    }
}

function initSidebarFilters() {
    const container = document.querySelector('.chat-sidebar-badges');
    const searchInput = document.getElementById('sidebar-search-input');

    if (container) {
        container.addEventListener('click', (e) => {
            const badge = e.target.closest('.sidebar-badge');
            if (badge) {
                container.querySelectorAll('.sidebar-badge').forEach(b => b.classList.remove('active'));
                badge.classList.add('active');
                currentFilter = badge.dataset.filter || 'all';
                renderSidebarList();
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim();
            renderSidebarList();
        });
    }
}

function initListListeners() {
    const listContainer = document.getElementById('my-communities-list');

    // Hover Button
    listContainer?.addEventListener('mouseover', (e) => {
        const item = e.target.closest('.chat-item');
        if (!item) return;
        const actionsDiv = item.querySelector('.chat-item-actions');
        if (!actionsDiv || actionsDiv.querySelector('.chat-hover-btn')) return;

        const btn = document.createElement('button');
        btn.className = 'chat-hover-btn';
        btn.dataset.action = 'open-chat-menu';
        
        btn.dataset.uuid = item.dataset.uuid;
        btn.dataset.type = item.dataset.type;
        btn.dataset.pinned = item.dataset.pinned;
        btn.dataset.fav = item.dataset.fav;
        btn.dataset.blocked = item.dataset.blocked;
        
        btn.innerHTML = '<span class="material-symbols-rounded">expand_more</span>';
        actionsDiv.appendChild(btn);
    });

    listContainer?.addEventListener('mouseout', (e) => {
        const item = e.target.closest('.chat-item');
        if (!item) return;
        if (item.contains(e.relatedTarget)) return;
        const btn = item.querySelector('.chat-hover-btn');
        if (btn && !btn.classList.contains('active')) btn.remove();
    });

    // Main List Click
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        
        // Menú Contextual
        const chatMenuBtn = e.target.closest('[data-action="open-chat-menu"]');
        if (chatMenuBtn) {
            e.preventDefault(); e.stopPropagation(); 
            const isPinned = chatMenuBtn.dataset.pinned === 'true';
            const isFav = chatMenuBtn.dataset.fav === 'true';
            const isBlocked = chatMenuBtn.dataset.blocked === 'true';
            showChatMenu(chatMenuBtn, chatMenuBtn.dataset.uuid, chatMenuBtn.dataset.type, isPinned, isFav, isBlocked);
            return;
        }

        // Clic en Canal Específico
        const channelItem = e.target.closest('[data-action="select-channel"]');
        if (channelItem && !e.target.closest('.channel-action-btn')) {
            const uuid = channelItem.dataset.uuid;
            const commUuid = channelItem.dataset.community;
            
            // Obtener datos de la comunidad para openChat
            const commItem = sidebarItems.find(i => i.uuid === commUuid);
            
            // Inyectar datos del canal
            const chatData = { ...commItem, channel_uuid: uuid, channel_name: channelItem.querySelector('.channel-name').innerText };
            
            // UI Update
            const parentList = channelItem.parentElement;
            parentList.querySelectorAll('.channel-item').forEach(el => el.classList.remove('active'));
            channelItem.classList.add('active');
            
            window.ACTIVE_CHANNEL_UUID = uuid;
            openChat(commUuid, chatData);
            return;
        }

        // Crear Canal
        const createBtn = e.target.closest('[data-action="create-channel-prompt"]');
        if (createBtn) {
            handleCreateChannel(createBtn.dataset.community);
            return;
        }

        // Eliminar Canal
        const deleteChBtn = e.target.closest('[data-action="delete-channel"]');
        if (deleteChBtn) {
            e.stopPropagation();
            const chUuid = deleteChBtn.dataset.uuid;
            const commUuid = deleteChBtn.closest('.channels-list-container').id.replace('channels-', '');
            handleDeleteChannel(chUuid, commUuid);
            return;
        }

        // Clic en Item Principal (Header)
        const item = e.target.closest('.chat-item');
        if (item) {
            const uuid = item.dataset.uuid;
            const type = item.dataset.type; 

            if (type === 'community') {
                // Toggle Accordion
                const chList = document.getElementById(`channels-${uuid}`);
                const icon = item.querySelector('.expand-icon');
                
                if (chList) {
                    if (chList.classList.contains('d-none')) {
                        chList.classList.remove('d-none');
                        if(icon) icon.style.transform = 'rotate(180deg)';
                        // Cargar canales si no están
                        if (chList.dataset.loaded !== "true") {
                            loadChannels(uuid);
                        }
                    } else {
                        chList.classList.add('d-none');
                        if(icon) icon.style.transform = 'rotate(0deg)';
                    }
                }
                
                // Opcional: Abrir el canal general o mantener estado
                // Si no hay chat activo, abrir el primero
                if (window.ACTIVE_CHAT_UUID !== uuid) {
                    const cached = channelsCache[uuid];
                    let targetChannel = null;
                    if (cached && cached.length > 0) targetChannel = cached[0];
                    
                    const itemData = sidebarItems.find(c => c.uuid === uuid);
                    if (itemData) {
                        itemData.channel_uuid = targetChannel ? targetChannel.uuid : null;
                        openChat(uuid, itemData);
                    }
                }

            } else {
                // Chat Privado
                const itemData = sidebarItems.find(c => c.uuid === uuid);
                openChat(uuid, itemData);
            }
        }
    });

    document.body.addEventListener('click', async (e) => {
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'join_public', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                const card = joinBtn.closest('.comm-card');
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
                loadSidebarList(); 
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
            return;
        }

        const blockBtn = e.target.closest('[data-action="block-user-chat"]');
        if (blockBtn) {
            e.preventDefault();
            document.querySelector('.dynamic-popover')?.remove();
            await blockUserFromChat(blockBtn.dataset.uuid);
            return;
        }

        const unblockBtn = e.target.closest('[data-action="unblock-user-chat"]');
        if (unblockBtn) {
            e.preventDefault();
            document.querySelector('.dynamic-popover')?.remove();
            await unblockUserFromChat(unblockBtn.dataset.uuid);
            return;
        }

        const pinAction = e.target.closest('[data-action="toggle-pin-chat"]');
        if (pinAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await togglePinChat(pinAction.dataset.uuid, pinAction.dataset.type);
            return;
        }

        const favAction = e.target.closest('[data-action="toggle-fav-chat"]');
        if (favAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await toggleFavChat(favAction.dataset.uuid, favAction.dataset.type);
            return;
        }

        const leaveAction = e.target.closest('[data-action="leave-community"]');
        if (leaveAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await leaveCommunity(leaveAction.dataset.uuid);
            return;
        }
        
        const deleteChatAction = e.target.closest('[data-action="delete-chat-conversation"]');
        if (deleteChatAction) {
            e.preventDefault();
            const uuid = deleteChatAction.dataset.uuid;
            document.querySelector('.dynamic-popover')?.remove();
            
            if(!confirm('¿Seguro que quieres eliminar este chat? Solo se borrará para ti.')) return;
            const res = await postJson('api/chat_handler.php', { action: 'delete_conversation', target_uuid: uuid });
            if(res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                if (window.ACTIVE_CHAT_UUID === uuid) {
                     window.ACTIVE_CHAT_UUID = null;
                     if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
                } else {
                    loadSidebarList();
                }
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            return;
        }
    });

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message' || type === 'private_message') {
            handleSidebarUpdate(payload);
        }
    });

    document.addEventListener('local-chat-read', (e) => {
        const uuid = e.detail.uuid;
        const item = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
        
        if (item) {
            const badge = item.querySelector('.unread-counter');
            if (badge) badge.remove();
            
            const preview = item.querySelector('.chat-item-preview');
            if (preview) {
                preview.style.fontWeight = 'normal';
                preview.style.color = '';
            }
            
            const dataItem = sidebarItems.find(i => i.uuid === uuid);
            if (dataItem) dataItem.unread_count = 0;
        }
    });
}

function initJoinByCode() {
    const btn = document.querySelector('[data-action="submit-join-community"]');
    if (!btn) return;
    const input = document.querySelector('[data-input="community-code"]');
    
    if(input) {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            if (v.length > 12) v = v.slice(0, 12);
            const parts = [];
            if (v.length > 0) parts.push(v.slice(0, 4));
            if (v.length > 4) parts.push(v.slice(4, 8));
            if (v.length > 8) parts.push(v.slice(8, 12));
            e.target.value = parts.join('-');
        });
    }

    btn.onclick = async () => {
        if (input.value.length < 14) return alert('Código incompleto.');
        setButtonLoading(btn, true);
        const res = await postJson('api/communities_handler.php', { action: 'join_by_code', access_code: input.value });
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    };
}

function initInfoPanelListener() {
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
        const avatar = m.profile_picture 
            ? (window.BASE_PATH || '/ProjectAurora/') + m.profile_picture 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(m.username)}`;
        
        const roleColor = (m.role === 'admin' || m.role === 'founder') ? '#d32f2f' : ((m.role === 'moderator') ? '#1976d2' : '#888');
        const roleText = m.role === 'founder' ? 'Fundador' : (m.role === 'admin' ? 'Admin' : (m.role === 'moderator' ? 'Mod' : 'Miembro'));

        html += `
            <div class="info-member-item">
                <img src="${avatar}" class="info-member-avatar" alt="${m.username}">
                <div class="info-member-details">
                    <span class="info-member-name">${escapeHtml(m.username)}</span>
                    <span class="info-member-role" style="color:${roleColor}; font-size:10px; font-weight:700;">${roleText}</span>
                </div>
            </div>
        `;
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
        html += `
            <img src="${src}" 
                 class="info-file-thumb" 
                 data-action="view-media" 
                 data-index="${index}" 
                 loading="lazy">
        `;
    });
    container.innerHTML = html;
}

export function initCommunitiesManager() {
    loadSidebarList(true); 
    loadPublicCommunities(); 
    initJoinByCode(); 
    initSidebarFilters(); 
    initInfoPanelListener(); 
    
    if (!window.communitiesListenersInit) {
        initListListeners();
        window.communitiesListenersInit = true;
    }
}
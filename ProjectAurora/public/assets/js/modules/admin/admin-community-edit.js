// public/assets/js/modules/admin/admin-community-edit.js

import { postJson } from '../../core/utilities.js';
import { t } from '../../core/i18n-manager.js';

let currentId = 0;
let currentChannels = []; // Array local de canales

function toggleAccessCodeVisibility(privacyValue) {
    const wrapper = document.getElementById('wrapper-access-code');
    if (!wrapper) return;

    if (privacyValue === 'private') {
        wrapper.classList.remove('d-none');
        // Pequeño timeout para asegurar que se muestre antes de intentar el foco
        setTimeout(() => document.getElementById('input-comm-code')?.focus(), 50); 
    } else {
        wrapper.classList.add('d-none');
    }
}

export function initAdminCommunityEdit() {
    const inputId = document.getElementById('community-target-id');
    currentId = inputId ? parseInt(inputId.value) : 0;
    currentChannels = []; // Resetear canales

    if (currentId > 0) {
        loadData();
    } else {
        // Generar código aleatorio al entrar si es nueva
        generateCode();
        // Ocultar al inicio si es nueva y por defecto es pública
        toggleAccessCodeVisibility('public');
        // Canal por defecto para nuevas comunidades
        currentChannels.push({ id: 0, name: 'General', type: 'text' });
        renderChannels();
    }

    initListeners();
}

function initListeners() {
    // Dropdown Tipo de Comunidad
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-comm-type"]');
        if (opt) {
            const val = opt.dataset.value;
            const icon = opt.dataset.icon;
            const labelKey = opt.dataset.labelKey;
            
            document.getElementById('input-comm-type').value = val;
            document.getElementById('text-type').textContent = t(labelKey);
            document.getElementById('icon-type').textContent = icon;
        }
    });

    // Dropdown privacidad [MODIFICADO: AÑADIDO TOGGLE DE CÓDIGO]
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-comm-privacy"]');
        if (opt) {
            const val = opt.dataset.value;
            const label = opt.dataset.label;
            const icon = opt.dataset.icon;
            
            document.getElementById('input-comm-privacy').value = val;
            document.getElementById('text-privacy').textContent = label;
            document.getElementById('icon-privacy').textContent = icon;

            // Lógica de mostrar/ocultar el código de acceso
            toggleAccessCodeVisibility(val);
        }
    });

    // Dropdown Tipo de Canal (Nuevo)
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-channel-type"]');
        if (opt) {
            const val = opt.dataset.value;
            const label = opt.dataset.label;
            document.getElementById('new-channel-type').value = val;
            document.getElementById('new-channel-type-text').textContent = label;
        }
    });

    // Agregar Canal
    const btnAddChannel = document.getElementById('btn-add-channel');
    if (btnAddChannel) {
        btnAddChannel.onclick = () => {
            const nameInput = document.getElementById('new-channel-name');
            const typeInput = document.getElementById('new-channel-type');
            
            const name = nameInput.value.trim();
            const type = typeInput.value;

            if (!name) return alert("El nombre del canal es obligatorio.");
            
            // Agregar al array local (id 0 indica nuevo)
            currentChannels.push({ id: 0, name: name, type: type });
            
            // Limpiar input y renderizar
            nameInput.value = '';
            renderChannels();
        };
    }

    // Eliminar Canal (Delegado)
    document.getElementById('channels-list-container')?.addEventListener('click', (e) => {
        const delBtn = e.target.closest('[data-action="remove-channel"]');
        if (delBtn) {
            const index = parseInt(delBtn.dataset.index);
            if (currentChannels[index]) {
                // Si es el último canal, advertir (opcional)
                if (currentChannels.length <= 1) {
                    return alert("La comunidad debe tener al menos un canal.");
                }
                currentChannels.splice(index, 1);
                renderChannels();
            }
        }
    });

    // Generar código
    document.getElementById('btn-gen-code').onclick = generateCode;

    // Previsualización de imágenes al salir del input
    document.getElementById('input-comm-pfp').addEventListener('blur', updatePreviews);
    document.getElementById('input-comm-banner').addEventListener('blur', updatePreviews);

    // Guardar
    document.getElementById('btn-save-community').onclick = saveCommunity;

    // Eliminar
    const btnDel = document.getElementById('btn-delete-community');
    if (btnDel) btnDel.onclick = deleteCommunity;
}

async function loadData() {
    const res = await postJson('api/admin_handler.php', { action: 'get_admin_community_details', id: currentId });
    if (res.success && res.community) {
        const c = res.community;
        document.getElementById('input-comm-name').value = c.community_name;
        document.getElementById('input-comm-code').value = c.access_code;
        document.getElementById('input-comm-pfp').value = c.profile_picture || '';
        document.getElementById('input-comm-banner').value = c.banner_picture || '';
        
        // Set Privacidad
        const privEl = document.querySelector(`[data-action="select-comm-privacy"][data-value="${c.privacy}"]`);
        if(privEl) privEl.click(); 

        // Set Tipo
        const typeEl = document.querySelector(`[data-action="select-comm-type"][data-value="${c.community_type}"]`);
        if (typeEl) {
            typeEl.click();
        } else {
            // Fallback a 'other'
            const defaultType = document.querySelector(`[data-action="select-comm-type"][data-value="other"]`);
            if(defaultType) defaultType.click();
        }
        
        // Cargar Canales
        if (c.channels && Array.isArray(c.channels)) {
            currentChannels = c.channels.map(ch => ({
                id: ch.id,
                name: ch.name,
                type: ch.type
            }));
        }
        renderChannels();
        
        // [MODIFICADO] Asegurar la visibilidad inicial al cargar datos
        toggleAccessCodeVisibility(c.privacy);

        updatePreviews();
    }
}

function renderChannels() {
    const container = document.getElementById('channels-list-container');
    if (!container) return;

    container.innerHTML = '';

    currentChannels.forEach((ch, index) => {
        const icon = (ch.type === 'announcement') ? 'campaign' : 'tag';
        
        const row = document.createElement('div');
        row.className = 'channel-edit-row';
        row.style.cssText = 'display: flex; align-items: center; padding: 8px 12px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; gap: 10px;';
        
        row.innerHTML = `
            <span class="material-symbols-rounded" style="color: #999; font-size: 20px;">${icon}</span>
            <div style="flex: 1; display: flex; flex-direction: column;">
                <span style="font-size: 14px; font-weight: 600; color: #333;">${ch.name}</span>
                <span style="font-size: 11px; color: #888; text-transform: capitalize;">${ch.type === 'text' ? 'Texto' : 'Anuncios'}</span>
            </div>
            <button class="component-icon-button small" data-action="remove-channel" data-index="${index}" style="width: 32px; height: 32px; border-color: transparent; color: #d32f2f;">
                <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
            </button>
        `;
        container.appendChild(row);
    });
}

function generateCode() {
    // Generar formato XXXX-XXXX-XXXX
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 12; i++) {
        if (i > 0 && i % 4 === 0) code += '-';
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('input-comm-code').value = code;
}

function updatePreviews() {
    const pfpUrl = document.getElementById('input-comm-pfp').value.trim();
    const bannerUrl = document.getElementById('input-comm-banner').value.trim();

    const imgPfp = document.getElementById('preview-avatar');
    const phPfp = document.getElementById('placeholder-avatar');
    
    if (pfpUrl) {
        imgPfp.src = pfpUrl; imgPfp.style.display = 'block'; phPfp.style.display = 'none';
    } else {
        imgPfp.style.display = 'none'; phPfp.style.display = 'flex';
    }

    const imgBan = document.getElementById('preview-banner');
    const phBan = document.getElementById('placeholder-banner');

    if (bannerUrl) {
        imgBan.src = bannerUrl; imgBan.style.display = 'block'; phBan.style.display = 'none';
    } else {
        imgBan.style.display = 'none'; phBan.style.display = 'flex';
    }
}

async function saveCommunity() {
    const btn = document.getElementById('btn-save-community');
    
    const name = document.getElementById('input-comm-name').value.trim();
    const code = document.getElementById('input-comm-code').value.trim();

    if (!name) return alert("El nombre es obligatorio.");
    if (!code) return alert("El código de acceso es obligatorio.");
    if (currentChannels.length === 0) return alert("Debes agregar al menos un canal.");

    // [FIX] Asegurarse de enviar el estado del botón a loading
    // Usamos una función propia setButtonLoading si está disponible o lo hacemos manual
    if (window.setButtonLoading) {
        window.setButtonLoading(btn, true);
    } else {
        btn.disabled = true;
        btn.innerHTML = '<div class="small-spinner"></div>';
    }

    const payload = {
        action: 'save_community',
        id: currentId,
        name: name,
        community_type: document.getElementById('input-comm-type').value,
        privacy: document.getElementById('input-comm-privacy').value,
        access_code: code,
        profile_picture: document.getElementById('input-comm-pfp').value,
        banner_picture: document.getElementById('input-comm-banner').value,
        channels: currentChannels // Enviamos el array completo
    };

    const res = await postJson('api/admin_handler.php', payload);
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (currentId === 0) {
            setTimeout(() => window.navigateTo('admin/communities'), 1000);
        } else {
            // Recargar datos para obtener IDs reales de canales nuevos
            loadData();
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    
    if (window.setButtonLoading) {
        window.setButtonLoading(btn, false, '<span class="material-symbols-rounded">save</span>');
    } else {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded">save</span>';
    }
}

async function deleteCommunity() {
    if (!confirm("¿Seguro que quieres eliminar esta comunidad? Esta acción es irreversible.")) return;
    
    const btn = document.getElementById('btn-delete-community');
    if (window.setButtonLoading) window.setButtonLoading(btn, true);

    const res = await postJson('api/admin_handler.php', { action: 'delete_community', id: currentId });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
        window.navigateTo('admin/communities');
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        if (window.setButtonLoading) window.setButtonLoading(btn, false, '<span class="material-symbols-rounded">delete</span>');
    }
}
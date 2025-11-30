// public/assets/js/modules/admin/admin-community-edit.js

import { postJson } from '../../core/utilities.js';
import { t } from '../../core/i18n-manager.js';

let currentId = 0;

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

    if (currentId > 0) {
        loadData();
    } else {
        // Generar código aleatorio al entrar si es nueva
        generateCode();
        // Ocultar al inicio si es nueva y por defecto es pública
        toggleAccessCodeVisibility('public');
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
        
        // [MODIFICADO] Asegurar la visibilidad inicial al cargar datos
        toggleAccessCodeVisibility(c.privacy);

        updatePreviews();
    }
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
    setButtonLoading(btn, true);

    const payload = {
        action: 'save_community',
        id: currentId,
        name: document.getElementById('input-comm-name').value,
        community_type: document.getElementById('input-comm-type').value,
        privacy: document.getElementById('input-comm-privacy').value,
        access_code: document.getElementById('input-comm-code').value,
        profile_picture: document.getElementById('input-comm-pfp').value,
        banner_picture: document.getElementById('input-comm-banner').value
    };

    const res = await postJson('api/admin_handler.php', payload);
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (currentId === 0) {
            setTimeout(() => window.navigateTo('admin/communities'), 1000);
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    setButtonLoading(btn, false, '<span class="material-symbols-rounded">save</span>');
}

async function deleteCommunity() {
    if (!confirm("¿Seguro que quieres eliminar esta comunidad? Esta acción es irreversible.")) return;
    
    const btn = document.getElementById('btn-delete-community');
    setButtonLoading(btn, true);

    const res = await postJson('api/admin_handler.php', { action: 'delete_community', id: currentId });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
        window.navigateTo('admin/communities');
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        setButtonLoading(btn, false, '<span class="material-symbols-rounded">delete</span>');
    }
}
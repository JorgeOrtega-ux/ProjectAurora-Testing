// public/assets/js/modules/banner-manager.js

import { t } from '../core/i18n-manager.js';
import { postJson } from '../core/utilities.js';

const STORAGE_PREFIX = 'aurora_dismissed_alert_';

export function initBannerManager() {
    // 1. Chequeo inicial al cargar
    checkInitialStatus();

    // 2. Escuchar Socket
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        if (type === 'system_alert_update') {
            if (payload.status === 'active') {
                renderBanner(payload.type, payload.instance_id, payload.meta_data);
            } else {
                removeBanner();
            }
        }
    });
}

async function checkInitialStatus() {
    const res = await postJson('api/admin_handler.php', { action: 'get_alert_status' });
    if (res.success && res.active_alert) {
        // Si viene de DB, meta_data podría ser string JSON
        let meta = res.active_alert.meta_data;
        if (typeof meta === 'string') {
            try { meta = JSON.parse(meta); } catch(e) {}
        }
        renderBanner(res.active_alert.type, res.active_alert.instance_id, meta);
    }
}

function renderBanner(type, instanceId, metaData = {}) {
    // Verificar si el usuario ya cerró ESTA instancia específica
    if (localStorage.getItem(STORAGE_PREFIX + instanceId)) {
        return;
    }

    // Si ya existe uno, actualizarlo
    let existingBanner = document.getElementById('global-system-banner');
    if (existingBanner) {
        existingBanner.remove();
    }

    const banner = document.createElement('div');
    banner.id = 'global-system-banner';
    banner.className = `system-banner banner-${type}`;
    
    const icons = {
        'maintenance_warning': 'engineering',
        'high_traffic': 'dns',
        'critical_issue': 'report',
        'update_info': 'update',
        'terms_update': 'gavel',
        'privacy_update': 'policy',
        'cookie_update': 'cookie'
    };

    const icon = icons[type] || 'info';
    
    // Construcción Dinámica del Mensaje
    let text = t(`admin.alerts.templates.${type}.text`);
    
    // Reemplazar variables {date} y {time} si existen en la plantilla
    if (metaData.date) {
        // Formatear fecha bonita si es posible (ej: 2025-11-28 -> 28 de noviembre del 2025)
        const dateObj = new Date(metaData.date + 'T00:00:00');
        const dateStr = dateObj.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
        
        text = text.replace('{date}', dateStr);
        text = text.replace('{time}', metaData.time || '');
    }

    // Append Link si existe
    if (metaData.link) {
        const seeMoreText = t('admin.alerts.see_more') || 'ver más';
        text += ` <a href="${metaData.link}" target="_blank" style="color:inherit; text-decoration:underline; font-weight:700;">${seeMoreText}</a>`;
    }

    banner.innerHTML = `
        <div class="banner-content">
            <span class="material-symbols-rounded banner-icon">${icon}</span>
            <span class="banner-text">${text}</span>
        </div>
        <button class="banner-close" aria-label="Cerrar">
            <span class="material-symbols-rounded">close</span>
        </button>
    `;

    // [CORREGIDO] Inyectar dentro de .main-content
    const targetContainer = document.querySelector('.main-content');
    
    if (targetContainer) {
        targetContainer.prepend(banner);
    } else {
        // Fallback al body si no existe el layout principal (ej: login fuera del layout app)
        document.body.prepend(banner);
    }

    // Lógica de cierre
    banner.querySelector('.banner-close').addEventListener('click', () => {
        localStorage.setItem(STORAGE_PREFIX + instanceId, 'true');
        banner.classList.add('fade-out-up');
        setTimeout(() => banner.remove(), 300);
    });
}

function removeBanner() {
    const banner = document.getElementById('global-system-banner');
    if (banner) {
        banner.classList.add('fade-out-up');
        setTimeout(() => banner.remove(), 300);
    }
}
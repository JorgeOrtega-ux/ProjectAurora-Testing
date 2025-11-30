// public/assets/js/modules/admin/admin-alerts.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';
import { closeAllModules } from '../../ui/main-controller.js';

// --- CONFIGURACIÓN DE FLUJO ---
const TYPES_WITH_DATE = [
    'maintenance_warning',
    'terms_update',
    'privacy_update',
    'cookie_update',
    'update_info' // Flujo: Primero fecha
];

const TYPES_WITH_LINK = [
    'update_info', // Flujo: Después enlace
    'terms_update',
    'privacy_update',
    'cookie_update'
];

export function initAdminAlerts() {
    checkActiveAlert();
    initListeners();
}

async function checkActiveAlert() {
    const res = await postJson('api/admin_handler.php', { action: 'get_alert_status' });
    if (res.success) {
        updateUI(res.active_alert);
    }
}

function updateUI(activeAlert) {
    const indicator = document.getElementById('active-alert-indicator');
    const indicatorName = document.getElementById('active-alert-name');
    const indicatorMeta = document.getElementById('active-alert-meta');
    
    const mainEmitBtn = document.getElementById('btn-emit-selected-alert');
    const triggerWrapper = document.querySelector('.trigger-select-wrapper');

    if (activeAlert) {
        // Hay alerta activa
        if (indicator) indicator.classList.remove('d-none');
        if (indicatorName) indicatorName.textContent = t(`admin.alerts.templates.${activeAlert.type}.title`);
        
        // Mostrar metadatos si existen
        if (indicatorMeta) {
            let metaText = '';
            if (activeAlert.meta_data) {
                const meta = (typeof activeAlert.meta_data === 'string') ? JSON.parse(activeAlert.meta_data) : activeAlert.meta_data;
                
                if (meta.date) metaText += `📅 ${meta.date} ${meta.time || ''} `;
                if (meta.link) metaText += `🔗 ${meta.link}`;
            }
            indicatorMeta.textContent = metaText;
        }
        
        if (mainEmitBtn) {
            mainEmitBtn.disabled = true;
            mainEmitBtn.textContent = 'Alerta en curso...';
        }
        
        if (triggerWrapper) {
            triggerWrapper.classList.add('disabled-interactive');
            triggerWrapper.style.opacity = '0.5';
        }

    } else {
        if (indicator) indicator.classList.add('d-none');
        
        if (triggerWrapper) {
            triggerWrapper.classList.remove('disabled-interactive');
            triggerWrapper.style.opacity = '1';
        }

        const currentSelection = document.getElementById('input-alert-type').value;
        if (mainEmitBtn) {
            if (currentSelection) {
                mainEmitBtn.disabled = false;
                mainEmitBtn.textContent = t('admin.alerts.emit_btn');
            } else {
                mainEmitBtn.disabled = true;
            }
        }
    }
}

function handleSelection(option) {
    const val = option.dataset.value;
    const label = option.dataset.label;
    const icon = option.dataset.icon;
    const color = option.dataset.color;

    // Inputs
    document.getElementById('input-alert-type').value = val;
    document.getElementById('current-alert-text').textContent = label;
    const iconEl = document.getElementById('current-alert-icon');
    iconEl.textContent = icon;
    iconEl.style.color = color;

    // Preview Description
    const descKey = `admin.alerts.templates.${val}.desc`;
    const previewEl = document.getElementById('alert-preview-desc');
    if (previewEl) previewEl.textContent = t(descKey);

    // Habilitar botón principal
    const btn = document.getElementById('btn-emit-selected-alert');
    if (btn) {
        btn.disabled = false;
        btn.textContent = t('admin.alerts.emit_btn');
    }

    // === LÓGICA SECUENCIAL ESTRICTA ===
    const dateWrapper = document.getElementById('wrapper-date-picker');
    const linkContainer = document.getElementById('wrapper-link-container');

    // 1. RESET: Ocultar ambos wrappers completos
    dateWrapper.classList.add('d-none');
    linkContainer.classList.add('d-none');

    const requiresDate = TYPES_WITH_DATE.includes(val);
    const requiresLink = TYPES_WITH_LINK.includes(val);

    // 2. SECUENCIA:
    if (requiresDate) {
        // Si requiere fecha, mostramos el wrapper de fecha.
        // El de enlace permanece oculto hasta confirmar.
        dateWrapper.classList.remove('d-none');
        
        // Reset visual
        document.getElementById('selected-datetime-text').textContent = t('admin.alerts.select_date_placeholder');
        document.getElementById('input-alert-date').value = '';
        document.getElementById('input-alert-time').value = '';
    } 
    else if (requiresLink) {
        // Si NO requiere fecha pero SÍ enlace, mostrar wrapper de enlace directamente
        linkContainer.classList.remove('d-none');
        setTimeout(() => document.getElementById('input-alert-link').focus(), 100);
    }
}

function handleDateTimeConfirm() {
    const dateIn = document.getElementById('picker-date-input').value;
    const timeIn = document.getElementById('picker-time-input').value;
    
    if (!dateIn) {
        alert('Selecciona al menos una fecha.');
        return;
    }

    // Guardar
    document.getElementById('input-alert-date').value = dateIn;
    document.getElementById('input-alert-time').value = timeIn;

    // Actualizar texto visual
    const displayDate = new Date(dateIn + 'T00:00:00').toLocaleDateString();
    const displayText = `${displayDate} ${timeIn ? 'a las ' + timeIn : ''}`;
    document.getElementById('selected-datetime-text').textContent = displayText;
    
    // Cerrar popover
    closeAllModules(); 

    // === PASO 2: MOSTRAR ENLACE SI ES NECESARIO ===
    const currentType = document.getElementById('input-alert-type').value;
    const requiresLink = TYPES_WITH_LINK.includes(currentType);
    
    if (requiresLink) {
        const linkContainer = document.getElementById('wrapper-link-container');
        linkContainer.classList.remove('d-none');
        linkContainer.classList.add('animate-fade-in'); 
        
        setTimeout(() => {
            const linkInput = document.getElementById('input-alert-link');
            if (linkInput) linkInput.focus();
        }, 200);
    }
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        
        // 1. Selección del Tipo
        const option = e.target.closest('[data-action="select-alert-option"]');
        if (option) {
            handleSelection(option);
            return;
        }

        // 2. Confirmar Fecha
        const confirmDateBtn = e.target.closest('[data-action="confirm-datetime"]');
        if (confirmDateBtn) {
            e.preventDefault();
            e.stopPropagation();
            handleDateTimeConfirm();
            return;
        }

        // 3. Emitir Alerta
        const emitBtn = e.target.closest('#btn-emit-selected-alert');
        if (emitBtn && !emitBtn.disabled) {
            const type = document.getElementById('input-alert-type').value;
            if (!type) return;

            // Validaciones (checking visibility of containers)
            const dateWrapper = document.getElementById('wrapper-date-picker');
            const linkContainer = document.getElementById('wrapper-link-container');
            
            const needsDate = !dateWrapper.classList.contains('d-none');
            const needsLink = !linkContainer.classList.contains('d-none');

            const dateVal = document.getElementById('input-alert-date').value;
            const linkVal = document.getElementById('input-alert-link').value;
            const timeVal = document.getElementById('input-alert-time').value;

            if (needsDate && !dateVal) {
                alert(t('admin.error.reason_required') + ' (Falta la fecha)');
                return;
            }
            if (needsLink && !linkVal) {
                alert(t('admin.error.reason_required') + ' (Falta el enlace)');
                return;
            }

            if (!confirm(t('admin.alerts.confirm_emit'))) return;

            setButtonLoading(emitBtn, true);
            
            const payload = { 
                action: 'activate_alert', 
                type,
                meta_data: {
                    date: dateVal,
                    time: timeVal,
                    link: linkVal
                }
            };

            const res = await postJson('api/admin_handler.php', payload);
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(emitBtn, false, t('admin.alerts.emit_btn'));
            }
        }

        // 4. Detener Alerta
        const stopBtn = e.target.closest('[data-action="stop-alert"]');
        if (stopBtn) {
            if (!confirm(t('admin.alerts.confirm_stop'))) return;

            setButtonLoading(stopBtn, true);
            const res = await postJson('api/admin_handler.php', { action: 'stop_alert' });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                
                // Reset UI
                document.getElementById('selected-datetime-text').textContent = t('admin.alerts.select_date_placeholder');
                document.getElementById('input-alert-date').value = '';
                document.getElementById('input-alert-time').value = '';
                document.getElementById('input-alert-link').value = '';
                
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(stopBtn, false);
        }
    });
}
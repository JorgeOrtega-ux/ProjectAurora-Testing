// public/assets/js/modules/admin/admin-server.js

import { t } from '../../core/i18n-manager.js';
import { postJson } from '../../core/utilities.js';

let debounceTimer = null;
let currentDomains = [];

const actionKeyMap = {
    'update-min-password-length': 'min_password_length',
    'update-max-password-length': 'max_password_length',
    'update-min-username-length': 'min_username_length',
    'update-max-username-length': 'max_username_length',
    'update-max-email-length': 'max_email_length',
    'update-max-login-attempts': 'max_login_attempts',
    'update-lockout-time-minutes': 'lockout_time_minutes',
    'update-code-resend-cooldown': 'code_resend_cooldown',
    'update-username-cooldown': 'username_cooldown',
    'update-email-cooldown': 'email_cooldown',
    'update-avatar-max-size': 'profile_picture_max_size',
    // [NUEVO] Anti-Spam Keys
    'update-chat-limit-count': 'chat_msg_limit',
    'update-chat-limit-time': 'chat_time_window'
};

async function updateConfig(key, value, elementToRevertOnError, silent = false) {
    const res = await postJson('api/admin_handler.php', { 
        action: 'update_server_config', 
        key, 
        value 
    });

    if (res.success) {
        if (!silent && window.alertManager) window.alertManager.showAlert(res.message, 'success');
        
        if (!window.SERVER_CONFIG) window.SERVER_CONFIG = {};
        window.SERVER_CONFIG[key] = value;
        console.log(`[Config] Updated ${key} to`, value);

        if (key === 'maintenance_mode' && value === 1) {
            const regToggle = document.getElementById('toggle-allow-registration');
            if (regToggle) regToggle.checked = false;
        }
    } else {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
        if (elementToRevertOnError && elementToRevertOnError.type === 'checkbox') {
            elementToRevertOnError.checked = !elementToRevertOnError.checked;
        }
    }
}

function handleStepperClick(btn) {
    const stepper = btn.closest('.component-stepper');
    if (!stepper) return;

    const action = stepper.dataset.action;
    const min = parseInt(stepper.dataset.min);
    const max = parseInt(stepper.dataset.max);
    let currentVal = parseInt(stepper.dataset.currentValue);
    
    const stepAction = btn.dataset.stepAction;
    const step1 = parseInt(stepper.dataset.step1 || 1);
    const step10 = parseInt(stepper.dataset.step10 || 10);

    let newVal = currentVal;

    if (stepAction === 'increment-1') newVal += step1;
    if (stepAction === 'decrement-1') newVal -= step1;
    if (stepAction === 'increment-10') newVal += step10;
    if (stepAction === 'decrement-10') newVal -= step10;

    if (newVal < min) newVal = min;
    if (newVal > max) newVal = max;

    stepper.dataset.currentValue = newVal;
    const valueDisplay = stepper.querySelector('.stepper-value');
    if (valueDisplay) valueDisplay.textContent = newVal;

    updateCardTexts(stepper, newVal);

    const btns = stepper.querySelectorAll('.stepper-button');
    btns.forEach(b => {
        const type = b.dataset.stepAction;
        if (type.includes('decrement')) b.disabled = (newVal <= min);
        if (type.includes('increment')) b.disabled = (newVal >= max);
    });

    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const key = actionKeyMap[action];
        if (key) {
            updateConfig(key, newVal, null, true); 
        }
    }, 500);
}

function updateCardTexts(stepper, newValue) {
    const card = stepper.closest('.component-card');
    if (!card) return;

    const textElements = card.querySelectorAll('[data-i18n]');
    textElements.forEach(el => {
        const key = el.dataset.i18n;
        el.innerHTML = t(key, { val: newValue });
        el.setAttribute('data-i18n-vars', JSON.stringify({ val: newValue }));
    });
}

function loadInitialDomains() {
    const container = document.getElementById('domain-list-container');
    if (!container) return;
    
    currentDomains = [];
    container.querySelectorAll('.domain-chip').forEach(chip => {
        const text = chip.querySelector('span:nth-child(2)').textContent;
        currentDomains.push(text);
    });
}

async function syncDomains() {
    await updateConfig('allowed_email_domains', currentDomains, null, false);
}

function renderDomains() {
    const container = document.getElementById('domain-list-container');
    if (!container) return;
    
    container.innerHTML = '';
    currentDomains.forEach(dom => {
        const chip = document.createElement('div');
        chip.className = 'domain-chip';
        chip.innerHTML = `
            <span class="material-symbols-rounded chip-icon">language</span>
            <span>${dom}</span>
            <span class="material-symbols-rounded chip-remove" data-action="remove-domain" data-domain="${dom}">close</span>
        `;
        container.appendChild(chip);
    });
}

export function initAdminServer() {
    loadInitialDomains();

    document.body.addEventListener('change', (e) => {
        const target = e.target;
        if (target.matches('#toggle-maintenance-mode')) {
            updateConfig('maintenance_mode', target.checked ? 1 : 0, target);
        }
        if (target.matches('#toggle-allow-registration')) {
            updateConfig('allow_registrations', target.checked ? 1 : 0, target);
        }
    });

    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        const btn = target.closest('.stepper-button');
        if (btn) handleStepperClick(btn);

        const header = target.closest('[data-action="toggle-accordion"]');
        if (header) {
            const currentAccordion = header.closest('.component-accordion');
            if (currentAccordion) {
                const allActive = document.querySelectorAll('.component-accordion.active');
                allActive.forEach(acc => {
                    if (acc !== currentAccordion) {
                        acc.classList.remove('active');
                    }
                });
                currentAccordion.classList.toggle('active');
            }
        }

        if (target.closest('[data-action="show-add-domain-form"]')) {
            document.getElementById('add-domain-btn-wrapper').classList.add('d-none');
            document.getElementById('add-domain-form-wrapper').classList.remove('d-none');
            document.getElementById('new-domain-input').focus();
        }

        if (target.closest('[data-action="cancel-add-domain"]')) {
            document.getElementById('new-domain-input').value = '';
            document.getElementById('add-domain-form-wrapper').classList.add('d-none');
            document.getElementById('add-domain-btn-wrapper').classList.remove('d-none');
        }

        if (target.closest('[data-action="save-new-domain"]')) {
            const input = document.getElementById('new-domain-input');
            let val = input.value.trim().toLowerCase();

            if (!val) return;
            
            const domainRegex = /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/;
            if (!domainRegex.test(val)) {
                if(window.alertManager) window.alertManager.showAlert('Formato inválido. Debe ser ej: gmail.com', 'error');
                return;
            }

            if (currentDomains.includes(val)) {
                if(window.alertManager) window.alertManager.showAlert('Este dominio ya está en la lista', 'warning');
                return;
            }

            currentDomains.push(val);
            renderDomains();
            await syncDomains();

            input.value = '';
            document.getElementById('add-domain-form-wrapper').classList.add('d-none');
            document.getElementById('add-domain-btn-wrapper').classList.remove('d-none');
        }

        const removeBtn = target.closest('[data-action="remove-domain"]');
        if (removeBtn) {
            const domainToRemove = removeBtn.dataset.domain;
            if (confirm(`¿Eliminar ${domainToRemove}?`)) {
                currentDomains = currentDomains.filter(d => d !== domainToRemove);
                renderDomains();
                await syncDomains();
            }
        }
    });
    
    document.querySelectorAll('.component-stepper').forEach(stepper => {
        const min = parseInt(stepper.dataset.min);
        const max = parseInt(stepper.dataset.max);
        const current = parseInt(stepper.dataset.currentValue);
        const btns = stepper.querySelectorAll('.stepper-button');
        btns.forEach(b => {
            const type = b.dataset.stepAction;
            if (type.includes('decrement')) b.disabled = (current <= min);
            if (type.includes('increment')) b.disabled = (current >= max);
        });
    });
}
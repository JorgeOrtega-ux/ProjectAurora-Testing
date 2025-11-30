// public/assets/js/modules/settings-manager.js

import { changeLanguage, t } from '../core/i18n-manager.js';
import { updateTheme } from '../core/theme-manager.js'; 
import { postJson, setButtonLoading, toggleCardError, qs } from '../core/utilities.js';

let areGlobalsInitialized = false;

export function initSettingsManager() {
    const isProfile = qs('[data-section="settings/your-profile"]');
    const isChangePass = qs('[data-section="settings/change-password"]');
    const isSessions = qs('[data-section="settings/sessions"]');
    const isDeleteAccount = qs('[data-section="settings/delete-account"]');
    const is2FA = qs('[data-section="settings/2fa-setup"]');
    
    if (isProfile) {
        initProfilePictureLogic();
        initUsernameLogic();
        initEmailLogic();
    }

    if (isChangePass) {
        initChangePasswordLogic();
    }

    if (isSessions) {
        initSessionsLogic();
    }

    if (isDeleteAccount) {
        initDeleteAccountLogic();
    }

    if (is2FA) {
        initTwoFactorLogic();
    }
    
    if (!areGlobalsInitialized) {
        initPreferencesLogic();        
        initBooleanPreferencesLogic(); 
        initAccountDeleteNavigation();
        initSessionsNavLogic();
        
        areGlobalsInitialized = true;
    }
}

function toggleMode(els, isEditing) {
    if (isEditing) {
        els.viewState.classList.remove('active'); els.viewState.classList.add('disabled');
        els.actionsView.classList.remove('active'); els.actionsView.classList.add('disabled');
        els.editState.classList.remove('disabled'); els.editState.classList.add('active');
        els.actionsEdit.classList.remove('disabled'); els.actionsEdit.classList.add('active');
    } else {
        els.editState.classList.remove('active'); els.editState.classList.add('disabled');
        els.actionsEdit.classList.remove('active'); els.actionsEdit.classList.add('disabled');
        els.viewState.classList.remove('disabled'); els.viewState.classList.add('active');
        els.actionsView.classList.remove('disabled'); els.actionsView.classList.add('active');
    }
}

function updateHeaderAvatar(src) {
    const headerImg = document.querySelector('.header-button.profile-button .profile-img');
    if (headerImg) headerImg.src = src;
}

function initAccountDeleteNavigation() {
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="trigger-account-delete"]');
        if (btn) {
            e.preventDefault();
            if (window.navigateTo) window.navigateTo('settings/delete-account');
        }
    });
}

function initDeleteAccountLogic() {
    const confirmBtn = qs('[data-action="confirm-account-deletion"]');
    const passInput = qs('[data-element="delete-confirm-password"]');
    const card = qs('.component-card--danger');

    if (!confirmBtn || !passInput) return;

    confirmBtn.onclick = async () => {
        const password = passInput.value;
        toggleCardError(card, '', false);

        if (!password) {
            toggleCardError(card, t('settings.delete_account.password_label')); 
            return;
        }

        if (!confirm(t('settings.delete_account.warning_text'))) {
            return;
        }

        setButtonLoading(confirmBtn, true);

        const res = await postJson('api/settings_handler.php', { 
            action: 'delete_account', 
            password: password 
        });

        if (res.success) {
            window.location.href = (window.BASE_PATH || '/ProjectAurora/') + 'status-page?status=deleted';
        } else {
            toggleCardError(card, res.message);
            setButtonLoading(confirmBtn, false, t('settings.delete_account.confirm_btn'));
        }
    };
}

function initSessionsNavLogic() {
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="trigger-sessions-manage"]');
        if (btn) {
            e.preventDefault();
            if (window.navigateTo) window.navigateTo('settings/sessions');
        }
    });
}

async function initSessionsLogic() {
    const container = qs('#sessions-list-container');
    const revokeAllBtn = qs('[data-action="revoke-all-sessions"]');
    if (!container) return;

    const res = await postJson('api/settings_handler.php', { action: 'get_sessions' });

    if (res.success) {
        renderSessionsList(res.sessions, container);
    } else {
        container.innerHTML = `<p style="text-align:center; color:#d32f2f;">${res.message}</p>`;
    }

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="revoke-single"]');
        if (btn) {
            const sessionIdDb = btn.dataset.id;
            if (!confirm(t('settings.sessions.logout_confirm') || '¿Cerrar sesión?')) return;

            btn.disabled = true; 
            btn.innerHTML = '<div class="small-spinner"></div>';

            const res = await postJson('api/settings_handler.php', { action: 'revoke_session', session_id_db: sessionIdDb });
            
            if (res.success) {
                const card = btn.closest('.component-card');
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
                if (window.alertManager) window.alertManager.showAlert(t('settings.sessions.session_revoked') || 'Sesión cerrada.', 'success');
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                btn.disabled = false;
                btn.innerHTML = t('global.delete');
            }
        }
    });

    if (revokeAllBtn) {
        revokeAllBtn.onclick = async () => {
            if (!confirm(t('settings.sessions.logout_all_confirm'))) return;
            setButtonLoading(revokeAllBtn, true);
            
            const res = await postJson('api/settings_handler.php', { action: 'revoke_all_sessions' });
            
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(t('settings.sessions.all_revoked') || 'Sesiones cerradas.', 'success');
                initSessionsLogic();
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(revokeAllBtn, false, t('settings.sessions.logout_all'));
        };
    }
}

function renderSessionsList(sessions, container) {
    if (sessions.length === 0) {
        container.innerHTML = `<p style="text-align:center; color:#666;">${t('settings.sessions.empty')}</p>`;
        return;
    }

    let html = '';
    sessions.forEach(sess => {
        const statusBadge = sess.is_current 
            ? `<span style="background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; margin-left:8px;">${t('settings.sessions.current_device')}</span>` 
            : '';
        
        const revokeBtn = sess.is_current 
            ? '' 
            : `<button class="component-button" data-action="revoke-single" data-id="${sess.id}" style="color:#d32f2f; border-color:#ffcdd2;">${t('global.delete')}</button>`;

        html += `
        <div class="component-card component-card--grouped" style="margin-bottom:16px;">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">${sess.icon}</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="display:flex; align-items:center;">
                            ${sess.os} - ${sess.browser} ${statusBadge}
                        </h2>
                        <p class="component-card__description">
                            ${sess.ip} • ${t('settings.sessions.last_active')}: ${new Date(sess.last_active).toLocaleString()}
                        </p>
                    </div>
                </div>
                <div class="component-card__actions">
                    ${revokeBtn}
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function initChangePasswordLogic() {
    const step1Card = qs('[data-step="password-step-1"]');
    const step2Card = qs('[data-step="password-step-2"]');
    const step2Sessions = qs('[data-step="password-step-2-sessions"]');
    const step2Actions = qs('[data-step="password-step-2-actions"]');
    
    const currentPassInput = qs('[data-element="current-password"]');
    const newPassInput = qs('[data-element="new-password"]');
    const confirmPassInput = qs('[data-element="confirm-password"]');
    const logoutCheck = qs('[data-element="logout-others-check"]');

    const verifyBtn = qs('[data-action="verify-current-password"]');
    const saveBtn = qs('[data-action="save-new-password"]');

    if (!step1Card || !verifyBtn) return;

    verifyBtn.onclick = async () => {
        const pass = currentPassInput.value;
        if (!pass) {
            toggleCardError(step1Card, t('settings.change_password.current_desc'));
            return;
        }

        setButtonLoading(verifyBtn, true);
        toggleCardError(step1Card, '', false);

        const res = await postJson('api/settings_handler.php', { action: 'verify_current_password', password: pass });

        if (res.success) {
            currentPassInput.disabled = true;
            verifyBtn.style.display = 'none'; 
            
            step2Card.classList.remove('disabled');
            step2Card.classList.add('active');
            step2Sessions.classList.remove('disabled');
            step2Sessions.classList.add('active');
            step2Actions.classList.remove('disabled');
            step2Actions.classList.add('active');

            newPassInput.focus();
        } else {
            toggleCardError(step1Card, res.message);
        }
        setButtonLoading(verifyBtn, false);
    };

    saveBtn.onclick = async () => {
        const newPass = newPassInput.value;
        const confirmPass = confirmPassInput.value;
        const logout = logoutCheck.checked;

        toggleCardError(step2Card, '', false);

        const minPass = window.SERVER_CONFIG?.min_password_length || 8;

        if (newPass.length < minPass) {
            toggleCardError(step2Card, t('auth.errors.password_short', { min: minPass }));
            return;
        }

        if (newPass !== confirmPass) {
            toggleCardError(step2Card, t('auth.errors.pass_mismatch'));
            return;
        }

        setButtonLoading(saveBtn, true);

        const res = await postJson('api/settings_handler.php', { 
            action: 'update_password', 
            new_password: newPass,
            logout_others: logout
        });

        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            setTimeout(() => {
                if (window.navigateTo) window.navigateTo('settings/login-security');
                else window.location.reload();
            }, 1500);
        } else {
            toggleCardError(step2Card, res.message);
        }
        setButtonLoading(saveBtn, false);
    };
}

function initBooleanPreferencesLogic() {
    document.body.addEventListener('change', async (e) => {
        const target = e.target;
        if (target.matches('input[type="checkbox"][data-preference-type="boolean"]')) {
            const fieldName = target.dataset.fieldName;
            const isChecked = target.checked;
            const card = target.closest('.component-card');
            const toggleWrapper = target.closest('.component-toggle-switch');

            if (!fieldName) return;

            toggleCardError(card, '', false);
            if (toggleWrapper) toggleWrapper.classList.add('disabled-interactive');

            const res = await postJson('api/settings_handler.php', {
                action: 'update_boolean_preference',
                field: fieldName,
                value: isChecked
            });
            
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(t('global.save_status'), 'success');
                
                if (fieldName === 'open_links_in_new_tab') {
                    window.OPEN_NEW_TAB = isChecked ? 1 : 0;
                } else if (fieldName === 'extended_message_time') {
                    window.USER_EXTENDED_MSG = isChecked ? 1 : 0;
                }

            } else {
                toggleCardError(card, res.message);
            }
            if (toggleWrapper) toggleWrapper.classList.remove('disabled-interactive');
        }
    });
}

function initPreferencesLogic() {
    document.body.addEventListener('click', async (e) => {
        const option = e.target.closest('.menu-link[data-value]');
        if (!option) return;

        if (option.classList.contains('active')) return;

        const module = option.closest('.popover-module');
        if (!module) return;

        const wrapper = module.closest('.trigger-select-wrapper');
        const card = option.closest('.component-card');
        const prefType = module.dataset.preferenceType; 
        const value = option.dataset.value;

        if (!prefType || !value) return;

        toggleCardError(card, '', false);
        if (wrapper) wrapper.classList.add('disabled-interactive');
        else module.classList.add('disabled-interactive');

        let payload = { action: '' };
        
        if (prefType === 'usage') {
            payload.action = 'update_usage';
            payload.usage = value;
        } else if (prefType === 'language') {
            payload.action = 'update_language';
            payload.language = value;
        } else if (prefType === 'theme') {
            payload.action = 'update_theme';
            payload.theme = value;
        } else if (prefType === 'message_privacy') { // [NUEVO]
            payload.action = 'update_privacy';
            payload.privacy = value;
        }

        const res = await postJson('api/settings_handler.php', payload);
        
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if (prefType === 'language') await changeLanguage(value);
            if (prefType === 'theme') updateTheme(value);
        } else {
            toggleCardError(card, res.message);
        }
        
        if (wrapper) wrapper.classList.remove('disabled-interactive');
        else module.classList.remove('disabled-interactive');
    });
}

function initProfilePictureLogic() {
    const cardItem = qs('[data-component="profile-picture-section"]');
    if (!cardItem) return;
    const elements = {
        fileInput: qs('[data-element="profile-picture-upload-input"]'),
        previewImg: qs('[data-element="profile-picture-preview-image"]'),
        overlayTrigger: qs('[data-action="trigger-profile-picture-upload"]'), 
        uploadBtn: qs('[data-action="profile-picture-upload-trigger"]'),
        changeBtn: qs('[data-action="profile-picture-change-trigger"]'),
        removeBtn: qs('[data-action="profile-picture-remove-trigger"]'),
        cancelBtn: qs('[data-action="profile-picture-cancel-trigger"]'),
        saveBtn: qs('[data-action="profile-picture-save-trigger-btn"]'),
        actionsDefault: qs('[data-state="profile-picture-actions-default"]'),
        actionsCustom: qs('[data-state="profile-picture-actions-custom"]'),
        actionsPreview: qs('[data-state="profile-picture-actions-preview"]')
    };
    if (!elements.fileInput) return;
    let originalImageSrc = elements.previewImg.src;
    const triggerUpload = (e) => { 
        if(e) e.preventDefault(); 
        toggleCardError(cardItem, '', false);
        elements.fileInput.click(); 
    };
    if (elements.uploadBtn) elements.uploadBtn.onclick = triggerUpload;
    if (elements.changeBtn) elements.changeBtn.onclick = triggerUpload;
    if (elements.overlayTrigger) elements.overlayTrigger.onclick = triggerUpload;
    elements.fileInput.onchange = function(e) {
        const file = this.files[0];
        if (!file) return;
        
        const maxMBReal = window.SERVER_CONFIG?.profile_picture_max_size || 2;
        const maxBytes = maxMBReal * 1024 * 1024;

        if (file.size > maxBytes) { 
            toggleCardError(cardItem, t('settings.profile.error_size', { size: maxMBReal })); 
            this.value = ''; 
            return; 
        }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) { toggleCardError(cardItem, t('settings.profile.error_format')); this.value = ''; return; }
        toggleCardError(cardItem, '', false);
        const reader = new FileReader();
        reader.onload = function(evt) {
            elements.previewImg.src = evt.target.result;
            elements.previewImg.style.display = 'block';
            togglePfpActions('preview');
        };
        reader.readAsDataURL(file);
    };
    elements.cancelBtn.onclick = () => {
        toggleCardError(cardItem, '', false);
        elements.previewImg.src = originalImageSrc;
        elements.fileInput.value = '';
        const isDefault = originalImageSrc.includes('data:image') || originalImageSrc === '' || originalImageSrc.endsWith('/') || originalImageSrc.includes('/default/') || originalImageSrc.includes('profile_pictures_default') || originalImageSrc.includes('ui-avatars.com');       
        togglePfpActions(isDefault ? 'default' : 'custom');
    };
    
    elements.saveBtn.onclick = async () => {
        const file = elements.fileInput.files[0];
        if (!file) return;
        setButtonLoading(elements.saveBtn, true);
        toggleCardError(cardItem, '', false);
        
        // MANUAL FETCH NEEDED FOR FILE UPLOAD
        const formData = new FormData();
        formData.append('action', 'update_profile_picture');
        formData.append('profile_picture', file);
        formData.append('csrf_token', qs('meta[name="csrf-token"]').getAttribute('content')); // Manual CSRF for FormData
        
        try {
            const res = await fetch((window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                const newSrc = data.avatar_url + '?t=' + new Date().getTime(); 
                elements.previewImg.src = newSrc;
                originalImageSrc = newSrc;
                updateHeaderAvatar(newSrc);
                togglePfpActions('custom');
            } else { toggleCardError(cardItem, data.message); }
        } catch (e) { toggleCardError(cardItem, t('global.error_connection')); }
        setButtonLoading(elements.saveBtn, false, t('global.save'));
    };

    elements.removeBtn.onclick = async () => {
        if (!confirm(t('settings.profile.reset_confirm') || '¿Restablecer foto de perfil?')) return;
        setButtonLoading(elements.removeBtn, true);
        toggleCardError(cardItem, '', false);
        
        // MANUAL FETCH NEEDED FOR FORM DATA IF COMPATIBILITY REQUIRED, BUT postJson SUPPORTS JSON
        // Remove pfp doesn't need file, so use postJson
        const res = await postJson('api/settings_handler.php', { action: 'remove_profile_picture' });
        
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'info');
            const newSrc = res.avatar_url + '?t=' + new Date().getTime();
            elements.previewImg.src = newSrc;
            originalImageSrc = newSrc;
            updateHeaderAvatar(newSrc);
            togglePfpActions('default'); 
        } else { toggleCardError(cardItem, res.message); }
        setButtonLoading(elements.removeBtn, false, t('global.delete'));
    };

    function togglePfpActions(mode) {
        if(elements.actionsDefault) elements.actionsDefault.className = (mode === 'default') ? 'active' : 'disabled';
        if(elements.actionsCustom) elements.actionsCustom.className = (mode === 'custom') ? 'active' : 'disabled';
        if(elements.actionsPreview) elements.actionsPreview.className = (mode === 'preview') ? 'active' : 'disabled';
    }
}

function initUsernameLogic() {
    const itemSection = qs('[data-component="username-section"]');
    if (!itemSection) return;
    const els = {
        viewState: qs('[data-state="username-view-state"]'),
        editState: qs('[data-state="username-edit-state"]'),
        actionsView: qs('[data-state="username-actions-view"]'),
        actionsEdit: qs('[data-state="username-actions-edit"]'),
        display: qs('[data-element="username-display-text"]'),
        input: qs('[data-element="username-input"]'),
        editBtn: qs('[data-action="username-edit-trigger"]'),
        cancelBtn: qs('[data-action="username-cancel-trigger"]'),
        saveBtn: qs('[data-action="username-save-trigger-btn"]')
    };
    if (!els.input) return;
    let originalUsername = els.input.value;
    els.editBtn.onclick = () => { toggleMode(els, true); toggleCardError(itemSection, '', false); els.input.value = ''; els.input.value = originalUsername; els.input.focus(); };
    els.cancelBtn.onclick = () => { els.input.value = originalUsername; toggleCardError(itemSection, '', false); toggleMode(els, false); };
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim();
        toggleCardError(itemSection, '', false);
        
        const minUser = window.SERVER_CONFIG?.min_username_length || 6;
        const maxUser = window.SERVER_CONFIG?.max_username_length || 32;

        if (newVal === originalUsername) { toggleMode(els, false); return; }
        if (newVal.length < minUser || newVal.length > maxUser) { 
            toggleCardError(itemSection, t('auth.errors.username_invalid', { min: minUser, max: maxUser })); 
            return; 
        }
        setButtonLoading(els.saveBtn, true);
        
        const res = await postJson('api/settings_handler.php', { action: 'update_username', username: newVal });
        
        if (res.success) { 
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success'); 
            originalUsername = res.new_username; 
            els.display.textContent = res.new_username; 
            els.input.value = res.new_username; 
            toggleMode(els, false); 
        } else { toggleCardError(itemSection, res.message || 'Error al actualizar.'); }
        
        setButtonLoading(els.saveBtn, false, t('global.save'));
    };
}

function initEmailLogic() {
    const itemSection = qs('[data-component="email-section"]');
    if (!itemSection) return;
    const els = {
        viewState: qs('[data-state="email-view-state"]'),
        editState: qs('[data-state="email-edit-state"]'),
        actionsView: qs('[data-state="email-actions-view"]'),
        actionsEdit: qs('[data-state="email-actions-edit"]'),
        display: qs('[data-element="email-display-text"]'),
        input: qs('[data-element="email-input"]'),
        editBtn: qs('[data-action="email-edit-trigger"]'),
        cancelBtn: qs('[data-action="email-cancel-trigger"]'),
        saveBtn: qs('[data-action="email-save-trigger-btn"]')
    };
    if (!els.input) return;
    let originalEmail = els.input.value;
    els.editBtn.onclick = () => { toggleMode(els, true); toggleCardError(itemSection, '', false); els.input.value = ''; els.input.value = originalEmail; els.input.focus(); };
    els.cancelBtn.onclick = () => { els.input.value = originalEmail; toggleCardError(itemSection, '', false); toggleMode(els, false); };
    
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim().toLowerCase();
        toggleCardError(itemSection, '', false);
        if (newVal === originalEmail) { toggleMode(els, false); return; }
        
        const basicRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!basicRegex.test(newVal)) { 
            toggleCardError(itemSection, t('auth.errors.email_invalid_domain')); 
            return; 
        }
        
        const config = window.SERVER_CONFIG || {};
        let allowed = config.allowed_email_domains;
        if (typeof allowed === 'string') { try { allowed = JSON.parse(allowed); } catch(e){ allowed = []; } }

        if (Array.isArray(allowed) && allowed.length > 0) {
            const domain = newVal.split('@')[1].toLowerCase();
            if (!allowed.some(d => d.toLowerCase() === domain)) {
                toggleCardError(itemSection, t('auth.errors.email_domain_restricted'));
                return;
            }
        }
        
        const maxEmail = window.SERVER_CONFIG?.max_email_length || 255;
        if(newVal.length > maxEmail) { toggleCardError(itemSection, t('auth.errors.email_long', {max: maxEmail})); return; }

        setButtonLoading(els.saveBtn, true);
        
        const res = await postJson('api/settings_handler.php', { action: 'update_email', email: newVal });
        
        if (res.success) { 
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success'); 
            originalEmail = res.new_email; 
            els.display.textContent = res.new_email; 
            els.input.value = res.new_email; 
            toggleMode(els, false); 
        } else { toggleCardError(itemSection, res.message || 'Error al actualizar.'); }
        
        setButtonLoading(els.saveBtn, false, t('global.save'));
    };
}

function initTwoFactorLogic() {
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="reload-page"]')) {
            location.reload();
        }
    });

    const els = {
        step1: qs('[data-step="2fa-step-1"]'),
        step2: qs('[data-step="2fa-step-2"]'),
        step3: qs('[data-step="2fa-step-3"]'),
        passInput: qs('[data-element="2fa-current-password"]'),
        verifyBtn: qs('[data-action="verify-pass-2fa"]'),
        qrContainer: qs('#qrcode-display'),
        manualText: qs('#manual-secret-text'),
        codeInput: qs('[data-element="2fa-verify-code"]'),
        confirmBtn: qs('[data-action="confirm-enable-2fa"]'),
        backupList: qs('#backup-codes-list'),
        disableBtn: qs('[data-action="disable-2fa-btn"]'),
        disablePass: qs('[data-element="2fa-disable-password"]')
    };

    let tempSecret = '';

    if (els.verifyBtn) {
        els.verifyBtn.onclick = async () => {
            const password = els.passInput.value;
            if (!password) return alert(t('settings.security.password_required') || 'Ingresa tu contraseña');

            setButtonLoading(els.verifyBtn, true);

            const res = await postJson('api/settings_handler.php', { action: 'verify_current_password', password: password });

            if (res.success) {
                await generateSecret(els);
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(els.verifyBtn, false, t('global.continue'));
            }
        };
    }

    if (els.confirmBtn) {
        els.confirmBtn.onclick = async () => {
            const code = els.codeInput.value.trim();
            if (code.length !== 6) return alert('El código debe tener 6 dígitos');

            setButtonLoading(els.confirmBtn, true);

            const res = await postJson('api/settings_handler.php', { 
                action: 'enable_2fa_confirm', 
                secret: tempSecret,
                code: code
            });

            if (res.success) {
                renderBackupCodes(res.backup_codes, els.backupList);
                
                els.step2.classList.remove('active');
                els.step2.classList.add('disabled');
                els.step3.classList.remove('disabled');
                els.step3.classList.add('active');
                
                if(window.alertManager) window.alertManager.showAlert(t('settings.2fa.success_title'), 'success');
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(els.confirmBtn, false, t('settings.2fa.activate_btn'));
            }
        };
    }

    if (els.disableBtn) {
        els.disableBtn.onclick = async () => {
            const password = els.disablePass.value;
            if (!password) return alert(t('settings.security.password_required') || 'Ingresa tu contraseña');

            if (!confirm(t('settings.2fa.disable_warning'))) return;

            setButtonLoading(els.disableBtn, true);

            const res = await postJson('api/settings_handler.php', { 
                action: 'disable_2fa', 
                password: password
            });

            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                setTimeout(() => {
                    window.location.reload(); 
                }, 1500);
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(els.disableBtn, false, t('settings.2fa.disable_btn'));
            }
        };
    }

    async function generateSecret(els) {
        const res = await postJson('api/settings_handler.php', { action: 'generate_2fa_secret' });

        if (res.success) {
            tempSecret = res.secret;
            els.manualText.textContent = res.secret;
            els.qrContainer.innerHTML = '';
            
            const uri = `otpauth://totp/ProjectAurora:${res.username}?secret=${res.secret}&issuer=ProjectAurora`;
            
            const qrCode = new QRCodeStyling({
                width: 280,
                height: 280,
                type: "svg",
                data: uri,
                dotsOptions: { color: "#000000", type: "rounded" },
                cornersSquareOptions: { type: "extra-rounded" },
                backgroundOptions: { color: "transparent" }
            });

            qrCode.append(els.qrContainer);

            els.step1.classList.remove('active');
            els.step1.classList.add('disabled');
            els.step2.classList.remove('disabled');
            els.step2.classList.add('active');
        }
    }
    
    function renderBackupCodes(codes, container) {
        if (!codes || !container) return;
        let html = '';
        codes.forEach(code => {
            html += `<div style="font-size: 18px; letter-spacing: 2px;">${code}</div>`;
        });
        container.innerHTML = html;
    }
}
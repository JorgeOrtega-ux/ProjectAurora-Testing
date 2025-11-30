// assets/js/modules/auth-manager.js

import { t } from '../core/i18n-manager.js';
import { postJson, qs } from '../core/utilities.js';

const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

function toggleStepVisibility(hideSelector, showSelector) {
    const toHide = qs(hideSelector);
    const toShow = qs(showSelector);

    if (toHide) {
        toHide.classList.remove('active');
        const err = toHide.querySelector('.form-error-message');
        if (err) { err.textContent = ''; err.classList.remove('active'); }
    }
    if (toShow) {
        toShow.classList.add('active');
    }
}

let resendTimerInterval = null;

function initResendTimer(linkSelector, startSeconds = 60) {
    const link = qs(linkSelector);
    if (!link) return;

    if (resendTimerInterval) clearInterval(resendTimerInterval);
    
    let seconds = startSeconds;
    
    link.classList.add('disabled-link');
    link.textContent = `${t('auth.register.resend_code')} (${seconds})`;

    resendTimerInterval = setInterval(() => {
        seconds--;
        if (seconds > 0) {
            link.textContent = `${t('auth.register.resend_code')} (${seconds})`;
        } else {
            clearInterval(resendTimerInterval);
            link.textContent = t('auth.register.resend_code');
            link.classList.remove('disabled-link');
        }
    }, 1000);
}

async function handleResendCode(type, linkSelector) {
    const link = qs(linkSelector);
    if (!link || link.classList.contains('disabled-link')) return;

    link.classList.add('disabled-link');

    const res = await postJson('api/auth_handler.php', { 
        action: 'resend_code',
        type: type
    });
    
    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        initResendTimer(linkSelector, 60);
    } else {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
        
        if (res.remaining_time) {
            initResendTimer(linkSelector, res.remaining_time);
        } else {
            link.classList.remove('disabled-link');
        }
    }
}

export function initAuthManager() {
    
    document.addEventListener('socket-message', (e) => {
        const data = e.detail; 
        const type = data.type;
        const payload = data.payload || {};
        const reason = payload.reason; 
        
        if (type === 'force_logout') {
            const msg = t('global.session_expired'); 
            if (window.alertManager) {
                window.alertManager.showAlert(msg, 'warning');
            } else {
                alert(msg);
            }
            
            setTimeout(() => {
                if (reason === 'suspended' || reason === 'deleted') {
                    window.location.href = API_BASE_PATH + 'status-page?status=' + reason;
                } else {
                    window.location.href = API_BASE_PATH + 'login';
                }
            }, 2000);
        }
    });

    document.body.addEventListener('input', (e) => {
        if (e.target.matches('[data-input="reg-code"], [data-input="login-2fa-code"], [data-input="rec-code"]')) {
            const input = e.target;
            let rawValue = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            
            if (rawValue.length > 12) rawValue = rawValue.slice(0, 12);

            const parts = [];
            if (rawValue.length > 0) parts.push(rawValue.slice(0, 4));
            if (rawValue.length > 4) parts.push(rawValue.slice(4, 8));
            if (rawValue.length > 8) parts.push(rawValue.slice(8, 12));

            input.value = parts.join('-');
        }
    });

    document.body.addEventListener('click', async (e) => {
        
        if (e.target.closest('[data-action="register-step1"]')) {
            e.preventDefault();
            await handleRegisterStep('step1', 'register_step_1', 2, 'register/additional-data');
        }
        if (e.target.closest('[data-action="register-step2"]')) {
            e.preventDefault();
            const success = await handleRegisterStep('step2', 'register_step_2', 3, 'register/verification-account');
            if (success) initResendTimer('[data-action="resend-register"]');
        }
        if (e.target.closest('[data-action="register-step3"]')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main', null);
        }
        if (e.target.closest('[data-action="resend-register"]')) {
            e.preventDefault();
            await handleResendCode('register', '[data-action="resend-register"]');
        }
        if (e.target.closest('[data-action="register-back-step1"]')) {
            e.preventDefault();
            switchRegisterStep(1, 'register');
        }

        if (e.target.closest('[data-action="rec-step1"]')) {
            e.preventDefault();
            await handleRecoveryLinkRequest();
        }

        if (e.target.closest('[data-action="reset-final-submit"]')) {
            e.preventDefault();
            await handleResetPasswordFinal();
        }

        if (e.target.closest('.floating-input-btn') && !e.target.closest('.username-magic-btn')) {
            const btn = e.target.closest('.floating-input-btn');
            const parent = btn.closest('.floating-label-group');
            if (!parent) return; 
            const input = parent.querySelector('input');

            if (input && input.tagName === 'INPUT') {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.querySelector('span').textContent = 'visibility_off';
                } else {
                    input.type = 'password';
                    btn.querySelector('span').textContent = 'visibility';
                }
            }
        }
        if (e.target.closest('.username-magic-btn')) {
            e.preventDefault();
            const input = document.querySelector('[data-input="reg-username"]');
            if (input) {
                input.value = generateMagicUsername();
                input.focus();
                input.classList.remove('input-error'); 
            }
        }

        if (e.target.closest('[data-action="login-submit"]')) {
            e.preventDefault();
            await handleLogin();
        }
        if (e.target.closest('[data-action="resend-login"]')) {
            e.preventDefault();
            await handleResendCode('login', '[data-action="resend-login"]');
        }
        if (e.target.closest('[data-action="login-2fa-submit"]')) {
            e.preventDefault();
            await handleLogin2FA();
        }
        if (e.target.closest('[data-action="login-2fa-back"]')) {
            e.preventDefault();
            toggleStepVisibility('[data-step="login-2"]', '[data-step="login-1"]');
            const loginUrl = API_BASE_PATH + 'login';
            history.pushState({ section: 'login' }, '', loginUrl);
        }
        
       const logoutBtn = e.target.closest('.menu-link-logout');
        if (logoutBtn) {
            e.preventDefault(); 
            
            if (logoutBtn.dataset.processing === "true") return;
            logoutBtn.dataset.processing = "true";
            
            const spinnerContainer = document.createElement('div');
            spinnerContainer.className = 'menu-link-icon'; 
            spinnerContainer.innerHTML = '<div class="small-spinner"></div>';
            
            logoutBtn.appendChild(spinnerContainer);
            
            const res = await postJson('api/auth_handler.php', { action: 'logout' });

            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(t('global.loading'), 'info');
                window.location.href = API_BASE_PATH + 'login';
            } else {
                spinnerContainer.remove();
                logoutBtn.dataset.processing = "false";
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
        }
    });
    
    if (qs('[data-step="register-3"].active')) initResendTimer('[data-action="resend-register"]');
    if (qs('[data-step="login-2"].active')) initResendTimer('[data-action="resend-login"]');
}

function generateMagicUsername() {
    const now = new Date();
    const pad = (num) => num.toString().padStart(2, '0');
    const year = now.getFullYear();
    const month = pad(now.getMonth() + 1);
    const day = pad(now.getDate());
    const hours = pad(now.getHours());
    const minutes = pad(now.getMinutes());
    const seconds = pad(now.getSeconds());
    const timePart = `${year}${month}${day}_${hours}${minutes}${seconds}`;
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let randomPart = '';
    for (let i = 0; i < 2; i++) randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    return `user${timePart}${randomPart}`;
}

function switchRegisterStep(stepNumber, urlPath) {
    qs('[data-step="register-1"]').classList.remove('active');
    qs('[data-step="register-2"]').classList.remove('active');
    qs('[data-step="register-3"]').classList.remove('active');
    
    const target = qs(`[data-step="register-${stepNumber}"]`);
    if (target) {
        target.classList.add('active');
        if (urlPath) {
            const newUrl = API_BASE_PATH + urlPath;
            history.pushState({ section: urlPath }, '', newUrl);
        }
    }
}

function isValidEmailDomain(email) {
    const basicRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!basicRegex.test(email)) return false;

    const config = window.SERVER_CONFIG || {};
    let allowed = config.allowed_email_domains;

    if (typeof allowed === 'string') {
        try { allowed = JSON.parse(allowed); } catch(e) { allowed = []; }
    }

    if (!allowed || !Array.isArray(allowed) || allowed.length === 0) {
        return true;
    }

    const parts = email.split('@');
    const domain = parts[parts.length - 1].toLowerCase();
    
    return allowed.some(d => d.toLowerCase() === domain);
}

function isValidUsername(username) {
    const min = window.SERVER_CONFIG?.min_username_length || 6;
    const max = window.SERVER_CONFIG?.max_username_length || 32;
    const regex = new RegExp(`^[a-zA-Z0-9_]{${min},${max}}$`);
    return regex.test(username);
}

async function handleRegisterStep(stepName, apiAction, nextStep, nextUrl) {
    let payload = { action: apiAction };
    let btnSelector, errorSelector, inputSelectors = [];
    let errorMessage = '';

    if (stepName === 'step1') {
        const emailIn = qs('[data-input="reg-email"]');
        const passIn = qs('[data-input="reg-password"]');
        if (!emailIn || !passIn) return false;
        
        const emailVal = emailIn.value.trim().toLowerCase();
        const passVal = passIn.value;

        const minPass = window.SERVER_CONFIG?.min_password_length || 8;

        if (!isValidEmailDomain(emailVal)) {
            errorMessage = t('auth.errors.email_domain_restricted');
            emailIn.classList.add('input-error');
        } else if (passVal.length < minPass) {
            errorMessage = t('auth.errors.password_short', { min: minPass });
            passIn.classList.add('input-error');
        }

        payload.email = emailVal;
        payload.password = passVal;
        
        const emailDisplay = qs('[data-display="email-verify"]');
        if (emailDisplay) emailDisplay.textContent = emailVal;

        btnSelector = '[data-action="register-step1"]'; 
        errorSelector = '[data-error="register-1"]'; 
        inputSelectors = ['[data-input="reg-email"]', '[data-input="reg-password"]'];
    
    } else if (stepName === 'step2') {
        const userIn = qs('[data-input="reg-username"]');
        if (!userIn) return false;

        const userVal = userIn.value.trim();
        const minUser = window.SERVER_CONFIG?.min_username_length || 6;
        const maxUser = window.SERVER_CONFIG?.max_username_length || 32;

        if (!isValidUsername(userVal)) {
            errorMessage = t('auth.errors.username_invalid', { min: minUser, max: maxUser });
            userIn.classList.add('input-error');
        }

        payload.username = userVal;
        btnSelector = '[data-action="register-step2"]'; 
        errorSelector = '[data-error="register-2"]'; 
        inputSelectors = ['[data-input="reg-username"]'];
    
    } else if (stepName === 'step3') {
        const codeIn = qs('[data-input="reg-code"]');
        if (!codeIn) return false;
        payload.code = codeIn.value.replace(/-/g, '');
        btnSelector = '[data-action="register-step3"]'; 
        errorSelector = '[data-error="register-3"]'; 
        inputSelectors = ['[data-input="reg-code"]'];
    }

    inputSelectors.forEach(sel => qs(sel).classList.remove('input-error'));
    const errorDiv = qs(errorSelector);
    if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }

    let hasEmpty = false;
    inputSelectors.forEach(sel => {
        const el = qs(sel);
        if(!el.value.trim()) { el.classList.add('input-error'); hasEmpty = true; }
    });

    if (hasEmpty) {
        if(errorDiv) { errorDiv.textContent = t('auth.errors.all_required'); errorDiv.classList.add('active'); }
        return false;
    }

    if (errorMessage) {
        if(errorDiv) { errorDiv.textContent = errorMessage; errorDiv.classList.add('active'); }
        return false;
    }

    return await sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl);
}

async function handleRecoveryLinkRequest() {
    const emailIn = qs('[data-input="rec-email"]');
    if(!emailIn) return;

    const emailVal = emailIn.value.trim().toLowerCase();
    const errorDiv = qs('[data-error="rec-1"]');
    const btn = qs('[data-action="rec-step1"]');
    
    if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }
    emailIn.classList.remove('input-error');

    if(!emailVal) { 
        emailIn.classList.add('input-error'); 
        if(errorDiv) { errorDiv.textContent = t('auth.errors.all_required'); errorDiv.classList.add('active'); }
        return; 
    }

    if (!isValidEmailDomain(emailVal)) {
        emailIn.classList.add('input-error');
        if(errorDiv) { errorDiv.textContent = t('auth.errors.email_domain_restricted'); errorDiv.classList.add('active'); }
        return;
    }

    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="btn-spinner"></div>';
    btn.disabled = true;

    const res = await postJson('api/auth_handler.php', { action: 'recovery_step_1', email: emailVal });

    if (res.success) {
        const display = qs('[data-display="rec-email"]');
        if(display) display.textContent = emailVal;
        
        if (window.alertManager) window.alertManager.showAlert(t('auth.recovery.link_sent_alert'), 'success');
        toggleStepVisibility('[data-step="rec-1"]', '[data-step="rec-success"]');
    } else {
        if(errorDiv) { errorDiv.textContent = res.message; errorDiv.classList.add('active'); }
    }
    
    btn.innerHTML = originalContent;
    btn.disabled = false;
}

async function handleResetPasswordFinal() {
    const passIn = qs('[data-input="reset-pass"]');
    const passConfirmIn = qs('[data-input="reset-pass-confirm"]'); 
    const tokenIn = qs('[data-input="reset-token"]');
    const errorDiv = qs('[data-error="reset-error"]');
    const btn = qs('[data-action="reset-final-submit"]');

    if (!passIn || !tokenIn || !passConfirmIn) return;

    const passVal = passIn.value;
    const passConfirmVal = passConfirmIn.value;
    const tokenVal = tokenIn.value;

    if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }
    passIn.classList.remove('input-error');
    passConfirmIn.classList.remove('input-error');

    const minPass = window.SERVER_CONFIG?.min_password_length || 8;

    if (passVal.length < minPass) {
        passIn.classList.add('input-error');
        if(errorDiv) { errorDiv.textContent = t('auth.errors.password_short', { min: minPass }); errorDiv.classList.add('active'); }
        return;
    }

    if (passVal !== passConfirmVal) {
        passIn.classList.add('input-error');
        passConfirmIn.classList.add('input-error');
        if(errorDiv) { errorDiv.textContent = t('auth.errors.pass_mismatch'); errorDiv.classList.add('active'); }
        return;
    }

    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="btn-spinner"></div>';
    btn.disabled = true;

    const res = await postJson('api/auth_handler.php', { 
        action: 'recovery_final', 
        token: tokenVal,
        password: passVal,
        password_confirm: passConfirmVal 
    });

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(t('auth.recovery.pass_updated'), 'success');
        window.location.href = API_BASE_PATH + 'login';
    } else {
        if(errorDiv) { errorDiv.textContent = res.message; errorDiv.classList.add('active'); }
        btn.innerHTML = originalContent; 
        btn.disabled = false;
    }
}

async function sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl) {
    const btn = qs(btnSelector);
    const errorDiv = qs(errorSelector);
    let originalContent = '';

    if(btn) { 
        originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="btn-spinner"></div>'; 
        btn.disabled = true; 
    }
    
    const result = await postJson('api/auth_handler.php', payload);

    if (result.success) {
        if (nextStep === 'main') {
            window.location.href = API_BASE_PATH;
        } else {
            if (payload.action === 'register_step_2' && window.alertManager) {
                window.alertManager.showAlert(t('auth.register.code_sent_alert'), 'success');
            }
            switchRegisterStep(nextStep, nextUrl);
            if(btn) { btn.innerHTML = originalContent; btn.disabled = false; }
        }
        return true;
    } else {
        if(errorDiv) { errorDiv.textContent = result.message; errorDiv.classList.add('active'); }
        if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
        return false;
    }
}

async function handleLogin() {
    const emailInput = qs('[data-input="login-email"]');
    const passInput = qs('[data-input="login-password"]');
    const errorDiv = qs('[data-error="login-error"]');

    if(errorDiv) errorDiv.classList.remove('active');
    emailInput.classList.remove('input-error');
    passInput.classList.remove('input-error');

    if (!emailInput.value.trim() || !passInput.value.trim()) {
        if(!emailInput.value.trim()) emailInput.classList.add('input-error');
        if(!passInput.value.trim()) passInput.classList.add('input-error');
        return;
    }

    const btn = qs('[data-action="login-submit"]');
    const originalContent = btn.innerHTML; 

    btn.innerHTML = '<div class="btn-spinner"></div>'; 
    btn.disabled = true;

    const res = await postJson('api/auth_handler.php', { 
        action: 'login', 
        email: emailInput.value.toLowerCase(), 
        password: passInput.value 
    });
    
    if (res.success) {
        if (res.require_2fa) {
            const nextUrl = API_BASE_PATH + 'login/verification-additional';
            history.pushState({ section: 'login/verification-additional' }, '', nextUrl);

            toggleStepVisibility('[data-step="login-1"]', '[data-step="login-2"]');
            
            const displayEmail = qs('[data-display="login-2fa-email"]');
            if(displayEmail && res.masked_email) {
                displayEmail.textContent = res.masked_email;
            }
            
            if (window.alertManager) window.alertManager.showAlert(t('auth.2fa.sent_alert'), 'info');
            initResendTimer('[data-action="resend-login"]');

            btn.innerHTML = originalContent;
            btn.disabled = false;
            
            setTimeout(() => {
                const codeField = qs('[data-input="login-2fa-code"]');
                if(codeField) codeField.focus();
            }, 100);

        } else {
            if (window.alertManager) window.alertManager.showAlert(t('auth.login.success'), 'info');
            window.location.href = API_BASE_PATH;
        }
    } else {
        if (res.is_account_issue && res.status_type) {
            let redirectUrl = API_BASE_PATH + 'status-page?status=' + res.status_type;
            if (res.reason) redirectUrl += '&reason=' + encodeURIComponent(res.reason);
            if (res.until) redirectUrl += '&until=' + encodeURIComponent(res.until);
            window.location.href = redirectUrl;
            return; 
        }

        if(errorDiv) {
            errorDiv.textContent = res.message;
            errorDiv.classList.add('active');
        }
        emailInput.classList.add('input-error');
        passInput.classList.add('input-error');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

async function handleLogin2FA() {
    const codeInput = qs('[data-input="login-2fa-code"]');
    const errorDiv = qs('[data-error="login-2fa"]');
    
    if (!codeInput) return;
    if (!codeInput.value.trim()) {
        codeInput.classList.add('input-error');
        return;
    }
    
    const btn = qs('[data-action="login-2fa-submit"]');
    const originalContent = btn.innerHTML;

    btn.innerHTML = '<div class="btn-spinner"></div>'; 
    btn.disabled = true;
    if(errorDiv) errorDiv.classList.remove('active');

    const cleanCode = codeInput.value.trim().replace(/-/g, '');

    const res = await postJson('api/auth_handler.php', { 
        action: 'login_2fa_verify', 
        code: cleanCode
    });

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(t('auth.2fa.success_alert'), 'success');
        window.location.href = API_BASE_PATH;
    } else {
        if(errorDiv) {
            errorDiv.textContent = res.message;
            errorDiv.classList.add('active');
        }
        codeInput.classList.add('input-error');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}
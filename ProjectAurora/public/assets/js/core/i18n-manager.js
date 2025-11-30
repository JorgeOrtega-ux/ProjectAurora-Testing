// public/assets/js/core/i18n-manager.js

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';
let translations = {};
let currentLang = 'es-latam'; // Por defecto

// Detectar idioma desde variable global inyectada por PHP o navegador
if (window.USER_LANG) {
    currentLang = window.USER_LANG;
}

/**
 * Mapea claves de traducción a variables dinámicas de la configuración del servidor.
 * Esto permite que {min}, {max}, {size} se rellenen automáticamente en validaciones JS.
 */
function getKeyVars(key) {
    const config = window.SERVER_CONFIG || {};
    
    switch(key) {
        // Contraseñas
        case 'auth.register.password_hint':
        case 'settings.change_password.new_desc':
        case 'auth.errors.password_short':
            return { min: config.min_password_length || 8 };
        
        // Usuarios
        case 'auth.register.username_label':
        case 'settings.profile.username_meta':
        case 'auth.errors.username_invalid':
            return { min: config.min_username_length || 6, max: config.max_username_length || 32 };
            
        // Foto de perfil
        // [MODIFICADO] Clave actualizada
        case 'settings.profile.profile_picture_meta':
        case 'settings.profile.error_size':
            return { size: config.profile_picture_max_size || 2 };
            
        // Email
        case 'auth.errors.email_long':
            return { max: config.max_email_length || 255 };
            
        default:
            return {};
    }
}

export async function initI18n() {
    try {
        const response = await fetch(`${BASE_PATH}assets/translations/${currentLang}.json`);
        if (!response.ok) throw new Error('Translation file not found');
        
        translations = await response.json();
        
        // Traducir todo el documento actual
        translateDocument();
        
        // Exponer función globalmente para uso en otros scripts legacy
        window.t = t;
        
        // Disparar evento de que i18n está listo
        document.dispatchEvent(new Event('i18n-ready'));

    } catch (error) {
        console.error('i18n Error:', error);
    }
}

/**
 * Cambia el idioma dinámicamente sin recargar la página
 * @param {string} newLang Código del nuevo idioma (ej: 'en-us')
 */
export async function changeLanguage(newLang) {
    currentLang = newLang;
    window.USER_LANG = newLang; // Actualizar variable global

    try {
        const response = await fetch(`${BASE_PATH}assets/translations/${newLang}.json`);
        
        if (!response.ok) {
            throw new Error(`Translation file not found for ${newLang}`);
        }
        
        translations = await response.json();
        console.log(`Idioma cambiado a: ${newLang}`);

    } catch (error) {
        console.warn(`[i18n] No se encontró traducción para "${newLang}". Se mostrarán las claves.`);
        translations = {}; 
    } finally {
        translateDocument();
    }
}
// Exponer cambio de idioma globalmente por si acaso
window.changeLanguage = changeLanguage;

/**
 * Traduce una clave dada. Soporta anidación (auth.login.title)
 * y reemplazo de variables {name}.
 */
export function t(key, vars = {}) {
    // Combinar variables manuales con variables automáticas de configuración
    const autoVars = getKeyVars(key);
    const finalVars = { ...autoVars, ...vars };

    const keys = key.split('.');
    let current = translations;
    
    for (const k of keys) {
        if (current[k] === undefined) {
            return key; // Si falta, devuelve la clave
        }
        current = current[k];
    }
    
    let text = current;
    
    if (typeof text === 'string') {
        Object.keys(finalVars).forEach(variable => {
            text = text.replace(new RegExp(`{${variable}}`, 'g'), finalVars[variable]);
        });
    }
    
    return text;
}

/**
 * Busca y traduce elementos en el DOM
 */
export function translateDocument(container = document) {
    // 1. Texto simple (textContent/innerHTML)
    const elements = container.querySelectorAll('[data-i18n]');
    elements.forEach(el => {
        const key = el.getAttribute('data-i18n');
        
        const rawVars = el.getAttribute('data-i18n-vars');
        let localVars = {};
        if (rawVars) {
            try { localVars = JSON.parse(rawVars); } catch(e) { console.warn('Invalid i18n-vars JSON', e); }
        }
        
        el.innerHTML = t(key, localVars); 
    });

    // 2. Placeholders
    const placeholders = container.querySelectorAll('[data-i18n-placeholder]');
    placeholders.forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        el.setAttribute('placeholder', t(key));
    });

    // 3. Tooltips (data-tooltip)
    const tooltips = container.querySelectorAll('[data-i18n-tooltip]');
    tooltips.forEach(el => {
        const key = el.getAttribute('data-i18n-tooltip');
        el.setAttribute('data-tooltip', t(key));
    });
    
    // 4. Títulos (title)
    const titles = container.querySelectorAll('[data-i18n-title]');
    titles.forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        el.setAttribute('title', t(key));
    });
}
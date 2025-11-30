// public/assets/js/core/theme-manager.js

let currentThemePreference = 'system';
let systemThemeMedia = null;

export function initThemeManager() {
    // Obtener preferencia del servidor o default
    currentThemePreference = window.USER_THEME || 'system';
    
    // Listener para cambios en el sistema operativo (solo si estamos en modo 'system')
    systemThemeMedia = window.matchMedia('(prefers-color-scheme: dark)');
    systemThemeMedia.addEventListener('change', (e) => {
        if (currentThemePreference === 'system') {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Aplicar tema inicial
    updateTheme(currentThemePreference);
}

/**
 * Actualiza la preferencia y aplica los cambios
 * @param {string} theme 'system', 'light', 'dark'
 */
export function updateTheme(theme) {
    currentThemePreference = theme;
    window.USER_THEME = theme; // Actualizar global

    if (theme === 'system') {
        const isSystemDark = systemThemeMedia.matches;
        applyTheme(isSystemDark ? 'dark' : 'light');
    } else {
        applyTheme(theme);
    }
}

function applyTheme(mode) {
    // Por ahora solo gestionamos las clases en el body
    if (mode === 'dark') {
        document.body.classList.add('dark-theme');
        document.body.classList.remove('light-theme');
    } else {
        document.body.classList.add('light-theme');
        document.body.classList.remove('dark-theme');
    }
}
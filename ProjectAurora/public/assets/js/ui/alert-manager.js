// public/assets/js/ui/alert-manager.js

const animationDuration = 500;
const containerClass = 'ui-notification-dock';
const containerDataAttr = 'alerts';
let alertContainer = null;

function getSelector() {
    return `.${containerClass}[data-container="${containerDataAttr}"]`;
}

function getContainer() {
    let container = document.querySelector(getSelector());
    if (!container) {
        container = document.createElement('div');
        container.classList.add(containerClass);
        container.dataset.container = containerDataAttr;
        document.body.appendChild(container);
    }
    alertContainer = container;
    return container;
}

function hideAlert(alertBox) {
    alertBox.classList.remove('show');
    setTimeout(() => {
        if (alertBox.parentNode) {
            alertBox.parentNode.removeChild(alertBox);
        }
        const container = document.querySelector(getSelector());
        if (container && container.children.length === 0) {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
            alertContainer = null;
        }
    }, animationDuration);
}

export function showAlert(message, type = 'info', duration = 4000) {
    const container = getContainer();
    const iconMap = {
        'success': 'check_circle',
        'error': 'error',
        'info': 'info',
        'warning': 'warning'
    };
    const iconName = iconMap[type] || 'info';

    // [NUEVO] Lógica de tiempo extendido
    // Si window.USER_EXTENDED_MSG es 1, duplicamos la duración por defecto.
    let finalDuration = duration;
    if (window.USER_EXTENDED_MSG === 1) {
        finalDuration = 8000; // 8 segundos en lugar de 4
    }

    const alertBox = document.createElement('div');
    alertBox.className = `alert-box alert-${type}`;

    const iconSpan = document.createElement('span');
    iconSpan.className = 'material-symbols-rounded';
    iconSpan.textContent = iconName;

    const textSpan = document.createElement('span');
    textSpan.textContent = message;

    alertBox.appendChild(iconSpan);
    alertBox.appendChild(textSpan);
    container.appendChild(alertBox);

    setTimeout(() => {
        alertBox.classList.add('show');
    }, 10);

    const hideTimer = setTimeout(() => {
        hideAlert(alertBox);
    }, finalDuration);

    alertBox.addEventListener('click', () => {
        clearTimeout(hideTimer);
        hideAlert(alertBox);
    });
}

export function initAlertManager() {
    // Exponer globalmente para compatibilidad con otros módulos que usan window.alertManager
    window.alertManager = { showAlert };
}
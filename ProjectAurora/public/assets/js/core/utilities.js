// public/assets/js/core/utilities.js

import { t } from './i18n-manager.js';

export const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

/**
 * Selector abreviado (Query Selector)
 */
export const qs = (selector, parent = document) => parent.querySelector(selector);

/**
 * Selector múltiple abreviado (Query Selector All)
 */
export const qsa = (selector, parent = document) => parent.querySelectorAll(selector);

/**
 * Obtiene el token CSRF del meta tag o input oculto
 */
export function getCsrfToken() {
    const meta = qs('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = qs('input[name="csrf_token"]');
    return input ? input.value : '';
}

/**
 * Realiza una petición POST estándar a la API esperando JSON
 * @param {string} endpoint - Ruta relativa desde la API (ej: 'api/auth_handler.php')
 * @param {object} payload - Datos a enviar
 * @returns {Promise<object>} - Respuesta JSON parseada
 */
export async function postJson(endpoint, payload = {}) {
    // Asegurarse de que la URL sea correcta
    const url = endpoint.startsWith('http') ? endpoint : `${BASE_PATH}${endpoint}`;
    
    // Inyectar CSRF automáticamente si no viene en el payload
    if (!payload.csrf_token) {
        payload.csrf_token = getCsrfToken();
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify(payload)
        });
        return await response.json();
    } catch (error) {
        console.error(`API Error [${endpoint}]:`, error);
        return { success: false, message: t('global.error_connection') };
    }
}

/**
 * Maneja el estado de carga de un botón
 * @param {HTMLElement} btn - El botón
 * @param {boolean} isLoading - Estado de carga
 * @param {string} [originalText] - Texto original (opcional si ya se guardó en dataset)
 */
export function setButtonLoading(btn, isLoading, originalText = '') {
    if (!btn) return;
    
    if (isLoading) {
        // Guardar el texto original si no existe
        if (!btn.dataset.originalHTML) {
            btn.dataset.originalHTML = btn.innerHTML;
        }
        btn.disabled = true;
        // Spinner estandarizado
        btn.innerHTML = '<div class="small-spinner" style="border-color:currentColor; border-top-color:transparent;"></div>';
    } else {
        btn.innerHTML = originalText || btn.dataset.originalHTML || btn.innerHTML;
        btn.disabled = false;
    }
}

/**
 * Muestra u oculta mensajes de error en tarjetas (component-card)
 * Busca automáticamente el div .component-card__error adyacente o lo crea.
 * @param {HTMLElement} element - Un elemento dentro de la tarjeta (input o botón)
 * @param {string} message - El mensaje de error
 * @param {boolean} show - true para mostrar, false para ocultar
 */
export function toggleCardError(element, message = '', show = true) {
    if (!element) return;
    
    const cardContainer = element.closest('.component-card') || element.closest('.component-wrapper');
    if (!cardContainer) return;

    let errorDiv = cardContainer.nextElementSibling;
    
    // Verificar si el siguiente elemento es el contenedor de error
    if (!errorDiv || !errorDiv.classList.contains('component-card__error')) {
        if (show) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'component-card__error';
            cardContainer.after(errorDiv);
        } else {
            return; // No hay error que ocultar
        }
    }

    if (show) {
        errorDiv.textContent = message;
        // Pequeño delay para permitir la transición CSS si existe
        requestAnimationFrame(() => errorDiv.classList.add('active'));
    } else {
        errorDiv.classList.remove('active');
        // Esperar a que termine la transición para eliminarlo del DOM
        setTimeout(() => {
            if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
        }, 200);
    }
}
// public/assets/js/ui/tooltip-manager.js
import { createPopper } from 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/esm/popper.min.js';

let tooltipEl = null; // Referencia al elemento actual
let popperInstance = null; // Referencia a la instancia de Popper
let isInitialized = false;

function createTooltipElementInstance() {
    const newTooltipEl = document.createElement('div');
    newTooltipEl.id = 'main-tooltip'; // ID fijo para poder encontrarlo siempre
    newTooltipEl.className = 'tooltip';
    newTooltipEl.setAttribute('role', 'tooltip');

    const textEl = document.createElement('div');
    textEl.className = 'tooltip-text';
    newTooltipEl.appendChild(textEl);

    return newTooltipEl;
}

// Esta función ahora es pública y más agresiva
export function hideTooltip() {
    // 1. Destruir la instancia de posicionamiento
    if (popperInstance) {
        popperInstance.destroy();
        popperInstance = null;
    }

    // 2. Eliminar el elemento que tenemos en la variable
    if (tooltipEl && tooltipEl.parentNode) {
        tooltipEl.parentNode.removeChild(tooltipEl);
    }
    tooltipEl = null;

    // 3. LIMPIEZA DE SEGURIDAD (Anti-infinitos)
    // Busca CUALQUIER tooltip que se haya quedado huérfano en el DOM y elimínalo.
    // Esto arregla el problema si la variable tooltipEl se perdió.
    const zombies = document.querySelectorAll('#main-tooltip');
    zombies.forEach(el => el.remove());
}

function showTooltip(target) {
    // PRIMER PASO: Limpiar cualquier cosa existente antes de crear nada nuevo
    hideTooltip();

    const tooltipText = target.getAttribute('data-tooltip');
    if (!tooltipText) return;

    tooltipEl = createTooltipElementInstance();
    document.body.appendChild(tooltipEl);

    tooltipEl.querySelector('.tooltip-text').textContent = tooltipText;
    tooltipEl.style.display = 'block';

    popperInstance = createPopper(target, tooltipEl, {
        placement: 'auto',
        modifiers: [
            {
                name: 'offset',
                options: { offset: [0, 8] },
            },
            {
                name: 'preventOverflow',
                options: { padding: 8 },
            },
        ],
    });
}

export function initTooltipManager() {
    if (isInitialized) return;

    const isCoarsePointer = window.matchMedia && window.matchMedia("(pointer: coarse)").matches;
    if (isCoarsePointer) return;

    // Listener para MOSTRAR
    document.body.addEventListener('mouseover', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;
        showTooltip(target);
    });

    // Listener para OCULTAR (Mouse sale)
    document.body.addEventListener('mouseout', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;
        // Pequeño delay opcional o cierre directo
        hideTooltip();
    });

    // Listener para OCULTAR (Click) - LA CLAVE DEL ÉXITO
    // Usamos { capture: true } para interceptar el clic EN LA BAJADA (antes de que llegue al botón).
    // Esto asegura que el tooltip se cierre INCLUSO si el botón luego se deshabilita o detiene la propagación.
    document.body.addEventListener('click', () => {
        hideTooltip();
    }, { capture: true });

    isInitialized = true;
}
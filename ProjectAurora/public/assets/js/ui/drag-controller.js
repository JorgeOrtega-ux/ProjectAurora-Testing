// public/assets/js/drag-controller.js
import { closeAllModules, isAppAnimating } from '../ui/main-controller.js';

export function initDragController() {
    // Definimos los módulos que tendrán capacidad de arrastre
    // Formato: { moduleId: '...', contentSelector: '...' }
    const draggableModules = [
        { 
            moduleId: 'moduleOptions', 
            contentSelector: '.menu-content' 
        },
        { 
            moduleId: 'moduleNotifications', 
            // [MODIFICADO] Ahora usamos .menu-content aquí también
            contentSelector: '.menu-content' 
        }
    ];

    draggableModules.forEach(config => {
        enableDragForModule(config.moduleId, config.contentSelector);
    });
}

function enableDragForModule(moduleId, contentSelector) {
    const moduleSelector = `[data-module="${moduleId}"]`;
    const dragZoneSelector = '.pill-container'; 

    const module = document.querySelector(moduleSelector);
    if (!module) return;

    const contentElement = module.querySelector(contentSelector);
    const dragZone = module.querySelector(dragZoneSelector);

    if (!contentElement || !dragZone) return;

    let initialY, startY, currentY, isDragging = false, animationFrameId;

    // Event Listeners para la zona de la píldora
    dragZone.addEventListener('mousedown', startDrag);
    dragZone.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        // Solo activar en móvil y si no hay animación en curso
        if (window.innerWidth > 468 || isAppAnimating()) return;
        
        isDragging = true;
        
        if (e.type === 'touchstart') {
            initialY = e.touches[0].pageY;
        } else {
            initialY = e.pageY;
        }

        // Obtener la transformación actual
        const currentTransform = window.getComputedStyle(contentElement).transform;
        
        if (currentTransform === 'none') {
            startY = 0;
        } else {
            try {
                const matrix = new DOMMatrix(currentTransform);
                startY = matrix.m42; 
            } catch (err) {
                startY = 0;
            }
        }

        // Desactivar transición para movimiento fluido
        contentElement.style.transition = 'none';

        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('mouseup', endDrag);
        document.addEventListener('touchend', endDrag);
    }

   function drag(e) {
        if (!isDragging) return;
        if (e.cancelable) e.preventDefault(); 

        if (e.type === 'touchmove') {
            currentY = e.touches[0].pageY;
        } else {
            currentY = e.pageY;
        }
        
        const movedY = currentY - initialY;
        let newTransformY = startY + movedY;

        // [CORRECCIÓN] Bloquear completamente el arrastre hacia arriba
        // Si el cálculo da negativo (hacia arriba), forzamos a 0.
        if (newTransformY < 0) {
            newTransformY = 0; 
        }

        if (animationFrameId) cancelAnimationFrame(animationFrameId);

        animationFrameId = requestAnimationFrame(() => {
            contentElement.style.transform = `translateY(${newTransformY}px)`;
        });
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        if (animationFrameId) cancelAnimationFrame(animationFrameId);

        document.removeEventListener('mousemove', drag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('mouseup', endDrag);
        document.removeEventListener('touchend', endDrag);

        const height = contentElement.offsetHeight;
        const dragThreshold = height * 0.25; // Si arrastra más del 25% de la altura, cierra
        
        const finalTransform = window.getComputedStyle(contentElement).transform;
        let finalY = 0;
        try {
            const matrix = new DOMMatrix(finalTransform);
            finalY = matrix.m42;
        } catch (err) {}

        // Reactivar transición para el snap back o cierre
        contentElement.style.transition = 'transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1)';

        if (finalY > dragThreshold) {
            // 1. Deslizamos hasta abajo visualmente
            contentElement.style.transform = 'translateY(100%)';
            
            contentElement.addEventListener('transitionend', () => {
                // 2. [CLAVE] Llamamos a closeAllModules con 'false' para evitar doble animación
                closeAllModules(null, false); 
            }, { once: true });

        } else {
            // Restaurar a posición original
            contentElement.style.transform = 'translateY(0)';
            
            contentElement.addEventListener('transitionend', () => {
                contentElement.style.transform = ''; 
                contentElement.style.transition = '';
            }, { once: true });
        }
    }
}
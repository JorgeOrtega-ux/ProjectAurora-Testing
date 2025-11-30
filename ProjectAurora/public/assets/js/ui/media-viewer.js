// public/assets/js/ui/media-viewer.js

// Estado interno del visualizador
let state = {
    isOpen: false,
    items: [], // Array de { src, type, user: {name, avatar}, date }
    currentIndex: 0
};

export function initMediaViewer() {
    // Listeners del propio visualizador (Cerrar, Nav)
    document.body.addEventListener('click', (e) => {
        // Abrir visualizador (delegación)
        const trigger = e.target.closest('[data-action="view-media"]');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            openViewerFromTrigger(trigger);
            return;
        }

        // Acciones dentro del visualizador
        if (!state.isOpen) return;

        if (e.target.closest('[data-action="close-viewer"]')) {
            closeViewer();
        } else if (e.target.closest('[data-action="viewer-next"]')) {
            nextImage();
        } else if (e.target.closest('[data-action="viewer-prev"]')) {
            prevImage();
        }
    });

    // Cerrar con ESC y Navegar con flechas
    document.addEventListener('keydown', (e) => {
        if (!state.isOpen) return;
        
        if (e.key === 'Escape') closeViewer();
        if (e.key === 'ArrowRight') nextImage();
        if (e.key === 'ArrowLeft') prevImage();
    });
}

function openViewerFromTrigger(trigger) {
    const container = trigger.closest('[data-media-items]');
    if (!container) return;

    try {
        // Recuperar la lista completa del grupo (mensaje o sidebar)
        const mediaList = JSON.parse(container.dataset.mediaItems);
        const index = parseInt(trigger.dataset.index);

        // Si es un mensaje, la info de usuario es constante para todos los items de ese grupo
        // Si es sidebar, cada item ya trae su info de usuario embebida
        
        // Normalizamos los items para el estado
        state.items = mediaList.map(item => ({
            src: item.src,
            type: item.type || 'image',
            user: item.user || { name: 'Desconocido', avatar: '' }, // Fallback
            date: item.date || ''
        }));

        state.currentIndex = index;
        state.isOpen = true;

        renderViewer();
        document.getElementById('media-viewer-overlay').classList.remove('d-none');

    } catch (error) {
        console.error("Error opening media viewer:", error);
    }
}

function closeViewer() {
    state.isOpen = false;
    state.items = [];
    document.getElementById('media-viewer-overlay').classList.add('d-none');
}

function nextImage() {
    if (state.currentIndex < state.items.length - 1) {
        state.currentIndex++;
        renderViewer();
    }
}

function prevImage() {
    if (state.currentIndex > 0) {
        state.currentIndex--;
        renderViewer();
    }
}

function renderViewer() {
    const currentItem = state.items[state.currentIndex];
    if (!currentItem) return;

    // 1. Imagen
    const imgEl = document.getElementById('viewer-main-image');
    imgEl.src = currentItem.src;

    // 2. Info Usuario
    document.getElementById('viewer-user-name').textContent = currentItem.user.name;
    document.getElementById('viewer-user-avatar').src = currentItem.user.avatar;
    if(currentItem.date) {
        document.getElementById('viewer-date').textContent = currentItem.date;
    }

    // 3. Contador
    const counterEl = document.getElementById('viewer-counter');
    counterEl.textContent = `${state.currentIndex + 1} / ${state.items.length}`;

    // 4. Estado de botones nav
    const prevBtn = document.querySelector('[data-action="viewer-prev"]');
    const nextBtn = document.querySelector('[data-action="viewer-next"]');
    
    prevBtn.style.opacity = (state.currentIndex === 0) ? '0.3' : '1';
    prevBtn.style.pointerEvents = (state.currentIndex === 0) ? 'none' : 'auto';
    
    nextBtn.style.opacity = (state.currentIndex === state.items.length - 1) ? '0.3' : '1';
    nextBtn.style.pointerEvents = (state.currentIndex === state.items.length - 1) ? 'none' : 'auto';
}
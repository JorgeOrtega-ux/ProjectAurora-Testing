// public/assets/js/ui/main-controller.js

let isAnimating = false;
let SHOULD_CLOSE_SURFACE_ON_CLICK = true; 
const allowedMobileMods = ['moduleOptions', 'moduleNotifications'];

export function initMainController() {
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true;

    // --- 1. EVENT LISTENER DE CLICS ---
    document.body.addEventListener('click', async (e) => {
        if (isAnimating) return;

        const target = e.target;

        // --- LÓGICA GLOBAL DE DROPDOWNS GENÉRICOS ---
        const toggleDropdownBtn = target.closest('[data-action="toggle-dropdown"]');
        if (toggleDropdownBtn) {
            e.preventDefault();
            e.stopPropagation();
            const targetId = toggleDropdownBtn.dataset.target;
            toggleGenericDropdown(targetId);
            return;
        }

        // --- LÓGICA CIERRE AL CLICAR FUERA (ADMIN SEARCH) ---
        const adminSearch = document.getElementById('admin-users-search-bar');
        const adminSearchTrigger = document.querySelector('[data-action="toggle-admin-user-search"]');
        
        if (adminSearch && !adminSearch.classList.contains('disabled')) {
            if (!adminSearch.contains(target) && !adminSearchTrigger.contains(target)) {
                adminSearch.classList.add('disabled');
                if (adminSearchTrigger) adminSearchTrigger.classList.remove('active');
            }
        }

        // A) LÓGICA DE SELECCIÓN EN LISTA DE USUARIOS
        const userRow = target.closest('.list-item-row');
        if (userRow) {
            if (!target.closest('button') && !target.closest('a')) {
                const container = userRow.closest('.list-body');
                if (container) {
                    container.querySelectorAll('.list-item-row.selected').forEach(row => {
                        row.classList.remove('selected');
                    });
                }
                userRow.classList.add('selected');
                return; 
            }
        }

        // B) LÓGICA DE SELECCIÓN EN DROPDOWNS
        const dropdownOption = target.closest('.popover-module .menu-link[data-value]');
        if (dropdownOption) {
            handleDropdownSelection(dropdownOption);
            return;
        }

        // C) LÓGICA PARA CERRAR SURFACE AL NAVEGAR
        const surfaceLink = target.closest('[data-module="moduleSurface"] .menu-link[data-nav]');
        if (surfaceLink) {
            const isSmallScreen = window.innerWidth <= 768;
            if (SHOULD_CLOSE_SURFACE_ON_CLICK && isSmallScreen) {
                closeAllModules();
            }
        }

        // D) LÓGICA DE APERTURA DE MÓDULOS (MENÚS)
        const trigger = target.closest('[data-action]');
        if (trigger) {
            const action = trigger.dataset.action;
            let targetModuleId = null;

            // Mapeo de acciones a IDs de módulos
            if (action === 'toggleModuleSurface') targetModuleId = 'moduleSurface';
            if (action === 'toggleModuleOptions') targetModuleId = 'moduleOptions';
            if (action === 'toggleModuleNotifications') targetModuleId = 'moduleNotifications';
            if (action === 'toggleModuleUsageSelect') targetModuleId = 'moduleUsageSelect';
            if (action === 'toggleModuleLanguageSelect') targetModuleId = 'moduleLanguageSelect';
            if (action === 'toggleModuleThemeSelect') targetModuleId = 'moduleThemeSelect';
            
            // [CORRECCIÓN] Agregado el caso faltante para Privacidad
            if (action === 'toggleModulePrivacySelect') targetModuleId = 'modulePrivacySelect';

            if (action === 'toggle-admin-user-search') {
                e.preventDefault();
                const searchBar = document.getElementById('admin-users-search-bar');
                if (searchBar) {
                    if (searchBar.classList.contains('disabled')) {
                        searchBar.classList.remove('disabled');
                        trigger.classList.add('active');
                        
                        setTimeout(() => {
                            const input = searchBar.querySelector('input');
                            if (input) {
                                input.focus();
                                searchBar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }, 50);
                    } else {
                        searchBar.classList.add('disabled');
                        trigger.classList.remove('active');
                    }
                }
                return;
            }

            if (targetModuleId) {
                e.preventDefault();
                toggleModule(targetModuleId);
                return; 
            }
        }

        const loadMoreBtn = target.closest('.btn-load-more');
        if (loadMoreBtn) {
            e.preventDefault();
            await handleLoadMore(loadMoreBtn);
            return;
        }

        if (allowCloseOnClickOutside) {
            const clickedInsideContent = target.closest('.menu-content');
            const clickedInsideTrigger = target.closest('[data-action^="toggleModule"]'); 
            const clickedInsideGeneric = target.closest('.popover-module');
            const clickedInsideGenericTrigger = target.closest('[data-action="toggle-dropdown"]');

            if (!clickedInsideContent && !clickedInsideTrigger && !clickedInsideGeneric && !clickedInsideGenericTrigger) {
                closeAllModules();
                closeGenericDropdowns();
            }
        }
    });

    // --- 2. EVENT LISTENER DE TECLADO ---
    document.addEventListener('keydown', (e) => {
        if (allowCloseOnEsc && e.key === 'Escape') {
            closeAllModules();
            closeGenericDropdowns();
            const adminSearch = document.getElementById('admin-users-search-bar');
            const adminBtn = document.querySelector('[data-action="toggle-admin-user-search"]');
            if (adminSearch && !adminSearch.classList.contains('disabled')) {
                adminSearch.classList.add('disabled');
                if(adminBtn) adminBtn.classList.remove('active');
            }
        }
    });
    
    // --- 3. LÓGICA DEL BUSCADOR PRINCIPAL ---
    const searchInput = document.querySelector('.header .search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length > 0) {
                    if (window.navigateTo) {
                        window.navigateTo(`search?q=${encodeURIComponent(query)}`);
                    } else {
                        window.location.href = `search?q=${encodeURIComponent(query)}`;
                    }
                    searchInput.blur();
                }
            }
        });
    }

    // --- 4. LÓGICA DE SOMBRA EN SCROLL ---
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    if (scrollContainer && topHeader) {
        scrollContainer.addEventListener('scroll', () => {
            if (scrollContainer.scrollTop > 0) {
                topHeader.classList.add('shadow');
            } else {
                topHeader.classList.remove('shadow');
            }
        });
    }
}

// Funciones auxiliares para Dropdowns Genéricos
function toggleGenericDropdown(targetId) {
    const targetEl = document.getElementById(targetId);
    if (targetEl) {
        const isCurrentlyOpen = !targetEl.classList.contains('disabled');
        // Cerrar otros módulos y dropdowns
        closeAllModules();
        closeGenericDropdowns(); 
        
        if (!isCurrentlyOpen) {
            targetEl.classList.remove('disabled');
            const trigger = document.querySelector(`[data-target="${targetId}"]`);
            if(trigger) trigger.classList.add('active');
        }
    }
}

function closeGenericDropdowns() {
    document.querySelectorAll('.popover-module:not([data-module]):not(.disabled)').forEach(el => {
        el.classList.add('disabled');
    });
    document.querySelectorAll('[data-action="toggle-dropdown"].active').forEach(el => {
        el.classList.remove('active');
    });
}

function handleDropdownSelection(optionElement) {
    const module = optionElement.closest('.popover-module');
    if (!module) return;

    const allLinks = module.querySelectorAll('.menu-link');
    allLinks.forEach(link => {
        link.classList.remove('active');
        const icons = link.querySelectorAll('.menu-link-icon');
        if (icons.length > 1) {
            icons[icons.length - 1].innerHTML = '';
        }
    });

    optionElement.classList.add('active');
    
    const activeIcons = optionElement.querySelectorAll('.menu-link-icon');
    if (activeIcons.length > 1) {
        activeIcons[activeIcons.length - 1].innerHTML = '<span class="material-symbols-rounded">check</span>';
    }

    const wrapper = module.closest('.trigger-select-wrapper');
    if (wrapper) {
        const triggerText = wrapper.querySelector('.trigger-selector .trigger-select-text span');
        const triggerIcon = wrapper.querySelector('.trigger-selector .trigger-select-icon span');
        
        const selectedText = optionElement.querySelector('.menu-link-text span')?.textContent 
                             || optionElement.querySelector('.menu-link-text')?.textContent;
        const selectedIcon = optionElement.querySelector('.menu-link-icon span')?.textContent;

        if (triggerText && selectedText) triggerText.textContent = selectedText;
        if (triggerIcon && selectedIcon) triggerIcon.textContent = selectedIcon;
    }

    closeAllModules();
    closeGenericDropdowns(); 
}

function toggleModule(moduleId) {
    const module = document.querySelector(`[data-module="${moduleId}"]`);
    if (!module) return;

    const isMobile = window.innerWidth <= 468;
    const supportsMobileAnim = allowedMobileMods.includes(moduleId);

    if (module.classList.contains('active')) {
        if (isMobile && supportsMobileAnim) {
            closeWithAnimation(module);
        } else {
            module.classList.remove('active');
            module.classList.add('disabled');
        }
    } else {
        closeAllModules(moduleId); 
        closeGenericDropdowns();
        module.classList.remove('disabled');
        module.classList.add('active');

        if (isMobile && supportsMobileAnim) {
            animateOpen(module);
        }
    }
}

function getContentElement(module) {
    return module.querySelector('.menu-content');
}

function animateOpen(module) {
    const content = getContentElement(module);
    if (!content) return;

    isAnimating = true;
    module.classList.add('animate-fade-in');
    content.classList.add('animate-in');

    content.addEventListener('animationend', () => {
        module.classList.remove('animate-fade-in');
        content.classList.remove('animate-in');
        isAnimating = false;
    }, { once: true });
}

function closeWithAnimation(module) {
    const content = getContentElement(module);
    if (!content) {
        module.classList.remove('active');
        module.classList.add('disabled');
        return;
    }

    isAnimating = true;
    module.classList.add('animate-fade-out');
    content.classList.add('animate-out');

    module.addEventListener('animationend', (e) => {
        if (e.target === module) {
            module.classList.remove('active', 'animate-fade-out');
            module.classList.add('disabled');
            content.classList.remove('animate-out');
            content.removeAttribute('style'); 
            isAnimating = false;
        }
    }, { once: true });
}

export function closeAllModules(exceptModuleId = null, animate = true) {
    const modules = document.querySelectorAll('[data-module]');
    const isMobile = window.innerWidth <= 468;

    modules.forEach(mod => {
        const modId = mod.dataset.module;
        if (modId !== exceptModuleId && mod.classList.contains('active')) {
            const supportsMobileAnim = allowedMobileMods.includes(modId);

            if (supportsMobileAnim && isMobile && animate) {
                closeWithAnimation(mod);
            } else {
                mod.classList.remove('active');
                mod.classList.add('disabled');
                mod.classList.remove('animate-fade-in', 'animate-fade-out');
                const content = getContentElement(mod);
                if(content) {
                    content.classList.remove('animate-in', 'animate-out');
                    content.removeAttribute('style'); 
                }
            }
        }
    });
}

export function isAppAnimating() {
    return isAnimating;
}

async function handleLoadMore(loadMoreBtn) {
    const query = loadMoreBtn.dataset.query;
    const offset = parseInt(loadMoreBtn.dataset.offset);
    const originalText = loadMoreBtn.textContent;
    
    loadMoreBtn.textContent = 'Cargando...';
    loadMoreBtn.disabled = true;
    
    try {
        const url = `${window.BASE_PATH}public/loader.php?section=search&q=${encodeURIComponent(query)}&offset=${offset}&ajax_partial=1`;
        const response = await fetch(url);
        const html = await response.text();
        
        const resultsList = document.getElementById('search-results-list');
        if (resultsList) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            const hasMoreFlag = tempDiv.querySelector('#ajax-has-more-flag');
            resultsList.insertAdjacentHTML('beforeend', html);
            
            if (hasMoreFlag) {
                loadMoreBtn.dataset.offset = offset + 2;
                loadMoreBtn.textContent = originalText;
                loadMoreBtn.disabled = false;
            } else {
                if (loadMoreBtn.parentElement) {
                    loadMoreBtn.parentElement.style.display = 'none';
                }
            }
        }
    } catch (error) {
        console.error(error);
        loadMoreBtn.textContent = 'Error';
    }
}
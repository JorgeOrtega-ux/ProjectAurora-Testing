// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';
import { initI18n, translateDocument } from './core/i18n-manager.js';
import { initThemeManager } from './core/theme-manager.js'; 

// [MODULES]
import { initAuthManager } from './modules/auth-manager.js';
import { initNotificationsManager } from './modules/social/notifications-manager.js';
import { initFriendsManager } from './modules/social/friends-manager.js';
import { initSettingsManager } from './modules/settings-manager.js';
import { initCommunitiesManager } from './modules/communities-manager.js'; 
import { initChatManager } from './modules/chat-manager.js'; // [NUEVO]
import { initBannerManager } from './modules/banner-manager.js';

// [UI]
import { initMainController } from './ui/main-controller.js';
import { initAlertManager } from './ui/alert-manager.js';
import { initTooltipManager } from './ui/tooltip-manager.js';
import { initDragController } from './ui/drag-controller.js';
import { initMediaViewer } from './ui/media-viewer.js'; 

// [SERVICES]
import { initSocketService } from './services/socket-service.js';

// [ADMIN MODULES]
import { initAdminDashboard } from './modules/admin/admin-dashboard.js';
import { initAdminUsers } from './modules/admin/admin-users.js';
import { initAdminUserDetails } from './modules/admin/admin-user-details.js';
import { initAdminServer } from './modules/admin/admin-server.js';
import { initAdminBackups } from './modules/admin/admin-backups.js';
import { initAdminAlerts } from './modules/admin/admin-alerts.js';
import { initAdminUserEdit } from './modules/admin/admin-user-edit.js'; 

import { initAdminCommunities } from './modules/admin/admin-communities.js';
import { initAdminCommunityEdit } from './modules/admin/admin-community-edit.js';

/**
 * Manejador de módulos por sección.
 */
export async function handleModuleLoading() {
    // Detectar qué sección está activa en el DOM
    const adminDashboard = document.querySelector('[data-section="admin/dashboard"]');
    const adminUsers = document.querySelector('[data-section="admin/users"]');
    const adminUserDetails = document.querySelector('[data-section^="admin/user-"]'); 
    
    const adminUserEdit = document.querySelector('[data-section="admin/user-edit"]');
    
    const adminServer = document.querySelector('[data-section="admin/server"]');
    const adminBackups = document.querySelector('[data-section="admin/backups"]');
    const adminAlerts = document.querySelector('[data-section="admin/alerts"]');
    
    const adminCommunities = document.querySelector('[data-section="admin/communities"]');
    const adminCommEdit = document.querySelector('[data-section="admin/community-edit"]');

    // Secciones de comunidad
    const joinComm = document.querySelector('[data-section="join-community"]');
    const mainPage = document.querySelector('[data-section="main"]');
    const explorerPage = document.querySelector('[data-section="explorer"]');

    if (adminDashboard) initAdminDashboard();
    if (adminUsers) initAdminUsers();
    
    if (adminUserDetails && !adminUserEdit) initAdminUserDetails();
    if (adminUserEdit) initAdminUserEdit(); 

    if (adminServer) initAdminServer();
    if (adminBackups) initAdminBackups();
    if (adminAlerts) initAdminAlerts();

    if (adminCommunities) initAdminCommunities();
    if (adminCommEdit) initAdminCommunityEdit();

    // Inicializar lógica de comunidades si estamos en vistas relevantes
    if (joinComm || mainPage || explorerPage) {
        initCommunitiesManager();
        initChatManager(); // [NUEVO] Inicializar gestor de chat
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        await initI18n();
        initThemeManager(); 
        initUrlManager();
        initAuthManager();

        window.initSettingsManager = () => {
            initSettingsManager();
            translateDocument();
        };
        window.initSettingsManager();

        initMainController();
        initTooltipManager();
        initAlertManager();
        initSocketService();
        initNotificationsManager();
        initFriendsManager();
        initDragController();
        
        initBannerManager();
        initMediaViewer(); 

        // Exponer la función de carga
        window.loadDynamicModules = handleModuleLoading;
        
        // Ejecutar carga inicial
        await handleModuleLoading();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});
// public/assets/js/modules/admin/admin-backups.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';

let selectedBackup = null;

export function initAdminBackups() {
    loadBackups();
    initListeners();
}

function initListeners() {
    document.body.addEventListener('click', (e) => {
        const row = e.target.closest('.user-capsule-row[data-action="select-backup-row"]');
        if (row) {
            handleRowSelection(row);
            return;
        }

        const createBtn = e.target.closest('[data-action="create-backup"]');
        if (createBtn) {
            createBackup(createBtn);
            return;
        }

        const restoreBtn = e.target.closest('#btn-restore-backup');
        if (restoreBtn) {
            restoreBackup(restoreBtn);
            return;
        }

        const deleteBtn = e.target.closest('[data-action="delete-backup"]');
        if (deleteBtn) {
            deleteBackup(deleteBtn);
            return;
        }

        const deselectBtn = e.target.closest('[data-action="deselect-backup"]');
        if (deselectBtn) {
            deselectAll();
            return;
        }

        if (selectedBackup && !e.target.closest('.capsule-list-container') && !e.target.closest('.component-toolbar')) {
            deselectAll();
        }
    });
}

async function loadBackups() {
    const container = document.getElementById('backups-list-container');
    if (!container) return;

    container.innerHTML = `<div class="small-spinner" style="margin: 20px auto;"></div>`;

    const res = await postJson('api/admin_handler.php', { action: 'list_backups' });

    if (res.success) {
        renderList(res.backups);
    } else {
        container.innerHTML = `<div class="empty-capsule-state" style="color: #d32f2f;"><p>${res.message}</p></div>`;
    }
}

function renderList(backups) {
    const container = document.getElementById('backups-list-container');
    if (!backups || backups.length === 0) {
        container.innerHTML = `
            <div class="empty-capsule-state">
                <span class="material-symbols-rounded icon">backup_table</span>
                <p>${t('admin.backups.empty')}</p>
            </div>`;
        return;
    }

    let html = '';
    backups.forEach(b => {
        html += `
            <div class="user-capsule-row" 
                data-selectable="true" 
                data-action="select-backup-row" 
                data-filename="${b.filename}">
                
                <div class="capsule-avatar" style="background-color: #e3f2fd; border: 1px solid #bbdefb;">
                    <span class="material-symbols-rounded" style="color: #1976d2; font-size: 20px;">database</span>
                </div>

                <div class="info-pill primary-pill">
                    <span class="pill-content strong">${b.filename}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">calendar_today</span>
                    <span class="pill-content">${b.created_at}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">hard_drive</span>
                    <span class="pill-content">${b.size}</span>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function handleRowSelection(row) {
    const filename = row.dataset.filename;
    
    if (row.classList.contains('selected')) {
        deselectAll();
        return;
    }

    document.querySelectorAll('.user-capsule-row.selected').forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
    selectedBackup = filename;

    updateToolbars(true, filename);
}

function deselectAll() {
    document.querySelectorAll('.user-capsule-row.selected').forEach(r => r.classList.remove('selected'));
    selectedBackup = null;
    updateToolbars(false);
}

function updateToolbars(isSelected, filename = '') {
    const defToolbar = document.getElementById('backup-toolbar-default');
    const selToolbar = document.getElementById('backup-toolbar-selected');
    const nameDisplay = document.getElementById('selected-backup-name');

    if (isSelected) {
        defToolbar.classList.add('d-none');
        selToolbar.classList.remove('d-none');
        if (nameDisplay) nameDisplay.textContent = filename;
    } else {
        selToolbar.classList.add('d-none');
        defToolbar.classList.remove('d-none');
    }
}

async function createBackup(btn) {
    setButtonLoading(btn, true);
    
    const res = await postJson('api/admin_handler.php', { action: 'create_backup' });
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadBackups();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    setButtonLoading(btn, false);
}

async function restoreBackup(btn) {
    if (!selectedBackup) return;
    if (!confirm(t('admin.backups.confirm_restore'))) return;

    setButtonLoading(btn, true);
    
    const res = await postJson('api/admin_handler.php', { action: 'restore_backup', filename: selectedBackup });
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        setButtonLoading(btn, false);
    }
}

async function deleteBackup(btn) {
    if (!selectedBackup) return;
    if (!confirm(t('admin.backups.confirm_delete'))) return;

    setButtonLoading(btn, true);

    const res = await postJson('api/admin_handler.php', { action: 'delete_backup', filename: selectedBackup });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
        deselectAll();
        loadBackups();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    setButtonLoading(btn, false);
}
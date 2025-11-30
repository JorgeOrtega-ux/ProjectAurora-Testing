<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetId = $_GET['id'] ?? 0; // 0 = Crear nueva
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
$pageTitle = $targetId ? 'Editar Comunidad' : 'Nueva Comunidad';
$pageDesc = $targetId ? 'Modifica los detalles y la apariencia de esta comunidad.' : 'Configura los parámetros iniciales para el nuevo grupo.';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/community-edit">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/communities" data-tooltip="Volver">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions"><?php echo $pageTitle; ?></span>
            </div>
            <div class="component-toolbar__right">
                <?php if($targetId): ?>
                <button class="component-icon-button" id="btn-delete-community" style="color: #d32f2f; border-color: #ffcdd2;" data-tooltip="Eliminar">
                    <span class="material-symbols-rounded">delete</span>
                </button>
                <div class="component-toolbar__separator"></div>
                <?php endif; ?>
                <button class="component-icon-button" id="btn-save-community" data-tooltip="Guardar">
                    <span class="material-symbols-rounded">save</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $pageTitle; ?></h1>
            <p class="component-page-description"><?php echo $pageDesc; ?></p>
        </div>

        <input type="hidden" id="community-target-id" value="<?php echo htmlspecialchars($targetId); ?>">

        <div class="component-card component-card--grouped">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" style="background-color: #eee; overflow: hidden; border: 1px solid #ddd;">
                        <img id="preview-avatar" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                        <span class="material-symbols-rounded" id="placeholder-avatar" style="font-size: 24px; color: #999;">image</span>
                    </div>
                    
                    <div class="component-card__text">
                        <h2 class="component-card__title">Logo / Avatar</h2>
                        <p class="component-card__description">URL de la imagen representativa.</p>
                    </div>
                </div>
                
                <div class="component-card__actions actions-right" style="flex: 1; max-width: 50%;">
                    <div class="component-input-wrapper w-100">
                        <input type="text" id="input-comm-pfp" class="component-text-input full-width" placeholder="https://ejemplo.com/logo.png">
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">panorama</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Banner de Portada</h2>
                        <p class="component-card__description">Imagen ancha para la cabecera.</p>
                    </div>
                </div>

                <div class="component-card__actions actions-right" style="flex: 1; max-width: 50%; flex-direction: column; align-items: flex-end; gap: 8px;">
                    <div style="width: 100%; height: 60px; background: #eee; border-radius: 8px; overflow: hidden; position: relative; border: 1px solid #ddd;">
                        <img id="preview-banner" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                    </div>
                    <div class="component-input-wrapper w-100">
                        <input type="text" id="input-comm-banner" class="component-text-input full-width" placeholder="https://ejemplo.com/banner.jpg">
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">badge</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Nombre de la Comunidad</h2>
                        <p class="component-card__description">El nombre público visible para todos.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <input type="text" id="input-comm-name" class="component-text-input full-width" placeholder="Ej: Universidad Estatal">
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">category</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Tipo de Comunidad</h2>
                        <p class="component-card__description">Define la categoría para facilitar la búsqueda.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-comm-type">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="icon-type">category</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="text-type" data-i18n="communities.types.other">Otro / General</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        <input type="hidden" id="input-comm-type" value="other">

                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-comm-type">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link" data-action="select-comm-type" data-value="municipality" data-icon="location_city" data-label-key="communities.types.municipality">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">location_city</span></div>
                                        <div class="menu-link-text"><span data-i18n="communities.types.municipality">Municipio</span></div>
                                    </div>
                                    <div class="menu-link" data-action="select-comm-type" data-value="university" data-icon="school" data-label-key="communities.types.university">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">school</span></div>
                                        <div class="menu-link-text"><span data-i18n="communities.types.university">Universidad</span></div>
                                    </div>
                                    <div class="menu-link active" data-action="select-comm-type" data-value="other" data-icon="groups" data-label-key="communities.types.other">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">groups</span></div>
                                        <div class="menu-link-text"><span data-i18n="communities.types.other">Otro / General</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">public</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Configuración de Privacidad</h2>
                        <p class="component-card__description">Define si el grupo es público o privado (requiere código).</p>
                    </div>
                </div>

                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper" style="width: 265px;">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-privacy">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="icon-privacy">public</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="text-privacy">Pública</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        <input type="hidden" id="input-comm-privacy" value="public">

                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-privacy">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link active" data-action="select-comm-privacy" data-value="public" data-label="Pública" data-icon="public">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">public</span></div>
                                        <div class="menu-link-text">Pública</div>
                                    </div>
                                    <div class="menu-link" data-action="select-comm-privacy" data-value="private" data-label="Privada" data-icon="lock">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">lock</span></div>
                                        <div class="menu-link-text">Privada</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-access-code" class="w-100 d-none">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-icon-container" style="background-color: #e3f2fd;">
                            <span class="material-symbols-rounded" style="color: #1976d2;">vpn_key</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Código de Acceso</h2>
                            <p class="component-card__description">Se requiere un código para que los usuarios puedan unirse a esta comunidad privada.</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="component-input-wrapper w-100">
                            <div class="input-with-actions">
                                <input type="text" id="input-comm-code" class="component-text-input" placeholder="Código (XXXX-XXXX-XXXX)" style="text-transform:uppercase; letter-spacing:1px; font-weight:600;">
                                <button type="button" class="component-button" id="btn-gen-code" title="Generar código aleatorio">
                                    <span class="material-symbols-rounded">autorenew</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div>
</div>
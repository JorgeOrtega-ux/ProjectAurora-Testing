# ==========================================
# DOCUMENTACIÓN DE CONFIGURACIÓN DEL PROYECTO
# ==========================================

# 1. CONFIGURACIÓN DE APACHE (.htaccess)
# ------------------------------------------
# Ubicación: Raíz del proyecto (/ProjectAurora/)
# Descripción: Maneja el enrutamiento amigable y la seguridad.
DirectoryIndex public/index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /ProjectAurora/

    # A. Prevenir bucles infinitos si ya se accede a /public
    RewriteRule ^public/ - [L]

    # B. SEGURIDAD: Bloquear acceso directo a archivos sensibles
    # Se incluye .env y .gitignore para que nadie pueda descargarlos desde el navegador
    RewriteRule ^(config|logs|bd\.sql|\.env|\.gitignore) - [F,L]

    # C. Servir archivos estáticos reales (imágenes, css, js) desde /public
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{DOCUMENT_ROOT}/ProjectAurora/public/$1 -f
    RewriteRule ^(.*)$ public/$1 [L]

    # D. Enrutar todo lo demás a index.php (Front Controller)
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . public/index.php [L]
</IfModule>


# 2. CONFIGURACIÓN DE GIT (.gitignore)
# ------------------------------------------
# Ubicación: Raíz del proyecto
# Descripción: Archivos que NO deben subirse al repositorio (GitHub) por seguridad.
# Ignorar archivo de variables de entorno (CONTIENE CONTRASEÑAS)
.env

# Ignorar carpeta de logs de errores
logs/

# Ignorar configuraciones del editor (opcional)
.vscode/
.idea/


# 3. VARIABLES DE ENTORNO (.env)
# ------------------------------------------
# Ubicación: Raíz del proyecto
# Descripción: Define las credenciales de la base de datos y entorno.
# NOTA: Este archivo debe crearse manualmente en el servidor, NO se sube al repo.
DB_HOST=localhost
DB_NAME=project_aurora_db
DB_USER=root
DB_PASS=


# ==========================================
# 4. REGLAS ESTRICTAS DE DESARROLLO
# ==========================================
# Estas reglas deben seguirse sin excepción al modificar o agregar código.
# A. MANEJO DE EVENTOS (JS/HTML)
# ------------------------------------------
# - PROHIBIDO usar eventos inline en HTML (ej: onclick="...", onchange="...").
# - La interacción debe manejarse SIEMPRE mediante `data-attributes` (ej: data-action="save", data-nav="login") y Event Listeners delegados en los archivos JS.
# - El código HTML debe permanecer limpio de lógica JavaScript.
# B. SELECTORES Y ESTILOS
# ------------------------------------------
# - EVITAR el uso de IDs (`id="..."`) para estilos CSS o selección en JS.
# - Usar `data-attributes` (ej: data-element="input-email") o clases para seleccionar elementos.
# - El uso de IDs está permitido SOLO en casos extremos y estrictamente necesarios (ej: vincular un <label for="id"> con un <input id="id">, o librerías de terceros que lo exijan).
# C. INTEGRIDAD DEL CÓDIGO
# ------------------------------------------
# - PROHIBIDO eliminar funcionalidades existentes durante refactorizaciones o mejoras.
# - Todo cambio debe preservar la lógica de negocio actual.
# D. ENTREGA DE CÓDIGO (RESPUESTAS DE LA IA)
# ------------------------------------------
# - Al solicitar código, se deben entregar los ARCHIVOS COMPLETOS.
# - PROHIBIDO omitir partes del código por brevedad (nada de "// ... resto del código") NI POR OPTIMIZACIÓN DE LA RESPUESTA.
# - Cada archivo entregado debe ser funcional copiando y pegando directamente.
# - PROHIBIDO modificar cadenas de texto o contenido existente si no se solicita explícitamente.
# - Cualquier modificación debe preservar la integridad total del archivo original sin omisiones.
# E. ARQUITECTURA JAVASCRIPT (ESTRICTO)
# ------------------------------------------
# - ESTILO: PROHIBIDO el uso de Clases (`class`).
# Se debe utilizar un enfoque funcional puro con módulos ES6 (similiar a `auth-manager.js`).
# - ESTRUCTURA: El archivo debe exportar una única función principal de inicialización (ej: `export function initAdminUsers()`).
# - ESTADO: El estado se maneja mediante variables `let` o `const` en el nivel superior del módulo (file scope), NO como propiedades de objeto.
# - LÓGICA: Utilizar funciones flecha (`const miFunc = () => ...`) o funciones declaradas para la lógica interna.
# - EVENTOS: Los listeners y la delegación de eventos deben definirse y configurarse dentro de la función de inicialización.
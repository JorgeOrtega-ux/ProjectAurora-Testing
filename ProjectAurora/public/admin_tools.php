<?php
// public/admin_tools.php

// 1. Configuración y Seguridad
if (session_status() === PHP_SESSION_NONE) session_start();

// Cargar configuraciones
require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';

// Verificar permisos (Solo Founder y Admin)
$userRole = $_SESSION['user_role'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($userRole, ['founder', 'administrator'])) {
    http_response_code(403);
    exit('<h1>Acceso Denegado</h1><p>No tienes permisos para acceder a esta herramienta.</p><a href="index.php">Volver</a>');
}

// Variables de estado para la vista
$redisStatus = ['connected' => false, 'msg' => '', 'details' => ''];
$bridgeResult = null;
$redisKeys = [];

// 2. Lógica de Redis (Diagnóstico Inicial)
if (!class_exists('Redis')) {
    $redisStatus['msg'] = "Extensión 'Redis' no instalada en PHP.";
} elseif (!isset($redis) || $redis === null) {
    $redisStatus['msg'] = "Variable \$redis no inicializada (Revisar database.php).";
} else {
    try {
        $pong = $redis->ping();
        if ($pong) {
            $redisStatus['connected'] = true;
            $redisStatus['msg'] = "Conectado";
            $redisStatus['details'] = "+ PONG recibido del servidor.";
            
            // Obtener claves actuales
            $keys = $redis->keys('chat:buffer:*');
            foreach ($keys as $key) {
                $len = $redis->lLen($key);
                $content = $redis->lRange($key, 0, 4); // Ver los primeros 5
                $redisKeys[] = ['key' => $key, 'count' => $len, 'preview' => $content];
            }
        }
    } catch (Exception $e) {
        $redisStatus['msg'] = "Excepción de Conexión";
        $redisStatus['details'] = $e->getMessage();
    }
}

// 3. Manejo de Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACCIÓN: LIMPIAR REDIS ---
    if ($action === 'clear_redis' && $redisStatus['connected']) {
        $count = 0;
        foreach ($redisKeys as $k) {
            $redis->del($k['key']);
            $count++;
        }
        // Recargar para actualizar vista
        header("Location: admin_tools.php?msg=redis_cleared&count=$count");
        exit;
    }

    // --- ACCIÓN: PROBAR BRIDGE (SOCKET) ---
    if ($action === 'test_bridge') {
        $host = '127.0.0.1';
        $port = 8081;
        $timeout = 5;
        
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if (!$fp) {
            $bridgeResult = [
                'success' => false,
                'title' => 'Error de Conexión',
                'msg' => "No se pudo conectar a $host:$port. Código: $errno - $errstr",
                'hint' => 'Verifica que socket-connect.py esté ejecutándose.'
            ];
        } else {
            // Enviar payload de prueba
            $testPayload = json_encode([
                'target_id' => 'global',
                'type' => 'admin_notification', 
                'payload' => ['message' => '🔔 TEST DE PUENTE EXITOSO (Desde Admin Tools) 🔔']
            ]);
            
            fwrite($fp, $testPayload);
            fclose($fp);
            
            $bridgeResult = [
                'success' => true,
                'title' => 'Señal Enviada Correctamente',
                'msg' => 'El socket aceptó la conexión y el payload fue enviado.',
                'hint' => 'Si tienes el chat abierto en otra pestaña, deberías ver una notificación global ahora mismo.'
            ];
        }
    }
}

// Mensajes de redirección
$alertMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'redis_cleared') {
    $c = $_GET['count'] ?? 0;
    $alertMsg = "Memoria Redis limpiada ($c colas eliminadas).";
}

$basePath = '/ProjectAurora/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools - Project Aurora</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/componnents.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <style>
        .tools-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (max-width: 768px) { .tools-grid { grid-template-columns: 1fr; } }
        
        .code-block {
            background: #212121;
            color: #a5d6a7;
            padding: 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 5px;
        }
        .status-dot {
            height: 12px; width: 12px; border-radius: 50%; display: inline-block; margin-right: 6px;
        }
        .dot-green { background-color: #4caf50; box-shadow: 0 0 0 2px #e8f5e9; }
        .dot-red { background-color: #f44336; box-shadow: 0 0 0 2px #ffebee; }
        
        .redis-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .redis-item:last-child { border-bottom: none; }
        .success-box { background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; border: 1px solid #c8e6c9; margin-bottom: 20px; }
        .error-box { background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; border: 1px solid #ffcdd2; margin-bottom: 20px; }
    </style>
</head>
<body style="background-color: #f5f5fa; padding: 20px;">

    <div class="component-wrapper full-width">
        
        <div class="component-header-card" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 class="component-page-title">Herramientas de Diagnóstico</h1>
                <p class="component-page-description">Utilidades del sistema para Redis y WebSockets.</p>
            </div>
            <a href="<?php echo $basePath; ?>admin/dashboard" class="component-button">
                <span class="material-symbols-rounded">arrow_back</span> Volver al Admin
            </a>
        </div>

        <?php if ($alertMsg): ?>
            <div class="success-box" style="margin-top: 16px;">
                <strong>Operación Exitosa:</strong> <?php echo htmlspecialchars($alertMsg); ?>
            </div>
        <?php endif; ?>

        <div class="tools-grid mt-16" style="margin-top: 20px;">

            <div class="component-card component-card--column">
                <div class="component-card__content w-100" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 10px;">
                    <div class="component-icon-container" style="background-color: #e3f2fd;">
                        <span class="material-symbols-rounded" style="color:#1565c0">dns</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Estado de Redis</h2>
                        <div style="margin-top: 5px; font-size: 14px;">
                            <?php if ($redisStatus['connected']): ?>
                                <span class="status-dot dot-green"></span> 
                                <span style="font-weight: 600; color: #2e7d32;">Conectado y Operativo</span>
                            <?php else: ?>
                                <span class="status-dot dot-red"></span> 
                                <span style="font-weight: 600; color: #c62828;">Error de Conexión</span>
                                <p style="font-size:12px; color:#666; margin-top:4px;"><?php echo $redisStatus['msg']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($redisStatus['connected']): ?>
                    <div class="w-100">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <h3 style="font-size:14px; margin:0;">Colas en Memoria (<?php echo count($redisKeys); ?>)</h3>
                            <?php if (count($redisKeys) > 0): ?>
                                <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar TODOS los mensajes en memoria?');">
                                    <input type="hidden" name="action" value="clear_redis">
                                    <button type="submit" class="component-button danger" style="height: 32px; font-size: 12px;">
                                        <span class="material-symbols-rounded" style="font-size:16px;">delete_sweep</span> Limpiar Todo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div style="max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 10px;">
                            <?php if (empty($redisKeys)): ?>
                                <p style="text-align:center; color:#999; font-size:13px; padding:20px;">
                                    El buffer está vacío. Todos los mensajes han sido procesados a MySQL.
                                </p>
                            <?php else: ?>
                                <?php foreach ($redisKeys as $item): ?>
                                    <div class="redis-item">
                                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600;">
                                            <span style="color:#333;"><?php echo htmlspecialchars($item['key']); ?></span>
                                            <span class="component-badge component-badge--neutral"><?php echo $item['count']; ?> msgs</span>
                                        </div>
                                        <?php if (!empty($item['preview'])): ?>
                                            <div class="code-block">
<?php 
foreach ($item['preview'] as $json) {
    $data = json_decode($json, true);
    echo htmlspecialchars(($data['sender_username'] ?? '??') . ': ' . ($data['message'] ?? '[Sin texto]')) . "\n";
}
?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-box w-100">
                        <strong>Detalles:</strong> <?php echo htmlspecialchars($redisStatus['details']); ?>
                        <br><br>
                        Verifica que el servicio de Redis esté corriendo y que <code>php_redis</code> esté habilitado en php.ini.
                    </div>
                <?php endif; ?>
            </div>

            <div class="component-card component-card--column">
                <div class="component-card__content w-100" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 10px;">
                    <div class="component-icon-container" style="background-color: #fff3e0;">
                        <span class="material-symbols-rounded" style="color:#f57c00">hub</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Puente PHP -> Python</h2>
                        <p class="component-card__description">Prueba la comunicación interna entre el backend y el servidor WebSocket.</p>
                    </div>
                </div>

                <div class="w-100">
                    <p style="font-size:13px; color:#666; margin-bottom:15px;">
                        Esta herramienta intenta abrir una conexión TCP al puerto <strong>8081</strong> (local) y enviar una notificación global de prueba.
                        Si funciona, deberías ver una alerta en cualquier pestaña donde tengas el chat abierto.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="test_bridge">
                        <button type="submit" class="component-button primary w-100">
                            <span class="material-symbols-rounded">send</span> Enviar Señal de Prueba
                        </button>
                    </form>

                    <?php if ($bridgeResult): ?>
                        <div class="<?php echo $bridgeResult['success'] ? 'success-box' : 'error-box'; ?>" style="margin-top: 20px;">
                            <strong style="font-size:15px;"><?php echo $bridgeResult['title']; ?></strong>
                            <p style="margin: 5px 0 10px 0; font-size:13px;"><?php echo htmlspecialchars($bridgeResult['msg']); ?></p>
                            <?php if (!empty($bridgeResult['hint'])): ?>
                                <small style="display:block; border-top:1px solid rgba(0,0,0,0.1); padding-top:5px;">
                                    💡 <?php echo htmlspecialchars($bridgeResult['hint']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
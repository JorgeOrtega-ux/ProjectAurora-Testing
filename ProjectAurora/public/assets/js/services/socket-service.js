// public/assets/js/services/socket-service.js

const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
const host = window.location.hostname;
const WS_URL = `${protocol}${host}:8080`;
const reconnectInterval = 5000;
let socket = null;
let shouldReconnect = true; // [FIX BUCLE INFINITO] Bandera de control

function connect() {
    if (!window.USER_ID) return;
    
    const timestamp = Date.now();
    console.log(`websocket_client: ${timestamp} connecting...`);
    
    socket = new WebSocket(WS_URL);

    if (window.socketService) {
        window.socketService.socket = socket;
    }

    socket.onopen = () => {
        console.log('websocket_client: connected');
        shouldReconnect = true; // Resetear bandera al conectar
        
        if (window.WS_TOKEN) {
            const requestId = Math.random().toString(16).substring(2, 10);
            socket.send(JSON.stringify({
                type: 'auth',
                token: window.WS_TOKEN,
                request_id: requestId 
            }));
        }
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            
            // [CORRECCIÓN] Solo desconectar si es un error de AUTENTICACIÓN permanente.
            // Se eliminó "|| data.type === 'error'" para que el spam no desconecte.
            if (data.type === 'auth_error_permanent') {
                console.error('websocket_client: Auth failed permanently. Stopping reconnection.');
                shouldReconnect = false;
                socket.close(); // Cierre limpio
                return;
            }

            document.dispatchEvent(new CustomEvent('socket-message', { detail: data }));
            
            if (data.type === 'system_status_update') {
                console.log('websocket_client: system status update received, reloading...');
                window.location.reload();
            }

        } catch (e) {
            console.error('websocket_client: error processing message', e);
        }
    };

    socket.onclose = (e) => {
        console.log('websocket_client: disconnected', e.reason);
        
        // [FIX BUCLE INFINITO] Solo reconectar si no fue un error fatal
        if (window.USER_ID && shouldReconnect) {
            setTimeout(connect, reconnectInterval);
        }
    };
    
    socket.onerror = (err) => {
        console.error('websocket_client: error', err);
    };
}

export function initSocketService() {
    window.socketService = { socket: null }; 
    connect();
}
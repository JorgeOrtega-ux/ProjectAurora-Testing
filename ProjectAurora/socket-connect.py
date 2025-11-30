import asyncio
import websockets
import json
import os
import sys
import logging
import hashlib
import time
import uuid
from logging.handlers import RotatingFileHandler
from datetime import datetime
from dotenv import load_dotenv
import aiomysql
import redis.asyncio as redis

# --- FIX PARA WINDOWS (CODIFICACIÓN) ---
if sys.platform == 'win32':
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8')

# Cargar variables de entorno
load_dotenv()

# --- CONFIGURACIÓN LOGGING ---
if not os.path.exists('logs'):
    os.makedirs('logs')

file_handler = RotatingFileHandler('logs/websocket_server.log', maxBytes=5*1024*1024, backupCount=3, encoding='utf-8')
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
file_handler.setFormatter(file_formatter)

console_handler = logging.StreamHandler(sys.stdout)
console_handler.setLevel(logging.INFO)
console_handler.setFormatter(file_formatter)

class AdminBroadcastHandler(logging.Handler):
    def emit(self, record):
        if not admin_sessions:
            return
        log_entry = self.format(record)
        payload = json.dumps({"type": "server_log_debug", "log": log_entry})
        try:
            loop = asyncio.get_running_loop()
            if loop.is_running():
                loop.create_task(self.broadcast(payload))
        except RuntimeError:
            pass 

    async def broadcast(self, payload):
        dead_sockets = set()
        for ws in admin_sessions:
            try:
                await ws.send(payload)
            except:
                dead_sockets.add(ws)
        admin_sessions.difference_update(dead_sockets)

admin_handler = AdminBroadcastHandler()
admin_handler.setLevel(logging.INFO)
admin_handler.setFormatter(logging.Formatter('[%(asctime)s] %(message)s', datefmt='%H:%M:%S'))

logger = logging.getLogger()
logger.setLevel(logging.INFO)
if logger.hasHandlers():
    logger.handlers.clear()
logger.addHandler(file_handler)
logger.addHandler(console_handler)
logger.addHandler(admin_handler)

# --- ESTADO GLOBAL ---
connected_clients = {} # { user_id: { session_id: websocket } }
admin_sessions = set()
db_pool = None 
redis_client = None

# Control de fuerza bruta (Auth)
ip_attempts = {} 
MAX_AUTH_ATTEMPTS = 5
ATTEMPT_WINDOW = 60 

DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'db': os.getenv('DB_NAME'),
    'autocommit': True
}

# Configuración Redis
REDIS_URL = os.getenv('REDIS_URL', 'redis://localhost')

async def init_db_pool():
    global db_pool
    try:
        db_pool = await aiomysql.create_pool(**DB_CONFIG, minsize=1, maxsize=20)
        logger.info("✅ Pool de conexiones a base de datos inicializado (Max 20).")
    except Exception as e:
        logger.critical(f"❌ Error fatal conectando a BD: {e}")
        exit(1)

async def init_redis_pool():
    global redis_client
    try:
        redis_client = redis.from_url(REDIS_URL, encoding="utf-8", decode_responses=True)
        logger.info("✅ Conexión a Redis inicializada.")
    except Exception as e:
        logger.critical(f"❌ Error fatal conectando a Redis: {e}")
        exit(1)

def check_rate_limit(ip):
    now = time.time()
    if ip not in ip_attempts:
        ip_attempts[ip] = {'count': 0, 'reset_at': now + ATTEMPT_WINDOW}
    
    data = ip_attempts[ip]
    if now > data['reset_at']:
        data['count'] = 0
        data['reset_at'] = now + ATTEMPT_WINDOW
    
    if data['count'] >= MAX_AUTH_ATTEMPTS:
        return False
    return True

def record_failed_attempt(ip):
    if ip in ip_attempts:
        ip_attempts[ip]['count'] += 1

async def verify_token_and_get_session(raw_token):
    if not db_pool:
        return None

    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()

    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                query = """
                    SELECT t.user_id, t.session_id, u.role, u.username, u.profile_picture, u.uuid 
                    FROM ws_auth_tokens t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.token = %s AND t.expires_at > NOW()
                """
                await cur.execute(query, (token_hash,))
                row = await cur.fetchone()
                if row:
                    return (str(row[0]), str(row[1]), str(row[2]), str(row[3]), str(row[4]), str(row[5]))
                return None
    except Exception as e:
        logger.error(f"Error verificando token: {e}")
        return None

async def broadcast_user_status(user_id, status):
    timestamp = datetime.now().isoformat()
    if admin_sessions:
        message = json.dumps({
            "type": "user_status_change",
            "payload": {"user_id": user_id, "status": status, "timestamp": timestamp}
        })
        dead_sockets = set()
        for ws in admin_sessions:
            try: await ws.send(message)
            except: dead_sockets.add(ws)
        admin_sessions.difference_update(dead_sockets)

# --- WORKER: FLUSH CHAT BUFFER ---
async def flush_chat_buffer(redis_key, context):
    if not db_pool or not redis_client: return

    # 1. Obtener los 10 mensajes más antiguos
    messages = await redis_client.lrange(redis_key, 0, 9)
    if not messages: return

    # 2. Recortar Redis (eliminar los que vamos a procesar)
    await redis_client.ltrim(redis_key, 10, -1)

    logger.info(f"💾 Flushing {len(messages)} mensajes de {redis_key} a MySQL...")

    async with db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            try:
                # Iniciar transacción
                await conn.begin()

                for json_msg in messages:
                    msg = json.loads(json_msg)
                    if not msg: continue

                    uuid_val = msg['uuid']
                    text = msg['message']
                    type_val = msg['type']
                    reply_to_uuid = msg.get('reply_to_uuid')
                    reply_to_id = None
                    created_at = msg['created_at']
                    
                    # Determinar tabla
                    table = 'private_messages' if context == 'private' else 'community_messages'

                    # Resolver ID de respuesta si existe
                    if reply_to_uuid:
                        await cur.execute(f"SELECT id FROM {table} WHERE uuid = %s", (reply_to_uuid,))
                        row = await cur.fetchone()
                        if row: reply_to_id = row[0]

                    new_msg_id = 0

                    if context == 'private':
                        sender_id = msg['sender_id']
                        receiver_id = msg['receiver_id']
                        
                        # Insertar mensaje privado
                        insert_sql = """
                            INSERT INTO private_messages 
                            (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at) 
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        await cur.execute(insert_sql, (uuid_val, sender_id, receiver_id, text, type_val, reply_to_id, reply_to_uuid, created_at))
                        new_msg_id = cur.lastrowid

                        # Adjuntos
                        if msg.get('attachments'):
                            for att in msg['attachments']:
                                await cur.execute("SELECT id FROM community_files WHERE file_path = %s", (att['path'],))
                                file_row = await cur.fetchone()
                                if file_row:
                                    await cur.execute("INSERT INTO private_message_attachments (message_id, file_id) VALUES (%s, %s)", (new_msg_id, file_row[0]))

                    else:
                        community_id = msg['community_id']
                        user_id = msg['user_id']
                        channel_id = msg.get('channel_id') # [NUEVO] Obtener canal

                        # Insertar mensaje comunidad (CON CHANNEL_ID)
                        insert_sql = """
                            INSERT INTO community_messages 
                            (uuid, community_id, channel_id, user_id, message, type, reply_to_id, reply_to_uuid, created_at) 
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        await cur.execute(insert_sql, (uuid_val, community_id, channel_id, user_id, text, type_val, reply_to_id, reply_to_uuid, created_at))
                        new_msg_id = cur.lastrowid

                        # Adjuntos
                        if msg.get('attachments'):
                            for att in msg['attachments']:
                                await cur.execute("SELECT id FROM community_files WHERE file_path = %s", (att['path'],))
                                file_row = await cur.fetchone()
                                if file_row:
                                    await cur.execute("INSERT INTO community_message_attachments (message_id, file_id) VALUES (%s, %s)", (new_msg_id, file_row[0]))

                await conn.commit()
                logger.info(f"✅ Flush exitoso: {len(messages)} mensajes guardados.")

            except Exception as e:
                await conn.rollback()
                logger.critical(f"❌ Error CRÍTICO en flush_chat_buffer: {e}")

# ---------------------------------------------------------------

async def handle_typing_event(user_id, payload):
    target_uuid = payload.get('target_uuid')
    if not target_uuid or not db_pool: return

    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT id FROM users WHERE uuid = %s", (target_uuid,))
                row = await cur.fetchone()
                if not row: return
                
                target_id = str(row[0])
                
                response = json.dumps({
                    "type": "typing",
                    "payload": {
                        "sender_id": user_id,
                    }
                })

                if target_id in connected_clients:
                    for ws in connected_clients[target_id].values():
                        try: await ws.send(response)
                        except: pass

    except Exception as e:
        logger.error(f"Error handling typing event: {e}")

# Anti-Spam
async def is_spamming(user_id):
    if not db_pool: return False
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT chat_msg_limit, chat_time_window FROM server_config WHERE id = 1")
                config = await cur.fetchone()
                
                if not config: return False
                
                limit = int(config[0]) if config[0] else 5
                seconds = int(config[1]) if config[1] else 10

                query = """
                    SELECT 
                    (SELECT COUNT(*) FROM community_messages WHERE user_id = %s AND created_at > (NOW() - INTERVAL %s SECOND)) +
                    (SELECT COUNT(*) FROM private_messages WHERE sender_id = %s AND created_at > (NOW() - INTERVAL %s SECOND)) 
                """
                await cur.execute(query, (user_id, seconds, user_id, seconds))
                count_row = await cur.fetchone()
                
                count = count_row[0] if count_row else 0
                
                return count >= limit
    except Exception as e:
        logger.error(f"Error en anti-spam check: {e}")
        return False

def generate_uuid_v4():
    return str(uuid.uuid4())

async def handle_chat_message(user_id, user_info, payload):
    target_uuid = payload.get('target_uuid')
    message_text = payload.get('message')
    context = payload.get('context', 'community')
    reply_to_uuid = payload.get('reply_to_uuid')
    channel_uuid = payload.get('channel_uuid') # [NUEVO] Canal opcional

    if not target_uuid or not message_text:
        return

    if await is_spamming(user_id):
        if user_id in connected_clients:
            err_msg = json.dumps({
                "type": "error",
                "message": "Estás enviando mensajes demasiado rápido."
            })
            for ws in connected_clients[user_id].values():
                try: await ws.send(err_msg)
                except: pass
        return 

    if not db_pool or not redis_client:
        logger.error("DB Pool o Redis no disponible.")
        return

    try:
        message_uuid = generate_uuid_v4()
        created_at = datetime.now().isoformat()
        
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                
                if context == 'private':
                    await cur.execute("SELECT id FROM users WHERE uuid = %s", (target_uuid,))
                    receiver_row = await cur.fetchone()
                    if not receiver_row: return
                    receiver_id = str(receiver_row[0])
                    if receiver_id == user_id: return 

                    reply_data = {}
                    if reply_to_uuid:
                        parent_query = "SELECT m.message, m.type, u.username FROM private_messages m JOIN users u ON m.sender_id = u.id WHERE m.uuid = %s"
                        await cur.execute(parent_query, (reply_to_uuid,))
                        parent_row = await cur.fetchone()
                        
                        if parent_row:
                            reply_data = {
                                'message': parent_row[0],
                                'type': parent_row[1],
                                'sender_username': parent_row[2]
                            }
                        else:
                            ids = sorted([int(user_id), int(receiver_id)])
                            search_key = f"chat:buffer:private:{ids[0]}:{ids[1]}"
                            cached_msgs = await redis_client.lrange(search_key, 0, -1)
                            for json_m in cached_msgs:
                                try:
                                    m_obj = json.loads(json_m)
                                    if m_obj.get('uuid') == reply_to_uuid:
                                        reply_data = {
                                            'message': m_obj.get('message'),
                                            'type': m_obj.get('type'),
                                            'sender_username': m_obj.get('sender_username')
                                        }
                                        break
                                except: continue

                    message_payload = {
                        "id": None,
                        "uuid": message_uuid,
                        "target_uuid": target_uuid,
                        "context": "private",
                        "message": message_text,
                        "sender_id": user_id,
                        "sender_uuid": user_info['uuid'],
                        "sender_username": user_info['username'],
                        "sender_profile_picture": user_info['profile_picture'],
                        "sender_role": user_info['role'],
                        "created_at": created_at,
                        "type": "text",
                        "status": "active",
                        "reply_to_uuid": reply_to_uuid,
                        "receiver_id": receiver_id,
                        "attachments": [],
                        "reply_message": reply_data.get('message'),
                        "reply_sender_username": reply_data.get('sender_username'),
                        "reply_type": reply_data.get('type')
                    }
                    
                    ids = sorted([int(user_id), int(receiver_id)])
                    redis_key = f"chat:buffer:private:{ids[0]}:{ids[1]}"
                    
                    await redis_client.rpush(redis_key, json.dumps(message_payload))

                    buffer_size = await redis_client.llen(redis_key)
                    if buffer_size >= 10:
                        asyncio.create_task(flush_chat_buffer(redis_key, context))

                    socket_response = {"type": "private_message", "payload": message_payload}
                    msg_json = json.dumps(socket_response)

                    if receiver_id in connected_clients:
                        for ws in connected_clients[receiver_id].values():
                            try: await ws.send(msg_json)
                            except: pass
                    if user_id in connected_clients:
                        for ws in connected_clients[user_id].values():
                            try: await ws.send(msg_json)
                            except: pass

                else:
                    # Contexto Comunidad
                    check_query = "SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = %s AND cm.user_id = %s"
                    await cur.execute(check_query, (target_uuid, user_id))
                    comm_row = await cur.fetchone()
                    if not comm_row: return
                    community_id = str(comm_row[0])

                    # [NUEVO] Resolver Channel ID
                    channel_id = None
                    if channel_uuid:
                        await cur.execute("SELECT id FROM community_channels WHERE uuid = %s AND community_id = %s", (channel_uuid, community_id))
                        chan_row = await cur.fetchone()
                        if chan_row:
                            channel_id = str(chan_row[0])
                    
                    # Si no hay canal específico, intentar usar el 'General' o el primero disponible
                    if not channel_id:
                         await cur.execute("SELECT id FROM community_channels WHERE community_id = %s ORDER BY created_at ASC LIMIT 1", (community_id,))
                         chan_row = await cur.fetchone()
                         if chan_row:
                             channel_id = str(chan_row[0])
                    
                    if not channel_id:
                         logger.warning(f"Intento de mensaje en comunidad {community_id} sin canales válidos.")
                         return

                    reply_data = {}
                    if reply_to_uuid:
                        parent_query = "SELECT m.message, m.type, u.username FROM community_messages m JOIN users u ON m.user_id = u.id WHERE m.uuid = %s"
                        await cur.execute(parent_query, (reply_to_uuid,))
                        parent_row = await cur.fetchone()
                        
                        if parent_row:
                            reply_data = {
                                'message': parent_row[0],
                                'type': parent_row[1],
                                'sender_username': parent_row[2]
                            }
                        else:
                            # [MODIFICADO] Buscar en el buffer del canal correcto
                            search_key = f"chat:buffer:channel:{channel_id}"
                            cached_msgs = await redis_client.lrange(search_key, 0, -1)
                            for json_m in cached_msgs:
                                try:
                                    m_obj = json.loads(json_m)
                                    if m_obj.get('uuid') == reply_to_uuid:
                                        reply_data = {
                                            'message': m_obj.get('message'),
                                            'type': m_obj.get('type'),
                                            'sender_username': m_obj.get('sender_username')
                                        }
                                        break
                                except: continue

                    message_payload = {
                        "id": None,
                        "uuid": message_uuid,
                        "target_uuid": target_uuid,
                        "community_uuid": target_uuid,
                        "channel_uuid": channel_uuid, # [NUEVO]
                        "context": "community",
                        "message": message_text,
                        "sender_id": user_id,
                        "sender_uuid": user_info['uuid'],
                        "sender_username": user_info['username'],
                        "sender_profile_picture": user_info['profile_picture'],
                        "sender_role": user_info['role'],
                        "created_at": created_at,
                        "type": "text",
                        "status": "active",
                        "reply_to_uuid": reply_to_uuid,
                        "community_id": community_id,
                        "channel_id": channel_id, # [NUEVO]
                        "user_id": user_id,
                        "attachments": [],
                        "reply_message": reply_data.get('message'),
                        "reply_sender_username": reply_data.get('sender_username'),
                        "reply_type": reply_data.get('type')
                    }

                    # [MODIFICADO] Key de Redis por Canal
                    redis_key = f"chat:buffer:channel:{channel_id}"
                    
                    await redis_client.rpush(redis_key, json.dumps(message_payload))

                    buffer_size = await redis_client.llen(redis_key)
                    if buffer_size >= 10:
                        asyncio.create_task(flush_chat_buffer(redis_key, context))

                    socket_response = {"type": "new_chat_message", "payload": message_payload}
                    msg_json = json.dumps(socket_response)

                    members_query = "SELECT user_id FROM community_members WHERE community_id = %s"
                    await cur.execute(members_query, (community_id,))
                    members = await cur.fetchall()
                    member_ids = set([str(m[0]) for m in members])

                    for connected_uid, sessions in connected_clients.items():
                        if connected_uid in member_ids:
                            for ws in sessions.values():
                                try: await ws.send(msg_json)
                                except: pass 

    except Exception as e:
        logger.error(f"Error procesando mensaje de chat: {e}")

async def handle_browser_client(websocket):
    user_id = None
    session_id = None
    user_role = None
    user_data_cache = {} 
    remote_ip = websocket.remote_address[0]
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
                
            msg_type = data.get('type')

            if msg_type == 'auth':
                if not check_rate_limit(remote_ip):
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Too many attempts."}))
                    return

                token = data.get('token')
                if not token: continue

                auth_data = await verify_token_and_get_session(token)
                
                if auth_data:
                    user_id, session_id, user_role, username, pfp, user_uuid = auth_data
                    
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                    connected_clients[user_id][session_id] = websocket
                    
                    user_data_cache = {
                        'role': user_role,
                        'username': username,
                        'profile_picture': pfp,
                        'uuid': user_uuid 
                    }

                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)
                    
                    logger.info(f"Usuario conectado: ID {user_id} | Rol: {user_role}")
                    
                    await websocket.send(json.dumps({"type": "connected"}))
                    await broadcast_user_status(user_id, 'online')
                else:
                    record_failed_attempt(remote_ip)
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Token invalid"}))
                    return 

            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    response = json.dumps({
                        "type": "online_users_list", 
                        "payload": list(connected_clients.keys())
                    })
                    await websocket.send(response)
            
            elif msg_type == 'chat_message':
                if user_id: 
                    await handle_chat_message(user_id, user_data_cache, data.get('payload', {}))

            elif msg_type == 'typing':
                if user_id:
                    await handle_typing_event(user_id, data.get('payload', {}))

    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        logger.error(f"Error socket cliente: {e}")
    finally:
        if user_id and session_id:
            if user_id in connected_clients and session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
                if not connected_clients[user_id]:
                    del connected_clients[user_id]
                    await broadcast_user_status(user_id, 'offline')
        
        if websocket in admin_sessions:
            admin_sessions.discard(websocket)

async def get_community_members(community_id):
    if not db_pool: return []
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT user_id FROM community_members WHERE community_id = %s", (community_id,))
                rows = await cur.fetchall()
                return [str(r[0]) for r in rows]
    except Exception as e:
        logger.error(f"Error fetching community members: {e}")
        return []

async def check_buffer_and_flush(redis_key, context):
    if not redis_client: return
    try:
        size = await redis_client.llen(redis_key)
        if size >= 10:
            await flush_chat_buffer(redis_key, context)
    except Exception as e:
        logger.error(f"Error checking buffer for PHP trigger: {e}")

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(8192) 
        msg = data.decode()
        if not msg: return
        
        try:
            full_payload = json.loads(msg)
        except json.JSONDecodeError:
            return

        target = str(full_payload.get('target_id'))
        msg_type = full_payload.get('type')
        payload_data = full_payload.get('payload', {})
        
        # --- CHECK AND FLUSH LOGIC ---
        if msg_type == 'new_chat_message':
            # [MODIFICADO] Extraer message_data y luego channel_id
            msg_data = payload_data.get('message_data', {})
            channel_id = msg_data.get('channel_id')
            
            if channel_id:
                redis_key = f"chat:buffer:channel:{channel_id}"
                await check_buffer_and_flush(redis_key, 'community')

        elif msg_type == 'private_message':
            msg_data = payload_data.get('message_data', {})
            sender_id = int(msg_data.get('sender_id'))
            
            receiver_id = int(target) 
            
            ids = sorted([sender_id, receiver_id])
            redis_key = f"chat:buffer:private:{ids[0]}:{ids[1]}"
            await check_buffer_and_flush(redis_key, 'private')
        # -----------------------------

        if msg_type == 'private_message':
            client_message = json.dumps({
                "type": "private_message",
                "payload": payload_data.get('message_data')
            })
            
            if target in connected_clients:
                for ws in connected_clients[target].values():
                    try: await ws.send(client_message)
                    except: pass
            
            sender_id = str(payload_data.get('sender_id'))
            if sender_id and sender_id in connected_clients:
                for ws in connected_clients[sender_id].values():
                    try: await ws.send(client_message)
                    except: pass

        elif target == 'community_broadcast':
            community_id = payload_data.get('community_id')
            message_data = payload_data.get('message_data') 
            
            client_message = json.dumps({
                "type": msg_type, 
                "payload": message_data
            })
            
            member_ids = await get_community_members(community_id)
            for uid in member_ids:
                if uid in connected_clients:
                    for ws in connected_clients[uid].values():
                        try: await ws.send(client_message)
                        except: pass

        elif target == 'global':
            client_message = json.dumps(full_payload)
            for uid, sessions in connected_clients.items():
                for ws in sessions.values():
                    try: await ws.send(client_message)
                    except: pass

        elif target in connected_clients:
            client_message = json.dumps(full_payload)
            for ws in connected_clients[target].values():
                try: await ws.send(client_message)
                except: pass

    except Exception as e:
        logger.error(f"Error puente PHP: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def main():
    await init_db_pool()
    await init_redis_pool()
    logger.info("=== Servidor Aurora Iniciado (Multi-Channel Support) ===")
    
    ws_server = await websockets.serve(handle_browser_client, "0.0.0.0", 8080)
    php_bridge = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
    
    await asyncio.Future() 

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("🛑 Servidor detenido.")
    except Exception as e:
        logger.critical(f"🔥 Error fatal: {e}")
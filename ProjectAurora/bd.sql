-- bd.sql

-- ==========================================
-- 1. LIMPIEZA INICIAL (RESET)
-- ==========================================
DROP DATABASE IF EXISTS project_aurora_db;

-- ==========================================
-- 2. CREACIÓN DE LA BASE DE DATOS
-- ==========================================
CREATE DATABASE IF NOT EXISTS project_aurora_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE project_aurora_db;

-- ==========================================
-- 3. CREACIÓN DE TABLAS PRINCIPALES
-- ==========================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    role VARCHAR(20) DEFAULT 'user',
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    suspension_reason TEXT NULL,
    suspension_end_date TIMESTAMP NULL,
    deletion_type ENUM('admin_decision', 'user_decision') NULL, 
    deletion_reason TEXT NULL,
    admin_comments TEXT NULL,
    is_2fa_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    backup_codes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, 
    code_type VARCHAR(50) NOT NULL,   
    code VARCHAR(64) NOT NULL,        
    payload JSON NULL,                
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (identifier),
    INDEX (code)
);

CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_check (user_identifier, ip_address, created_at)
);

CREATE TABLE IF NOT EXISTS friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (sender_id, receiver_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, 
    type VARCHAR(50) NOT NULL, 
    message TEXT NOT NULL,
    related_id INT NULL, 
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ws_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL, 
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    usage_intent VARCHAR(50) DEFAULT 'personal',
    language VARCHAR(10) DEFAULT 'en-us',
    theme VARCHAR(20) DEFAULT 'system',
    open_links_in_new_tab TINYINT(1) DEFAULT 1, 
    extended_message_time TINYINT(1) DEFAULT 0,
    message_privacy ENUM('everyone', 'friends', 'nobody') DEFAULT 'friends',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    performed_by INT NULL, 
    change_type ENUM('username', 'email', 'profile_picture', 'password', '2fa_disabled', 'privacy_update') NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by_ip VARCHAR(45) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_check (user_id, change_type, changed_at)
);

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (session_id)
);

CREATE TABLE IF NOT EXISTS user_suspension_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NULL, 
    reason TEXT NOT NULL,
    duration_days INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NULL, 
    lifted_by INT NULL, 
    lifted_at TIMESTAMP NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lifted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_role_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NULL,
    old_role VARCHAR(50) NOT NULL,
    new_role VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_role_audit (user_id, admin_id, changed_at)
);

CREATE TABLE IF NOT EXISTS server_config (
    id INT PRIMARY KEY DEFAULT 1,
    maintenance_mode TINYINT(1) DEFAULT 0,
    allow_registrations TINYINT(1) DEFAULT 1,
    min_password_length INT DEFAULT 8,
    max_password_length INT DEFAULT 72,
    min_username_length INT DEFAULT 6,
    max_username_length INT DEFAULT 32,
    max_email_length INT DEFAULT 255,
    max_login_attempts INT DEFAULT 5,
    lockout_time_minutes INT DEFAULT 5,
    code_resend_cooldown INT DEFAULT 60, 
    username_cooldown INT DEFAULT 30, 
    email_cooldown INT DEFAULT 12,    
    profile_picture_max_size INT DEFAULT 2,    
    allowed_email_domains JSON DEFAULT NULL,
    chat_msg_limit INT DEFAULT 5,
    chat_time_window INT DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO server_config (id, allowed_email_domains) 
VALUES (1, '["gmail.com", "outlook.com", "hotmail.com", "yahoo.com", "icloud.com"]');

CREATE TABLE IF NOT EXISTS system_alerts_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,          
    instance_id VARCHAR(50) NOT NULL,   
    status ENUM('active', 'stopped') DEFAULT 'active',
    admin_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stopped_at TIMESTAMP NULL,
    meta_data JSON NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 4. COMUNIDADES
-- ==========================================

CREATE TABLE IF NOT EXISTS communities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    community_name VARCHAR(100) NOT NULL,
    community_type ENUM('municipality', 'university', 'other') DEFAULT 'other',
    access_code CHAR(14) NOT NULL UNIQUE, 
    privacy ENUM('public', 'private') DEFAULT 'public',
    member_count INT DEFAULT 0,
    profile_picture VARCHAR(255) NULL,
    banner_picture VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (privacy),
    INDEX (access_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [NUEVO] TABLA DE CANALES
CREATE TABLE IF NOT EXISTS community_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    community_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    type ENUM('text', 'announcement') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin', 'moderator') DEFAULT 'member',
    is_pinned TINYINT(1) DEFAULT 0,
    is_favorite TINYINT(1) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (community_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [MODIFICADO] SE AGREGA CHANNEL_ID
CREATE TABLE IF NOT EXISTS community_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    community_id INT NOT NULL,
    channel_id INT DEFAULT NULL, -- Nuevo campo para soportar canales
    user_id INT NOT NULL,
    reply_to_id INT NULL,
    reply_to_uuid CHAR(36) NULL,
    message TEXT NOT NULL,
    type ENUM('text', 'image', 'system', 'mixed') DEFAULT 'text',
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES community_channels(id) ON DELETE CASCADE, -- Relación con canales
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES community_messages(id) ON DELETE SET NULL,
    UNIQUE KEY (uuid),
    INDEX (community_id, channel_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_message_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 5. MENSAJERÍA PRIVADA (DMs)
-- ==========================================

CREATE TABLE IF NOT EXISTS private_chat_clearance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    partner_id INT NOT NULL,
    cleared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_pinned TINYINT(1) DEFAULT 0,
    is_favorite TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_clearance (user_id, partner_id)
);

CREATE TABLE IF NOT EXISTS private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('text', 'image', 'system', 'mixed') DEFAULT 'text',
    reply_to_id INT NULL,
    reply_to_uuid CHAR(36) NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES private_messages(id) ON DELETE SET NULL,
    UNIQUE KEY (uuid),
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS private_message_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES private_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 6. GESTIÓN DE ARCHIVOS
-- ==========================================

CREATE TABLE IF NOT EXISTS community_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    uploader_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_id INT NOT NULL,
    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES community_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS private_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_id INT NOT NULL,
    FOREIGN KEY (message_id) REFERENCES private_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES community_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 7. DATOS DE PRUEBA
-- ==========================================

INSERT IGNORE INTO communities (uuid, community_name, community_type, access_code, privacy, member_count, profile_picture, banner_picture) VALUES
('comm-uuid-001', 'Universidad Autónoma de Nuevo León', 'university', 'UANL-STUD-2025', 'public', 120, 'https://ui-avatars.com/api/?name=UANL&background=F7C700&color=000', 'https://placehold.co/600x200/F7C700/000000?text=UANL'),
('comm-uuid-002', 'Tecnológico de Monterrey', 'university', 'ITESM-TEC-2025', 'public', 890, 'https://ui-avatars.com/api/?name=TEC&background=0033A0&color=fff', 'https://placehold.co/600x200/0033A0/ffffff?text=Borregos+TEC'),
('comm-uuid-003', 'Municipio de San Pedro', 'municipality', 'SPGG-CITY-2025', 'public', 45, 'https://ui-avatars.com/api/?name=SP&background=333333&color=fff', 'https://placehold.co/600x200/333333/ffffff?text=San+Pedro+Garza+Garcia'),
('comm-uuid-004', 'Municipio de Monterrey', 'municipality', 'MTY-CIUD-2025', 'public', 200, 'https://ui-avatars.com/api/?name=MTY&background=1E88E5&color=fff', 'https://placehold.co/600x200/1E88E5/ffffff?text=Monterrey'),
('comm-uuid-005', 'Project Aurora Staff', 'other', 'AURO-XH55-99ZZ', 'private', 5, 'https://ui-avatars.com/api/?name=PA&background=000000&color=fff', 'https://placehold.co/600x200/000000/ffffff?text=Project+Aurora');
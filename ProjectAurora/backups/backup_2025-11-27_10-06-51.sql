-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: project_aurora_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `communities`
--

DROP TABLE IF EXISTS `communities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `communities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `community_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `access_code` char(14) NOT NULL,
  `privacy` enum('public','private') DEFAULT 'public',
  `member_count` int(11) DEFAULT 1,
  `profile_picture` varchar(255) DEFAULT NULL,
  `banner_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `access_code` (`access_code`),
  KEY `creator_id` (`creator_id`),
  KEY `privacy` (`privacy`),
  KEY `access_code_2` (`access_code`),
  CONSTRAINT `communities_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communities`
--

LOCK TABLES `communities` WRITE;
/*!40000 ALTER TABLE `communities` DISABLE KEYS */;
INSERT INTO `communities` VALUES (1,'comm-uuid-001',1,'Desarrolladores PHP','Comunidad para amantes del código backend.','PHP7-CODE-2025','public',120,'https://ui-avatars.com/api/?name=PHP&background=0D8ABC&color=fff','https://picsum.photos/seed/php/600/200','2025-11-26 18:57:58'),(2,'comm-uuid-002',1,'Diseño UI/UX','Compartimos recursos de diseño e inspiración.','DSGN-2025-FREE','public',45,'https://ui-avatars.com/api/?name=UI&background=E91E63&color=fff','https://picsum.photos/seed/uiux/600/200','2025-11-26 18:57:58'),(3,'comm-uuid-003',1,'Proyecto Aurora Secret','Solo personal autorizado del proyecto.','AURO-XH55-99ZZ','private',5,'https://ui-avatars.com/api/?name=PA&background=000000&color=fff','https://picsum.photos/seed/aurora/600/200','2025-11-26 18:57:58'),(4,'comm-uuid-004',1,'Gaming Latam','Torneos y discusiones sobre videojuegos.','GAME-PLAY-NOW1','public',890,'https://ui-avatars.com/api/?name=GL&background=4CAF50&color=fff','https://picsum.photos/seed/gaming/600/200','2025-11-26 18:57:58'),(5,'comm-uuid-005',1,'Club de Lectura VIP','Acceso solo con invitación para lectores.','READ-BOOK-CLUB','private',12,'https://ui-avatars.com/api/?name=CL&background=FF9800&color=fff','https://picsum.photos/seed/books/600/200','2025-11-26 18:57:58');
/*!40000 ALTER TABLE `communities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_members`
--

DROP TABLE IF EXISTS `community_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `community_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin','moderator') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_membership` (`community_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `community_members_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_members`
--

LOCK TABLES `community_members` WRITE;
/*!40000 ALTER TABLE `community_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `friendships`
--

DROP TABLE IF EXISTS `friendships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friendships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_friendship` (`sender_id`,`receiver_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `friendships_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendships_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `friendships`
--

LOCK TABLES `friendships` WRITE;
/*!40000 ALTER TABLE `friendships` DISABLE KEYS */;
INSERT INTO `friendships` VALUES (13,1,2,'accepted','2025-11-26 06:45:54','2025-11-26 19:48:58');
/*!40000 ALTER TABLE `friendships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (15,1,'friend_accepted','<strong>user20251124_1409461r</strong> aceptó tu solicitud.',2,0,'2025-11-26 19:48:58');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_identifier` varchar(255) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_check` (`user_identifier`,`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (1,'1','pref_update_limit','192.168.1.158','2025-11-24 20:10:34'),(2,'1','pref_update_limit','192.168.1.158','2025-11-24 20:10:35'),(3,'1','friend_request_limit','192.168.1.158','2025-11-24 20:31:52'),(4,'1','friend_request_limit','192.168.1.158','2025-11-24 20:32:54'),(5,'1','friend_request_limit','192.168.1.158','2025-11-24 20:32:56'),(6,'1','friend_request_limit','192.168.1.158','2025-11-24 20:32:57'),(7,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:06'),(8,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:07'),(9,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:08'),(10,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:09'),(11,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:09'),(12,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:10'),(13,'1','friend_request_limit','192.168.1.158','2025-11-24 20:33:10'),(14,'1','friend_request_limit','192.168.1.158','2025-11-24 20:34:36'),(15,'jorge2@gmail.com','login_fail','192.168.1.161','2025-11-25 20:10:02'),(16,'jorge@gmail.com','login_fail','192.168.1.161','2025-11-25 20:10:06'),(17,'jorge2@gmail.com','login_fail','192.168.1.161','2025-11-25 20:10:11'),(18,'1','pref_update_limit','192.168.1.161','2025-11-25 20:12:05'),(19,'1','pref_update_limit','192.168.1.161','2025-11-25 20:12:06'),(20,'1','friend_request_limit','192.168.1.161','2025-11-26 06:45:54'),(21,'1','pref_update_limit','192.168.1.161','2025-11-26 18:24:07'),(22,'1','pref_update_limit','192.168.1.161','2025-11-26 18:24:09');
/*!40000 ALTER TABLE `security_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `server_config`
--

DROP TABLE IF EXISTS `server_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `allow_registrations` tinyint(1) DEFAULT 1,
  `min_password_length` int(11) DEFAULT 8,
  `max_password_length` int(11) DEFAULT 72,
  `min_username_length` int(11) DEFAULT 6,
  `max_username_length` int(11) DEFAULT 32,
  `max_email_length` int(11) DEFAULT 255,
  `max_login_attempts` int(11) DEFAULT 5,
  `lockout_time_minutes` int(11) DEFAULT 5,
  `code_resend_cooldown` int(11) DEFAULT 60,
  `username_cooldown` int(11) DEFAULT 30,
  `email_cooldown` int(11) DEFAULT 12,
  `profile_picture_max_size` int(11) DEFAULT 2,
  `allowed_email_domains` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_email_domains`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `server_config`
--

LOCK TABLES `server_config` WRITE;
/*!40000 ALTER TABLE `server_config` DISABLE KEYS */;
INSERT INTO `server_config` VALUES (1,0,1,8,72,6,32,255,5,5,60,30,12,2,'[\"gmail.com\",\"outlook.com\",\"hotmail.com\",\"yahoo.com\",\"icloud.com\",\"casa.com\",\"cas.com\",\"gmail.es\"]','2025-11-26 05:47:57');
/*!40000 ALTER TABLE `server_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_alerts_history`
--

DROP TABLE IF EXISTS `system_alerts_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_alerts_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `instance_id` varchar(50) NOT NULL,
  `status` enum('active','stopped') DEFAULT 'active',
  `admin_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stopped_at` timestamp NULL DEFAULT NULL,
  `meta_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_data`)),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `status` (`status`),
  CONSTRAINT `system_alerts_history_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_alerts_history`
--

LOCK TABLES `system_alerts_history` WRITE;
/*!40000 ALTER TABLE `system_alerts_history` DISABLE KEYS */;
INSERT INTO `system_alerts_history` VALUES (1,'maintenance_warning','42e1b08f-1277-4905-9a5d-92085a245ae8','stopped',1,'2025-11-27 06:24:46','2025-11-27 06:25:00','{\"date\":\"2025-12-24\",\"time\":\"17:30\",\"link\":\"\"}'),(2,'update_info','608133c1-2bf6-430d-9ba4-a167e38c7218','stopped',1,'2025-11-27 06:25:34','2025-11-27 06:25:42','{\"date\":\"2025-12-30\",\"time\":\"00:30\",\"link\":\"https:\\/\\/github.com\\/JorgeOrtega-ux\\/ProjectAurora\"}'),(3,'terms_update','5a919899-b2d3-4093-98da-9730ac50316d','stopped',1,'2025-11-27 06:26:01','2025-11-27 06:26:10','{\"date\":\"2025-12-30\",\"time\":\"00:30\",\"link\":\"https:\\/\\/github.com\\/JorgeOrtega-ux\\/ProjectAurora\"}'),(4,'update_info','2743e6a0-67f0-46cd-afd2-600077c078ab','stopped',1,'2025-11-27 06:50:36','2025-11-27 16:06:08','{\"date\":\"2025-12-06\",\"time\":\"\",\"link\":\"w\"}'),(5,'terms_update','52ffb562-c9fd-46bf-b788-4487907579b5','stopped',1,'2025-11-27 16:06:32','2025-11-27 16:06:44','{\"date\":\"2025-11-29\",\"time\":\"04:12\",\"link\":\"https:\\/\\/github.com\\/JorgeOrtega-ux\\/ProjectAurora\"}');
/*!40000 ALTER TABLE `system_alerts_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_audit_logs`
--

DROP TABLE IF EXISTS `user_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `change_type` enum('username','email','profile_picture','password','2fa_disabled') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by_ip` varchar(45) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_check` (`user_id`,`change_type`,`changed_at`),
  CONSTRAINT `user_audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_audit_logs`
--

LOCK TABLES `user_audit_logs` WRITE;
/*!40000 ALTER TABLE `user_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `usage_intent` varchar(50) DEFAULT 'personal',
  `language` varchar(10) DEFAULT 'en-us',
  `theme` varchar(20) DEFAULT 'system',
  `open_links_in_new_tab` tinyint(1) DEFAULT 1,
  `extended_message_time` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,1,'personal','es-latam','system',1,0,'2025-11-26 18:24:09'),(2,2,'personal','es-latam','system',1,0,'2025-11-24 20:10:00');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_role_logs`
--

DROP TABLE IF EXISTS `user_role_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_role_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `old_role` varchar(50) NOT NULL,
  `new_role` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_role_audit` (`user_id`,`admin_id`,`changed_at`),
  CONSTRAINT `user_role_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_role_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_role_logs`
--

LOCK TABLES `user_role_logs` WRITE;
/*!40000 ALTER TABLE `user_role_logs` DISABLE KEYS */;
INSERT INTO `user_role_logs` VALUES (1,2,1,'user','moderator','192.168.1.161','2025-11-25 21:04:45'),(2,2,1,'moderator','administrator','192.168.1.161','2025-11-26 05:40:37'),(3,2,1,'administrator','moderator','192.168.1.161','2025-11-26 05:46:56'),(4,2,1,'moderator','administrator','192.168.1.161','2025-11-26 06:46:06'),(5,2,1,'administrator','moderator','192.168.1.161','2025-11-26 07:07:23');
/*!40000 ALTER TABLE `user_role_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (5,1,'rbasikmdi10b5d78kiqm1u2n8e','192.168.1.161','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2025-11-27 06:58:07','2025-11-25 20:09:18'),(8,2,'29h5tpk5djbtjjt2dgl166etmh','192.168.1.161','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2025-11-26 19:49:44','2025-11-26 19:48:55'),(9,1,'1jldasd93af4pur9l1acvr51r1','192.168.1.157','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2025-11-27 16:05:42','2025-11-27 16:05:41');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_suspension_logs`
--

DROP TABLE IF EXISTS `user_suspension_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_suspension_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `reason` text NOT NULL,
  `duration_days` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ends_at` timestamp NULL DEFAULT NULL,
  `lifted_by` int(11) DEFAULT NULL,
  `lifted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `lifted_by` (`lifted_by`),
  CONSTRAINT `user_suspension_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_suspension_logs_ibfk_2` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_suspension_logs`
--

LOCK TABLES `user_suspension_logs` WRITE;
/*!40000 ALTER TABLE `user_suspension_logs` DISABLE KEYS */;
INSERT INTO `user_suspension_logs` VALUES (1,2,1,'Violación de términos de servicio',6,'2025-11-26 07:07:47','2025-12-02 07:07:47',1,'2025-11-26 07:09:30','2025-11-26 07:07:47');
/*!40000 ALTER TABLE `user_suspension_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `account_status` enum('active','suspended','deleted') DEFAULT 'active',
  `suspension_reason` text DEFAULT NULL,
  `suspension_end_date` timestamp NULL DEFAULT NULL,
  `deletion_type` enum('admin_decision','user_decision') DEFAULT NULL,
  `deletion_reason` text DEFAULT NULL,
  `admin_comments` text DEFAULT NULL,
  `is_2fa_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backup_codes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'c4f573a8-9fe8-4bf1-938f-cc78f1bae546','user20251124_131127w6','12@gmail.com','$2y$10$/K.KV9dYwbJscdfynskLXu61349OoEf.plLrKpSmjIcTKi5T1YxaK','assets/uploads/profile_pictures/default/c4f573a8-9fe8-4bf1-938f-cc78f1bae546.png','founder','active',NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2025-11-24 19:11:43'),(2,'ea759cb3-a7f3-44f6-b6b7-b883613b9e69','user20251124_1409461r','11@gmail.com','$2y$10$BpdkrXLGPcycXSZ2cRhlk.qPmhulhCap/0kV0Wu1bRvYDw78USGVK','assets/uploads/profile_pictures/default/ea759cb3-a7f3-44f6-b6b7-b883613b9e69.png','moderator','active',NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2025-11-24 20:10:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_codes`
--

DROP TABLE IF EXISTS `verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `code_type` varchar(50) NOT NULL,
  `code` varchar(64) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
INSERT INTO `verification_codes` VALUES (2,'11@gmail.es','registration','8b5f843ff760304514cb474ccfc8be824958c068dad4b81ac338505fb70ec269','{\"username\":\"user20251124_132139r2\",\"password_hash\":\"$2y$10$UQbl6x53K.hcRN5L1cOQ6.\\/hVQKQfw8kVJYh.wvxt.0MsSc0z.EA6\"}','2025-11-24 19:36:39','2025-11-24 19:21:39');
/*!40000 ALTER TABLE `verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ws_auth_tokens`
--

DROP TABLE IF EXISTS `ws_auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ws_auth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ws_auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ws_auth_tokens`
--

LOCK TABLES `ws_auth_tokens` WRITE;
/*!40000 ALTER TABLE `ws_auth_tokens` DISABLE KEYS */;
INSERT INTO `ws_auth_tokens` VALUES (177,2,'29h5tpk5djbtjjt2dgl166etmh','4d4b3f3985d2e0e6ced15f8753a0bc0482d5ffa05e58a7ad931d18aa195db551','2025-11-26 19:51:44','2025-11-26 19:49:44'),(199,1,'1jldasd93af4pur9l1acvr51r1','8dea8aa6397489054035b02d518c7733c023e4278c0fde5ae93abe02b9fb9cd0','2025-11-27 16:07:42','2025-11-27 16:05:42');
/*!40000 ALTER TABLE `ws_auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-27 10:06:53

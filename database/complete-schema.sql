/*M!999999\- enable the sandbox mode */ 

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bullying_report_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_report` (`report_id`),
  CONSTRAINT `bullying_report_attachments_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `bullying_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bullying_report_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='File attachments for bullying reports (screenshots, evidence)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bullying_report_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL COMMENT 'Counselor/admin who wrote note',
  `note_type` enum('internal','action','follow_up','resolution') DEFAULT 'internal',
  `note_text` text NOT NULL,
  `is_confidential` tinyint(1) DEFAULT 1 COMMENT 'Only visible to admin/counselor',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  KEY `idx_author` (`author_id`),
  CONSTRAINT `bullying_report_notes_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `bullying_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bullying_report_notes_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal notes and actions on bullying reports';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bullying_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_user_id` int(11) DEFAULT NULL COMMENT 'If logged-in user submits, link to user',
  `reporter_name` varchar(255) DEFAULT NULL COMMENT 'Name of reporter (optional)',
  `reporter_email` varchar(255) DEFAULT NULL COMMENT 'Contact email (optional)',
  `reporter_phone` varchar(20) DEFAULT NULL COMMENT 'Contact phone (optional)',
  `is_anonymous` tinyint(1) DEFAULT 0 COMMENT 'Reporter chose to remain anonymous',
  `reporter_relationship` enum('student','parent','teacher','staff','witness','other') DEFAULT NULL COMMENT 'Reporter relationship to incident',
  `incident_date` date NOT NULL COMMENT 'When did incident occur',
  `incident_time` time DEFAULT NULL COMMENT 'Approximate time of incident',
  `incident_location` varchar(255) NOT NULL COMMENT 'Where did incident occur',
  `victim_name` varchar(255) DEFAULT NULL COMMENT 'Name of victim (if known)',
  `victim_grade` varchar(20) DEFAULT NULL COMMENT 'Grade of victim',
  `bully_name` varchar(255) DEFAULT NULL COMMENT 'Name of alleged bully (if known)',
  `bully_grade` varchar(20) DEFAULT NULL COMMENT 'Grade of alleged bully',
  `incident_description` text NOT NULL COMMENT 'Detailed description of what happened',
  `incident_type` enum('physical','verbal','cyber','social','other') NOT NULL COMMENT 'Type of bullying',
  `witnesses` text DEFAULT NULL COMMENT 'Names of witnesses (if any)',
  `previous_incidents` tinyint(1) DEFAULT 0 COMMENT 'Has this happened before?',
  `previous_incidents_details` text DEFAULT NULL COMMENT 'Details of previous incidents',
  `status` enum('submitted','under_review','investigating','resolved','closed','no_action_needed') DEFAULT 'submitted' COMMENT 'Current status of report',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium' COMMENT 'Priority level',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Counselor/Admin assigned to handle',
  `action_taken` text DEFAULT NULL COMMENT 'What actions were taken',
  `resolution_notes` text DEFAULT NULL COMMENT 'Final resolution notes',
  `follow_up_required` tinyint(1) DEFAULT 1 COMMENT 'Does this need follow-up?',
  `follow_up_date` date DEFAULT NULL COMMENT 'When to follow up',
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL COMMENT 'When was report resolved',
  `resolved_by` int(11) DEFAULT NULL COMMENT 'Who resolved the report',
  PRIMARY KEY (`id`),
  KEY `reporter_user_id` (`reporter_user_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_incident_date` (`incident_date`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_submitted` (`submitted_at`),
  CONSTRAINT `bullying_reports_ibfk_1` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bullying_reports_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bullying_reports_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bullying incident reports - CONFIDENTIAL';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fuel_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_purpose_id` int(11) NOT NULL,
  `mileage` int(11) NOT NULL,
  `fuel_amount` decimal(10,2) NOT NULL,
  `entry_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vehicle_id` (`vehicle_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_trip_purpose_id` (`trip_purpose_id`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `fuel_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  CONSTRAINT `fuel_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fuel_records_ibfk_3` FOREIGN KEY (`trip_purpose_id`) REFERENCES `trip_purposes` (`id`),
  CONSTRAINT `fuel_records_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `fuel_records_view` AS SELECT
 1 AS `id`,
  1 AS `entry_date`,
  1 AS `vehicle_name`,
  1 AS `vehicle_number`,
  1 AS `user_name`,
  1 AS `user_email`,
  1 AS `purpose_code`,
  1 AS `purpose_description`,
  1 AS `mileage`,
  1 AS `fuel_amount`,
  1 AS `notes`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `updated_by_name` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `role` enum('staff','manager','admin','super_admin') DEFAULT 'staff',
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`),
  KEY `invited_by` (`invited_by`),
  CONSTRAINT `invitations_ibfk_1` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=946 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `slug` varchar(100) NOT NULL,
  `base_url` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1064 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `data` longtext DEFAULT NULL,
  `row_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `report_runs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_runs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `frequency` enum('daily','weekly','monthly','custom') DEFAULT 'daily',
  `time` time DEFAULT '09:00:00',
  `email_recipients` text DEFAULT NULL,
  `export_format` enum('pdf','excel','csv') DEFAULT 'pdf',
  `is_active` tinyint(1) DEFAULT 1,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_id` (`report_id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_next_run` (`next_run_at`,`is_active`),
  CONSTRAINT `report_schedules_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_slug` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `template` varchar(100) NOT NULL,
  `data_source` text NOT NULL,
  `date_column` varchar(100) DEFAULT NULL,
  `group_by` varchar(100) DEFAULT NULL,
  `schedule_enabled` tinyint(1) DEFAULT 0,
  `query_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`query_config`)),
  `schedule_frequency` enum('once','daily','weekly','monthly') DEFAULT 'once',
  `schedule_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_config`)),
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_slug` (`module_slug`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_next_run` (`next_run_at`,`is_active`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_value` varchar(50) NOT NULL COMMENT 'Role value from Roles.php',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Is this role currently enabled?',
  `display_order` int(11) DEFAULT 0 COMMENT 'Custom display order (overrides hierarchy)',
  `notes` text DEFAULT NULL COMMENT 'Admin notes about this role',
  `disabled_at` timestamp NULL DEFAULT NULL COMMENT 'When role was disabled',
  `disabled_by` int(11) DEFAULT NULL COMMENT 'Who disabled the role',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_value` (`role_value`),
  KEY `disabled_by` (`disabled_by`),
  KEY `idx_active` (`is_active`),
  KEY `idx_role` (`role_value`),
  CONSTRAINT `role_settings_ibfk_1` FOREIGN KEY (`disabled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manage which roles are active/visible in the system';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `role` enum('staff','maintenance','maintenance_director','manager','admin','super_admin') NOT NULL DEFAULT 'staff',
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_section` (`user_id`,`section_id`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_user_section` (`user_id`,`section_id`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `section_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_access_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `section_access_view` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `user_email`,
  1 AS `user_name`,
  1 AS `section_id`,
  1 AS `section_name`,
  1 AS `section_slug`,
  1 AS `section_display_name`,
  1 AS `section_icon`,
  1 AS `section_base_url`,
  1 AS `section_is_active`,
  1 AS `role`,
  1 AS `granted_by`,
  1 AS `granted_by_name`,
  1 AS `granted_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `section_admin_view` AS SELECT
 1 AS `id`,
  1 AS `section_id`,
  1 AS `section_name`,
  1 AS `section_slug`,
  1 AS `user_id`,
  1 AS `user_name`,
  1 AS `user_email`,
  1 AS `permission_level`,
  1 AS `can_view_all`,
  1 AS `can_edit_records`,
  1 AS `can_delete_records`,
  1 AS `can_export`,
  1 AS `can_manage_users`,
  1 AS `granted_by`,
  1 AS `granted_by_name`,
  1 AS `granted_at`,
  1 AS `is_active` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_administrators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Section they manage',
  `user_id` int(11) NOT NULL COMMENT 'User with admin rights',
  `permission_level` enum('viewer','editor','admin','owner') DEFAULT 'admin' COMMENT 'Level of access',
  `can_view_all` tinyint(1) DEFAULT 1 COMMENT 'View all records',
  `can_edit_records` tinyint(1) DEFAULT 1 COMMENT 'Edit any record',
  `can_delete_records` tinyint(1) DEFAULT 0 COMMENT 'Delete records',
  `can_export` tinyint(1) DEFAULT 1 COMMENT 'Export data',
  `can_manage_users` tinyint(1) DEFAULT 0 COMMENT 'Add/remove section users',
  `can_manage_fields` tinyint(1) DEFAULT 0 COMMENT 'Modify section structure',
  `granted_by` int(11) DEFAULT NULL COMMENT 'Who granted this access',
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional expiration date',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Active assignment',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_user` (`section_id`,`user_id`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_user` (`user_id`),
  KEY `idx_section` (`section_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `section_administrators_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_administrators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_administrators_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section-specific administrator assignments';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requires_forms` tinyint(1) DEFAULT 0,
  `requires_submissions` tinyint(1) DEFAULT 0,
  `requires_notifications` tinyint(1) DEFAULT 0,
  `requires_guidelines` tinyint(1) DEFAULT 0,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_compatibility_checks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_record_id` int(11) DEFAULT NULL,
  `install_id` int(11) DEFAULT NULL,
  `check_type` varchar(50) NOT NULL COMMENT 'Type of check (hub_version, dependencies, etc.)',
  `check_name` varchar(100) NOT NULL COMMENT 'What was checked',
  `required_value` varchar(100) DEFAULT NULL COMMENT 'What was required',
  `actual_value` varchar(100) DEFAULT NULL COMMENT 'What was found',
  `status` enum('pass','fail','warning') NOT NULL,
  `severity` enum('critical','error','warning','info') DEFAULT 'error',
  `message` text DEFAULT NULL COMMENT 'Human-readable message',
  `resolution` text DEFAULT NULL COMMENT 'How to fix if failed',
  `checked_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_install` (`install_id`),
  KEY `idx_status` (`status`,`severity`),
  CONSTRAINT `section_compatibility_checks_ibfk_1` FOREIGN KEY (`install_id`) REFERENCES `section_package_installs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed compatibility check results';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `enable_status_tracking` tinyint(1) DEFAULT 1,
  `enable_priority_levels` tinyint(1) DEFAULT 1,
  `enable_attachments` tinyint(1) DEFAULT 0,
  `enable_follow_up_notes` tinyint(1) DEFAULT 1,
  `enable_assignment` tinyint(1) DEFAULT 1,
  `max_attachments` int(11) DEFAULT 5,
  `allowed_file_types` text DEFAULT NULL,
  `form_config` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_id` (`section_id`),
  CONSTRAINT `section_configuration_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_field_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Parent section',
  `field_name` varchar(100) NOT NULL COMMENT 'Internal field identifier',
  `field_type` varchar(50) NOT NULL COMMENT 'Data type (text, number, date, select, etc.)',
  `field_label` varchar(255) NOT NULL COMMENT 'Display label for users',
  `field_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Type-specific configuration (options, validation, etc.)' CHECK (json_valid(`field_config`)),
  `is_required` tinyint(1) DEFAULT 0 COMMENT 'Must be filled out',
  `is_searchable` tinyint(1) DEFAULT 1 COMMENT 'Include in search index',
  `is_exportable` tinyint(1) DEFAULT 1 COMMENT 'Include in exports',
  `is_editable_by_creator` tinyint(1) DEFAULT 0 COMMENT 'Can creator edit their own entry',
  `show_in_list` tinyint(1) DEFAULT 1 COMMENT 'Show in table view',
  `show_in_detail` tinyint(1) DEFAULT 1 COMMENT 'Show in detail view',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Display order',
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Custom validation (min, max, regex, etc.)' CHECK (json_valid(`validation_rules`)),
  `help_text` text DEFAULT NULL COMMENT 'Helper text shown to users',
  `default_value` text DEFAULT NULL COMMENT 'Default value for new entries',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_field` (`section_id`,`field_name`),
  KEY `idx_section_order` (`section_id`,`sort_order`),
  KEY `idx_type` (`field_type`),
  KEY `idx_type_required` (`field_type`,`is_required`),
  CONSTRAINT `section_field_definitions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Define custom fields for dynamic sections';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_guidelines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `guideline_type` enum('submission','review','general') DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_section_active` (`section_id`,`is_active`),
  CONSTRAINT `section_guidelines_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=396 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_installations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Reference to sections table',
  `package_id` varchar(100) DEFAULT NULL COMMENT 'Source package identifier',
  `package_record_id` int(11) DEFAULT NULL,
  `installed_version` varchar(20) NOT NULL COMMENT 'Currently installed version',
  `available_version` varchar(20) DEFAULT NULL COMMENT 'Latest available version',
  `auto_update` tinyint(1) DEFAULT 0 COMMENT 'Auto-update when new version available',
  `status` varchar(50) DEFAULT 'installed',
  `installed_by` int(11) DEFAULT NULL COMMENT 'User who installed',
  `installed_at` timestamp NULL DEFAULT current_timestamp(),
  `upgraded_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `installed_by` (`installed_by`),
  KEY `idx_section` (`section_id`),
  KEY `idx_updates` (`package_id`,`installed_version`,`available_version`),
  CONSTRAINT `section_installations_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_installations_ibfk_2` FOREIGN KEY (`installed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track installed sections and available updates';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Parent section',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent menu item (for sub-menus)',
  `label` varchar(100) NOT NULL COMMENT 'Menu text',
  `icon` varchar(10) DEFAULT NULL COMMENT 'Emoji or icon',
  `route` varchar(255) NOT NULL COMMENT 'URL path',
  `required_permission` varchar(50) DEFAULT NULL COMMENT 'Permission needed to see',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Display order',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Show in menu',
  `open_in_new_tab` tinyint(1) DEFAULT 0 COMMENT 'Open in new window',
  `badge_count_query` text DEFAULT NULL COMMENT 'SQL query for badge number',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_section_parent` (`section_id`,`parent_id`,`sort_order`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `section_menu_items_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_menu_items_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `section_menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dynamic menu items for section dashboards';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_notification_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Which section',
  `action_type` enum('submission','approval','approved','rejected','due_soon','overdue','scheduled') NOT NULL COMMENT 'What action triggers notification',
  `notify_role` enum('staff','student','maintenance_staff','custodial','cafeteria','custodial_manager','maintenance_director','business_manager','substitute_manager','counselor','principal','admin','super_admin') NOT NULL,
  `email_template` varchar(100) DEFAULT NULL COMMENT 'Template file to use for email',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Is this notification rule active?',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_action_role` (`section_id`,`action_type`,`notify_role`),
  KEY `idx_section_action` (`section_id`,`action_type`),
  KEY `idx_role` (`notify_role`),
  CONSTRAINT `section_notification_roles_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines which roles get notified for section actions';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_notification_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `notify_on_submission` tinyint(1) DEFAULT 1,
  `notify_on_status_change` tinyint(1) DEFAULT 0,
  `notify_on_assignment` tinyint(1) DEFAULT 1,
  `notify_on_comment` tinyint(1) DEFAULT 0,
  `email_enabled` tinyint(1) DEFAULT 1,
  `sms_enabled` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_role_notify` (`section_id`,`role_name`),
  KEY `idx_section_notify_active` (`section_id`,`is_active`),
  CONSTRAINT `section_notification_rules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_id` int(11) NOT NULL,
  `package_id` varchar(100) NOT NULL,
  `package_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Cached package metadata' CHECK (json_valid(`package_data`)),
  `available_versions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of available versions' CHECK (json_valid(`available_versions`)),
  `latest_version` varchar(20) DEFAULT NULL,
  `download_url` varchar(500) DEFAULT NULL,
  `cached_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cache` (`repository_id`,`package_id`),
  KEY `idx_package` (`package_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `section_package_cache_ibfk_1` FOREIGN KEY (`repository_id`) REFERENCES `section_package_repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached package listings from repositories';
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `section_package_compatibility_summary` AS SELECT
 1 AS `install_id`,
  1 AS `package_id`,
  1 AS `package_version`,
  1 AS `install_status`,
  1 AS `total_checks`,
  1 AS `failures`,
  1 AS `warnings`,
  1 AS `critical_issues`,
  1 AS `attempted_at` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_dependencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` varchar(100) NOT NULL,
  `package_version` varchar(20) NOT NULL,
  `dependency_type` enum('package','module','extension','table') NOT NULL,
  `dependency_name` varchar(100) NOT NULL,
  `version_constraint` varchar(50) DEFAULT NULL COMMENT 'e.g., ^1.0.0, >=2.0.0',
  `is_optional` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dependency` (`package_id`,`package_version`,`dependency_type`,`dependency_name`),
  KEY `idx_package` (`package_id`,`package_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Package dependencies for checking';
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `section_package_install_history` AS SELECT
 1 AS `id`,
  1 AS `package_id`,
  1 AS `package_version`,
  1 AS `status`,
  1 AS `installation_type`,
  1 AS `attempted_by`,
  1 AS `installer_name`,
  1 AS `attempted_at`,
  1 AS `completed_at`,
  1 AS `section_id`,
  1 AS `section_name`,
  1 AS `error_message`,
  1 AS `duration_seconds` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_installs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` varchar(100) NOT NULL COMMENT 'Package identifier',
  `package_version` varchar(20) NOT NULL COMMENT 'Version attempted',
  `status` enum('pending','success','failed','rolled_back') NOT NULL,
  `installation_type` enum('new','upgrade','downgrade','reinstall') DEFAULT 'new',
  `attempted_by` int(11) DEFAULT NULL COMMENT 'User who attempted install',
  `attempted_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL COMMENT 'Created section (if successful)',
  `error_message` text DEFAULT NULL COMMENT 'Error if failed',
  `compatibility_report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full compatibility check results' CHECK (json_valid(`compatibility_report`)),
  `installation_log` text DEFAULT NULL COMMENT 'Detailed installation log',
  PRIMARY KEY (`id`),
  KEY `attempted_by` (`attempted_by`),
  KEY `section_id` (`section_id`),
  KEY `idx_package` (`package_id`,`package_version`),
  KEY `idx_status` (`status`),
  KEY `idx_attempted_at` (`attempted_at` DESC),
  CONSTRAINT `section_package_installs_ibfk_1` FOREIGN KEY (`attempted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `section_package_installs_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track all package installation attempts';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` varchar(100) NOT NULL,
  `from_version` varchar(20) NOT NULL,
  `to_version` varchar(20) NOT NULL,
  `migration_sql` text DEFAULT NULL COMMENT 'SQL to run for upgrade',
  `rollback_sql` text DEFAULT NULL COMMENT 'SQL to rollback',
  `migration_order` int(11) DEFAULT 0 COMMENT 'Order to run migrations',
  `is_required` tinyint(1) DEFAULT 1 COMMENT 'Can be skipped?',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_migration` (`package_id`,`from_version`,`to_version`),
  KEY `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Version upgrade/downgrade migrations';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_repositories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL COMMENT 'Repository API URL',
  `type` enum('github','gitlab','custom','local') DEFAULT 'custom',
  `is_official` tinyint(1) DEFAULT 0 COMMENT 'Official Hub repository',
  `is_enabled` tinyint(1) DEFAULT 1,
  `requires_auth` tinyint(1) DEFAULT 0,
  `auth_token` varchar(255) DEFAULT NULL COMMENT 'API token if needed',
  `priority` int(11) DEFAULT 50 COMMENT 'Search priority (higher = first)',
  `last_sync` timestamp NULL DEFAULT NULL COMMENT 'Last time we synced',
  `sync_interval` int(11) DEFAULT 3600 COMMENT 'Sync frequency in seconds',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_enabled` (`is_enabled`,`priority` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remote package repositories';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL COMMENT '1-5 stars',
  `title` varchar(255) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `hub_version` varchar(20) DEFAULT NULL COMMENT 'Hub version when reviewed',
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Verified purchase/install',
  `helpful_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_package` (`package_id`,`rating` DESC),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `section_package_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rating_range` CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User ratings and reviews for packages';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_package_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installation_id` int(11) NOT NULL COMMENT 'Installed section',
  `package_id` varchar(100) NOT NULL,
  `current_version` varchar(20) NOT NULL,
  `available_version` varchar(20) NOT NULL,
  `update_type` enum('major','minor','patch','security') NOT NULL,
  `is_security` tinyint(1) DEFAULT 0 COMMENT 'Security update',
  `breaking_changes` tinyint(1) DEFAULT 0 COMMENT 'Has breaking changes',
  `changelog` text DEFAULT NULL COMMENT 'What changed',
  `notified_at` timestamp NULL DEFAULT current_timestamp(),
  `is_dismissed` tinyint(1) DEFAULT 0,
  `auto_update_scheduled` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_installation` (`installation_id`),
  KEY `idx_security` (`is_security`,`is_dismissed`),
  CONSTRAINT `section_package_updates_ibfk_1` FOREIGN KEY (`installation_id`) REFERENCES `section_installations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track available updates for installed packages';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` varchar(100) NOT NULL COMMENT 'Unique identifier (e.g., woodson.fuel-tracking)',
  `name` varchar(100) NOT NULL COMMENT 'Internal name (slug format)',
  `display_name` varchar(255) NOT NULL COMMENT 'User-friendly display name',
  `description` text DEFAULT NULL COMMENT 'What this section does',
  `author` varchar(255) DEFAULT NULL COMMENT 'Package creator name',
  `author_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `author_organization` varchar(255) DEFAULT NULL COMMENT 'School/District name',
  `license` varchar(100) DEFAULT 'proprietary',
  `version` varchar(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Semantic version',
  `package_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Complete package definition' CHECK (json_valid(`package_data`)),
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `validation_status` varchar(50) DEFAULT 'pending',
  `can_install` tinyint(1) DEFAULT 0,
  `category` varchar(50) DEFAULT 'other' COMMENT 'Category for marketplace',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Search tags' CHECK (json_valid(`tags`)),
  `download_count` int(11) DEFAULT 0 COMMENT 'Times downloaded',
  `rating_avg` decimal(3,2) DEFAULT 0.00 COMMENT 'Average rating (0-5)',
  `rating_count` int(11) DEFAULT 0 COMMENT 'Number of ratings',
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'Available in marketplace',
  `is_featured` tinyint(1) DEFAULT 0 COMMENT 'Featured in marketplace',
  `requires_approval` tinyint(1) DEFAULT 0 COMMENT 'Needs admin approval workflow',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hub_version_min` varchar(20) DEFAULT NULL COMMENT 'Minimum Hub version required',
  `hub_version_max` varchar(20) DEFAULT NULL COMMENT 'Maximum Hub version (if known)',
  `php_version_min` varchar(20) DEFAULT NULL COMMENT 'Minimum PHP version',
  `mysql_version_min` varchar(20) DEFAULT NULL COMMENT 'Minimum MySQL version',
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Required packages/modules' CHECK (json_valid(`dependencies`)),
  `conflicts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Conflicting packages' CHECK (json_valid(`conflicts`)),
  `tested_up_to` varchar(20) DEFAULT NULL COMMENT 'Tested up to Hub version',
  `is_deprecated` tinyint(1) DEFAULT 0 COMMENT 'Package deprecated',
  `deprecation_reason` text DEFAULT NULL COMMENT 'Why deprecated',
  `changelog` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Version history' CHECK (json_valid(`changelog`)),
  `screenshots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Screenshot URLs' CHECK (json_valid(`screenshots`)),
  `repository_url` varchar(255) DEFAULT NULL COMMENT 'Source repository',
  `demo_url` varchar(255) DEFAULT NULL COMMENT 'Live demo',
  `support_url` varchar(255) DEFAULT NULL COMMENT 'Support/docs URL',
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_id` (`package_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_category` (`category`),
  KEY `idx_public` (`is_public`,`is_featured`),
  KEY `idx_search` (`name`,`display_name`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store section packages for import/export and marketplace';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_record_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL COMMENT 'Parent record',
  `field_name` varchar(100) DEFAULT NULL COMMENT 'Field this file belongs to',
  `original_filename` varchar(255) NOT NULL COMMENT 'User uploaded filename',
  `stored_filename` varchar(255) NOT NULL COMMENT 'Server storage filename',
  `file_path` varchar(500) NOT NULL COMMENT 'Storage path',
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'File type',
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'User who uploaded',
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_record` (`record_id`),
  KEY `idx_field` (`field_name`),
  CONSTRAINT `section_record_attachments_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `section_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_record_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='File attachments for section records';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_record_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL COMMENT 'Reference to section_records',
  `action` enum('create','update','delete','approve','reject','restore','export') NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Data before change' CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Data after change' CHECK (json_valid(`new_data`)),
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of fields that changed' CHECK (json_valid(`changed_fields`)),
  `change_summary` text DEFAULT NULL COMMENT 'Human-readable summary',
  `changed_by` int(11) DEFAULT NULL COMMENT 'User who made change',
  `changed_at` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of user',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/device info',
  PRIMARY KEY (`id`),
  KEY `idx_record` (`record_id`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_action` (`action`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_action_date` (`action`,`changed_at` DESC),
  CONSTRAINT `section_record_history_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `section_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_record_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all record changes';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Parent section',
  `record_uuid` varchar(36) NOT NULL COMMENT 'UUID for referencing',
  `record_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'All field values as JSON' CHECK (json_valid(`record_data`)),
  `status` enum('draft','pending','approved','rejected','archived') DEFAULT 'approved' COMMENT 'Workflow status',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Soft delete flag',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User who last updated',
  `approved_by` int(11) DEFAULT NULL COMMENT 'User who approved (if workflow)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'When approved',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When soft deleted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_uuid` (`record_uuid`),
  KEY `updated_by` (`updated_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_section_status` (`section_id`,`status`,`is_deleted`),
  KEY `idx_uuid` (`record_uuid`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_created` (`status`,`created_at` DESC),
  FULLTEXT KEY `idx_record_search` (`record_data`),
  CONSTRAINT `section_records_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `section_records_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `section_records_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store dynamic section data as JSON';
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `section_records_view` AS SELECT
 1 AS `id`,
  1 AS `section_id`,
  1 AS `section_name`,
  1 AS `section_slug`,
  1 AS `record_uuid`,
  1 AS `record_data`,
  1 AS `status`,
  1 AS `created_by`,
  1 AS `creator_name`,
  1 AS `creator_email`,
  1 AS `updated_by`,
  1 AS `updater_name`,
  1 AS `approved_by`,
  1 AS `approver_name`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `approved_at`,
  1 AS `is_deleted` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_review_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_add_notes` tinyint(1) DEFAULT 1,
  `can_change_status` tinyint(1) DEFAULT 1,
  `can_assign` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_role_review` (`section_id`,`role_name`),
  KEY `idx_section_review` (`section_id`,`role_name`),
  CONSTRAINT `section_review_permissions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_role_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Which section',
  `role` enum('staff','student','maintenance_staff','custodial','cafeteria','custodial_manager','maintenance_director','business_manager','substitute_manager','counselor','principal','admin','super_admin') NOT NULL,
  `granted_by` int(11) DEFAULT NULL COMMENT 'Super admin who granted access',
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_role` (`section_id`,`role`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_section` (`section_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `section_role_access_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_role_access_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_submission_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `can_submit` tinyint(1) DEFAULT 1,
  `allow_anonymous` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section_role` (`section_id`,`role_name`),
  KEY `idx_section_submit` (`section_id`,`can_submit`),
  CONSTRAINT `section_submission_permissions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_workflow_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL COMMENT 'Workflow instance',
  `step_number` int(11) NOT NULL COMMENT 'Step in workflow',
  `action` enum('approve','reject','delegate','comment') NOT NULL,
  `actor_id` int(11) DEFAULT NULL COMMENT 'User who took action',
  `comments` text DEFAULT NULL COMMENT 'Optional comments',
  `acted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_instance` (`instance_id`),
  KEY `idx_actor` (`actor_id`),
  CONSTRAINT `section_workflow_actions_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `section_workflow_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_workflow_actions_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log workflow actions and approvals';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_workflow_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL COMMENT 'Record in workflow',
  `workflow_id` int(11) NOT NULL COMMENT 'Workflow definition',
  `current_step` int(11) DEFAULT 1 COMMENT 'Current step number',
  `status` enum('pending','in_progress','approved','rejected','cancelled') DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_id` (`workflow_id`),
  KEY `idx_record` (`record_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `section_workflow_instances_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `section_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_workflow_instances_ibfk_2` FOREIGN KEY (`workflow_id`) REFERENCES `section_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track workflow instances for records';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_workflows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL COMMENT 'Section using this workflow',
  `workflow_name` varchar(100) NOT NULL COMMENT 'Workflow identifier',
  `steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Workflow steps configuration' CHECK (json_valid(`steps`)),
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Currently in use',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `section_workflows_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_workflows_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval workflow definitions';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `section_type` enum('recording','request_form','scheduled_report','hub','other') NOT NULL DEFAULT 'recording' COMMENT 'Type of section functionality',
  `requires_approval` tinyint(1) DEFAULT 0 COMMENT 'Does this section require approval workflow?',
  `send_notifications` tinyint(1) DEFAULT 0 COMMENT 'Send email notifications for this section?',
  `notification_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON config for who gets notified when' CHECK (json_valid(`notification_config`)),
  `icon` varchar(100) DEFAULT NULL,
  `base_url` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `order_position` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_dynamic` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `fk_section_category` (`category_id`),
  CONSTRAINT `fk_section_category` FOREIGN KEY (`category_id`) REFERENCES `section_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2595 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `site_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1384 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requirement_key` varchar(100) NOT NULL COMMENT 'hub_version, php_version, etc.',
  `requirement_value` varchar(100) NOT NULL COMMENT 'Current system value',
  `checked_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `requirement_key` (`requirement_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache system requirements for compatibility checks';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings`)),
  `is_active` tinyint(1) DEFAULT 0,
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'System themes cannot be deleted',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `themes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trip_purposes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `description` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_dismissed_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `alert_key` varchar(100) NOT NULL,
  `dismissed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_alert` (`user_id`,`alert_type`,`alert_key`),
  KEY `idx_user_alert_type` (`user_id`,`alert_type`),
  CONSTRAINT `user_dismissed_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_global_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` enum('staff','student','maintenance_staff','custodial','cafeteria','custodial_manager','maintenance_director','business_manager','substitute_manager','counselor','principal','admin','super_admin') NOT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_role` (`role`),
  KEY `granted_by` (`granted_by`),
  CONSTRAINT `user_global_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_global_roles_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_module_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `role` enum('staff','manager','admin','super_admin') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_module` (`user_id`,`module_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module_id` (`module_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `granted_by` (`granted_by`),
  CONSTRAINT `user_module_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_module_access_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_module_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=382 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `user_module_access_view` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `user_name`,
  1 AS `user_email`,
  1 AS `module_id`,
  1 AS `module_name`,
  1 AS `module_display_name`,
  1 AS `module_icon`,
  1 AS `module_slug`,
  1 AS `module_base_url`,
  1 AS `module_role`,
  1 AS `is_active`,
  1 AS `granted_at`,
  1 AS `granted_by_name` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alt_email` varchar(255) DEFAULT NULL,
  `preferred_contact_method` enum('email','alt_email','sms','email_only','sms_only') DEFAULT 'email',
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `picture` varchar(500) DEFAULT NULL,
  `role` enum('staff','student','maintenance_staff','custodial','cafeteria','custodial_manager','maintenance_director','business_manager','substitute_manager','counselor','principal','admin','super_admin') NOT NULL DEFAULT 'staff',
  `user_group` enum('staff','student','maintenance','custodial','cafeteria','other') DEFAULT 'staff' COMMENT 'Organizational group for determining hub display',
  `invited_by` int(11) DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  UNIQUE KEY `google_id_2` (`google_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_google_id` (`google_id`),
  KEY `idx_role` (`role`),
  KEY `invited_by` (`invited_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_username` (`username`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26864 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `make` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `vin` varchar(17) DEFAULT NULL,
  `license_plate` varchar(20) DEFAULT NULL,
  `fuel_type` enum('gasoline','diesel','propane','electric','other') DEFAULT NULL,
  `initial_mileage` int(11) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `tank_capacity` decimal(6,2) DEFAULT NULL COMMENT 'Tank capacity in gallons',
  `inspection_due_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_number` (`vehicle_number`),
  KEY `idx_vehicle_number` (`vehicle_number`),
  KEY `idx_is_active` (`is_active`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `fuel_records_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `fuel_records_view` AS select `fr`.`id` AS `id`,`fr`.`entry_date` AS `entry_date`,`v`.`name` AS `vehicle_name`,`v`.`vehicle_number` AS `vehicle_number`,`u`.`name` AS `user_name`,`u`.`email` AS `user_email`,`tp`.`code` AS `purpose_code`,`tp`.`description` AS `purpose_description`,`fr`.`mileage` AS `mileage`,`fr`.`fuel_amount` AS `fuel_amount`,`fr`.`notes` AS `notes`,`fr`.`created_at` AS `created_at`,`fr`.`updated_at` AS `updated_at`,`u2`.`name` AS `updated_by_name` from ((((`fuel_records` `fr` join `vehicles` `v` on(`fr`.`vehicle_id` = `v`.`id`)) join `users` `u` on(`fr`.`user_id` = `u`.`id`)) join `trip_purposes` `tp` on(`fr`.`trip_purpose_id` = `tp`.`id`)) left join `users` `u2` on(`fr`.`updated_by` = `u2`.`id`)) order by `fr`.`entry_date` desc,`fr`.`created_at` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `section_access_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `section_access_view` AS select `sa`.`id` AS `id`,`sa`.`user_id` AS `user_id`,`u`.`email` AS `user_email`,`u`.`name` AS `user_name`,`sa`.`section_id` AS `section_id`,`s`.`name` AS `section_name`,`s`.`slug` AS `section_slug`,`s`.`display_name` AS `section_display_name`,`s`.`icon` AS `section_icon`,`s`.`base_url` AS `section_base_url`,`s`.`is_active` AS `section_is_active`,`sa`.`role` AS `role`,`sa`.`granted_by` AS `granted_by`,`gb`.`name` AS `granted_by_name`,`sa`.`granted_at` AS `granted_at`,`sa`.`updated_at` AS `updated_at` from (((`section_access` `sa` join `users` `u` on(`sa`.`user_id` = `u`.`id`)) join `sections` `s` on(`sa`.`section_id` = `s`.`id`)) left join `users` `gb` on(`sa`.`granted_by` = `gb`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `section_admin_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `section_admin_view` AS select `sa`.`id` AS `id`,`sa`.`section_id` AS `section_id`,`s`.`display_name` AS `section_name`,`s`.`slug` AS `section_slug`,`sa`.`user_id` AS `user_id`,`u`.`name` AS `user_name`,`u`.`email` AS `user_email`,`sa`.`permission_level` AS `permission_level`,`sa`.`can_view_all` AS `can_view_all`,`sa`.`can_edit_records` AS `can_edit_records`,`sa`.`can_delete_records` AS `can_delete_records`,`sa`.`can_export` AS `can_export`,`sa`.`can_manage_users` AS `can_manage_users`,`sa`.`granted_by` AS `granted_by`,`gb`.`name` AS `granted_by_name`,`sa`.`granted_at` AS `granted_at`,`sa`.`is_active` AS `is_active` from (((`section_administrators` `sa` join `sections` `s` on(`sa`.`section_id` = `s`.`id`)) join `users` `u` on(`sa`.`user_id` = `u`.`id`)) left join `users` `gb` on(`sa`.`granted_by` = `gb`.`id`)) where `sa`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `section_package_compatibility_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `section_package_compatibility_summary` AS select `pi`.`id` AS `install_id`,`pi`.`package_id` AS `package_id`,`pi`.`package_version` AS `package_version`,`pi`.`status` AS `install_status`,count(0) AS `total_checks`,sum(case when `cc`.`status` = 'fail' then 1 else 0 end) AS `failures`,sum(case when `cc`.`status` = 'warning' then 1 else 0 end) AS `warnings`,sum(case when `cc`.`severity` = 'critical' then 1 else 0 end) AS `critical_issues`,`pi`.`attempted_at` AS `attempted_at` from (`section_package_installs` `pi` left join `section_compatibility_checks` `cc` on(`pi`.`id` = `cc`.`install_id`)) group by `pi`.`id`,`pi`.`package_id`,`pi`.`package_version`,`pi`.`status`,`pi`.`attempted_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `section_package_install_history`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `section_package_install_history` AS select `pi`.`id` AS `id`,`pi`.`package_id` AS `package_id`,`pi`.`package_version` AS `package_version`,`pi`.`status` AS `status`,`pi`.`installation_type` AS `installation_type`,`pi`.`attempted_by` AS `attempted_by`,`u`.`name` AS `installer_name`,`pi`.`attempted_at` AS `attempted_at`,`pi`.`completed_at` AS `completed_at`,`pi`.`section_id` AS `section_id`,`s`.`display_name` AS `section_name`,`pi`.`error_message` AS `error_message`,timestampdiff(SECOND,`pi`.`attempted_at`,`pi`.`completed_at`) AS `duration_seconds` from ((`section_package_installs` `pi` left join `users` `u` on(`pi`.`attempted_by` = `u`.`id`)) left join `sections` `s` on(`pi`.`section_id` = `s`.`id`)) order by `pi`.`attempted_at` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `section_records_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `section_records_view` AS select `sr`.`id` AS `id`,`sr`.`section_id` AS `section_id`,`s`.`display_name` AS `section_name`,`s`.`slug` AS `section_slug`,`sr`.`record_uuid` AS `record_uuid`,`sr`.`record_data` AS `record_data`,`sr`.`status` AS `status`,`sr`.`created_by` AS `created_by`,`cu`.`name` AS `creator_name`,`cu`.`email` AS `creator_email`,`sr`.`updated_by` AS `updated_by`,`uu`.`name` AS `updater_name`,`sr`.`approved_by` AS `approved_by`,`au`.`name` AS `approver_name`,`sr`.`created_at` AS `created_at`,`sr`.`updated_at` AS `updated_at`,`sr`.`approved_at` AS `approved_at`,`sr`.`is_deleted` AS `is_deleted` from ((((`section_records` `sr` join `sections` `s` on(`sr`.`section_id` = `s`.`id`)) left join `users` `cu` on(`sr`.`created_by` = `cu`.`id`)) left join `users` `uu` on(`sr`.`updated_by` = `uu`.`id`)) left join `users` `au` on(`sr`.`approved_by` = `au`.`id`)) where `sr`.`is_deleted` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `user_module_access_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`WISDAdmin`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `user_module_access_view` AS select `uma`.`id` AS `id`,`uma`.`user_id` AS `user_id`,`u`.`name` AS `user_name`,`u`.`email` AS `user_email`,`uma`.`module_id` AS `module_id`,`m`.`name` AS `module_name`,`m`.`display_name` AS `module_display_name`,`m`.`icon` AS `module_icon`,`m`.`slug` AS `module_slug`,`m`.`base_url` AS `module_base_url`,`uma`.`role` AS `module_role`,`uma`.`is_active` AS `is_active`,`uma`.`granted_at` AS `granted_at`,`grantor`.`name` AS `granted_by_name` from (((`user_module_access` `uma` join `users` `u` on(`uma`.`user_id` = `u`.`id`)) join `modules` `m` on(`uma`.`module_id` = `m`.`id`)) left join `users` `grantor` on(`uma`.`granted_by` = `grantor`.`id`)) where `uma`.`is_active` = 1 and `m`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


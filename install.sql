-- ============================================================
-- PeopleDisplay v2.0 — Database Schema
-- ============================================================
-- Encoding:    UTF-8 (utf8mb4)
-- Engine:      InnoDB
-- Compatible:  MySQL 5.7+ / MariaDB 10.3+
--
-- Run this script ONCE during installation.
-- All statements use IF NOT EXISTS — safe to re-run.
--
-- Tables: 28
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABLE: config
-- System-wide settings stored as a single config row (id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `config` (
  `id`                                  int(11)                                               NOT NULL DEFAULT 1,
  `scriptURL`                           text                                                  DEFAULT NULL,
  `sheetID`                             text                                                  DEFAULT NULL,
  `presentationID`                      text                                                  DEFAULT NULL,
  `visibleFields`                       text                                                  DEFAULT '[]',
  `locations`                           text                                                  DEFAULT '[]',
  `extraButtons`                        text                                                  DEFAULT '[]',
  `locations_order`                     text                                                  DEFAULT NULL,
  `allow_auto_fullscreen`               tinyint(1)                                            DEFAULT 0,
  `presentationAutoShowMs`              int(11)                                               DEFAULT 120000,
  `button1_name`                        varchar(50)                                           DEFAULT 'PAUZE',
  `button1_ask_until`                   tinyint(1)                                            DEFAULT 0,
  `button2_name`                        varchar(50)                                           DEFAULT 'THUISWERKEN',
  `button2_ask_until`                   tinyint(1)                                            DEFAULT 0,
  `button3_name`                        varchar(50)                                           DEFAULT 'VAKANTIE',
  `button3_ask_until`                   tinyint(1)                                            DEFAULT 0,
  `allow_user_button_names`             tinyint(1)                                            DEFAULT 0,
  `name_display_option`                 enum('volledig','voornaam','achternaam','initiaal_achternaam') DEFAULT 'volledig',
  `visitor_notification_fallback_email` varchar(255)                                          DEFAULT NULL,
  `license_key`                         varchar(100)                                          DEFAULT NULL,
  `license_tier`                        varchar(20)                                           DEFAULT NULL,
  `license_domain`                      varchar(255)                                          DEFAULT NULL,
  `license_activated_at`                datetime                                              DEFAULT NULL,
  `license_expires_at`                  datetime                                              DEFAULT NULL,
  `license_status`                      enum('active','expired','revoked','invalid','pending') DEFAULT 'pending',
  `license_notes`                       text                                                  DEFAULT NULL,
  `eula_accepted`                       tinyint(1)                                            DEFAULT 0,
  `eula_accepted_at`                    datetime                                              DEFAULT NULL,
  `eula_version`                        varchar(10)                                           DEFAULT '1.0',
  `current_version`                     varchar(20)                                           DEFAULT '2.0.0',
  `last_update_check`                   datetime                                              DEFAULT NULL,
  `update_dismissed_version`            varchar(20)                                           DEFAULT NULL,
  `updated_at`                          timestamp                                             NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config` (`id`, `visibleFields`, `locations`, `extraButtons`, `button1_name`, `button2_name`, `button3_name`)
VALUES (1, '["Naam","Functie","Afdeling"]', '["Kantoor"]', '[]', 'PAUZE', 'THUISWERKEN', 'VAKANTIE')
ON DUPLICATE KEY UPDATE `id` = 1;

-- ============================================================
-- TABLE: user_groups
-- Optional user group assignments for admins
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_groups` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `name`          varchar(100) NOT NULL,
  `description`   varchar(255) DEFAULT NULL,
  `created_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: users
-- Admin and staff login accounts
-- IMPORTANT: column is 'password_hash' (NOT 'password')
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                       int(11)                           NOT NULL AUTO_INCREMENT,
  `username`                 varchar(100)                      NOT NULL,
  `password_hash`            varchar(255)                      NOT NULL,
  `display_name`             varchar(150)                      DEFAULT NULL,
  `email`                    varchar(255)                      DEFAULT NULL,
  `profile_photo`            varchar(255)                      DEFAULT NULL,
  `role`                     enum('user','admin','superadmin') NOT NULL DEFAULT 'user',
  `group_id`                 int(11)                           DEFAULT NULL,
  `features`                 longtext                          DEFAULT NULL,
  `active`                   tinyint(1)                        NOT NULL DEFAULT 1,
  `can_show_presentation`    tinyint(1)                        NOT NULL DEFAULT 0,
  `can_use_scanner`          tinyint(1)                        DEFAULT 0,
  `presentation_id`          varchar(255)                      DEFAULT NULL,
  `presentation_idle_seconds` int(11)                          DEFAULT 120,
  `created_at`               timestamp                         NOT NULL DEFAULT current_timestamp(),
  `updated_at`               datetime                          DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at`               datetime                          DEFAULT NULL,
  `last_login`               datetime                          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username`   (`username`),
  KEY `idx_group_id`         (`group_id`),
  KEY `idx_deleted_at`       (`deleted_at`),
  KEY `idx_active`           (`active`),
  CONSTRAINT `fk_users_group_id` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: employees
-- Core table: employee presence records
-- ============================================================
CREATE TABLE IF NOT EXISTS `employees` (
  `id`                          int(11)                      NOT NULL AUTO_INCREMENT,
  `employee_id`                 varchar(100)                 DEFAULT NULL,
  `naam`                        varchar(200)                 NOT NULL,
  `voornaam`                    varchar(100)                 DEFAULT NULL,
  `achternaam`                  varchar(100)                 DEFAULT NULL,
  `status`                      enum('IN','OUT','OVERLEG')   NOT NULL DEFAULT 'OUT',
  `sub_status`                  varchar(50)                  DEFAULT NULL,
  `sub_status_type`             varchar(50)                  DEFAULT NULL,
  `sub_status_until`            datetime                     DEFAULT NULL,
  `locatie`                     varchar(100)                 DEFAULT NULL,
  `visible_locations`           text                         DEFAULT NULL,
  `allow_manual_location_change` tinyint(1)                 DEFAULT 0,
  `foto_url`                    varchar(500)                 DEFAULT NULL,
  `functie`                     varchar(150)                 DEFAULT NULL,
  `afdeling`                    varchar(150)                 DEFAULT NULL,
  `bhv`                         enum('Ja','Nee')             NOT NULL DEFAULT 'Nee',
  `email`                       varchar(255)                 DEFAULT NULL,
  `telefoon`                    varchar(50)                  DEFAULT NULL,
  `notities`                    text                         DEFAULT NULL,
  `tijdstip`                    datetime                     DEFAULT current_timestamp(),
  `actief`                      tinyint(1)                   DEFAULT 1,
  `created_at`                  timestamp                    NOT NULL DEFAULT current_timestamp(),
  `updated_at`                  timestamp                    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee_id`      (`employee_id`),
  KEY `idx_status`           (`status`),
  KEY `idx_actief`           (`actief`),
  KEY `idx_locatie`          (`locatie`),
  KEY `idx_sub_status_until` (`sub_status_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: afdelingen
-- Departments / teams list
-- ============================================================
CREATE TABLE IF NOT EXISTS `afdelingen` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `afdeling_name` varchar(150) NOT NULL,
  `afdeling_code` varchar(50)  DEFAULT NULL,
  `description`   varchar(255) DEFAULT NULL,
  `active`        tinyint(1)   DEFAULT 1,
  `sort_order`    int(11)      DEFAULT 0,
  `created_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_active`     (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: locations
-- Physical locations / offices / branches
-- ============================================================
CREATE TABLE IF NOT EXISTS `locations` (
  `id`                   int(11)      NOT NULL AUTO_INCREMENT,
  `location_name`        varchar(150) NOT NULL,
  `location_code`        varchar(20)  DEFAULT NULL,
  `address`              varchar(255) DEFAULT NULL,
  `active`               tinyint(1)   DEFAULT 1,
  `sort_order`           int(11)      DEFAULT 0,
  `created_at`           timestamp    NOT NULL DEFAULT current_timestamp(),
  `ip_range`             varchar(255) DEFAULT NULL,
  `primary_ip`           varchar(45)  DEFAULT NULL,
  `backup_ip`            varchar(45)  DEFAULT NULL,
  `ip_range_start`       varchar(45)  DEFAULT NULL,
  `ip_range_end`         varchar(45)  DEFAULT NULL,
  `auto_checkin_enabled` tinyint(1)   DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_active`     (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: visitors
-- Visitor registrations and check-ins
-- ============================================================
CREATE TABLE IF NOT EXISTS `visitors` (
  `id`                      int(11)                                              NOT NULL AUTO_INCREMENT,
  `voornaam`                varchar(100)                                         NOT NULL,
  `achternaam`              varchar(100)                                         NOT NULL,
  `email`                   varchar(255)                                         NOT NULL,
  `telefoon`                varchar(50)                                          DEFAULT NULL,
  `bedrijf`                 varchar(255)                                         DEFAULT NULL,
  `contactpersoon`          varchar(150)                                         NOT NULL DEFAULT '',
  `locatie`                 varchar(100)                                         NOT NULL DEFAULT '',
  `bezoek_datum`            date                                                 NOT NULL,
  `tijd`                    time                                                 DEFAULT NULL,
  `status`                  enum('AANGEMELD','BINNEN','VERTROKKEN','GEANNULEERD') NOT NULL DEFAULT 'AANGEMELD',
  `checked_in_at`           datetime                                             DEFAULT NULL,
  `checked_out_at`          datetime                                             DEFAULT NULL,
  `is_multi_day`            tinyint(1)                                           DEFAULT 0,
  `start_date`              date                                                 DEFAULT NULL,
  `end_date`                date                                                 DEFAULT NULL,
  `privacy_accepted`        tinyint(1)                                           DEFAULT 0,
  `privacy_accepted_at`     datetime                                             DEFAULT NULL,
  `checkin_token`           varchar(64)                                          DEFAULT NULL,
  `checkout_token`          varchar(64)                                          DEFAULT NULL,
  `tokens_valid_until`      datetime                                             DEFAULT NULL,
  `registration_email_sent` tinyint(1)                                           DEFAULT 0,
  `checkin_email_sent`      tinyint(1)                                           DEFAULT 0,
  `checkout_email_sent`     tinyint(1)                                           DEFAULT 0,
  `notes`                   text                                                 DEFAULT NULL,
  `created_at`              timestamp                                            NOT NULL DEFAULT current_timestamp(),
  `updated_at`              timestamp                                            NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bezoek_datum`  (`bezoek_datum`),
  KEY `idx_status`        (`status`),
  KEY `idx_checkin_token` (`checkin_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: kiosk_tokens
-- Auto-login tokens for unattended kiosk devices
-- ============================================================
CREATE TABLE IF NOT EXISTS `kiosk_tokens` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)      NOT NULL,
  `token`       varchar(64)  NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `allowed_ip`  varchar(45)  DEFAULT NULL,
  `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  `last_used`   datetime     DEFAULT NULL,
  `expires_at`  datetime     DEFAULT NULL,
  `is_active`   tinyint(1)   NOT NULL DEFAULT 1,
  `created_by`  int(11)      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_sessions
-- Active login session tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id`            int(11)       NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)       NOT NULL,
  `session_id`    varchar(255)  NOT NULL,
  `ip_address`    varchar(45)   DEFAULT NULL,
  `user_agent`    text          DEFAULT NULL,
  `browser`       varchar(100)  DEFAULT NULL,
  `device`        varchar(100)  DEFAULT NULL,
  `last_activity` datetime      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `login_time`    datetime      NOT NULL DEFAULT current_timestamp(),
  `is_active`     tinyint(1)    NOT NULL DEFAULT 1,
  `page_url`      varchar(500)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_id`  (`session_id`),
  KEY `idx_user_id`           (`user_id`),
  KEY `idx_last_activity`     (`last_activity`),
  KEY `idx_is_active`         (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: remember_tokens
-- "Remember me" persistent login tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NOT NULL,
  `token`      varchar(255) NOT NULL,
  `selector`   varchar(32)  DEFAULT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_selector` (`selector`),
  KEY `idx_token`   (`token`(64)),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: employee_audit
-- Audit trail for employee record changes
-- NOTE: column is 'created_at' (NOT 'changed_at') — matches PHP
-- ============================================================
CREATE TABLE IF NOT EXISTS `employee_audit` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `employee_id`  varchar(100) DEFAULT NULL,
  `action`       varchar(100) NOT NULL,
  `field_changed` varchar(50) DEFAULT NULL,
  `old_value`    text         DEFAULT NULL,
  `new_value`    text         DEFAULT NULL,
  `changed_by`   int(11)      DEFAULT NULL,
  `ip_address`   varchar(45)  DEFAULT NULL,
  `user_agent`   text         DEFAULT NULL,
  `created_at`   datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: feature_keys
-- Available feature flag definitions
-- NOTE: column is 'key_name' (NOT 'feature_key') — matches PHP
-- ============================================================
CREATE TABLE IF NOT EXISTS `feature_keys` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `key_name`        varchar(100) NOT NULL,
  `feature_name`    varchar(100) DEFAULT NULL,
  `category`        varchar(50)  DEFAULT NULL,
  `default_enabled` tinyint(1)   DEFAULT 0,
  `description`     text         DEFAULT NULL,
  `created_at`      datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `feature_keys` (`key_name`, `feature_name`, `category`, `description`) VALUES
  ('bhv_print',          'BHV afdrukken',       'rapportage',   'BHV (emergency) overzicht afdrukken'),
  ('visitor_management', 'Bezoekersbeheer',      'bezoekers',    'Bezoekersbeheer — bezoekers registreren en beheren'),
  ('sub_status',         'Sub-status',           'aanwezigheid', 'Sub-status instellen (bijv. PAUZE, THUISWERKEN)'),
  ('location_override',  'Locatie wijzigen',     'aanwezigheid', 'Locatie handmatig wijzigen')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;

-- ============================================================
-- TABLE: user_features
-- Per-user feature visibility/access control
-- NOTE: uses feature_key_id (int FK) + visible — matches PHP
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_features` (
  `id`             int(11)    NOT NULL AUTO_INCREMENT,
  `user_id`        int(11)    NOT NULL,
  `feature_key_id` int(11)    NOT NULL,
  `visible`        tinyint(1) DEFAULT 1,
  `created_at`     timestamp  NOT NULL DEFAULT current_timestamp(),
  `updated_at`     timestamp  NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_feature` (`user_id`, `feature_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_afdelingen
-- Which departments a user has access to (filter scope)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_afdelingen` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)   NOT NULL,
  `afdeling_id` int(11)   NOT NULL,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_afdeling` (`user_id`, `afdeling_id`),
  KEY `idx_user_id`     (`user_id`),
  KEY `idx_afdeling_id` (`afdeling_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_button_names
-- Per-user custom labels for the 3 status buttons
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_button_names` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)     NOT NULL,
  `button1_name` varchar(20) DEFAULT NULL,
  `button2_name` varchar(20) DEFAULT NULL,
  `button3_name` varchar(20) DEFAULT NULL,
  `created_at`   timestamp   NOT NULL DEFAULT current_timestamp(),
  `updated_at`   timestamp   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: email_log
-- Outgoing email tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `to_email`   varchar(255) NOT NULL,
  `subject`    varchar(500) DEFAULT NULL,
  `status`     varchar(50)  DEFAULT 'sent',
  `created_at` datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: admins
-- Legacy v1 admin table (kept for backward compatibility)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `username`      varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: button_config
-- Global button appearance/naming configuration
-- ============================================================
CREATE TABLE IF NOT EXISTS `button_config` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `button_key` varchar(20) NOT NULL,
  `name`       varchar(50) NOT NULL,
  `color`      varchar(20) DEFAULT NULL,
  `enabled`    tinyint(1)  DEFAULT 1,
  `updated_at` timestamp   NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_button_key` (`button_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: feature_audit
-- Audit log for feature flag changes per user
-- ============================================================
CREATE TABLE IF NOT EXISTS `feature_audit` (
  `id`          int(11)                                     NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)                                     NOT NULL,
  `feature_key` varchar(50)                                 NOT NULL,
  `action`      enum('ENABLED','DISABLED','CREATED','DELETED') NOT NULL,
  `changed_by`  int(11)                                     DEFAULT NULL,
  `changed_at`  timestamp                                   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id`     (`user_id`),
  KEY `idx_feature_key` (`feature_key`),
  KEY `idx_changed_at`  (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: filters
-- Configurable filter definitions
-- ============================================================
CREATE TABLE IF NOT EXISTS `filters` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `filter_name` varchar(50) NOT NULL,
  `filter_type` varchar(50) DEFAULT NULL,
  `options`     text        DEFAULT NULL,
  `enabled`     tinyint(1)  DEFAULT 1,
  `created_at`  timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_filter_name` (`filter_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: labee_config
-- Key/value configuration store
-- ============================================================
CREATE TABLE IF NOT EXISTS `labee_config` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `key_name`   varchar(100) NOT NULL,
  `value`      text         DEFAULT NULL,
  `updated_at` timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notification_settings
-- Email/notification configuration (key/value)
-- ============================================================
CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         DEFAULT NULL,
  `description`   varchar(255) DEFAULT NULL,
  `updated_at`    timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notification_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('email_enabled',      '0',                         'Email notificaties aan/uit'),
  ('smtp_host',          '',                          'SMTP server'),
  ('smtp_port',          '587',                       'SMTP poort'),
  ('smtp_username',      '',                          'SMTP gebruikersnaam'),
  ('smtp_password',      '',                          'SMTP wachtwoord'),
  ('smtp_from_email',    '',                          'Afzender email'),
  ('smtp_from_name',     'PeopleDisplay Bezoekers',   'Afzender naam'),
  ('notify_on_online',   '1',                         'Notificeer bij online registratie'),
  ('notify_on_checkin',  '1',                         'Notificeer bij check-in')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ============================================================
-- TABLE: roles
-- Role definitions (for future RBAC system)
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `role_name`   varchar(50) NOT NULL,
  `description` text        DEFAULT NULL,
  `created_at`  timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: roles_permissions
-- Permission definitions per role
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles_permissions` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `role_id`    int(11)      NOT NULL,
  `permission` varchar(100) NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role_id`, `permission`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: users_roles
-- Many-to-many: user ↔ role assignments
-- ============================================================
CREATE TABLE IF NOT EXISTS `users_roles` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)   NOT NULL,
  `role_id`    int(11)   NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role` (`user_id`, `role_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_filters
-- Per-user saved filter settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_filters` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)     NOT NULL,
  `filter_name`  varchar(50) NOT NULL,
  `filter_value` text        DEFAULT NULL,
  `created_at`   timestamp   NOT NULL DEFAULT current_timestamp(),
  `updated_at`   timestamp   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_filter` (`user_id`, `filter_name`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_locations
-- Per-user location access assignments
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_locations` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)   NOT NULL,
  `location_id` int(11)   NOT NULL,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_location` (`user_id`, `location_id`),
  KEY `idx_user_id`     (`user_id`),
  KEY `idx_location_id` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_menu
-- Per-user custom menu item configuration
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_menu` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)   NOT NULL,
  `menu_items` text      DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: visitors_backup
-- Legacy v1 visitors table (preserved for migration/reference)
-- ============================================================
CREATE TABLE IF NOT EXISTS `visitors_backup` (
  `id`                      int(11)                                          NOT NULL DEFAULT 0,
  `voornaam`                varchar(100)                                     DEFAULT NULL,
  `achternaam`              varchar(100)                                     DEFAULT NULL,
  `visitor_id`              varchar(50)                                      NOT NULL,
  `naam`                    varchar(150)                                     NOT NULL,
  `functie`                 varchar(100)                                     DEFAULT NULL,
  `bedrijf`                 varchar(150)                                     DEFAULT NULL,
  `email`                   varchar(255)                                     DEFAULT NULL,
  `telefoon`                varchar(50)                                      DEFAULT NULL,
  `bezoek_datum`            date                                             NOT NULL,
  `start_date`              date                                             DEFAULT NULL,
  `end_date`                date                                             DEFAULT NULL,
  `is_multi_day`            tinyint(1)                                       DEFAULT 0,
  `privacy_accepted`        tinyint(1)                                       DEFAULT 0,
  `privacy_accepted_at`     datetime                                         DEFAULT NULL,
  `checkin_token`           varchar(64)                                      DEFAULT NULL,
  `checkout_token`          varchar(64)                                      DEFAULT NULL,
  `tokens_valid_until`      datetime                                         DEFAULT NULL,
  `registration_email_sent` tinyint(1)                                       DEFAULT 0,
  `checkin_email_sent`      tinyint(1)                                       DEFAULT 0,
  `checkout_email_sent`     tinyint(1)                                       DEFAULT 0,
  `bezoek_tijd`             time                                             DEFAULT NULL,
  `reden_bezoek`            text                                             DEFAULT NULL,
  `locatie`                 varchar(100)                                     NOT NULL,
  `contactpersoon_id`       int(10)                                          DEFAULT NULL,
  `contactpersoon_naam`     varchar(150)                                     DEFAULT NULL,
  `status`                  enum('AANGEMELD','BINNEN','VERTROKKEN','GEANNULEERD') DEFAULT 'AANGEMELD',
  `registratie_type`        enum('ONLINE','LOCATIE')                         NOT NULL,
  `checked_in_at`           datetime                                         DEFAULT NULL,
  `checked_out_at`          datetime                                         DEFAULT NULL,
  `notification_sent`       tinyint(1)                                       DEFAULT 0,
  `notification_sent_at`    datetime                                         DEFAULT NULL,
  `notities`                text                                             DEFAULT NULL,
  `created_by`              int(10)                                          DEFAULT NULL,
  `created_at`              timestamp                                        NOT NULL DEFAULT current_timestamp(),
  `updated_at`              timestamp                                        NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `idx_visitor_id`  (`visitor_id`),
  KEY `idx_bezoek_datum` (`bezoek_datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: license_tiers
-- License package definitions with limits and feature flags
-- ============================================================
CREATE TABLE IF NOT EXISTS `license_tiers` (
  `id`              int(11)        NOT NULL AUTO_INCREMENT,
  `tier_code`       varchar(20)    NOT NULL,
  `tier_name`       varchar(50)    NOT NULL,
  `tier_description` text          DEFAULT NULL,
  `max_users`       int(11)        NOT NULL,
  `max_employees`   int(11)        NOT NULL,
  `max_locations`   int(11)        NOT NULL,
  `max_departments` int(11)        NOT NULL,
  `features`        text           DEFAULT NULL,
  `price_eur`       decimal(10,2)  DEFAULT NULL,
  `sort_order`      int(11)        DEFAULT 0,
  `active`          tinyint(1)     DEFAULT 1,
  `created_at`      datetime       DEFAULT current_timestamp(),
  `updated_at`      datetime       DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tier_code` (`tier_code`),
  KEY `idx_active`     (`active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `license_tiers`
  (`tier_code`, `tier_name`, `tier_description`, `max_users`, `max_employees`, `max_locations`, `max_departments`, `features`, `price_eur`, `sort_order`, `active`)
VALUES
  ('starter',      'Starter',      'Ideaal voor kleine organisaties met één locatie',          3,  10, 1, 3,  '{"visitor_management":false,"bhv_print":true,"sub_status":true,"location_override":false,"kiosk_mode":false,"api_access":false}', 25.00,  1, 1),
  ('professional', 'Professional', 'Voor organisaties met meerdere locaties',                  5,  25, 3, 6,  '{"visitor_management":true,"bhv_print":true,"sub_status":true,"location_override":true,"kiosk_mode":false,"api_access":false}', 75.00,  2, 1),
  ('business',     'Business',     'Volledige functionaliteit voor grote organisaties',        10, 60, 6, 10, '{"visitor_management":true,"bhv_print":true,"sub_status":true,"location_override":true,"kiosk_mode":true,"api_access":true}',  125.00, 3, 1)
ON DUPLICATE KEY UPDATE `tier_code` = `tier_code`;

-- ============================================================
-- TABLE: license_log
-- Audit trail for all license-related actions
-- ============================================================
CREATE TABLE IF NOT EXISTS `license_log` (
  `id`          int(11)                                                       NOT NULL AUTO_INCREMENT,
  `license_key` varchar(100)                                                  DEFAULT NULL,
  `action`      enum('activated','deactivated','validated','failed','upgraded','expired') NOT NULL,
  `domain`      varchar(255)                                                  DEFAULT NULL,
  `ip_address`  varchar(45)                                                   DEFAULT NULL,
  `user_agent`  text                                                          DEFAULT NULL,
  `details`     text                                                          DEFAULT NULL,
  `created_at`  datetime                                                      DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_license_key` (`license_key`),
  KEY `idx_action`      (`action`),
  KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- Tables created: 30
-- Run install.php after this to create your admin account
-- ============================================================

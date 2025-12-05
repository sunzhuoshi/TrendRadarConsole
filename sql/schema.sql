-- TrendRadarConsole Database Schema
-- Compatible with MySQL 5.6

-- Users table for authentication
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Login username',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    `email` VARCHAR(100) COMMENT 'User email (optional)',
    `github_owner` VARCHAR(100) COMMENT 'GitHub repository owner',
    `github_repo` VARCHAR(100) COMMENT 'GitHub repository name',
    `github_token` VARCHAR(255) COMMENT 'GitHub PAT (encrypted)',
    `docker_container_name` VARCHAR(100) COMMENT 'Docker container name for local deployment',
    `docker_config_path` VARCHAR(255) COMMENT 'Path to config directory for Docker',
    `docker_output_path` VARCHAR(255) COMMENT 'Path to output directory for Docker',
    `docker_image` VARCHAR(255) DEFAULT 'wantcat/trendradar:latest' COMMENT 'Docker image to use',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User accounts';

-- Configuration table for storing TrendRadar configurations
CREATE TABLE IF NOT EXISTS `configurations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Reference to user',
    `name` VARCHAR(100) NOT NULL COMMENT 'Configuration name',
    `description` TEXT COMMENT 'Configuration description',
    `config_data` TEXT NOT NULL COMMENT 'JSON formatted configuration data',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether this configuration is active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    UNIQUE INDEX `idx_user_name` (`user_id`, `name`),
    CONSTRAINT `fk_configurations_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TrendRadar configurations';

-- Platforms table for storing platform definitions
CREATE TABLE IF NOT EXISTS `platforms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_id` INT UNSIGNED NOT NULL COMMENT 'Reference to configuration',
    `platform_id` VARCHAR(50) NOT NULL COMMENT 'Platform identifier (e.g., toutiao, baidu)',
    `platform_name` VARCHAR(100) NOT NULL COMMENT 'Display name for the platform',
    `is_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Whether this platform is enabled',
    `sort_order` INT DEFAULT 0 COMMENT 'Display order',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_config_id` (`config_id`),
    CONSTRAINT `fk_platforms_config` FOREIGN KEY (`config_id`) REFERENCES `configurations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Platform definitions';

-- Keywords table for storing frequency words
CREATE TABLE IF NOT EXISTS `keywords` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_id` INT UNSIGNED NOT NULL COMMENT 'Reference to configuration',
    `keyword_group` INT DEFAULT 0 COMMENT 'Group number for organizing keywords',
    `keyword` VARCHAR(200) NOT NULL COMMENT 'The keyword text',
    `keyword_type` ENUM('normal', 'required', 'filter', 'limit') DEFAULT 'normal' COMMENT 'Type: normal, required (+), filter (!), limit (@)',
    `limit_value` INT DEFAULT NULL COMMENT 'Limit value when type is limit',
    `sort_order` INT DEFAULT 0 COMMENT 'Sort order within group',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_config_id` (`config_id`),
    INDEX `idx_group` (`keyword_group`),
    CONSTRAINT `fk_keywords_config` FOREIGN KEY (`config_id`) REFERENCES `configurations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Frequency words/keywords';

-- Webhooks table for notification settings
CREATE TABLE IF NOT EXISTS `webhooks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_id` INT UNSIGNED NOT NULL COMMENT 'Reference to configuration',
    `webhook_type` VARCHAR(50) NOT NULL COMMENT 'Type: feishu, dingtalk, wework, telegram, email, ntfy, bark, slack',
    `webhook_url` TEXT COMMENT 'Webhook URL or primary configuration',
    `additional_config` TEXT COMMENT 'JSON formatted additional configuration',
    `is_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Whether this webhook is enabled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_config_id` (`config_id`),
    CONSTRAINT `fk_webhooks_config` FOREIGN KEY (`config_id`) REFERENCES `configurations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Notification webhook configurations';

-- Settings table for general settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_id` INT UNSIGNED NOT NULL COMMENT 'Reference to configuration',
    `setting_key` VARCHAR(100) NOT NULL COMMENT 'Setting key',
    `setting_value` TEXT COMMENT 'Setting value',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_config_id` (`config_id`),
    UNIQUE INDEX `idx_config_key` (`config_id`, `setting_key`),
    CONSTRAINT `fk_settings_config` FOREIGN KEY (`config_id`) REFERENCES `configurations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='General settings';

-- Operation logs table for tracking configuration changes
-- Note: target_id is a polymorphic reference that can point to different tables based on target_type
-- (configuration, platform, keyword, webhook, setting). No foreign key constraint is used to allow
-- flexibility and preserve logs even when the target entity is deleted.
CREATE TABLE IF NOT EXISTS `operation_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Reference to user who performed the action',
    `action` VARCHAR(50) NOT NULL COMMENT 'Action type (e.g., load_from_github, save_to_github, update_platform)',
    `target_type` VARCHAR(50) COMMENT 'Target entity type (e.g., configuration, platform, keyword, webhook, setting)',
    `target_id` INT UNSIGNED COMMENT 'ID of the target entity (polymorphic reference based on target_type)',
    `details` TEXT COMMENT 'JSON formatted additional details',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_operation_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Operation logs for tracking configuration changes';

-- Migrations table for tracking applied database migrations
-- Note: VARCHAR(191) is used instead of VARCHAR(255) to stay within MySQL 5.6's
-- 767-byte index limit when using utf8mb4 charset (191 * 4 = 764 bytes)
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(191) NOT NULL UNIQUE COMMENT 'Migration filename',
    `batch` INT NOT NULL COMMENT 'Batch number for grouping migrations',
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Database migrations tracking';

-- Note: Default user and configuration are created during registration
-- No default data is inserted here

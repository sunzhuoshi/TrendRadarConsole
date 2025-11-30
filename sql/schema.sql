-- TrendRadarConsole Database Schema
-- Compatible with MySQL 5.6

-- Configuration table for storing TrendRadar configurations
CREATE TABLE IF NOT EXISTS `configurations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Configuration name',
    `description` TEXT COMMENT 'Configuration description',
    `config_data` TEXT NOT NULL COMMENT 'JSON formatted configuration data',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether this configuration is active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- Insert default configuration
INSERT INTO `configurations` (`name`, `description`, `config_data`, `is_active`) VALUES
('Default', 'Default TrendRadar configuration', '{}', 1);

-- Get the default configuration ID and insert default platforms
SET @default_config_id = LAST_INSERT_ID();

INSERT INTO `platforms` (`config_id`, `platform_id`, `platform_name`, `is_enabled`, `sort_order`) VALUES
(@default_config_id, 'toutiao', '今日头条', 1, 1),
(@default_config_id, 'baidu', '百度热搜', 1, 2),
(@default_config_id, 'wallstreetcn-hot', '华尔街见闻', 1, 3),
(@default_config_id, 'thepaper', '澎湃新闻', 1, 4),
(@default_config_id, 'bilibili-hot-search', 'bilibili 热搜', 1, 5),
(@default_config_id, 'cls-hot', '财联社热门', 1, 6),
(@default_config_id, 'ifeng', '凤凰网', 1, 7),
(@default_config_id, 'tieba', '贴吧', 1, 8),
(@default_config_id, 'weibo', '微博', 1, 9),
(@default_config_id, 'douyin', '抖音', 1, 10),
(@default_config_id, 'zhihu', '知乎', 1, 11);

-- Insert default settings
INSERT INTO `settings` (`config_id`, `setting_key`, `setting_value`) VALUES
(@default_config_id, 'report_mode', 'incremental'),
(@default_config_id, 'rank_threshold', '5'),
(@default_config_id, 'sort_by_position_first', 'false'),
(@default_config_id, 'max_news_per_keyword', '0'),
(@default_config_id, 'rank_weight', '0.6'),
(@default_config_id, 'frequency_weight', '0.3'),
(@default_config_id, 'hotness_weight', '0.1'),
(@default_config_id, 'enable_crawler', 'true'),
(@default_config_id, 'enable_notification', 'true'),
(@default_config_id, 'push_window_enabled', 'false'),
(@default_config_id, 'push_window_start', '20:00'),
(@default_config_id, 'push_window_end', '22:00'),
(@default_config_id, 'push_window_once_per_day', 'true');

-- Migration: 007_create_feature_toggles_table
-- Created: 2024-12-07
-- Description: Create feature_toggles table for admin-controlled features

-- Create feature_toggles table
CREATE TABLE IF NOT EXISTS `feature_toggles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `feature_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Feature identifier (e.g., github_deployment, docker_deployment)',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this feature is enabled: 0=disabled, 1=enabled',
    `description` TEXT COMMENT 'Feature description',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Admin-controlled feature toggles';

-- Insert default feature toggles
INSERT INTO `feature_toggles` (`feature_key`, `is_enabled`, `description`) VALUES
    ('github_deployment', 1, 'GitHub deployment functionality'),
    ('docker_deployment', 1, 'Docker deployment functionality'),
    ('advanced_mode', 1, 'Advanced mode features')
ON DUPLICATE KEY UPDATE `id`=`id`; -- No-op to allow re-running migration safely


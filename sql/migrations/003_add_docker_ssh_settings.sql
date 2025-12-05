-- Migration: Create Docker workers table
-- Version: 003
-- Description: Create separate table for Docker worker SSH connection settings

-- Create Docker workers table
CREATE TABLE IF NOT EXISTS `docker_workers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Reference to user who owns this worker',
    `name` VARCHAR(100) NOT NULL DEFAULT 'Default Worker' COMMENT 'Worker display name',
    `ssh_host` VARCHAR(255) NOT NULL COMMENT 'Docker worker SSH host address',
    `ssh_port` INT UNSIGNED DEFAULT 22 COMMENT 'Docker worker SSH port',
    `ssh_username` VARCHAR(100) NOT NULL COMMENT 'Docker worker SSH username',
    `ssh_password` VARCHAR(255) DEFAULT NULL COMMENT 'Docker worker SSH password (encrypted)',
    `workspace_path` VARCHAR(255) DEFAULT '/srv/trendradar' COMMENT 'Docker workspace path on remote server',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether this worker is active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_docker_workers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Docker worker SSH connection settings';

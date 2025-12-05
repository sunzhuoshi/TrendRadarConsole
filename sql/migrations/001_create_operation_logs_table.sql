-- Migration: 001_create_operation_logs_table
-- Created: 2024-12-03
-- Description: Add operation_logs table for tracking configuration changes

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

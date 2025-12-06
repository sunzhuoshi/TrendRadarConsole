-- Migration: 005_rename_dev_mode_to_advanced_mode
-- Created: 2024-12-06
-- Description: Rename dev_mode column to advanced_mode in users table

-- Rename dev_mode column to advanced_mode
ALTER TABLE `users` 
    CHANGE COLUMN `dev_mode` `advanced_mode` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT 'Advanced mode: 0=disabled, 1=enabled';


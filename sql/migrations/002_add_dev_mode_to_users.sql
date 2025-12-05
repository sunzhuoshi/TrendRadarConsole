-- Migration: 002_add_dev_mode_to_users
-- Created: 2024-12-05
-- Description: Add dev_mode column to users table for development mode feature

-- Add dev_mode column to users table
-- Default is 0 (disabled), 1 means enabled
ALTER TABLE `users` 
    ADD COLUMN `dev_mode` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT 'Development mode: 0=disabled, 1=enabled' 
    AFTER `github_token`;

-- Migration: Add Docker SSH settings to users table
-- Version: 003
-- Description: Add columns for Docker worker SSH connection settings

-- Add Docker SSH connection columns to users table
ALTER TABLE `users` 
ADD COLUMN `docker_ssh_host` VARCHAR(255) DEFAULT NULL COMMENT 'Docker worker SSH host address' AFTER `dev_mode`,
ADD COLUMN `docker_ssh_port` INT UNSIGNED DEFAULT 22 COMMENT 'Docker worker SSH port' AFTER `docker_ssh_host`,
ADD COLUMN `docker_ssh_username` VARCHAR(100) DEFAULT NULL COMMENT 'Docker worker SSH username' AFTER `docker_ssh_port`,
ADD COLUMN `docker_ssh_password` VARCHAR(255) DEFAULT NULL COMMENT 'Docker worker SSH password (encrypted)' AFTER `docker_ssh_username`,
ADD COLUMN `docker_workspace_path` VARCHAR(255) DEFAULT '/srv/trendradar' COMMENT 'Docker workspace path on remote server' AFTER `docker_ssh_password`;

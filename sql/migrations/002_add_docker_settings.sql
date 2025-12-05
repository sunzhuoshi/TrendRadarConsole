-- Migration: Add Docker settings columns to users table
-- This adds columns for local Docker deployment support

ALTER TABLE `users` 
ADD COLUMN `docker_container_name` VARCHAR(100) COMMENT 'Docker container name for local deployment' AFTER `github_token`,
ADD COLUMN `docker_config_path` VARCHAR(255) COMMENT 'Path to config directory for Docker' AFTER `docker_container_name`,
ADD COLUMN `docker_output_path` VARCHAR(255) COMMENT 'Path to output directory for Docker' AFTER `docker_config_path`,
ADD COLUMN `docker_image` VARCHAR(255) DEFAULT 'wantcat/trendradar:latest' COMMENT 'Docker image to use' AFTER `docker_output_path`;

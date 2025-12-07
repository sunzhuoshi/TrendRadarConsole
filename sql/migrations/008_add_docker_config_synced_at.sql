-- Migration: Add docker_config_synced_at to users table
-- This tracks when configuration was last synced to Docker container

ALTER TABLE `users` 
ADD COLUMN `docker_config_synced_at` TIMESTAMP NULL DEFAULT NULL 
COMMENT 'Last time configuration was synced to Docker container' 
AFTER `is_admin`;

-- Migration: Add is_public field to docker_workers table
-- Version: 004
-- Description: Add is_public column to support public/private Docker workers

-- Add is_public column
ALTER TABLE `docker_workers` ADD COLUMN `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Whether this worker is public (0=private, 1=public)' AFTER `is_active`;

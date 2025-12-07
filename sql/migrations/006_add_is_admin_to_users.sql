-- Migration: 006_add_is_admin_to_users
-- Created: 2024-12-07
-- Description: Add is_admin field to users table for admin role management

-- Add is_admin column to users table
ALTER TABLE `users` 
    ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT 'Admin role: 0=regular user, 1=admin' 
    AFTER `advanced_mode`;

-- Create index for faster admin checks
CREATE INDEX `idx_is_admin` ON `users` (`is_admin`);

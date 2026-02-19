-- =============================================
-- Migration: 001_add_branch_id_to_funds
-- Date: 2026-02-16
-- Description: Add branch_id to funds table for 
--   branch-scoped fund management
-- =============================================

USE `kitacc`;

-- Add branch_id column
ALTER TABLE `funds` 
    ADD COLUMN `branch_id` INT UNSIGNED NOT NULL AFTER `id`;

-- Set existing funds to Main Branch (id=1) as default
UPDATE `funds` SET `branch_id` = 1 WHERE `branch_id` = 0;

-- Add foreign key constraint
ALTER TABLE `funds`
    ADD CONSTRAINT `fk_funds_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE;

-- Record migration
INSERT INTO `migrations` (`filename`, `description`) 
VALUES ('001_add_branch_id_to_funds', 'Add branch_id to funds table for branch-scoped fund management');

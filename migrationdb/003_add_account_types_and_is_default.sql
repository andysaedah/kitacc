-- =============================================
-- Migration: Add account_types table & is_default column to accounts
-- =============================================

-- Add is_default column to accounts table
-- The main bank account per branch should be marked as default and cannot be deactivated
ALTER TABLE `accounts` ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- Create account_types table for superadmin to manage account types
CREATE TABLE IF NOT EXISTS `account_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default account types
INSERT INTO `account_types` (`name`, `description`) VALUES
('Bank Account', 'Standard bank account for deposits and withdrawals'),
('Petty Cash', 'Small cash fund for minor expenses');

-- Add account_type_id foreign key to accounts table
ALTER TABLE `accounts` ADD COLUMN `account_type_id` INT UNSIGNED NULL AFTER `type`;
ALTER TABLE `accounts` ADD CONSTRAINT `fk_accounts_account_type` FOREIGN KEY (`account_type_id`) REFERENCES `account_types`(`id`) ON DELETE SET NULL;

-- Migrate existing accounts: set account_type_id based on current type ENUM
-- Bank accounts -> account_type_id 1, Petty Cash -> account_type_id 2
UPDATE `accounts` SET `account_type_id` = 1 WHERE `type` = 'bank';
UPDATE `accounts` SET `account_type_id` = 2 WHERE `type` = 'petty_cash';

-- =============================================
-- KiTAcc: Church Account Made Easy
-- Database Schema
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

CREATE DATABASE IF NOT EXISTS `kitacc` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `kitacc`;

-- =============================================
-- BRANCHES
-- =============================================
CREATE TABLE `branches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` TEXT NULL,
    `phone` VARCHAR(20) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- USERS
-- =============================================
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `branch_id` INT UNSIGNED NULL,
    `role` ENUM('superadmin','admin_finance','branch_finance','user','viewer') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SYSTEM SETTINGS (Key-Value Store)
-- Expected keys:
--   app_name        - Application display name (default: KiTAcc)
--   app_tagline     - Application tagline (default: Church Account Made Easy)
--   church_name     - Church/organization name
--   timezone        - PHP timezone (default: Asia/Kuala_Lumpur)
--   accounting_mode - 'simple' or 'fund'
--   currency_symbol - Currency display symbol (default: RM)
--   currency_code   - ISO currency code (default: MYR)
-- =============================================
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ACCOUNTS (Bank / Petty Cash)
-- =============================================
CREATE TABLE `accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('bank','petty_cash') NOT NULL DEFAULT 'bank',
    `account_number` VARCHAR(50) NULL,
    `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CATEGORIES (Income / Expense)
-- =============================================
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('income','expense','claim') NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FUNDS (Fund Accounting Mode)
-- =============================================
CREATE TABLE `funds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TRANSACTIONS
-- =============================================
CREATE TABLE `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT UNSIGNED NOT NULL,
    `type` ENUM('income','expense','transfer') NOT NULL,
    `account_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `fund_id` INT UNSIGNED NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `date` DATE NOT NULL,
    `description` TEXT NULL,
    `reference_number` VARCHAR(50) NULL,
    `receipt_path` VARCHAR(255) NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED NULL,
    `claim_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`fund_id`) REFERENCES `funds`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_branch_date` (`branch_id`, `date`),
    INDEX `idx_type_date` (`type`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FUND TRANSFERS (Inter-fund)
-- =============================================
CREATE TABLE `fund_transfers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT UNSIGNED NOT NULL,
    `from_fund_id` INT UNSIGNED NOT NULL,
    `to_fund_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `description` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_fund_id`) REFERENCES `funds`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`to_fund_id`) REFERENCES `funds`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CLAIMS
-- =============================================
CREATE TABLE `claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT UNSIGNED NOT NULL,
    `submitted_by` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NULL,
    `description` TEXT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `receipt_date` DATE NULL,
    `receipt_path` VARCHAR(255) NULL,
    `category_id` INT UNSIGNED NULL,
    `account_id` INT UNSIGNED NULL,
    `fund_id` INT UNSIGNED NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`fund_id`) REFERENCES `funds`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_branch_status` (`branch_id`, `status`),
    INDEX `idx_submitted_by` (`submitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- AUDIT LOG
-- =============================================
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_user_action` (`user_id`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PASSWORD RESET TOKENS
-- =============================================
CREATE TABLE `password_reset_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token_hash`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

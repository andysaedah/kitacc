-- =============================================
-- KiTAcc: Migration Tracking Table
-- Run this ONCE on existing databases to
-- enable the migration system.
-- =============================================

USE `kitacc`;

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

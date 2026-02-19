-- =============================================
-- Migration: Add app_name and app_tagline settings
-- For existing databases that don't have these keys
-- =============================================

-- Add app_name setting (skip if exists)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
VALUES ('app_name', 'KiTAcc');

-- Add app_tagline setting (skip if exists)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
VALUES ('app_tagline', 'Church Account Made Easy');

-- Ensure timezone setting exists (skip if exists)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
VALUES ('timezone', 'Asia/Kuala_Lumpur');

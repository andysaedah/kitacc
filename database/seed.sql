-- =============================================
-- KiTAcc: Seed Data
-- Default superadmin, categories, funds, settings
-- =============================================

USE `kitacc`;

-- Default Branch
INSERT INTO `branches` (`id`, `name`, `address`, `phone`) VALUES
(1, 'Main Branch', 'Church Main Address', '');

-- Default Superadmin (password: password123)
INSERT INTO `users` (`id`, `username`, `password_hash`, `name`, `email`, `branch_id`, `role`) VALUES
(1, 'admin', '$2y$10$iPO5dWcbITb4YPV.yPrvR.gBreCyPbL8sPXhvO4f1NEpaKjDIvEjK', 'Super Admin', 'admin@church.com', 1, 'superadmin');

-- System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'KiTAcc'),
('app_tagline', 'Church Account Made Easy'),
('church_name', 'My Church'),
('timezone', 'Asia/Kuala_Lumpur'),
('accounting_mode', 'simple'),
('currency_symbol', 'RM'),
('currency_code', 'MYR');

-- Income Categories (Pre-defined)
INSERT INTO `categories` (`name`, `type`) VALUES
('Tithes', 'income'),
('Offering', 'income'),
('Faith Pledge', 'income'),
('Love Gift', 'income'),
('Donation', 'income');

-- Expense Categories (Pre-defined)
INSERT INTO `categories` (`name`, `type`) VALUES
('Claim', 'expense'),
('Administration', 'expense'),
('Utilities', 'expense');

-- Funds (Pre-defined)
INSERT INTO `funds` (`branch_id`, `name`, `description`) VALUES
(1, 'General Fund', 'Default unallocated fund'),
(1, 'Mission', 'Mission fund for outreach and missionary work');

-- Default Account for Main Branch
INSERT INTO `accounts` (`branch_id`, `name`, `type`, `account_number`, `balance`) VALUES
(1, 'Main Bank Account', 'bank', '', 0.00),
(1, 'Petty Cash', 'petty_cash', NULL, 0.00);

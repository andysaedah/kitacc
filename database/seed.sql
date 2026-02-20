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
('Contribution', 'income'),
('Donation', 'income'),
('Fundraising', 'income'),
('Others', 'income');

-- Expense Categories (Pre-defined)
INSERT INTO `categories` (`name`, `type`) VALUES
('Claim', 'expense'),
('Administration', 'expense'),
('Rental', 'expense'),
('Bills', 'expense'),
('Salary', 'expense'),
('Allowance', 'expense'),
('Love Gift', 'expense'),
('Statutory', 'expense'),
('Donation', 'expense'),
('Contribution', 'expense'),
('Insurance', 'expense'),
('Maintenance', 'expense'),
('Outreach / Ministry', 'expense'),
('Departmental', 'expense'),
('Utilities', 'expense'),
('Others', 'expense');

-- Funds (Pre-defined)
INSERT INTO `funds` (`branch_id`, `name`, `description`) VALUES
(1, 'General Fund', 'Default unallocated fund'),
(1, 'Mission', 'Mission fund for outreach and missionary work'),
(1, 'Petty Cash', 'Small cash fund for minor day-to-day expenses');

-- Default Account Types
INSERT INTO `account_types` (`name`, `description`, `icon`, `color`) VALUES
('Bank Account', 'Standard bank account for deposits and withdrawals', 'fa-university', 'primary');

INSERT INTO `accounts` (`branch_id`, `name`, `account_type_id`, `account_number`, `balance`, `is_default`) VALUES
(1, 'Main Bank Account', 1, '', 0.00, 1);

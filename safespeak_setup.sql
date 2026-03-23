-- SafeSpeak Anonymous Reporting System Setup
-- Run this SQL script to create the necessary database table

CREATE TABLE IF NOT EXISTS `anonymous_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` varchar(64) NOT NULL UNIQUE COMMENT 'Unique identifier for the report (not sequential)',
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `category` enum('academic','bullying','safety','discrimination','other') DEFAULT 'other',
    `urgency` enum('low','medium','high','critical') DEFAULT 'medium',
    `contact_email` varchar(255) DEFAULT NULL COMMENT 'Optional contact email (anonymized)',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address for rate limiting (hashed for privacy)',
    `user_agent` text COMMENT 'Browser user agent for additional context',
    `status` enum('pending','reviewing','resolved','dismissed') DEFAULT 'pending',
    `admin_notes` text COMMENT 'Internal notes by administrators',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `reviewed_at` timestamp NULL DEFAULT NULL,
    `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed the report',
    PRIMARY KEY (`id`),
    KEY `report_id` (`report_id`),
    KEY `status` (`status`),
    KEY `category` (`category`),
    KEY `urgency` (`urgency`),
    KEY `created_at` (`created_at`),
    KEY `reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_reports_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anonymous reports submitted through SafeSpeak';

-- Insert sample data for testing (optional)
INSERT INTO `anonymous_reports` (`report_id`, `subject`, `message`, `category`, `urgency`, `status`) VALUES
('ANON-TEST-001', 'Test Report', 'This is a test anonymous report to verify the system is working.', 'other', 'low', 'pending');

-- Create activity log for SafeSpeak actions
ALTER TABLE `activity_log` ADD COLUMN IF NOT EXISTS `reference_type` varchar(50) DEFAULT NULL COMMENT 'Type of referenced object (post, comment, report, etc.)';
ALTER TABLE `activity_log` ADD COLUMN IF NOT EXISTS `reference_id` int(11) DEFAULT NULL COMMENT 'ID of referenced object';

-- Add index for better performance
ALTER TABLE `activity_log` ADD KEY IF NOT EXISTS `reference` (`reference_type`, `reference_id`);
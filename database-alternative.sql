-- Alternative Database Schema for QR Attendance System
-- Enhanced version with additional features and improved structure

-- Create database
CREATE DATABASE IF NOT EXISTS `qr_attendance_v2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `qr_attendance_v2`;

-- 1. Users Table (Enhanced)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `phone` VARCHAR(20),
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'organizer', 'attendee') DEFAULT 'attendee',
    `profile_image` VARCHAR(255),
    `is_active` BOOLEAN DEFAULT TRUE,
    `email_verified` BOOLEAN DEFAULT FALSE,
    `last_login` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`)
);

-- 2. Organizations Table
CREATE TABLE `organizations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `logo` VARCHAR(255),
    `website` VARCHAR(255),
    `contact_email` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`)
);

-- 3. User Organizations (Many-to-Many)
CREATE TABLE `user_organizations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `organization_id` INT NOT NULL,
    `role` ENUM('owner', 'admin', 'member') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_org` (`user_id`, `organization_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_org_id` (`organization_id`)
);

-- 4. Event Categories
CREATE TABLE `event_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `color` VARCHAR(7) DEFAULT '#007bff',
    `icon` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`)
);

-- 5. Venues Table
CREATE TABLE `venues` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` TEXT,
    `city` VARCHAR(50),
    `state` VARCHAR(50),
    `country` VARCHAR(50),
    `postal_code` VARCHAR(20),
    `capacity` INT,
    `latitude` DECIMAL(10, 8),
    `longitude` DECIMAL(11, 8),
    `description` TEXT,
    `image` VARCHAR(255),
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_city` (`city`),
    INDEX `idx_capacity` (`capacity`)
);

-- 6. Events Table (Enhanced)
CREATE TABLE `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `short_description` VARCHAR(500),
    `category_id` INT,
    `organization_id` INT,
    `venue_id` INT,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `registration_start` DATETIME,
    `registration_end` DATETIME,
    `attendance_start` DATETIME,
    `attendance_end` DATETIME,
    `max_attendees` INT,
    `is_public` BOOLEAN DEFAULT TRUE,
    `requires_approval` BOOLEAN DEFAULT FALSE,
    `is_recurring` BOOLEAN DEFAULT FALSE,
    `recurring_pattern` JSON,
    `event_image` VARCHAR(255),
    `banner_image` VARCHAR(255),
    `status` ENUM('draft', 'published', 'cancelled', 'completed') DEFAULT 'draft',
    `access_code` VARCHAR(20) UNIQUE,
    `qr_code_data` TEXT,
    `settings` JSON,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_start_datetime` (`start_datetime`),
    INDEX `idx_status` (`status`),
    INDEX `idx_organization` (`organization_id`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_access_code` (`access_code`)
);

-- 7. Event Sessions (For multi-day events)
CREATE TABLE `event_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `location` VARCHAR(255),
    `speaker` VARCHAR(100),
    `max_attendees` INT,
    `session_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_start_datetime` (`start_datetime`)
);

-- 8. Registrations Table
CREATE TABLE `registrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `registration_type` ENUM('individual', 'group') DEFAULT 'individual',
    `group_size` INT DEFAULT 1,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    `notes` TEXT,
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at` DATETIME,
    `approved_by` INT,
    `cancelled_at` DATETIME,
    `cancellation_reason` TEXT,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_event_user` (`event_id`, `user_id`),
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
);

-- 9. Attendance Records (Enhanced)
CREATE TABLE `attendance_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `session_id` INT,
    `check_in_time` DATETIME NOT NULL,
    `check_out_time` DATETIME,
    `attendance_type` ENUM('check_in', 'check_out', 'both') DEFAULT 'check_in',
    `qr_code_used` VARCHAR(255),
    `device_info` JSON,
    `location_lat` DECIMAL(10, 8),
    `location_lng` DECIMAL(11, 8),
    `verification_method` ENUM('qr_code', 'manual', 'nfc', 'biometric') DEFAULT 'qr_code',
    `is_verified` BOOLEAN DEFAULT TRUE,
    `verified_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `event_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_event_user` (`event_id`, `user_id`),
    INDEX `idx_check_in_time` (`check_in_time`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_verification_method` (`verification_method`)
);

-- 10. Event Teams
CREATE TABLE `event_teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('organizer', 'coordinator', 'volunteer', 'security') DEFAULT 'volunteer',
    `permissions` JSON,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_event_user_role` (`event_id`, `user_id`),
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_user_id` (`user_id`)
);

-- 11. QR Codes Table
CREATE TABLE `qr_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `session_id` INT,
    `code_data` VARCHAR(500) UNIQUE NOT NULL,
    `code_type` ENUM('event', 'session', 'check_in', 'check_out') DEFAULT 'event',
    `is_active` BOOLEAN DEFAULT TRUE,
    `expires_at` DATETIME,
    `usage_limit` INT,
    `usage_count` INT DEFAULT 0,
    `generated_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `event_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_code_data` (`code_data`),
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_active` (`is_active`)
);

-- 12. Notifications Table
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('event_reminder', 'attendance_confirmation', 'registration_status', 'system', 'announcement') DEFAULT 'system',
    `related_id` INT,
    `related_type` VARCHAR(50),
    `is_read` BOOLEAN DEFAULT FALSE,
    `email_sent` BOOLEAN DEFAULT FALSE,
    `push_sent` BOOLEAN DEFAULT FALSE,
    `sent_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
);

-- 13. Audit Log Table
CREATE TABLE `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_table_name` (`table_name`),
    INDEX `idx_created_at` (`created_at`)
);

-- 14. Settings Table
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    `description` TEXT,
    `type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `is_public` BOOLEAN DEFAULT FALSE,
    `updated_by` INT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_key` (`key`),
    INDEX `idx_is_public` (`is_public`)
);

-- 15. Reports Table
CREATE TABLE `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `report_type` ENUM('attendance_summary', 'registration_stats', 'demographics', 'session_breakdown') DEFAULT 'attendance_summary',
    `title` VARCHAR(200) NOT NULL,
    `data` JSON,
    `filters` JSON,
    `generated_by` INT NOT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `file_path` VARCHAR(255),
    `is_public` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_report_type` (`report_type`),
    INDEX `idx_generated_by` (`generated_by`)
);

-- Insert default data
INSERT INTO `event_categories` (`name`, `description`, `color`, `icon`) VALUES
('Conference', 'Professional conferences and seminars', '#007bff', 'fa-briefcase'),
('Workshop', 'Hands-on training sessions', '#28a745', 'fa-tools'),
('Social', 'Social gatherings and networking', '#ffc107', 'fa-users'),
('Sports', 'Sports and fitness events', '#dc3545', 'fa-running'),
('Cultural', 'Cultural events and performances', '#6f42c1', 'fa-theater-masks'),
('Academic', 'Academic events and lectures', '#17a2b8', 'fa-graduation-cap');

INSERT INTO `settings` (`key`, `value`, `description`, `type`, `is_public`) VALUES
('site_name', 'QR Attendance System', 'Name of the application', 'string', true),
('default_timezone', 'UTC', 'Default timezone for the system', 'string', false),
('max_file_size', '5242880', 'Maximum file upload size in bytes', 'number', false),
('allow_registration', 'true', 'Allow public user registration', 'boolean', true),
('email_notifications', 'true', 'Enable email notifications', 'boolean', false),
('qr_expiry_hours', '24', 'QR code expiry time in hours', 'number', false);

-- Create views for common queries
CREATE VIEW `event_attendance_summary` AS
SELECT 
    e.id as event_id,
    e.title,
    e.start_datetime,
    COUNT(DISTINCT r.user_id) as registered_count,
    COUNT(DISTINCT ar.user_id) as attended_count,
    COUNT(DISTINCT CASE WHEN ar.check_out_time IS NOT NULL THEN ar.user_id END) as completed_count
FROM events e
LEFT JOIN registrations r ON e.id = r.event_id AND r.status = 'approved'
LEFT JOIN attendance_records ar ON e.id = ar.event_id
WHERE e.status = 'published'
GROUP BY e.id, e.title, e.start_datetime;

CREATE VIEW `user_activity_summary` AS
SELECT 
    u.id as user_id,
    CONCAT(u.first_name, ' ', u.last_name) as full_name,
    u.email,
    COUNT(DISTINCT r.event_id) as events_registered,
    COUNT(DISTINCT ar.event_id) as events_attended,
    MAX(ar.check_in_time) as last_attendance
FROM users u
LEFT JOIN registrations r ON u.id = r.user_id AND r.status = 'approved'
LEFT JOIN attendance_records ar ON u.id = ar.user_id
GROUP BY u.id, u.first_name, u.last_name, u.email;

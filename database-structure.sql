CREATE DATABASE IF NOT EXISTS `qr_attendance_v2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `qr_attendance_v2`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `phone` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `end_time` TIME NULL,
    `attendance_start` TIME NULL,
    `attendance_end` TIME NULL,
    `image` VARCHAR(255) NULL,
    `venue_name` VARCHAR(255) NULL,
    `venue_location` VARCHAR(255) NULL,
    `target_audience` VARCHAR(255) NULL,
    `location_lat` DECIMAL(10,7) NULL,
    `location_lng` DECIMAL(10,7) NULL,
    `created_by` INT NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'online',
    `registration_mode` VARCHAR(20) NOT NULL DEFAULT 'self',
    `access_code` VARCHAR(20) NULL,
    `invited_emails` TEXT NULL,
    `deleted` BOOLEAN NOT NULL DEFAULT FALSE,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_event_date_time` (`date`, `time`),
    INDEX `idx_deleted` (`deleted`),
    CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `event_id` INT NOT NULL,
    `participant_name` VARCHAR(255) NULL,
    `participant_email` VARCHAR(255) NULL,
    `participant_phone` VARCHAR(50) NULL,
    `invite_status` VARCHAR(30) NOT NULL DEFAULT 'registered',
    `invited_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `access_code` VARCHAR(20) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_participant_email` (`participant_email`),
    CONSTRAINT `fk_participants_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_participants_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `user_name` VARCHAR(255) NULL,
    `user_email` VARCHAR(255) NULL,
    `user_phone` VARCHAR(50) NULL,
    `device_info` LONGTEXT NULL,
    `attendance_status` VARCHAR(30) NOT NULL DEFAULT 'present',
    `time` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `scan_lat` DECIMAL(10,7) NULL,
    `scan_lng` DECIMAL(10,7) NULL,
    `scan_address` VARCHAR(255) NULL,
    `scan_ip` VARCHAR(80) NULL,
    `browser_info` LONGTEXT NULL,
    `distance_from_venue` DECIMAL(10,2) NULL,
    `phone_matched` BOOLEAN NOT NULL DEFAULT FALSE,
    `verification_method` VARCHAR(30) NULL,
    `check_in_time` TIME NULL,
    `notes` TEXT NULL,
    UNIQUE KEY `unique_user_event_attendance` (`user_id`, `event_id`),
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_time` (`time`),
    CONSTRAINT `fk_attendance_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attendance_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_attendance_id` (`attendance_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deleted_events` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `original_event_id` INT NOT NULL,
    `event_name` VARCHAR(255) NOT NULL,
    `deleted_by` INT NOT NULL,
    `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reason` TEXT NULL,
    `attendance_data_preserved` BOOLEAN DEFAULT TRUE,
    `event_data` JSON NULL,
    INDEX `idx_original_event_id` (`original_event_id`),
    INDEX `idx_deleted_by` (`deleted_by`),
    INDEX `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

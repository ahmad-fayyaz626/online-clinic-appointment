-- Online Clinic Management System Database Schema
-- Compatible with MySQL / MariaDB on XAMPP (Windows) and LAMP (Linux)

CREATE DATABASE IF NOT EXISTS `clinic_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clinic_db`;

-- 1. Specialties Table
CREATE TABLE IF NOT EXISTS `specialties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table (Patients, Doctors, Admin)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `role` ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Doctors Table (Professional profiles linked to users)
CREATE TABLE IF NOT EXISTS `doctors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `specialty_id` INT NOT NULL,
    `qualification` VARCHAR(150) NOT NULL,
    `experience_years` INT NOT NULL,
    `fee` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doctors_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `status` ENUM('booked', 'cancelled', 'completed') NOT NULL DEFAULT 'booked',
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- Initial Seed Data
-- =======================================================

-- Specialties
INSERT INTO `specialties` (`id`, `name`) VALUES
(1, 'Cardiology'),
(2, 'Dermatology'),
(3, 'Neurology'),
(4, 'Orthopedics'),
(5, 'Pediatrics'),
(6, 'General Medicine'),
(7, 'Dentistry'),
(8, 'Ophthalmology')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Doctor User Accounts (Password: Doctor@123)
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`) VALUES
(1, 'Dr. Sara Khan', 'sara.khan@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03001112233', 'doctor'),
(2, 'Dr. Ahmed Raza', 'ahmed.raza@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03002223344', 'doctor'),
(3, 'Dr. Ayesha Malik', 'ayesha.malik@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03003334455', 'doctor'),
(4, 'Dr. Usman Tariq', 'usman.tariq@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03004445566', 'doctor'),
(5, 'Dr. Fatima Noor', 'fatima.noor@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03005556677', 'doctor'),
(6, 'Dr. Bilal Hassan', 'bilal.hassan@clinic.com', '$2y$10$cb6BuqFnXmW.Fi6Wxe8kCOiG9A7PM7teCTd20i3AThtqvr7hYEtjW', '03006667788', 'doctor')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Doctor Profiles
INSERT INTO `doctors` (`id`, `user_id`, `specialty_id`, `qualification`, `experience_years`, `fee`) VALUES
(1, 1, 1, 'MBBS, FCPS (Cardiology)', 12, 2500.00),
(2, 2, 7, 'BDS, RDS (Oral & Maxillofacial)', 8, 1500.00),
(3, 3, 3, 'MBBS, MD (Neurology), MRCP', 15, 3000.00),
(4, 4, 5, 'MBBS, DCH, FCPS (Pediatrics)', 9, 1800.00),
(5, 5, 2, 'MBBS, FCPS (Dermatology)', 7, 2000.00),
(6, 6, 4, 'MBBS, MS (Orthopedic Surgery)', 11, 2200.00)
ON DUPLICATE KEY UPDATE `qualification`=VALUES(`qualification`), `fee`=VALUES(`fee`);

-- Sample Patient User (Password: Patient@123)
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`) VALUES
(7, 'Ali Khan', 'ali@example.com', '$2y$10$gU0ize7xZn6Hgvrp.3504Of19PtscXQo3i.FXPFhdzFXip6V6Wou2', '03123456789', 'patient')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

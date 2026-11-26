-- Online Clinic Management System Database Schema
-- Compatible with MySQL / MariaDB on XAMPP (Windows) and LAMP (Linux)

-- Select the target database before importing this file.

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
    queue_min_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    queue_max_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
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
    `status` ENUM('booked', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'booked',
    `reason` VARCHAR(255) DEFAULT NULL,
    `doctor_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_at DATETIME NULL,
    checked_in_by INT NULL,
    consultation_started_at DATETIME NULL,
    consultation_finished_at DATETIME NULL,
    active_doctor_id INT GENERATED ALWAYS AS (
        CASE WHEN status = 'booked' AND consultation_started_at IS NOT NULL THEN doctor_id END
    ) STORED,
    UNIQUE KEY uq_current_consultation (active_doctor_id),
    KEY ix_queue_doctor_day (doctor_id, appointment_date, status, appointment_time),
    KEY ix_queue_doctor_finish (doctor_id, consultation_finished_at),
    KEY ix_queue_patient_day (patient_id, appointment_date, appointment_time),
    CONSTRAINT fk_appointments_check_in_actor FOREIGN KEY (checked_in_by)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Reception queue audit history
CREATE TABLE IF NOT EXISTS appointment_queue_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    actor_id INT NULL,
    event_type ENUM('check_in', 'undo_check_in', 'no_show', 'restore') NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    KEY ix_queue_events_appointment (appointment_id, id),
    CONSTRAINT fk_queue_events_appointment FOREIGN KEY (appointment_id)
        REFERENCES appointments (id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_events_actor FOREIGN KEY (actor_id)
        REFERENCES users (id) ON DELETE SET NULL
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

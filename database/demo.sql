-- Optional demo data for an empty local development database.

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

-- Clinic Administrator User (Password: Admin@123)
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`) VALUES
(8, 'Clinic Admin', 'admin@clinic.com', '$2y$10$6FJ3nayJ3AVyKyBY81VtSe0jgMPYkOlX4.kJEXqj1IlsueYF/BUKq', '03001239999', 'admin')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Sample Patient Appointments
INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `reason`, `doctor_notes`, `created_at`) VALUES
(1, 7, 1, CURDATE(), '10:00:00', 'booked', 'Routine cardiovascular health checkup and blood pressure monitoring.', NULL, NOW()),
(2, 7, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '14:00:00', 'completed', 'Persistent migraine headaches and visual aura episodes.', 'Diagnosed with tension-triggered migraine. Prescribed Tab Rizatriptan 10mg PRN and Tab Magnesium 400mg daily. Advised adequate sleep and hydration.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 7, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', 'booked', 'Tooth sensitivity and routine dental scaling.', NULL, NOW()),
(4, 7, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '15:00:00', 'cancelled', 'Allergic skin reaction consultation.', NULL, DATE_SUB(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

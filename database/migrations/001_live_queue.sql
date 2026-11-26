-- Apply to an existing CarePulse database before deploying the PHP changes.
-- MySQL/MariaDB DDL commits each statement. Back up first and apply once.
ALTER TABLE doctors
    ADD COLUMN queue_min_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ADD COLUMN queue_max_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45;

ALTER TABLE appointments
    ADD COLUMN checked_in_at DATETIME NULL,
    ADD COLUMN checked_in_by INT NULL,
    ADD COLUMN consultation_started_at DATETIME NULL,
    ADD COLUMN consultation_finished_at DATETIME NULL,
    ADD COLUMN active_doctor_id INT GENERATED ALWAYS AS (
        CASE WHEN status = 'booked' AND consultation_started_at IS NOT NULL THEN doctor_id END
    ) STORED,
    ADD UNIQUE INDEX uq_current_consultation (active_doctor_id),
    ADD INDEX ix_queue_doctor_day (doctor_id, appointment_date, status, appointment_time),
    ADD INDEX ix_queue_doctor_finish (doctor_id, consultation_finished_at),
    ADD INDEX ix_queue_patient_day (patient_id, appointment_date, appointment_time),
    ADD CONSTRAINT fk_appointments_check_in_actor FOREIGN KEY (checked_in_by)
        REFERENCES users (id) ON DELETE SET NULL;

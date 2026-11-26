-- Apply after 001_live_queue.sql on an existing installation.
ALTER TABLE appointments
    MODIFY COLUMN status ENUM('booked', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'booked';

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

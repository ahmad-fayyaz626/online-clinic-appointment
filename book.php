<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_login();

$patient_id = $_SESSION['user_id'];
$errors = [];

// Fetch all doctors for selection dropdown or details
$doctors_stmt = $pdo->query("
    SELECT 
        d.id AS doctor_id,
        u.full_name AS doctor_name,
        s.name AS specialty_name,
        d.qualification,
        d.experience_years,
        d.fee
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    ORDER BY u.full_name ASC
");
$all_doctors = $doctors_stmt->fetchAll();

// Doctor ID from query or form
$selected_doctor_id = isset($_REQUEST['doctor_id']) ? (int)$_REQUEST['doctor_id'] : 0;

// Fetch selected doctor details if provided
$selected_doctor = null;
if ($selected_doctor_id > 0) {
    $doc_stmt = $pdo->prepare("
        SELECT 
            d.id AS doctor_id,
            u.full_name AS doctor_name,
            s.name AS specialty_name,
            d.qualification,
            d.experience_years,
            d.fee
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.id
        INNER JOIN specialties s ON d.specialty_id = s.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $doc_stmt->execute([$selected_doctor_id]);
    $selected_doctor = $doc_stmt->fetch();
}

// Available standard clinic consultation slots
$available_slots = [
    '09:00:00' => '09:00 AM',
    '10:00:00' => '10:00 AM',
    '11:00:00' => '11:00 AM',
    '12:00:00' => '12:00 PM',
    '14:00:00' => '02:00 PM',
    '15:00:00' => '03:00 PM',
    '16:00:00' => '04:00 PM',
    '17:00:00' => '05:00 PM',
    '18:00:00' => '06:00 PM',
    '19:00:00' => '07:00 PM'
];

$appointment_date = $_POST['appointment_date'] ?? date('Y-m-d');
$appointment_time = $_POST['appointment_time'] ?? '';
$reason           = trim($_POST['reason'] ?? '');

// Handle appointment booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id        = (int)($_POST['doctor_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $reason           = trim($_POST['reason'] ?? '');

    // 1. Verify doctor exists
    if ($doctor_id <= 0) {
        $errors[] = 'Please select a valid doctor.';
    } else {
        $check_doc = $pdo->prepare("SELECT id FROM doctors WHERE id = ?");
        $check_doc->execute([$doctor_id]);
        if (!$check_doc->fetch()) {
            $errors[] = 'The selected doctor does not exist.';
        }
    }

    // 2. Validate date
    if (empty($appointment_date)) {
        $errors[] = 'Please select an appointment date.';
    } else {
        $today = date('Y-m-d');
        if ($appointment_date < $today) {
            $errors[] = 'Appointment date cannot be in the past.';
        }
    }

    // 3. Validate time slot
    if (empty($appointment_time)) {
        $errors[] = 'Please choose a consultation time slot.';
    } elseif (!array_key_exists($appointment_time, $available_slots)) {
        $errors[] = 'Please select a valid clinic consultation time slot.';
    }

    // Business Rule 1: Patient cannot book 2 appointments at the same date and time (where status = booked)
    if (empty($errors)) {
        $patient_conflict_stmt = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE patient_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ? 
              AND status = 'booked'
            LIMIT 1
        ");
        $patient_conflict_stmt->execute([$patient_id, $appointment_date, $appointment_time]);
        if ($patient_conflict_stmt->fetch()) {
            $errors[] = 'You already have another appointment scheduled at this exact date and time. Please pick another slot.';
        }
    }

    // Business Rule 2: Doctor cannot be double-booked on the same slot (status = booked only)
    if (empty($errors)) {
        $doctor_conflict_stmt = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ? 
              AND status = 'booked'
            LIMIT 1
        ");
        $doctor_conflict_stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
        if ($doctor_conflict_stmt->fetch()) {
            $errors[] = 'This doctor is already booked for this time slot on the selected date. Please choose a different slot.';
        }
    }

    // Insert appointment
    if (empty($errors)) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason)
                VALUES (?, ?, ?, ?, 'booked', ?)
            ");
            $insert_stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $reason]);

            $time_formatted = $available_slots[$appointment_time] ?? $appointment_time;
            set_flash('success', "Your appointment has been successfully booked for {$appointment_date} at {$time_formatted}!");
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Booking failed due to a database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Book Medical Appointment';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <a href="index.php" class="btn btn-outline btn-sm" id="btn-back-doctors">
            &larr; Back to Doctors Directory
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">Schedule Your Consultation</h2>
            <span class="badge badge-specialty">Instant Confirmation</span>
        </div>

        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($selected_doctor): ?>
                <!-- Selected Doctor Summary Card -->
                <div style="background-color: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Selected Specialist</div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 2px 0;"><?php echo e($selected_doctor['doctor_name']); ?></h3>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                            <span class="badge badge-specialty"><?php echo e($selected_doctor['specialty_name']); ?></span> &middot; <?php echo e($selected_doctor['qualification']); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Consultation Fee</div>
                        <div style="font-size: 1.35rem; font-weight: 800; color: var(--success);">
                            Rs. <?php echo number_format($selected_doctor['fee'], 0); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form action="book.php" method="POST" id="appointment-booking-form">
                <?php if ($selected_doctor): ?>
                    <input type="hidden" name="doctor_id" value="<?php echo (int)$selected_doctor['doctor_id']; ?>">
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label" for="doctor_id">Select Doctor / Specialist *</label>
                        <select name="doctor_id" id="doctor_id" class="form-select" required>
                            <option value="">-- Choose a Doctor --</option>
                            <?php foreach ($all_doctors as $doc): ?>
                                <option value="<?php echo $doc['doctor_id']; ?>" <?php echo ($selected_doctor_id === (int)$doc['doctor_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($doc['doctor_name']); ?> (<?php echo e($doc['specialty_name']); ?> - Rs. <?php echo number_format($doc['fee'], 0); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="appointment_date">Appointment Date *</label>
                        <input 
                            type="date" 
                            name="appointment_date" 
                            id="appointment_date" 
                            class="form-control" 
                            min="<?php echo date('Y-m-d'); ?>" 
                            value="<?php echo e($appointment_date); ?>" 
                            required
                        >
                        <div class="form-text">Select today or an upcoming clinic date.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="appointment_time">Consultation Time Slot *</label>
                        <select name="appointment_time" id="appointment_time" class="form-select" required>
                            <option value="">-- Choose Time Slot --</option>
                            <?php foreach ($available_slots as $time_val => $label): ?>
                                <option value="<?php echo $time_val; ?>" <?php echo ($appointment_time === $time_val) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Each slot is 45 minutes of consultation.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason">Reason for Visit / Symptoms (Optional)</label>
                    <textarea 
                        name="reason" 
                        id="reason" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Briefly describe your symptoms or routine checkup reason..."
                    ><?php echo e($reason); ?></textarea>
                </div>

                <div style="display: flex; gap: 14px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" id="btn-confirm-booking" style="flex: 2;">
                        Confirm & Book Appointment
                    </button>
                    <a href="index.php" class="btn btn-outline" style="flex: 1; text-align: center;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

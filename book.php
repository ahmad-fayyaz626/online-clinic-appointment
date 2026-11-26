<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_role('patient');
require_once __DIR__ . '/includes/clinic_queue.php';

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
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$selected_doctor_id = filter_input($submitted ? INPUT_POST : INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;

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

$appointment_date = is_string($_POST['appointment_date'] ?? null) ? $_POST['appointment_date'] : date('Y-m-d');
$appointment_time = is_string($_POST['appointment_time'] ?? null) ? $_POST['appointment_time'] : '';
$reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';

if ($submitted) {
    $parsedDate = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $appointment_date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $appointment_date) : false;
    if (!$selected_doctor) {
        $errors[] = 'Select a doctor.';
    }
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $appointment_date || $appointment_date < date('Y-m-d')) {
        $errors[] = 'Choose a valid appointment date from today onward.';
    }
    if (!isset($available_slots[$appointment_time])) {
        $errors[] = 'Choose a consultation time.';
    }
    if (mb_strlen($reason) > 255) {
        $errors[] = 'Keep the reason for your visit to 255 characters or fewer.';
    }

    if ($errors === []) {
        try {
            (new ClinicQueue($pdo))->book($patient_id, $selected_doctor_id, $appointment_date, $appointment_time, $reason);
            set_flash('success', 'Appointment booked. Bring your appointment token to reception to check in.');
            header('Location: dashboard.php', true, 303);
            exit;
        } catch (QueueRuleViolation $error) {
            $errors[] = $error->getMessage();
        } catch (PDOException $error) {
            report_app_error($error);
            $errors[] = 'The appointment could not be booked. Please try again.';
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
                <?= csrf_field() ?>
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
                        <div class="form-text">When you arrive, give your appointment token to reception.</div>
                    </div>
                </div>
                <p class="form-text" id="slot-feedback" role="status"></p>

                <div class="form-group">
                    <label class="form-label" for="reason">Reason for Visit / Symptoms (Optional)</label>
                    <textarea 
                        name="reason"
                        maxlength="255"
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

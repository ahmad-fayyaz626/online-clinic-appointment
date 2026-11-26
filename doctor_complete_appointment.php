<?php
require_once __DIR__ . '/config/db.php';

// Access control: Doctor only
require_doctor();
require_once __DIR__ . '/includes/clinic_queue.php';
$queue = new ClinicQueue($pdo);

$user_id = $_SESSION['user_id'];
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$appointment_id = filter_input($submitted ? INPUT_POST : INPUT_GET, $submitted ? 'appointment_id' : 'id', FILTER_VALIDATE_INT) ?: 0;

if ($appointment_id <= 0) {
    set_flash('error', 'Invalid appointment selected.');
    header('Location: doctor_dashboard.php');
    exit;
}

$doctor_id = $queue->doctorId($user_id);

// 2. Fetch appointment details and verify ownership
$apt_stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.consultation_started_at,
        a.reason,
        a.doctor_notes,
        p.full_name AS patient_name,
        p.email AS patient_email,
        p.phone AS patient_phone
    FROM appointments a
    INNER JOIN users p ON a.patient_id = p.id
    WHERE a.id = ? AND a.doctor_id = ?
    LIMIT 1
");
$apt_stmt->execute([$appointment_id, $doctor_id]);
$appointment = $apt_stmt->fetch();

if (!$appointment) {
    set_flash('error', 'Appointment not found or access denied.');
    header('Location: doctor_dashboard.php');
    exit;
}

if (!$submitted && ($appointment['status'] !== 'booked' || $appointment['consultation_started_at'] === null)) {
    set_flash('error', 'Open the consultation in progress from the live queue.');
    header('Location: doctor_dashboard.php');
    exit;
}

$errors = [];
$doctor_notes = $appointment['doctor_notes'] ?? '';

if ($submitted) {
    $doctor_notes = is_string($_POST['doctor_notes'] ?? null) ? trim($_POST['doctor_notes']) : '';
    if (mb_strlen($doctor_notes) > 10000) {
        $errors[] = 'Keep the consultation notes to 10,000 characters or fewer.';
    } else {
        try {
            $queue->finish($appointment_id, $doctor_id, $doctor_notes);
            set_flash('success', 'Consultation completed. The live queue has been updated.');
            header('Location: doctor_dashboard.php', true, 303);
            exit;
        } catch (QueueRuleViolation $error) {
            set_flash('error', $error->getMessage());
            header('Location: doctor_dashboard.php', true, 303);
            exit;
        } catch (PDOException $error) {
            report_app_error($error);
            $errors[] = 'The consultation could not be completed. Your notes are still on this form.';
        }
    }
}

$time_obj = DateTime::createFromFormat('H:i:s', $appointment['appointment_time']);
$formatted_time = $time_obj ? $time_obj->format('g:i A') : $appointment['appointment_time'];
$formatted_date = date('l, d M Y', strtotime($appointment['appointment_date']));

$page_title = 'Complete Consultation';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 650px; margin: 20px auto;">
    <div style="margin-bottom: 20px;">
        <a href="doctor_dashboard.php" class="btn btn-outline btn-sm">
            &larr; Back to Schedule
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 0;">
                Complete Patient Consultation
            </h2>
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

            <!-- Patient Summary Card -->
            <div style="background-color: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;">
                <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary); margin-bottom: 4px;">
                    <?php echo e($appointment['patient_name']); ?>
                </div>
                <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 10px;">
                    📞 <?php echo e($appointment['patient_phone']); ?> &middot; ✉️ <?php echo e($appointment['patient_email']); ?>
                </div>
                <div style="display: flex; gap: 16px; font-size: 0.88rem; color: var(--text-secondary); flex-wrap: wrap;">
                    <div>📅 <strong>Date:</strong> <?php echo $formatted_date; ?></div>
                    <div>⏰ <strong>Time Slot:</strong> <?php echo $formatted_time; ?></div>
                </div>
                <?php if (!empty($appointment['reason'])): ?>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); font-size: 0.88rem; color: var(--text-secondary);">
                        <strong>Patient's Visit Reason:</strong> <?php echo e($appointment['reason']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Complete Consultation Form -->
            <form action="doctor_complete_appointment.php" method="POST" id="complete-consultation-form">
                <?= csrf_field() ?>
                <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['appointment_id']; ?>">

                <div class="form-group">
                    <label class="form-label" for="doctor_notes">
                        Clinical Diagnosis & Prescription Notes
                    </label>
                    <textarea 
                        id="doctor_notes" 
                        name="doctor_notes"
                        maxlength="10000"
                        rows="5" 
                        class="form-control" 
                        placeholder="Enter the findings and instructions for this visit."
                        style="resize: vertical; font-family: inherit;"
                    ><?php echo e($doctor_notes); ?></textarea>
                    <div class="form-text">
                        These notes will be displayed on the patient's dashboard for their medical reference.
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="background-color: var(--success); border-color: var(--success); flex: 1;" id="btn-save-consultation">
                        ✓ Complete Consultation & Save Notes
                    </button>
                    <a href="doctor_dashboard.php" class="btn btn-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

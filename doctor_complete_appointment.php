<?php
require_once __DIR__ . '/config/db.php';

// Access control: Doctor only
require_doctor();

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0);

if ($appointment_id <= 0) {
    set_flash('error', 'Invalid appointment selected.');
    header('Location: doctor_dashboard.php');
    exit;
}

// 1. Fetch doctor ID for currently logged-in user
$doctor_stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$doctor_stmt->execute([$user_id]);
$doctor = $doctor_stmt->fetch();

if (!$doctor) {
    set_flash('error', 'Doctor profile not found.');
    header('Location: doctor_dashboard.php');
    exit;
}

$doctor_id = (int)$doctor['id'];

// 2. Fetch appointment details and verify ownership
$apt_stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
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

$errors = [];
$doctor_notes = $appointment['doctor_notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_notes = trim($_POST['doctor_notes'] ?? '');

    try {
        $update_stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'completed', doctor_notes = ? 
            WHERE id = ? AND doctor_id = ?
        ");
        $update_stmt->execute([$doctor_notes, $appointment_id, $doctor_id]);

        set_flash('success', "Consultation for {$appointment['patient_name']} completed successfully with prescription recorded!");
        header('Location: doctor_dashboard.php');
        exit;
    } catch (PDOException $e) {
        $errors[] = 'Database error updating appointment: ' . $e->getMessage();
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
                <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['appointment_id']; ?>">

                <div class="form-group">
                    <label class="form-label" for="doctor_notes">
                        Clinical Diagnosis & Prescription Notes
                    </label>
                    <textarea 
                        id="doctor_notes" 
                        name="doctor_notes" 
                        rows="5" 
                        class="form-control" 
                        placeholder="Write clinical findings, diagnosis, and prescription (e.g. Tab Paracetamol 500mg 1+0+1 after meals for 3 days. Drink plenty of water and rest.)"
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

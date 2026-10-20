<?php
require_once __DIR__ . '/config/db.php';

// Access control: Admin only
require_admin();

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doctor_id <= 0) {
    set_flash('error', 'Invalid doctor record requested.');
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch doctor details
$stmt = $pdo->prepare("
    SELECT 
        d.id AS doctor_id,
        d.user_id,
        d.specialty_id,
        d.qualification,
        d.experience_years,
        d.fee,
        u.full_name,
        u.email,
        u.phone
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
    LIMIT 1
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    set_flash('error', 'Doctor not found.');
    header('Location: admin_dashboard.php');
    exit;
}

$specialties = $pdo->query("SELECT id, name FROM specialties ORDER BY name ASC")->fetchAll();

$errors = [];
$full_name        = $doctor['full_name'];
$phone            = $doctor['phone'];
$specialty_id     = (int)$doctor['specialty_id'];
$qualification    = $doctor['qualification'];
$experience_years = (int)$doctor['experience_years'];
$fee              = (float)$doctor['fee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $specialty_id     = (int)($_POST['specialty_id'] ?? 0);
    $qualification    = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $fee              = (float)($_POST['fee'] ?? 0);
    $new_password     = $_POST['new_password'] ?? '';

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Doctor full name is required.';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }
    if ($specialty_id <= 0) {
        $errors[] = 'Please select a valid specialty.';
    }
    if (empty($qualification)) {
        $errors[] = 'Qualification details are required.';
    }
    if ($experience_years < 0) {
        $errors[] = 'Experience years must be 0 or greater.';
    }
    if ($fee <= 0) {
        $errors[] = 'Consultation fee must be greater than zero.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update users table
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    throw new Exception('New password must be at least 6 characters long.');
                }
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $u_stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?");
                $u_stmt->execute([$full_name, $phone, $hashed, $doctor['user_id']]);
            } else {
                $u_stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $u_stmt->execute([$full_name, $phone, $doctor['user_id']]);
            }

            // 2. Update doctors table
            $d_stmt = $pdo->prepare("
                UPDATE doctors 
                SET specialty_id = ?, qualification = ?, experience_years = ?, fee = ? 
                WHERE id = ?
            ");
            $d_stmt->execute([$specialty_id, $qualification, $experience_years, $fee, $doctor_id]);

            $pdo->commit();

            set_flash('success', "Doctor {$full_name}'s profile updated successfully.");
            header('Location: admin_dashboard.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Error updating doctor profile: ' . $e->getMessage();
        }
    }
}

$page_title = 'Edit Doctor Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 650px; margin: 20px auto;">
    <div style="margin-bottom: 20px;">
        <a href="admin_dashboard.php" class="btn btn-outline btn-sm">
            &larr; Back to Admin Dashboard
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 0;">
                Edit Doctor Profile
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

            <form action="admin_doctor_edit.php?id=<?php echo $doctor_id; ?>" method="POST" id="edit-doctor-form">
                <div class="form-group">
                    <label class="form-label" for="full_name">Doctor Full Name</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        value="<?php echo e($full_name); ?>" 
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Email (Read Only)</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            value="<?php echo e($doctor['email']); ?>" 
                            disabled 
                            style="background-color: #f1f5f9; cursor: not-allowed;"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Contact Phone</label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            value="<?php echo e($phone); ?>" 
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="specialty_id">Medical Specialty</label>
                    <select id="specialty_id" name="specialty_id" class="form-select" required>
                        <?php foreach ($specialties as $sp): ?>
                            <option value="<?php echo (int)$sp['id']; ?>" <?php echo ($specialty_id === (int)$sp['id']) ? 'selected' : ''; ?>>
                                <?php echo e($sp['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="qualification">Qualifications & Degrees</label>
                    <input 
                        type="text" 
                        id="qualification" 
                        name="qualification" 
                        class="form-control" 
                        value="<?php echo e($qualification); ?>" 
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="experience_years">Experience (Years)</label>
                        <input 
                            type="number" 
                            id="experience_years" 
                            name="experience_years" 
                            class="form-control" 
                            min="0" 
                            value="<?php echo e($experience_years); ?>" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fee">Consultation Fee (PKR)</label>
                        <input 
                            type="number" 
                            id="fee" 
                            name="fee" 
                            class="form-control" 
                            min="100" 
                            step="50" 
                            value="<?php echo e($fee); ?>" 
                            required
                        >
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px; padding-top: 15px; border-top: 1px dashed var(--border);">
                    <label class="form-label" for="new_password">New Password (Leave blank to keep existing)</label>
                    <div class="password-input-group">
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-control" 
                            placeholder="Enter new password if changing"
                        >
                        <button type="button" class="password-toggle-btn" data-target="new_password" aria-label="Show password" tabindex="-1">
                            <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-block">
                        Update Doctor Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

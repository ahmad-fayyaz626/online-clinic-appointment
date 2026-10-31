<?php
require_once __DIR__ . '/config/db.php';

// Access control: Admin only
require_admin();

// Fetch specialties for dropdown
$specialties = $pdo->query("SELECT id, name FROM specialties ORDER BY name ASC")->fetchAll();

$errors = [];
$full_name        = '';
$email            = '';
$phone            = '';
$specialty_id     = '';
$qualification    = '';
$experience_years = '';
$fee              = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $phone            = trim($_POST['phone'] ?? '');
    $specialty_id     = (int)($_POST['specialty_id'] ?? 0);
    $qualification    = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $fee              = (float)($_POST['fee'] ?? 0);

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Doctor full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }
    if ($specialty_id <= 0) {
        $errors[] = 'Please select a valid medical specialty.';
    }
    if (empty($qualification)) {
        $errors[] = 'Qualification details are required (e.g. MBBS, FCPS).';
    }
    if ($experience_years < 0) {
        $errors[] = 'Experience years must be 0 or greater.';
    }
    if ($fee <= 0) {
        $errors[] = 'Consultation fee must be greater than zero.';
    }

    // Check if email already exists
    if (empty($errors)) {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            $errors[] = 'A user account with this email address already exists.';
        }
    }

    // Insert into database with transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Create user account with 'doctor' role
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, role) 
                VALUES (?, ?, ?, ?, 'doctor')
            ");
            $user_stmt->execute([$full_name, $email, $hashed_password, $phone]);
            $new_user_id = (int)$pdo->lastInsertId();

            // 2. Create doctor profile
            $doc_stmt = $pdo->prepare("
                INSERT INTO doctors (user_id, specialty_id, qualification, experience_years, fee)
                VALUES (?, ?, ?, ?, ?)
            ");
            $doc_stmt->execute([$new_user_id, $specialty_id, $qualification, $experience_years, $fee]);

            $pdo->commit();

            set_flash('success', "Doctor {$full_name} added to the clinic successfully!");
            header('Location: admin_dashboard.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Database error creating doctor: ' . $e->getMessage();
        }
    }
}

$page_title = 'Add New Doctor';
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
                Add New Doctor to Clinic
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

            <form action="admin_doctor_add.php" method="POST" id="add-doctor-form">
                <div class="form-group">
                    <label class="form-label" for="full_name">Doctor Full Name</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="e.g. Dr. Haris Mahmood" 
                        value="<?php echo e($full_name); ?>" 
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="email">Login Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="e.g. haris@clinic.com" 
                            value="<?php echo e($email); ?>" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Account Password</label>
                        <div class="password-input-group">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Min 6 characters" 
                                required
                            >
                            <button type="button" class="password-toggle-btn" data-target="password" onclick="togglePasswordVisibility('password', this, event)" aria-label="Show password" title="Show password" tabindex="-1">
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
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="phone">Contact Phone</label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            placeholder="e.g. 03001234567" 
                            value="<?php echo e($phone); ?>" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="specialty_id">Medical Specialty</label>
                        <select id="specialty_id" name="specialty_id" class="form-select" required>
                            <option value="">-- Choose Specialty --</option>
                            <?php foreach ($specialties as $sp): ?>
                                <option value="<?php echo (int)$sp['id']; ?>" <?php echo ((int)$specialty_id === (int)$sp['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($sp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="qualification">Qualifications & Degrees</label>
                    <input 
                        type="text" 
                        id="qualification" 
                        name="qualification" 
                        class="form-control" 
                        placeholder="e.g. MBBS, FCPS (Cardiology), MRCP" 
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
                            max="60" 
                            placeholder="e.g. 8" 
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
                            placeholder="e.g. 2000" 
                            value="<?php echo e($fee); ?>" 
                            required
                        >
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-block" id="btn-save-doctor">
                        Save Doctor Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

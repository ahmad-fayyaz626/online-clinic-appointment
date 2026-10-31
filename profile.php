<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_login();

$user_id = (int)$_SESSION['user_id'];

// 1. Fetch current user data
$stmt = $pdo->prepare("SELECT id, full_name, email, phone, role, password FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    set_flash('error', 'User account not found.');
    header('Location: login.php');
    exit;
}

// 2. If user is doctor, fetch doctor details
$doctor_info = null;
if ($user_data['role'] === 'doctor') {
    $doc_stmt = $pdo->prepare("
        SELECT d.id AS doctor_id, d.qualification, d.experience_years, d.fee, s.name AS specialty_name
        FROM doctors d
        INNER JOIN specialties s ON d.specialty_id = s.id
        WHERE d.user_id = ?
        LIMIT 1
    ");
    $doc_stmt->execute([$user_id]);
    $doctor_info = $doc_stmt->fetch();
}

$profile_errors  = [];
$password_errors = [];

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name)) {
        $profile_errors[] = 'Full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profile_errors[] = 'A valid email address is required.';
    } else {
        // Check if email is already taken by another account
        $check_email = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1");
        $check_email->execute([$email, $user_id]);
        if ($check_email->fetch()) {
            $profile_errors[] = 'This email address is already in use by another account.';
        }
    }
    if (empty($phone)) {
        $profile_errors[] = 'Phone number is required.';
    }

    // Optional fee update if doctor
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : null;
    if ($user_data['role'] === 'doctor' && $fee !== null && $fee <= 0) {
        $profile_errors[] = 'Consultation fee must be greater than zero.';
    }

    if (empty($profile_errors)) {
        try {
            $pdo->beginTransaction();

            $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $update_stmt->execute([$full_name, $email, $phone, $user_id]);

            if ($user_data['role'] === 'doctor' && $fee !== null) {
                $doc_update = $pdo->prepare("UPDATE doctors SET fee = ? WHERE user_id = ?");
                $doc_update->execute([$fee, $user_id]);
            }

            $pdo->commit();

            // Refresh session details
            $_SESSION['user_name']  = $full_name;
            $_SESSION['user_email'] = $email;

            set_flash('success', 'Your profile details and email have been updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $profile_errors[] = 'Database error updating profile: ' . $e->getMessage();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password)) {
        $password_errors[] = 'Please enter your current password.';
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $password_errors[] = 'Your current password is incorrect.';
    }

    if (empty($new_password) || strlen($new_password) < 6) {
        $password_errors[] = 'New password must be at least 6 characters long.';
    }

    if ($new_password !== $confirm_password) {
        $password_errors[] = 'New password and confirmation do not match.';
    }

    if (empty($password_errors)) {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pwd_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $pwd_stmt->execute([$hashed, $user_id]);

            set_flash('success', 'Password changed successfully! Please keep your new password safe.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $password_errors[] = 'Database error changing password: ' . $e->getMessage();
        }
    }
}

$page_title = 'Account Profile Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 780px; margin: 10px auto;">
    <div class="page-header" style="margin-bottom: 24px;">
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin: 0 0 6px 0;">
            Account Settings
        </h1>
        <p style="color: var(--text-secondary); margin: 0;">
            Manage your personal profile information and update your account password.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
        <!-- Card 1: Personal Details -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin: 0;">
                    Personal Information
                </h2>
                <span class="badge badge-specialty" style="text-transform: capitalize;">
                    Role: <?php echo e($user_data['role']); ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (!empty($profile_errors)): ?>
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($profile_errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="profile.php" method="POST" id="profile-info-form">
                    <input type="hidden" name="action" value="update_profile">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                class="form-control" 
                                value="<?php echo e($user_data['full_name']); ?>" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                class="form-control" 
                                value="<?php echo e($user_data['phone']); ?>" 
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address (Login Username)</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            value="<?php echo e($user_data['email']); ?>" 
                            required
                        >
                        <div class="form-text">You can change your email address anytime. It is used to log in to your account.</div>
                    </div>

                    <?php if ($doctor_info): ?>
                        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px; margin: 16px 0;">
                            <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">Medical Specialist Information</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.9rem;">
                                <div>Specialty: <strong><?php echo e($doctor_info['specialty_name']); ?></strong></div>
                                <div>Qualification: <strong><?php echo e($doctor_info['qualification']); ?></strong></div>
                                <div>Experience: <strong><?php echo (int)$doctor_info['experience_years']; ?> Years</strong></div>
                                <div>
                                    <label class="form-label" for="fee" style="margin-bottom: 4px;">Consultation Fee (PKR)</label>
                                    <input 
                                        type="number" 
                                        id="fee" 
                                        name="fee" 
                                        class="form-control" 
                                        min="100" 
                                        step="50" 
                                        value="<?php echo (float)$doctor_info['fee']; ?>"
                                        style="max-width: 180px;"
                                    >
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" id="btn-save-profile">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card 2: Change Password -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin: 0;">
                    Change Password
                </h2>
            </div>
            <div class="card-body">
                <?php if (!empty($password_errors)): ?>
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($password_errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="profile.php" method="POST" id="change-password-form">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <div class="password-input-group">
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-control" 
                                placeholder="Enter current password" 
                                required
                            >
                            <button type="button" class="password-toggle-btn" data-target="current_password" onclick="togglePasswordVisibility('current_password', this, event)" aria-label="Show password" title="Show password" tabindex="-1">
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

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password</label>
                            <div class="password-input-group">
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="form-control" 
                                    placeholder="Min 6 characters" 
                                    required
                                >
                                <button type="button" class="password-toggle-btn" data-target="new_password" onclick="togglePasswordVisibility('new_password', this, event)" aria-label="Show password" title="Show password" tabindex="-1">
                                    <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <div class="password-input-group">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    placeholder="Re-enter new password" 
                                    required
                                >
                                <button type="button" class="password-toggle-btn" data-target="confirm_password" onclick="togglePasswordVisibility('confirm_password', this, event)" aria-label="Show password" title="Show password" tabindex="-1">
                                    <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
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

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-outline" id="btn-change-password">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

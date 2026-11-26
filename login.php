<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in based on role
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'patient';
    if ($role === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif ($role === 'doctor') {
        header('Location: doctor_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($email === '' || $password === '' || strlen($email) > 150 || strlen($password) > 4096) {
        $error = 'Please enter both your email and password.';
    } else {
        // Standard user query
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $hash = $user['password'] ?? '$2y$10$UVAHa0L4caQ9SuWprFf.La2vlpaOIAdfE0Z0c4WcKlNyouOAIAkHK';
        $demoPassword = $production && in_array($password, ['Admin@123', 'Doctor@123', 'Patient@123'], true);
        if (password_verify($password, $hash) && $user && !$demoPassword) {
            // Authentication successful: regenerate session to prevent session fixation
            session_regenerate_id(true);
            $_SESSION = [];

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['auth_version'] = hash('sha256', $user['password']);

            // Role-based destination redirect
            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } elseif ($user['role'] === 'doctor') {
                header('Location: doctor_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password combination.';
        }
    }
}

$page_title = 'Account Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="card">
        <div class="card-body">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account (Patient, Doctor, or Admin)</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" id="login-error-alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo e($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate id="login-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="e.g. ali@example.com" 
                        value="<?php echo e($email); ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-input-group">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password" 
                            required
                        >
                        <button type="button" class="password-toggle-btn" data-target="password" onclick="togglePasswordVisibility('password', this, event)" aria-label="Show password" title="Show password" tabindex="-1">
                            <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btn-submit-login">
                    Sign In
                </button>
            </form>

            <div class="auth-footer">
                Don't have a patient account yet? <a href="signup.php">Register Here</a>
            </div>

            <!-- Demo Test Credentials Box -->
            <div style="margin-top: 24px; padding: 14px 16px; background: #f8fafc; border: 1px dashed var(--border); border-radius: var(--radius-md); font-size: 0.85rem;">
                <div style="font-weight: 700; color: var(--primary); margin-bottom: 8px;">🔑 Quick Test Credentials:</div>
                <div style="display: flex; flex-direction: column; gap: 6px; color: var(--text-secondary);">
                    <div><strong>Admin:</strong> <code>admin@clinic.com</code> &middot; <code>Admin@123</code></div>
                    <div><strong>Doctor:</strong> <code>sara.khan@clinic.com</code> &middot; <code>Doctor@123</code></div>
                    <div><strong>Patient:</strong> <code>ali@example.com</code> &middot; <code>Patient@123</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

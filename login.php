<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        // Query user by email
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Authentication successful: regenerate session to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            set_flash('success', "Welcome back, " . $user['full_name'] . "!");
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email or password combination.';
        }
    }
}

$page_title = 'Patient Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="card">
        <div class="card-body">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to manage your clinic appointments</p>
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
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter your password" 
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btn-submit-login">
                    Sign In
                </button>
            </form>

            <div class="auth-footer">
                Don't have an account yet? <a href="signup.php">Register Here</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

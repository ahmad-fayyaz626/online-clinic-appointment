<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$full_name = '';
$email     = '';
$phone     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm_p = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = 'Please provide a valid phone number.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_p) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if email already registered
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // Insert user into database
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, role) 
                VALUES (?, ?, ?, ?, 'patient')
            ");
            $insert_stmt->execute([$full_name, $email, $hashed_password, $phone]);

            set_flash('success', 'Registration successful! Please login with your credentials.');
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error during registration: ' . $e->getMessage();
        }
    }
}

$page_title = 'Patient Registration';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="card">
        <div class="card-body">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Register as a patient to book clinic appointments</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="signup.php" method="POST" novalidate id="signup-form">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="e.g. Ali Khan" 
                        value="<?php echo e($full_name); ?>" 
                        required
                    >
                </div>

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
                    <label class="form-label" for="phone">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-control" 
                        placeholder="e.g. 03001234567" 
                        value="<?php echo e($phone); ?>" 
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
                        placeholder="Minimum 6 characters" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control" 
                        placeholder="Re-enter your password" 
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btn-submit-signup">
                    Register Account
                </button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Log In</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

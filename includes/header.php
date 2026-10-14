<?php
// Ensure config is loaded
require_once __DIR__ . '/../config/db.php';

$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Online Clinic Appointment Management System - Book appointments with top doctors easily and securely.">
    <title><?php echo isset($page_title) ? e($page_title) . ' - CarePulse Clinic' : 'CarePulse Clinic - Online Appointments'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="brand-logo" id="brand-logo">
                <div class="logo-badge">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div class="brand-text">CarePulse <span>Clinic</span></div>
            </a>

            <ul class="nav-links">
                <li>
                    <a href="index.php" class="nav-link <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>" id="nav-doctors">
                        Doctors
                    </a>
                </li>

                <?php if (is_logged_in()): ?>
                    <li>
                        <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" id="nav-appointments">
                            My Appointments
                        </a>
                    </li>
                    <li class="user-badge" id="user-badge">
                        <span class="user-avatar-initial"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                        <span><?php echo e($user['name']); ?></span>
                    </li>
                    <li>
                        <a href="logout.php" class="btn btn-outline btn-sm" id="btn-logout">
                            Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="login.php" class="nav-link <?php echo ($current_page === 'login.php') ? 'active' : ''; ?>" id="nav-login">
                            Login
                        </a>
                    </li>
                    <li>
                        <a href="signup.php" class="btn btn-primary btn-sm" id="nav-signup">
                            Create Account
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content">
    <div class="container">
        <?php
        // Display flash messages if any exist
        $success_msg = get_flash('success');
        $error_msg   = get_flash('error');
        $info_msg    = get_flash('info');
        ?>
        <?php if ($success_msg || $error_msg || $info_msg): ?>
            <div class="alert-container">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success" role="alert" id="alert-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <span><?php echo e($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-error" role="alert" id="alert-error">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?php echo e($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($info_msg): ?>
                    <div class="alert alert-info" role="alert" id="alert-info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span><?php echo e($info_msg); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

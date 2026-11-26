<?php
// Ensure config is loaded
require_once __DIR__ . '/../config/db.php';

$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();
$role = $user['role'] ?? 'guest';
$portalLinks = [
    'admin' => ['admin_dashboard.php' => 'Clinic operations', 'admin_specialties.php' => 'Specialties', 'index.php' => 'Doctor directory'],
    'doctor' => ['doctor_dashboard.php' => 'My schedule', 'index.php' => 'Doctor directory'],
    'patient' => ['index.php' => 'Find a doctor', 'dashboard.php' => 'My appointments'],
    'guest' => ['index.php' => 'Find a doctor', 'index.php#visiting' => 'Your visit'],
][$role];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f8fbfa">
    <meta name="description" content="Find a doctor, book a consultation and follow your appointment at CarePulse Clinic.">
    <title><?= isset($page_title) ? e($page_title) . ' | CarePulse Clinic' : 'CarePulse Clinic' ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="assets/css/clinic.css?v=<?= filemtime(__DIR__ . '/../assets/css/clinic.css') ?>">
</head>
<body class="clinic-<?= e($role) ?>">
<a class="clinic-skip" href="#main-content">Skip to main content</a>
<header class="clinic-header" data-clinic-header>
    <div class="container clinic-bar">
        <a href="index.php" class="brand-logo clinic-brand" id="brand-logo">
            <span class="logo-badge" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span class="brand-text">CarePulse <span>Clinic</span></span>
        </a>
        <button class="clinic-menu-toggle" type="button" aria-expanded="false" aria-controls="clinic-navigation" data-menu-toggle hidden>
            <span class="clinic-menu-icon" aria-hidden="true"></span><span>Menu</span>
        </button>
        <nav id="clinic-navigation" class="clinic-navigation" aria-label="Main navigation">
            <ul class="clinic-nav-links">
                <?php foreach ($portalLinks as $href => $label): ?>
                    <?php $active = $current_page === $href
                        || ($href === 'admin_dashboard.php' && strpos($current_page, 'admin_doctor_') === 0)
                        || ($href === 'doctor_dashboard.php' && $current_page === 'doctor_complete_appointment.php'); ?>
                    <li><a class="clinic-nav-link<?= $active ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $current_page === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="clinic-account">
                <?php if ($user): ?>
                    <a class="clinic-account-link<?= $current_page === 'profile.php' ? ' active' : '' ?>" href="profile.php"<?= $current_page === 'profile.php' ? ' aria-current="page"' : '' ?>>
                        <span class="clinic-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span>Profile</span>
                    </a>
                    <form action="logout.php" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm" id="btn-logout">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="clinic-nav-link<?= $current_page === 'login.php' ? ' active' : '' ?>" href="login.php"<?= $current_page === 'login.php' ? ' aria-current="page"' : '' ?>>Sign in</a>
                    <a href="signup.php" class="btn btn-primary"<?= $current_page === 'signup.php' ? ' aria-current="page"' : '' ?>>Create account</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<main class="main-content" id="main-content" tabindex="-1">
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

<?php
/**
 * Database Connection Configuration
 * Compatible with XAMPP (Windows) and LAMP (Linux)
 */

// Start session if not already started and headers not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database credentials (with fallback defaults)
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_name = getenv('DB_NAME') ?: 'clinic_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Automatic fallback for Linux / WSL environment credentials
    try {
        $pdo = new PDO($dsn, 'clinic_user', 'apna_password', $options);
    } catch (PDOException $e2) {
        die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
    }
}

    // Auto-migration: ensure doctor_notes column exists on appointments
    try {
        $col_check = $pdo->query("SHOW COLUMNS FROM `appointments` LIKE 'doctor_notes'")->fetch();
        if (!$col_check) {
            $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `doctor_notes` TEXT DEFAULT NULL AFTER `reason`");
        }
    } catch (Exception $e) {
        // Table not yet created or permissions restricted
    }

    // Auto-seed: ensure administrator account exists
    try {
        $admin_check = $pdo->query("SELECT id, password FROM `users` WHERE `email` = 'admin@clinic.com' LIMIT 1")->fetch();
        if (!$admin_check) {
            $admin_hash = password_hash('Admin@123', PASSWORD_BCRYPT);
            $seed_stmt = $pdo->prepare("
                INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`) 
                VALUES ('Clinic Admin', 'admin@clinic.com', ?, '03001239999', 'admin')
            ");
            $seed_stmt->execute([$admin_hash]);
        } else {
            // If admin exists but password needs to be verified/synced to Admin@123
            if (!password_verify('Admin@123', $admin_check['password'])) {
                $new_hash = password_hash('Admin@123', PASSWORD_BCRYPT);
                $update_pwd = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `email` = 'admin@clinic.com'");
                $update_pwd->execute([$new_hash]);
            }
        }
    } catch (Exception $e) {
        // Users table not yet created
    }

/**
 * Helper to safely escape HTML output
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helpers
 */
function set_flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Auth helper functions
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'patient'
    ];
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Please login to access this page.');
        header('Location: login.php');
        exit;
    }
}

function is_doctor() {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'doctor';
}

function is_admin() {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function require_doctor() {
    require_login();
    if (!is_doctor()) {
        set_flash('error', 'Access restricted to medical doctors only.');
        header('Location: index.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Access restricted to clinic administrators only.');
        header('Location: index.php');
        exit;
    }
}

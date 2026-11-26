<?php

$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^([A-Z][A-Z_0-9]*)=(.*)$/', trim($line), $entry) && getenv($entry[1]) === false) {
            $value = trim($entry[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
                $value = substr($value, 1, -1);
            }
            putenv($entry[1] . '=' . $value);
        }
    }
}

$production = getenv('APP_ENV') === 'production';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set(getenv('CLINIC_TIMEZONE') ?: 'Asia/Karachi');

function report_app_error(Throwable $error): void
{
    error_log(sprintf('CarePulse %s (%s) in %s:%d', get_class($error), $error->getCode(),
        basename($error->getFile()), $error->getLine()));
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $error): void {
        report_app_error($error);
        http_response_code(500);
        echo 'CarePulse encountered a problem. Please try again.';
    });

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $production || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        http_response_code(503);
        exit('CarePulse cannot start a session right now. Please try again shortly.');
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private, no-store');

    // Every browser POST in CarePulse uses a same-session request token.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (!is_string($_POST['csrf_token'] ?? null)
            || !isset($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
        http_response_code(403);
        exit('This form has expired. Go back, refresh the page and try again.');
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'clinic_db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASS');
if ($production && (!$dbUser || $dbUser === 'root' || $dbPassword === false || $dbPassword === '')) {
    throw new RuntimeException('Configure a dedicated database account for production.');
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser ?: 'root',
        $dbPassword === false ? '' : $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ',
        ]
    );
} catch (PDOException $error) {
    if (PHP_SAPI === 'cli') {
        throw $error;
    }
    report_app_error($error);
    http_response_code(503);
    exit('CarePulse is temporarily unavailable. Please try again shortly.');
}

if (!empty($_SESSION['user_id'])) {
    $account = $pdo->prepare('SELECT role, password FROM users WHERE id = ?');
    $account->execute([$_SESSION['user_id']]);
    $record = $account->fetch();
    if (!$record || $record['role'] !== ($_SESSION['user_role'] ?? null)
        || !hash_equals(hash('sha256', $record['password']), $_SESSION['auth_version'] ?? '')) {
        $_SESSION = [];
        session_regenerate_id(true);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            http_response_code(403);
            exit('Your session has changed. Sign in again and retry.');
        }
    }
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function new_password_is_valid($password): bool
{
    return is_string($password) && mb_strlen($password) >= 12 && strlen($password) <= 72
        && !str_contains($password, chr(0));
}

function csrf_field(): string
{
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function set_flash($type, $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type)
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please sign in to access this page.');
        header('Location: login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        http_response_code(403);
        exit('This page is unavailable for your account.');
    }
}

function is_doctor(): bool
{
    return is_logged_in() && $_SESSION['user_role'] === 'doctor';
}

function is_admin(): bool
{
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

function require_doctor(): void
{
    require_role('doctor');
}

function require_admin(): void
{
    require_role('admin');
}

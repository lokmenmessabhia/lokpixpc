<?php
session_start();
include 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$email = '';
$password = '';
$error = '';

// Fetch site settings
$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
    }
}

// Function to format font family for Google Fonts URL
function formatFontForURL($font) {
    return str_replace(' ', '+', $font);
}

$font_family = $settings['font_family'] ?? 'Montserrat';

// Auto-login with remember_me cookie
if (!isset($_SESSION['loggedin']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];

    $stmt = $pdo->prepare("SELECT * FROM remember_me_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tokenData) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$tokenData['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);
            header("Location: index.php");
            exit;
        }
    }
    setcookie('remember_me', '', time() - 3600, '/');
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper: Rate limit login
function checkRateLimit($ip) {
    global $pdo;

    $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);

    if ($stmt->fetchColumn() >= 5) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$ip]);
    return true;
}

// Helper: Rate limit password reset
function checkResetAttempts($email) {
    global $pdo;

    $pdo->prepare("DELETE FROM password_reset_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetchColumn() >= 3) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO password_reset_attempts (email, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$email]);
    return true;
}

// Process POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else if (isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
        // Forgot Password
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            exit;
        }

        if (strlen($email) > 255) {
            echo json_encode(['status' => 'error', 'message' => 'Email address is too long']);
            exit;
        }

        if (!checkResetAttempts($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Too many password reset attempts. Try later.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $reset_token = bin2hex(random_bytes(32));
                $reset_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
                $stmt->execute([$reset_token, $reset_expiry, $email]);

                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'lokmen13.messabhia@gmail.com';
                $mail->Password = 'dfbk qkai wlax rscb';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('lokmen13.messabhia@gmail.com', 'EcoTech Support');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Click the following link to reset your password:<br><a href='{$reset_link}'>{$reset_link}</a><br>This link will expire in 24 hours.";
                $mail->AltBody = "Click to reset your password: {$reset_link}";

                $mail->send();
                echo json_encode(['status' => 'success', 'message' => 'Reset link sent to your email.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Email not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Mail error: ' . $e->getMessage()]);
        }
        exit;
    } else {
        // Login
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember-me']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else if (strlen($email) > 255) {
            $error = "Email is too long.";
        } else if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (isset($settings['maintenance_mode']) && intval($settings['maintenance_mode']) === 1) {
                    $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
                    $stmtAdmin->execute([$email]);
                    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

                    if (!$admin) {
                        echo "The site is currently under maintenance. Please try again later.";
                        exit;
                    }
                }

                $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$_SERVER['REMOTE_ADDR']]);

                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                session_regenerate_id(true);

                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("INSERT INTO remember_me_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                    $stmt->execute([$user['id'], $token]);
                    setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/', "", false, true);
                }

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= formatFontForURL($font_family) ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <?php if (!empty($settings['header_scripts'])): ?>
        <?= $settings['header_scripts'] ?>
    <?php endif; ?>
   <style>
       /* --- CSS Reset & Base --- */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: var(--font-family);
}

body {
    font-family: var(--font-family);
    line-height: 1.5;
    background-color: var(--header-bg);
    color: var(--header-text);
}

a {
    text-decoration: none;
    color: inherit;
}

button {
    cursor: pointer;
    font-family: inherit;
    border: none;
    background: none;
}

ul,
li {
    list-style: none;
}

/* --- Color Variables (White Mode) --- */
:root {
    --header-bg: #FFFFFF;
    --header-text: #343a40;
    --header-text-secondary: #6c757d;
    --header-accent: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
    --header-accent-hover: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
    --header-accent-rgb: <?= implode(', ', sscanf($settings['primary_color'] ?? '#0d6efd', '#%02x%02x%02x')) ?>;
    --header-border: #dee2e6;
    --dropdown-bg: #FFFFFF;
    --dropdown-border: #dee2e6;
    --dropdown-hover-bg: #f8f9fa;
    --dropdown-shadow: rgba(0, 0, 0, 0.1);
    --mega-menu-grid-bg: #FFFFFF;
    --mega-menu-grid-bg-hover: #f8f9fa;
    --mega-menu-heading-color: #495057;

    --button-radius: 4px;
    --focus-ring-color: rgba(var(--header-accent-rgb), 0.25);

    --search-bg: #FFFFFF;
    --search-border: #ced4da;
    --search-focus-border: #86b7fe;
    --search-placeholder-text: #6c757d;

    --notification-bg: #dc3545;
    --notification-text: #FFFFFF;

    --warning-bg: #FFF3CD;
    --warning-text: #664d03;
    --warning-border: #FFEEBA;
    --warning-link: #523e02;
    
    /* Additional variables for the existing application */
    --background: #FFFFFF;
    --card-background: #FFFFFF;
    --text-primary: #343a40;
    --text-secondary: #6c757d;
    --primary-color: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
    --primary-hover: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
    --input-border: #dee2e6;
    --input-background: #FFFFFF;
    --error-color: #dc3545;
    --success-color: #22c55e;
    --font-family: '<?= $font_family ?>', system-ui, -apple-system, sans-serif;
}

/* Dark mode if enabled */
<?php if (($settings['theme_mode'] ?? 'light') === 'dark'): ?>
:root {
    --header-bg: #1a1a1a;
    --header-text: #f8f9fa;
    --header-text-secondary: #adb5bd;
    --header-border: #343a40;
    --dropdown-bg: #1a1a1a;
    --dropdown-border: #343a40;
    --dropdown-hover-bg: #2d2d2d;
    --dropdown-shadow: rgba(0, 0, 0, 0.3);
    --mega-menu-grid-bg: #1a1a1a;
    --mega-menu-grid-bg-hover: #2d2d2d;
    --mega-menu-heading-color: #e9ecef;
    --search-bg: #2d2d2d;
    --search-border: #495057;
    --search-focus-border: #86b7fe;
    --search-placeholder-text: #adb5bd;
    
    /* Additional variables for the existing application in dark mode */
    --background: #121212;
    --card-background: #1a1a1a;
    --text-primary: #f8f9fa;
    --text-secondary: #adb5bd;
    --input-border: #343a40;
    --input-background: #2d2d2d;
    --font-family: '<?= $font_family ?>', system-ui, -apple-system, sans-serif;
}
<?php endif; ?>

body {
    margin: 0;
    padding: 0.5rem;
    font-family: var(--font-family);
    background: var(--background);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(var(--header-accent-rgb), 0.05) 0%, transparent 20%),
        radial-gradient(circle at 90% 80%, rgba(var(--header-accent-rgb), 0.05) 0%, transparent 20%);
}

/* Header layout adjustments */
.header-top.guest-layout {
    justify-content: flex-start;
    gap: 1rem;
}

.header-top.guest-layout .search-container {
    margin-left: auto;
    margin-right: 1rem;
    width: 300px;
}

/* Side Menu - Restore scrolling */
.side-menu {
    position: fixed;
    top: 0;
    left: -100%;
    width: 380px;
    height: 100vh;
    background: var(--dropdown-bg);
    z-index: 1002;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px var(--dropdown-shadow);
    transition: left 0.3s ease;
    overflow-y: auto;
}

.side-menu-categories {
    padding: 8px;
    overflow-y: auto;
    max-height: calc(100vh - 200px);
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .header-top.guest-layout {
        flex-wrap: wrap;
    }

    .header-top.guest-layout .search-container {
        order: 2;
        width: 100%;
        margin: 0.5rem 0;
    }

    .header-top.guest-layout .auth-buttons {
        order: 3;
        margin-left: auto;
    }
}

.logo-container {
    position: relative;
    width: 100%;
    text-align: center;
    margin-bottom: 1.5rem;
    margin-top: 0.5rem;
}

.logo {
    width: 140px;
    height: auto;
}

.wrapper {
    background: var(--card-background);
    padding: 2rem;
    border-radius: 1.5rem;
    box-shadow: 0 20px 25px -5px var(--dropdown-shadow), 0 8px 10px -6px var(--dropdown-shadow);
    width: 100%;
    max-width: 400px;
    position: relative;
    overflow: hidden;
}

.wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(to right, var(--header-accent), #818cf8);
}

.title {
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 2.5rem;
    color: var(--text-primary);
    letter-spacing: -0.025em;
    font-family: var(--font-family);
}

.field {
    margin-bottom: 1.5rem;
    position: relative;
}

.field label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.925rem;
    font-family: var(--font-family);
}

.field input[type="email"],
.field input[type="password"],
.field input[type="text"] {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 1px solid var(--input-border);
    border-radius: 0.75rem;
    font-size: 1rem;
    box-sizing: border-box;
    transition: all 0.2s ease;
    background-color: var(--input-background);
    color: var(--text-primary);
    font-family: var(--font-family);
}

.field input:focus {
    outline: none;
    border-color: var(--header-accent);
    box-shadow: 0 0 0 4px var(--focus-ring-color);
}

.content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox input[type="checkbox"] {
    accent-color: var(--header-accent);
}

.checkbox label {
    color: var(--text-secondary);
    font-size: 0.925rem;
    font-family: var(--font-family);
}

.content a {
    color: var(--header-accent);
    text-decoration: none;
    font-size: 0.925rem;
    font-weight: 500;
    transition: color 0.2s ease;
    font-family: var(--font-family);
}

.content a:hover {
    color: var(--header-accent-hover);
}

.field input[type="submit"] {
    width: 100%;
    padding: 0.875rem 1.5rem;
    background: var(--header-accent);
    color: var(--notification-text);
    border: none;
    border-radius: 0.75rem;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.field input[type="submit"]:hover {
    background: var(--header-accent-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px var(--focus-ring-color), 0 2px 4px -2px var(--focus-ring-color);
}

.signup-link {
    text-align: center;
    margin-top: 1.5rem;
    color: var(--text-secondary);
    font-size: 0.925rem;
    font-family: var(--font-family);
}

.signup-link a {
    color: var(--header-accent);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
    font-family: var(--font-family);
}

.signup-link a:hover {
    color: var(--header-accent-hover);
}

.error {
    margin-bottom: 1.5rem;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    text-align: center;
    font-size: 0.925rem;
    font-weight: 500;
    color: var(--error-color);
    background: rgb(239 68 68 / 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
    animation: slideIn 0.3s ease-out;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
}

.modal-content {
    background: var(--card-background);
    margin: 15% auto;
    padding: 2.5rem;
    width: 90%;
    max-width: 440px;
    border-radius: 1.5rem;
    position: relative;
    box-shadow: 0 25px 50px -12px var(--dropdown-shadow);
}

.modal-content h2 {
    text-align: center;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 2rem;
    color: var(--text-primary);
    letter-spacing: -0.025em;
    font-family: var(--font-family);
}

.submit-btn {
    width: 100%;
    padding: 0.875rem 1.5rem;
    background: var(--header-accent);
    color: var(--notification-text);
    border: none;
    border-radius: 0.75rem;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
    margin-top: 1rem;
}

.submit-btn:hover {
    background: var(--header-accent-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px var(--focus-ring-color), 0 2px 4px -2px var(--focus-ring-color);
}

.submit-btn:disabled {
    background: var(--header-text-secondary);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.close {
    position: absolute;
    right: 1.5rem;
    top: 1.5rem;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: none;
    background: var(--background);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1.25rem;
}

.close:hover {
    background: var(--input-border);
    color: var(--text-primary);
}

#forgot-email {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 1px solid var(--input-border);
    border-radius: 0.75rem;
    font-size: 1rem;
    box-sizing: border-box;
    transition: all 0.2s ease;
    background-color: var(--input-background);
    color: var(--text-primary);
    font-family: var(--font-family);
    margin-bottom: 1rem;
}

#forgot-email:focus {
    outline: none;
    border-color: var(--header-accent);
    box-shadow: 0 0 0 4px var(--focus-ring-color);
}

.modal-content label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.925rem;
    font-family: var(--font-family);
}

.message {
    padding: 1rem 1.25rem;
    margin: 1rem 0;
    border-radius: 0.75rem;
    text-align: center;
    font-size: 0.925rem;
    font-weight: 500;
    animation: slideIn 0.3s ease-out;
}

.message.success {
    color: var(--success-color);
    background: rgb(34 197 94 / 0.08);
    border: 1px solid rgba(34, 197, 94, 0.1);
}

.message.error {
    color: var(--error-color);
    background: rgb(239 68 68 / 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 480px) {
    body {
        padding: 0.5rem;
    }
    
    .wrapper {
        padding: 1.5rem;
        border-radius: 1rem;
    }

    .logo-container {
        margin-bottom: 1.5rem;
        margin-top: 0.5rem;
    }

    .logo {
        width: 120px;
    }

    .title {
        font-size: 1.5rem;
        margin-bottom: 2rem;
    }

    .field {
        margin-bottom: 1.25rem;
    }

    .field input[type="email"],
    .field input[type="password"],
    .field input[type="text"],
    #forgot-email {
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
    }

    .content {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .checkbox {
        margin-bottom: 0.5rem;
    }

    .field input[type="submit"],
    .submit-btn {
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
    }

    .modal-content {
        margin: 5% auto;
        padding: 1.5rem;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-content h2 {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .close {
        right: 1rem;
        top: 1rem;
    }

    .message {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }

    .signup-link {
        font-size: 0.875rem;
    }
}

@media (max-width: 360px) {
    .wrapper {
        padding: 1.25rem;
    }

    .title {
        font-size: 1.25rem;
    }

    .field label,
    .checkbox label {
        font-size: 0.875rem;
    }

    .content a {
        font-size: 0.875rem;
    }
}

@media (max-height: 600px) and (orientation: landscape) {
    body {
        padding: 0.5rem;
    }

    .wrapper {
        margin: 0;
        padding: 1.25rem;
    }

    .modal-content {
        margin: 2% auto;
    }

    .title {
        margin-bottom: 1.5rem;
    }

    .field {
        margin-bottom: 1rem;
    }

    .logo-container {
        top: 1rem;
    }

    .logo {
        width: 100px;
    }
}

/* Update styles for the show password button */
#togglePassword {
    position: absolute;
    right: 12px;
    top: 35%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
}

#togglePassword img {
    width: 24px;
    height: 24px;
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

#togglePassword:hover img {
    opacity: 0.9;
}

.continue-without-registration {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--input-border);
        }

        .skip-registration-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.925rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .skip-registration-btn:hover {
            color: var(--text-primary);
            background: color-mix(in srgb, var(--primary-color) 5%, transparent);
        }

        .skip-registration-btn i {
            font-size: 0.875rem;
            transition: transform 0.2s ease;
        }

        .skip-registration-btn:hover i {
            transform: translateX(4px);
        }

        @media (max-width: 480px) {
            .continue-without-registration {
                margin-top: 1.25rem;
                padding-top: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <button id="theme-switch" aria-label="Toggle dark mode">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454z"/>
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12zm0-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM11 1h2v3h-2V1zm0 19h2v3h-2v-3zM3.515 4.929l1.414-1.414L7.05 5.636 5.636 7.05 3.515 4.93zM16.95 18.364l1.414-1.414 2.121 2.121-1.414 1.414-2.121-2.121zm2.121-14.85l1.414 1.415-2.121 2.121-1.414-1.414 2.121-2.121zM5.636 16.95l1.414 1.414-2.121 2.121-1.414-1.414 2.121-2.121zM23 11v2h-3v-2h3zM4 11v2H1v-2h3z"/>
        </svg>
    </button>
<!-- Add this after the title div in your HTML -->
<?php if (isset($settings['maintenance_mode']) && intval($settings['maintenance_mode']) === 1): ?>
    <div class="warning" style="background: var(--warning-bg); color: var(--warning-text); padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; border: 1px solid var(--warning-border);">
        <strong>Maintenance Mode Active:</strong> Only administrators can login at this time.
    </div>
<?php endif; ?>
    <div class="logo-container">
        <img src="logo (1).png" alt="EcoTech Logo" class="logo">
    </div>
    
    <div class="wrapper">
        <div class="title">Welcome Back</div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="field">
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" placeholder=" ">
                <label for="email">Email</label>
            </div>
            <div class="field">
                <input type="password" id="password" name="password" required placeholder=" ">
                <label for="password">Password</label>
                <button type="button" id="togglePassword" onclick="togglePasswordVisibility()">
                    <img src="visibility_24dp_00000_FILL0_wght400_GRAD0_opsz24.png" alt="Show password" class="show-password">
                    <img src="visibility_off_24dp_00000_FILL0_wght400_GRAD0_opsz24.png" alt="Hide password" class="hide-password" style="display: none;">
                </button>
            </div>
            <div class="content">
                <div class="checkbox">
                    <input type="checkbox" id="remember-me" name="remember-me">
                    <label for="remember-me">Remember me</label>
                </div>
                <a href="#" onclick="showForgotPassword()" style="color: #4682B4;">Forgot Password?</a>
            </div>
            <div class="field">
                <input type="submit" value="Login">
            </div>
            <div class="signup-link">
                Don't have an account? <a href="sign-up.php">Sign Up</a>
            </div>
            
            <div class="continue-without-registration">
                <a href="index.php" class="skip-registration-btn">
                    <i class="fas fa-arrow-right"></i> Continue without Login
                </a>
            </div>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="closeModal()">&times;</button>
            <h2>Reset Password</h2>
            <div class="field">
                <label for="forgot-email">Email Address</label>
                <input type="email" id="forgot-email" required placeholder="Enter your email">
            </div>
            <button onclick="sendResetLink()" class="submit-btn">Send Reset Link</button>
        </div>
    </div>

    <script>
    // Add dark mode functionality
    const themeSwitch = document.getElementById('theme-switch');
    const body = document.body;

    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        body.classList.add('darkmode');
    }

    // Theme switch click handler
    themeSwitch.addEventListener('click', () => {
        body.classList.toggle('darkmode');
        const isDarkMode = body.classList.contains('darkmode');
        localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
    });

    // Existing JavaScript code
    const modal = document.getElementById('forgotPasswordModal');
    
    function showForgotPassword() {
        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }

    function sendResetLink() {
        const email = document.getElementById('forgot-email').value;
        const submitBtn = document.querySelector('.submit-btn');
        const modalContent = document.querySelector('.modal-content');
        
        // Remove any existing message
        const existingMessage = modalContent.querySelector('.message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        if (!email) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message error';
            messageDiv.textContent = 'Please enter your email address';
            modalContent.querySelector('h2').insertAdjacentElement('afterend', messageDiv);
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=forgot_password&email=${encodeURIComponent(email)}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
        })
        .then(response => response.json())
        .then(data => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${data.status}`;
            messageDiv.textContent = data.message;
            modalContent.querySelector('h2').insertAdjacentElement('afterend', messageDiv);
            
            if (data.status === 'success') {
                document.getElementById('forgot-email').value = '';
                // Close modal after 3 seconds on success
                setTimeout(closeModal, 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message error';
            messageDiv.textContent = 'An error occurred. Please try again.';
            modalContent.querySelector('h2').insertAdjacentElement('afterend', messageDiv);
        })
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Reset Link';
        });
    }

    // Update password visibility toggle functionality
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('togglePassword');
        const showIcon = toggleButton.querySelector('.show-password');
        const hideIcon = toggleButton.querySelector('.hide-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            showIcon.style.display = 'none';
            hideIcon.style.display = 'block';
        } else {
            passwordInput.type = 'password';
            showIcon.style.display = 'block';
            hideIcon.style.display = 'none';
        }
    }

    </script>
</body>
</html>

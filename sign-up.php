<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
session_start();
include 'db_connect.php'; // Ensure this path is correct

$showModal = false; // Initialize popup flag
$verification_message = ''; // Variable to store verification result message

// Check if registration is allowed
$stmt = $pdo->prepare("SELECT * FROM site_settings LIMIT 1");
$stmt->execute();
$site_settings = $stmt->fetch(PDO::FETCH_ASSOC);

$registration_disabled = !$site_settings['allow_registration'];
$error_message = $registration_disabled ? "Registration is currently disabled by the administrator." : "";
$theme_mode = $site_settings['theme_mode'] ?? 'light';

// Function to adjust brightness for hover states
function adjustBrightness($hex, $steps) {
    $hex = ltrim($hex, '#');
    $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $steps));
    $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $steps));
    $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $steps));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$registration_disabled) {
    // Sign-up logic
    if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['phone']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $verification_token = mt_rand(100000, 999999); // 6-digit verification code

        // Check if the email or username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            echo "Error: This email or username is already registered. Please use different credentials.";
        } else {
            // Insert user into the database
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, verification_token, email_verified) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$username, $email, $phone, $password, $verification_token])) {
                // Send verification email (optional since the user enters the code in the popup)
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'lokmen13.messabhia@gmail.com';
                    $mail->Password   = 'dfbk qkai wlax rscb'; // Use app-specific password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('lokmen13.messabhia@gmail.com', 'Lokpix');
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Email Verification';
                    $mail->Body    = "Thank you for signing up with Lokpix!
                     Please enter the verification code below 
                     to confirm your email address: <br><br>
                                      <strong>$verification_token</strong>";

                    $mail->send();
                    $_SESSION['email'] = $email; // Store email in session for verification
                    $showModal = true; // Trigger the popup on successful registration
                } catch (Exception $e) {
                    echo "Error: Email could not be sent. {$mail->ErrorInfo}";
                }
            } else {
                echo "Error: Could not register. Please try again.";
            }
        }
    }
}

// Token verification logic (POST from modal)
if (isset($_POST['verification_token']) && isset($_SESSION['email'])) {
    $verification_token = $_POST['verification_token'];
    $email = $_SESSION['email'];

    // Fetch stored verification token from the database
    $stmt = $pdo->prepare("SELECT verification_token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['verification_token'] == $verification_token) {
            // Update user as verified
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
            $stmt->execute([$email]);

            // Clear session variable to avoid re-verification
            unset($_SESSION['email']);

            // Redirect to login page after successful verification
            header("Location: login.php");
            exit(); // Stop further execution
        } else {
            $verification_message = 'Invalid token. Please try again.';
        }
    } else {
        $verification_message = 'User not found. Please try again.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?= htmlspecialchars($site_settings['site_name'] ?? 'EcoTech') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($site_settings['font_family']) ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $site_settings['primary_color'] ?>;
            --primary-hover: <?= adjustBrightness($site_settings['primary_color'], -10) ?>;
            --accent-color: <?= $site_settings['accent_color'] ?>;
            --error-color: #ef4444;
            --success-color: #22c55e;
            --background: #f5f7ff;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --input-border: #e2e8f0;
            --input-background: #f8fafc;
            --shadow-color: rgb(0 0 0 / 0.05);
            --font-family: '<?= $site_settings['font_family'] ?>', system-ui, -apple-system, sans-serif;
        }

        /* Dark mode styles */
        :root[data-theme="dark"] {
            --background: #0f172a;
            --card-background: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --input-border: #334155;
            --input-background: #1e293b;
            --shadow-color: rgb(0 0 0 / 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family);
        }

        body {
            margin: 0;
            padding: 2rem;
            font-family: var(--font-family);
            background-color: var(--background);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: 
                radial-gradient(circle at 10% 20%, color-mix(in srgb, var(--primary-color) 5%, transparent) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, color-mix(in srgb, var(--primary-color) 5%, transparent) 0%, transparent 20%);
            padding-top: 6rem;
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .logo-container {
            position: fixed;
            top: 2rem;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            z-index: 1000;
        }

        .logo {
            width: 140px;
            height: auto;
            animation: fadeIn 0.6s ease-out;
        }

        .wrapper {
            background: var(--card-background);
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px var(--shadow-color), 0 8px 10px -6px var(--shadow-color);
            width: 100%;
            max-width: 440px;
            margin: 1rem;
            position: relative;
            overflow: hidden;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2.5rem;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .field {
            margin-bottom: 1.5rem;
        }

        .field label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.925rem;
        }

        .field input[type="text"],
        .field input[type="email"],
        .field input[type="password"],
        .field input[type="tel"] {
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color) 10%, transparent);
        }

        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            transition: color 0.2s ease;
            font-family: var(--font-family);
        }

        .toggle-password:hover {
            color: var(--text-primary);
        }

        .eye-icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        .field input[type="submit"] {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-family: var(--font-family);
        }

        .field input[type="submit"]:hover {
            background: var(--primary-hover);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.925rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .login-link a:hover {
            color: var(--primary-hover);
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

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                padding: 1rem;
                background-image: none;
                padding-top: 5rem;
            }
            
            .wrapper {
                padding: 1.5rem;
                margin: 0;
                border-radius: 1rem;
            }

            .title {
                font-size: 1.5rem;
                margin-bottom: 2rem;
            }

            .field {
                margin-bottom: 1.25rem;
            }

            .field input[type="text"],
            .field input[type="email"],
            .field input[type="password"],
            .field input[type="tel"] {
                padding: 0.75rem 1rem;
                font-size: 0.95rem;
            }

            .logo-container {
                top: 1.5rem;
            }

            .logo {
                width: 120px;
            }
        }

        @media (max-width: 360px) {
            .wrapper {
                padding: 1.25rem;
            }

            .title {
                font-size: 1.25rem;
            }

            .field label {
                font-size: 0.875rem;
            }
        }

        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 0.5rem;
                padding-top: 4rem;
            }

            .wrapper {
                margin: 0;
                padding: 1.25rem;
            }

            .logo-container {
                top: 1rem;
            }

            .logo {
                width: 100px;
            }
        }

        .field input[type="tel"] {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--input-border);
            border-radius: 0.75rem;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.2s ease;
            background-color: var(--input-background);
            font-family: var(--font-family);
        }

        .field input[type="tel"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color) 10%, transparent);
        }

        /* Modal styles */
        .modal {
            background: var(--card-background);
            color: var(--text-primary);
            border: 1px solid var(--input-border);
        }

        .modal input {
            background: var(--input-background);
            color: var(--text-primary);
            border: 1px solid var(--input-border);
        }

        .modal button {
            background: var(--primary-color);
            color: white;
        }

        .modal button:hover {
            background: var(--primary-hover);
        }

        .error-banner {
            background: rgb(239 68 68 / 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .field input:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            background-color: color-mix(in srgb, var(--input-background) 80%, var(--text-secondary));
        }

        .field input[type="submit"]:disabled {
            background-color: var(--text-secondary);
            cursor: not-allowed;
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
    <div class="logo-container">
        <img src="logo (1).png" alt="EcoTech Logo" class="logo">
    </div>

    <div class="wrapper">
        <div class="title">Create Account</div>
        
        <?php if ($registration_disabled): ?>
            <div class="error-banner"><?php echo htmlspecialchars($error_message); ?></div>
        <?php elseif (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="sign-up.php" method="post">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter your username" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
            </div>
            
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
            </div>

            <div class="field">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required placeholder="Enter your phone number" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
            </div>
            
            <div class="field">
                <label for="password">Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Enter your password" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="field">
                <input type="submit" value="Sign Up" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
            </div>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login</a>
            </div>

            <div class="continue-without-registration">
                <a href="index.php" class="skip-registration-btn">
                    <i class="fas fa-arrow-right"></i> Continue without registration
                </a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const button = input.nextElementSibling;
            const svg = button.querySelector('svg');
            if (type === 'text') {
                svg.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            } else {
                svg.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }
    </script>
</body>
</html>

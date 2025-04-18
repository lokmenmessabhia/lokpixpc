<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';
$validToken = false;

// Verify token from URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Fetch additional user information
    $stmt = $pdo->prepare("SELECT id, email, phone, profile_picture FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $validToken = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
} else {
    $error = "No reset token provided.";
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Verify token again for security
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            try {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                $success = "Password has been reset successfully. You can now login with your new password.";
                $validToken = false; // Hide the form
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
            }
        } else {
            $error = "Invalid or expired reset link. Please request a new one.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EcoTech</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --error-color: #ef4444;
            --success-color: #22c55e;
            --background: #f5f7ff;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --input-border: #e2e8f0;
            --input-background: #f8fafc;
        }

        body {
            margin: 0;
            padding: 2rem;
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background: var(--background);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(79, 70, 229, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(79, 70, 229, 0.05) 0%, transparent 20%);
        }

        .wrapper {
            background: var(--card-background);
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.05);
            width: 100%;
            max-width: 440px;
            margin: 1rem;
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
            background: linear-gradient(to right, var(--primary-color), #818cf8);
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2.5rem;
            color: var(--text-primary);
            letter-spacing: -0.025em;
            font-family: 'Poppins', sans-serif;
        }

        .field {
            margin-bottom: 1.5rem;
        }

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
            font-family: 'Poppins', sans-serif;
        }

        .field input[type="password"]:focus,
        .field input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .submit-btn,
        .field input[type="submit"] {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        .submit-btn:hover,
        .field input[type="submit"]:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -2px rgba(79, 70, 229, 0.1);
        }

        .error, .success {
            margin-bottom: 1.5rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            text-align: center;
            font-size: 0.925rem;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .error {
            color: var(--error-color);
            background: rgb(239 68 68 / 0.08);
            border: 1px solid rgba(239, 68, 68, 0.1);
        }

        .success {
            color: var(--success-color);
            background: rgb(34 197 94 / 0.08);
            border: 1px solid rgba(34, 197, 94, 0.1);
        }

        .password-requirements {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            padding-left: 0.5rem;
        }

        .user-info {
            text-align: center;
            margin-bottom: 2.5rem;
            padding: 2rem;
            background: var(--background);
            border-radius: 1rem;
            box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
        }

        .profile-picture {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin: 0 auto 1.25rem;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            padding: 3px;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1);
        }

        .user-details {
            color: var(--text-primary);
        }

        .user-email {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .user-phone {
            color: var(--text-secondary);
            font-size: 0.925rem;
        }

        .default-avatar {
            background: linear-gradient(135deg, var(--primary-color), #818cf8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            font-weight: 600;
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
            }
            
            .wrapper {
                padding: 2rem;
                margin: 0.5rem;
            }

            .title {
                font-size: 1.75rem;
            }
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.925rem;
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
        }

        .toggle-password:hover {
            color: var(--text-primary);
        }

        .eye-icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        .field input[type="password"],
        .field input[type="text"] {
            padding-right: 3rem !important;
        }

        ::placeholder {
            color: #94a3b8;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="title">Reset Password</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div class="field">
                <a href="login.php" class="submit-btn">Return to Login</a>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken && !$success): ?>
            <div class="user-info">
                <?php if ($user): ?>
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture default-avatar">
                            <?php echo strtoupper(substr($user['username'] ?? $user['email'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="user-details">
                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <?php if (!empty($user['phone_number'])): ?>
                            <div class="user-phone"><?php echo htmlspecialchars($user['phone_number']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" onsubmit="return validateForm()">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-requirements">
                        Password must be at least 8 characters long
                    </div>
                </div>
                
                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="field">
                    <input type="submit" value="Reset Password">
                </div>
            </form>
            
            <script>
            function validateForm() {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length < 8) {
                    alert('Password must be at least 8 characters long');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match');
                    return false;
                }
                
                return true;
            }

            function togglePassword(inputId) {
                const input = document.getElementById(inputId);
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                // Update icon (optional enhancement)
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
        <?php endif; ?>
        
        <?php if (!$validToken && !$success): ?>
            <div class="field">
                <a href="login.php" class="submit-btn">Return to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

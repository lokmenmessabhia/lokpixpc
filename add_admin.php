<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is a superadmin
$isAdmin = false;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? AND role = 'superadmin'");
        $stmt->execute([$_SESSION['email']]);
        if ($stmt->fetch()) {
            $isAdmin = true;
        }
    } catch (PDOException $e) {
        error_log("Error verifying admin status: " . $e->getMessage());
    }
}

if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Email already exists.";
            } else {
                // Insert new admin
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (email, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$email, $hashed_password, $role]);
                $success_message = "Admin account created successfully!";
            }
        } catch (PDOException $e) {
            $error_message = "Error creating admin account: " . $e->getMessage();
        }
    }
}

include 'dash_header.php';
?>

<style>
    .card {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 2rem;
        box-shadow: var(--shadow);
        max-width: 600px;
        margin: 2rem auto;
    }

    .card h2 {
        color: var(--text);
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text);
        font-weight: 500;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--background);
        color: var(--text);
        font-family: inherit;
        font-size: 1rem;
        transition: var(--transition);
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(var(--primary), 0.1);
    }

    .btn {
        background: var(--primary);
        color: #fff;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--radius);
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
    }

    .btn:hover {
        background: var(--accent);
        transform: translateY(-2px);
    }

    .alert {
        padding: 1rem;
        border-radius: var(--radius);
        margin-bottom: 1.5rem;
    }

    .alert-success {
        background: rgba(var(--success), 0.1);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .alert-error {
        background: rgba(var(--danger), 0.1);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .password-requirements {
        margin-top: 1rem;
        padding: 1rem;
        background: var(--background);
        border-radius: var(--radius);
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .password-requirements ul {
        list-style: none;
        margin-top: 0.5rem;
    }

    .password-requirements li {
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .password-requirements i {
        color: var(--text-secondary);
    }
</style>

<div class="container">
    <div class="card">
        <h2>Add New Admin</h2>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role">Admin Role</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="superadmin">Super Admin</option>
                </select>
            </div>

            <div class="password-requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li><i class="fas fa-check-circle"></i> At least 8 characters long</li>
                    <li><i class="fas fa-check-circle"></i> Contains at least one uppercase letter</li>
                    <li><i class="fas fa-check-circle"></i> Contains at least one number</li>
                    <li><i class="fas fa-check-circle"></i> Contains at least one special character</li>
                </ul>
            </div>

            <button type="submit" class="btn">Create Admin Account</button>
        </form>
    </div>
</div>

<script>
    // Password validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const requirements = document.querySelectorAll('.password-requirements i');

    function validatePassword() {
        const value = password.value;
        const checks = [
            value.length >= 8,
            /[A-Z]/.test(value),
            /[0-9]/.test(value),
            /[!@#$%^&*]/.test(value)
        ];

        checks.forEach((check, index) => {
            requirements[index].className = check ? 
                'fas fa-check-circle' : 'fas fa-times-circle';
            requirements[index].style.color = check ? 
                'var(--success)' : 'var(--danger)';
        });
    }

    password.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', function() {
        if (password.value === confirmPassword.value) {
            confirmPassword.style.borderColor = 'var(--success)';
        } else {
            confirmPassword.style.borderColor = 'var(--danger)';
        }
    });
</script>
</body>
</html>
<?php
// Check if constants are already defined before defining them
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'lokpixpc');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// Create a new PDO instance and handle connection errors
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::ATTR_PERSISTENT => true
        ]
    );
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Create login_attempts table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_ip_time (ip_address, attempt_time)
)");

// Create password_reset_attempts table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_email_time (email, attempt_time)
)");
?>

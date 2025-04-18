<?php
require_once 'db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(255) NOT NULL,
        subscription_data TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_email)
    )";
    
    $pdo->exec($sql);
    echo "Push subscriptions table created successfully";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}

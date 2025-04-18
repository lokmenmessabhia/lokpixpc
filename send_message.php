<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if all required fields are present
if (!isset($_POST['seller_email']) || !isset($_POST['message']) || !isset($_POST['product_name'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$sender_email = $_SESSION['email'];
$receiver_email = $_POST['seller_email'];
$message = trim($_POST['message']);
$product_name = $_POST['product_name'];

// Validate message
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

try {
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_email, receiver_email, message, product_name, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$sender_email, $receiver_email, $message, $product_name])) {
        // Get sender's username
        $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
        $stmt->execute([$sender_email]);
        $sender_username = $stmt->fetchColumn() ?: explode('@', $sender_email)[0];

        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'notification' => [
                'title' => 'New Message from ' . $sender_username,
                'body' => substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send message'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error sending message: ' . $e->getMessage()
    ]);
}

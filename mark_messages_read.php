<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$sender_email = isset($_GET['sender_email']) ? $_GET['sender_email'] : null;
$current_user_email = $_SESSION['email'];

if (!$sender_email) {
    echo json_encode(['success' => false, 'error' => 'Sender email not provided']);
    exit;
}

try {
    // Mark messages as read
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET read_status = 1,
            read_at = NOW()
        WHERE receiver_email = ? 
        AND sender_email = ?
        AND read_status = 0
    ");
    
    $stmt->execute([$current_user_email, $sender_email]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Messages marked as read'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

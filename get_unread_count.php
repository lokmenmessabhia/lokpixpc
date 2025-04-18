<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if sender_email is provided
if (!isset($_GET['sender_email'])) {
    echo json_encode(['success' => false, 'error' => 'Sender email not provided']);
    exit;
}

$current_user_email = $_SESSION['email'];
$sender_email = $_GET['sender_email'];

try {
    // Get unread count for specific sender
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_email = ? 
        AND sender_email = ?
        AND read_status = 0
    ");
    
    $stmt->execute([$current_user_email, $sender_email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$result['unread_count']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error getting unread count: ' . $e->getMessage()
    ]);
}

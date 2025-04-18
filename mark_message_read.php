<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Get the message ID from the POST data
$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? $data['message_id'] : null;

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Message ID not provided']);
    exit;
}

try {
    // Mark the specific message as read
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET read_status = 1 
        WHERE id = ? 
        AND receiver_email = ?
    ");
    
    $stmt->execute([$message_id, $_SESSION['email']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

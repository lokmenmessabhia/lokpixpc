<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$seller_email = isset($_GET['seller_email']) ? $_GET['seller_email'] : null;
$current_user_email = $_SESSION['email'];

if (!$seller_email) {
    echo json_encode(['success' => false, 'error' => 'Seller email not provided']);
    exit;
}

try {
    // Get messages
    $stmt = $pdo->prepare("
    SELECT m.*, 
           u_sender.profile_picture as sender_picture,
           u_receiver.profile_picture as receiver_picture
    FROM messages m
    LEFT JOIN users u_sender ON m.sender_email = u_sender.email
    LEFT JOIN users u_receiver ON m.receiver_email = u_receiver.email
    WHERE (m.sender_email = ? AND m.receiver_email = ?)
    OR (m.sender_email = ? AND m.receiver_email = ?)
    ORDER BY m.created_at ASC
");

    $stmt->execute([$current_user_email, $seller_email, $seller_email, $current_user_email]);
    $messages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format time
        $messageTime = new DateTime($row['created_at']);
        $now = new DateTime();
        $diff = $now->diff($messageTime);

        if ($diff->days == 0) {
            $formatted_time = "Today " . $messageTime->format('g:i A');
        } elseif ($diff->days == 1) {
            $formatted_time = "Yesterday " . $messageTime->format('g:i A');
        } else {
            $formatted_time = $messageTime->format('M j, g:i A');
        }

        // Set seen text
        $seen_text = "";
        if ($row['read_status'] == 1) {
            $read_time = new DateTime($row['read_at']);
            $read_diff = $now->diff($read_time);
            
            if ($read_diff->days == 0) {
                $seen_text = "Seen today at " . $read_time->format('g:i A');
            } elseif ($read_diff->days == 1) {
                $seen_text = "Seen yesterday at " . $read_time->format('g:i A');
            } else {
                $seen_text = "Seen " . $read_time->format('M j') . " at " . $read_time->format('g:i A');
            }
        }

        // Determine if the message is sent by the current user
        $is_sent = $row['sender_email'] === $_SESSION['email'];
        
        // Get the appropriate profile picture
        $profile_picture = $is_sent ? 
            ($row['sender_picture'] ? 'uploads/profiles/' . $row['sender_picture'] : 'https://i.top4top.io/p_3273sk4691.jpg') :
            ($row['receiver_picture'] ? 'uploads/profiles/' . $row['receiver_picture'] : 'https://i.top4top.io/p_3273sk4691.jpg');
        
        $messages[] = array(
            'id' => $row['id'],
            'message' => $row['message'],
            'sender_email' => $row['sender_email'],
            'receiver_email' => $row['receiver_email'],
            'created_at' => $row['created_at'],
            'formatted_time' => $formatted_time,
            'read_status' => $row['read_status'],
            'read_at' => $row['read_at'],
            'profile_picture' => $profile_picture,
            'is_sent' => $is_sent,
            'show_seen' => $is_sent && $row['read_status'] == 1,
            'seen_text' => $seen_text,
            'left_on_read' => $row['read_status'] == 1
        );
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

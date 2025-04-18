<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

function saveSubscription($subscription, $user_email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_email, subscription_data) 
                              VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE subscription_data = ?");
        $stmt->execute([$user_email, $subscription, $subscription]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function sendPushNotification($subscription, $message) {
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:your-email@lokpixpc.com',
            'publicKey' => 'YOUR_PUBLIC_VAPID_KEY', // You'll need to generate these keys
            'privateKey' => 'YOUR_PRIVATE_VAPID_KEY' // You'll need to generate these keys
        ],
    ];

    $webPush = new WebPush($auth);
    $webPush->sendNotification(
        $subscription,
        json_encode([
            'title' => 'New Message - LokPixPC',
            'body' => $message,
            'icon' => 'logo (1) text.png'
        ])
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['subscription']) && isset($_SESSION['email'])) {
        if (saveSubscription(json_encode($data['subscription']), $_SESSION['email'])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save subscription']);
        }
    }
}

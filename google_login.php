<?php
session_start();
require_once 'vendor/autoload.php';
include 'db_connect.php';

// Google Client Configuration
$client = new Google_Client();
$client->setClientId('lokmen13.messabhia@gmail.com');
$client->setClientSecret('dfbk qkai wlax rscb');
$client->setRedirectUri('http://localhost/google_login.php');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    // Get user info
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $email = $google_account_info->email;
    $name = $google_account_info->name;

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (email, name, email_verified) VALUES (?, ?, 1)");
        $stmt->execute([$email, $name]);
        $userid = $pdo->lastInsertId();
    } else {
        $userid = $user['id'];
    }

    // Set session
    $_SESSION['loggedin'] = true;
    $_SESSION['userid'] = $userid;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'user';

    header('Location: index.php');
    exit;
} else {
    // Generate login URL
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
}
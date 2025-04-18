<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct

// Check if tracking_number is provided in the URL
if (!isset($_GET['tracking_number'])) {
    die('Tracking number not provided.');
}

$tracking_number = $_GET['tracking_number'];

// Fetch tracking information from the database
try {
    $stmt = $pdo->prepare("SELECT * FROM tracking_info WHERE tracking_number = :tracking_number");
    $stmt->bindParam(':tracking_number', $tracking_number);
    $stmt->execute();
    $tracking_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tracking_info) {
        die('No tracking information found for this number.');
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - Lokpix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e9ecef;
            margin: 0;
            padding: 0;
        }
        .tracking-container {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 20px;
        }
        .tracking-info {
            margin-bottom: 20px;
        }
        .tracking-info p {
            margin: 10px 0;
            color: #555;
        }
        .back-button {
            text-align: center;
            margin-top: 20px;
        }
        .back-button a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="tracking-container">
        <h1>Tracking Information</h1>
        <div class="tracking-info">
            <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($tracking_info['tracking_number']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($tracking_info['status']); ?></p>
            <p><strong>Last Updated:</strong> <?php echo htmlspecialchars($tracking_info['last_updated']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($tracking_info['location']); ?></p>
            <p><strong>Additional Info:</strong> <?php echo nl2br(htmlspecialchars($tracking_info['additional_info'])); ?></p>
        </div>
        <div class="back-button">
            <a href="profile.php">Back to Profile</a>
        </div>
    </div>
</body>
</html>
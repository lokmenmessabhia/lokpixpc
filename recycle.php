<?php
session_start();
ob_start();
require_once 'db_connect.php';
include 'header.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Check if the user is logged in
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        die("Error: You must be logged in. Please log in to continue.");
    }
    
    $user_id = $_SESSION['user_id'];
    $data = [
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'part_name' => $_POST['part_name'] ?? '',
        'buying_year' => $_POST['buying_year'] ?? '',
        'category_id' => $_POST['category_id'] ?? '',
        'subcategory_id' => $_POST['subcategory_id'] ?? '',
        'condition' => $_POST['condition'] ?? '',
        'pickup' => $_POST['pickup'] ?? ''
    ];

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $photo_name = time() . "_" . basename($_FILES['photo']['name']);
        $photo_path = $upload_dir . $photo_name;
        
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            die("Error uploading photo.");
        }
    } else {
        die("Photo upload is required.");
    }

    try {
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO recycle_requests 
            (user_id, email, phone, part_name, buying_year, category_id, subcategory_id, component_condition, photo, pickup_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $data['email'], $data['phone'], $data['part_name'], $data['buying_year'],
            $data['category_id'], $data['subcategory_id'], $data['condition'], $photo_path, $data['pickup']
        ]);

        // Send Telegram notification
        $telegram_config = [
            'bot_token' => "7322742533:AAEEYMpmOGhkwuOyfU-6Y4c6UtjK09ti9vE",
            'chat_id' => "-1002458122628"
        ];

        // Get additional exchange information
        $exchange_option = $_POST['exchange_option'] ?? 'no';
        $original_price = $_POST['original_price'] ?? '0';
        $store_component_id = $_POST['store_component'] ?? '';
        
        // Calculate trade value if exchange is selected
        $trade_value = 0;
        if ($exchange_option === 'yes' && $original_price) {
            $rates = [
                'Working' => 0.5,
                'Damaged' => 0.2,
                'Not Working' => 0.1
            ];
            $trade_value = floatval($original_price) * $rates[$data['condition']];
        }

        // Get store component details if selected
        $store_component_info = '';
        if ($store_component_id) {
            $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = ?");
            $stmt->execute([$store_component_id]);
            $component = $stmt->fetch();
            if ($component) {
                $final_price = $component['price'] - $trade_value;
                $store_component_info = "\n💱 *Selected Component:* {$component['name']}" .
                                      "\n💰 *Component Price:* \${$component['price']}" .
                                      "\n🔄 *Trade-in Value:* \${$trade_value}" .
                                      "\n💵 *Final Price:* \${$final_price}";
            }
        }

        $message = "♻️ *New Recycle Request*\n\n" .
                  "👤 *User ID:* $user_id\n" .
                  "📧 *Email:* {$data['email']}\n" .
                  "📞 *Phone:* {$data['phone']}\n" .
                  "🔧 *Part Name:* {$data['part_name']}\n" .
                  "📅 *Buying Year:* {$data['buying_year']}\n" .
                  "📦 *Category ID:* {$data['category_id']}\n" .
                  "📂 *Subcategory ID:* {$data['subcategory_id']}\n" .
                  "✅ *Condition:* {$data['condition']}\n" .
                  "🚚 *Delivery Option:* {$data['pickup']}\n" .
                  "🔄 *Exchange Option:* $exchange_option" .
                  ($exchange_option === 'yes' ? "\n💰 *Original Price:* \$$original_price" : "") .
                  ($store_component_info ? $store_component_info : "");

        $photo_path_absolute = __DIR__ . '/' . $photo_path;
        if (!file_exists($photo_path_absolute)) die("Error: Uploaded photo not found.");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.telegram.org/bot{$telegram_config['bot_token']}/sendPhoto",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'chat_id' => $telegram_config['chat_id'],
                'photo' => new CURLFile($photo_path_absolute),
                'caption' => $message,
                'parse_mode' => 'Markdown'
            ]
        ]);

        if (!curl_exec($curl)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Error sending Telegram message: ' . curl_error($curl)]);
            exit();
        }
        curl_close($curl);

        // Get category and subcategory names
        $category_name = $pdo->query("SELECT name FROM categories WHERE id = {$data['category_id']}")->fetchColumn();
        $subcategory_name = $pdo->query("SELECT name FROM subcategories WHERE id = {$data['subcategory_id']}")->fetchColumn();
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'lokmen13.messabhia@gmail.com';
            $mail->Password = 'dfbk qkai wlax rscb';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('lokmen13.messabhia@gmail.com', 'Lokpix');
            $mail->addAddress($data['email']);
            $mail->isHTML(true);
            $mail->Subject = "Recycling Request Confirmation";

            // Create HTML email body
            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333333;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        background-color: #28a745;
                        color: white;
                        padding: 20px;
                        text-align: center;
                        border-radius: 5px 5px 0 0;
                    }
                    .content {
                        background-color: #ffffff;
                        padding: 20px;
                        border: 1px solid #dddddd;
                        border-radius: 0 0 5px 5px;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 20px;
                        padding: 20px;
                        color: #666666;
                        font-size: 12px;
                    }
                    .details {
                        background-color: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #28a745;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 15px 0;
                    }
                    .info {
                        border-left: 4px solid #28a745;
                        padding-left: 15px;
                        margin: 15px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🌱 Recycling Request Confirmation</h1>
                    </div>
                    
                    <div class='content'>
                        <h2>Thank you for your recycling request!</h2>
                        <p>We're excited to help you responsibly recycle your electronic components. Here are the details of your submission:</p>
                        
                        <div class='details'>
                            <p><strong>Part Name:</strong> " . htmlspecialchars($data['part_name']) . "</p>
                            <p><strong>Buying Year:</strong> " . htmlspecialchars($data['buying_year']) . "</p>
                            <p><strong>Category:</strong> " . htmlspecialchars($category_name) . "</p>
                            <p><strong>Subcategory:</strong> " . htmlspecialchars($subcategory_name) . "</p>
                            <p><strong>Condition:</strong> " . htmlspecialchars($data['condition']) . "</p>
                            <p><strong>Delivery Option:</strong> " . htmlspecialchars($data['pickup']) . "</p>
                            " . ($exchange_option === 'yes' ? "
                            <p><strong>Exchange Option:</strong> Yes</p>
                            <p><strong>Trade-in Value:</strong> $" . number_format($trade_value, 2) . "</p>
                            " : "") . "
                        </div>

                        <div class='info'>
                            <h3>Next Steps:</h3>
                            <ol>
                                <li>Our team will review your submission within 24-48 hours</li>
                                <li>You'll receive a follow-up email with detailed instructions</li>
                                " . ($data['pickup'] === 'pickup' ? "<li>Our pickup team will contact you to arrange collection</li>" : "<li>Instructions for drop-off will be provided</li>") . "
                            </ol>
                        </div>

                        <p>If you have any questions, please don't hesitate to contact our support team.</p>
                        
                        <a href='https://lokpixpc.com/contact' class='button'>Contact Support</a>
                    </div>

                    <div class='footer'>
                        <p>This email was sent by Lokpix PC Recycling Service</p>
                        <p>© " . date('Y') . " Lokpix. All rights reserved.</p>
                        <p>23 Rue Zaafrania, Annaba 23000, Algeria</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags(str_replace(
                ['<br>', '</div>', '</p>'], 
                ["\n", "\n", "\n\n"],
                $emailBody
            ));

            $mail->send();
        } catch (Exception $e) {
            // Log email failure but don't stop the process
            error_log("Failed to send confirmation email. Mailer Error: {$mail->ErrorInfo}");
        }

        $_SESSION['loggedin'] = true; // Ensure this is set after successful login

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Fetch necessary data
try {
    // Check if the user is logged in
    if (!isset($_SESSION['loggedin'])) {
        die("Error: You must be logged in to access this page.");
    }

    $stmt = $pdo->prepare("SELECT email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $categories = $pdo->query("
        SELECT id, name 
        FROM categories 
        WHERE name IN ('PC Components', 'Networking', 'Peripherals')
        ORDER BY name
    ")->fetchAll();
    $subcategories = $pdo->query("
        SELECT s.id AS subcategory_id, s.name AS subcategory_name, 
               s.category_id, c.name AS category_name 
        FROM subcategories s 
        JOIN categories c ON s.category_id = c.id 
        WHERE c.name IN ('PC Components', 'Networking', 'Peripherals')
        ORDER BY c.name, s.name
    ")->fetchAll();
    $store_components = $pdo->query("
        SELECT p.*, c.name as category_name, s.name as subcategory_name,
               CONCAT('uploads/products/', pi.image_url) as product_image
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN subcategories s ON p.subcategory_id = s.id
        LEFT JOIN product_images pi ON p.id = pi.product_id 
        WHERE c.name IN ('PC Components', 'Networking', 'Peripherals')
          AND (pi.is_primary = 1 OR pi.is_primary IS NULL)
        ORDER BY c.name, s.name, p.name
    ")->fetchAll();
} catch (PDOException $e) {
    die("Error loading data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Your PC Components</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Improved CSS */
        body { 
            font-family: 'Poppins', sans-serif; 
            margin: 0; 
            background: #f4f4f4; 
            line-height: 1.6;
        }
        .container { 
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-wrapper {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .form-section { 
            background: white; 
            padding: 30px;
            border-radius: 12px;
            flex: 1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-section h3 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: #2c3e50;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        input, select { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        .submit-btn { 
            background: #28a745; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer;
            font-weight: 500;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .submit-btn:hover {
            background: #218838;
        }
        .info-banner { 
            background: #fff3cd; 
            border-left: 5px solid #ffc107; 
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .info-banner h2 { 
            color: #856404; 
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-banner ul { 
            color: #856404; 
            margin: 0;
            padding-left: 20px;
            font-size: 0.95rem;
        }
        .info-banner li {
            margin-bottom: 6px;
            line-height: 1.4;
        }
        #exchangeDetails {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        #priceCalculation {
            margin-top: 15px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 6px;
        }
        #priceCalculation p {
            margin: 5px 0;
            font-weight: 500;
        }
        /* File input styling */
        input[type="file"] {
            padding: 8px;
            background: #f8f9fa;
        }
        /* Responsive design */
        @media (max-width: 768px) {
            .form-wrapper {
                flex-direction: column;
            }
            .form-section {
                margin-bottom: 20px;
            }
        }

        /* Popup/Modal Styles */
        .popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .popup-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .popup h3 {
            margin: 0 0 20px 0;
            color: #28a745;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .popup p {
            color: #666;
            font-size: 16px;
            margin-bottom: 25px;
        }

        .popup .confirm-btn {
            background: #28a745;
            color: white;
            padding: 12px 35px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }

        .popup .confirm-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .popup .confirm-btn:active {
            transform: translateY(0);
        }

        .popup .cancel-btn {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .popup .cancel-btn:hover {
            background: #5a6268;
        }

        .popup .form-group {
            margin-bottom: 15px;
        }

        .popup small {
            display: block;
            color: #666;
            margin-top: 5px;
        }

        /* Partners Section Styles */
        .partners-section {
            padding: 40px;
            margin-bottom: 30px;
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .partners-section h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
            font-size: 2.2rem;
            position: relative;
            padding-bottom: 15px;
        }

        .partners-section h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #28a745;
            border-radius: 2px;
        }

        .partners-section > p {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        .associations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .association-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .association-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .association-card img {
            width: 140px;
            height: 140px;
            object-fit: contain;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .association-card:hover img {
            transform: scale(1.05);
        }

        .association-card h3 {
            color: #28a745;
            margin: 15px 0;
            font-size: 1.2rem;
            text-align: center;
            font-weight: 600;
        }

        /* Special styling for golden partner */
        .association-card.golden-partner {
            border: 2px solid #FFD700;
            background: linear-gradient(135deg, #fff8e7, #ffffff);
            position: relative;
        }

        .association-card.golden-partner::before {
            content: '★ Golden Partner';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #FFD700;
            color: #000;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .association-card.golden-partner h3 {
            color: #B8860B;
        }

        .association-card p {
            color: #666;
            text-align: center;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .partners-section {
                padding: 20px;
            }

            .partners-section h2 {
                font-size: 1.8rem;
            }

            .associations-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .association-card {
                padding: 15px;
            }

            .association-card img {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 480px) {
            .associations-grid {
                grid-template-columns: 1fr;
            }

            .association-card.golden-partner::before {
                font-size: 0.7rem;
                padding: 3px 8px;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            /* Form adjustments */
            .form-wrapper {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-section {
                padding: 15px;
            }
            
            /* Info banner adjustments */
            .info-banner {
                padding: 12px 15px;
                margin: 15px 10px;
            }
            
            .info-banner h2 {
                font-size: 1.1rem;
            }
            
            .info-banner ul {
                font-size: 0.9rem;
                padding-left: 15px;
            }
            
            .info-banner li {
                margin-bottom: 5px;
            }
            
            /* Partners section adjustments */
            .partners-section {
                padding: 20px 15px;
            }
            
            .associations-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .association-card {
                padding: 15px;
            }
            
            .association-card img {
                width: 100px;
                height: 100px;
            }
            
            /* Popup adjustments */
            .popup-content {
                padding: 20px;
                width: 95%;
                margin: 10px;
            }
            
            .popup h3 {
                font-size: 20px;
            }
            
            /* Form elements adjustments */
            input, select {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 8px 10px;
            }
            
            .submit-btn {
                padding: 10px 20px;
            }
            
            /* Header adjustments (if header.php exists) */
            header {
                padding: 10px;
            }
            
            /* Navigation adjustments */
            nav {
                flex-direction: column;
                align-items: center;
            }
            
            nav a {
                margin: 5px 0;
            }
        }

        /* Small mobile devices */
        @media (max-width: 480px) {
            .info-banner h2 {
                font-size: 1.1rem;
            }
            
            .form-section h3 {
                font-size: 1.2rem;
            }
            
            .popup-content {
                padding: 15px;
            }
            
            .popup h3 {
                font-size: 18px;
            }
            
            /* Adjust button sizes */
            .confirm-btn, .cancel-btn {
                width: 100%;
                margin: 5px 0;
            }
            
            /* Stack buttons in popups */
            .popup .confirm-btn,
            .popup .cancel-btn {
                display: block;
                margin: 10px 0;
            }
            
            /* Adjust form group spacing */
            .form-group {
                margin-bottom: 15px;
            }
            
            /* Make price calculation more readable */
            #priceCalculation p {
                font-size: 14px;
            }
        }

        /* Fix for iOS input zoom */
        @media screen and (-webkit-min-device-pixel-ratio: 0) { 
            select,
            textarea,
            input {
                font-size: 16px !important;
            }
        }

        .product-details {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .product-image {
            text-align: center;
            margin-bottom: 15px;
        }

        .product-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 8px;
        }

        .product-info {
            padding: 10px;
        }

        .product-info h4 {
            color: #28a745;
            margin: 0 0 10px 0;
        }

        .product-info p {
            margin: 5px 0;
            color: #666;
        }

        .product-info p:last-child {
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="successPopup" class="popup" style="display: none;">
            <div class="popup-content">
                <h3>✅ Success!</h3>
                <p>Your recycling request has been submitted successfully! We'll process it shortly.
                    please check your email for further instructions and confirmation details.
                </p>
                <button onclick="closeSuccessPopup()" class="confirm-btn">Continue</button>
            </div>
        </div>

        <!-- Update the partners section HTML -->
        <div class="partners-section">
            <h2>Our Recycling Partners</h2>
            <p>We work with certified recycling partners and environmental organizations to ensure proper handling and disposal of electronic waste.</p>
            
            <div class="associations-grid">
                <div class="association-card">
                    <img src="https://wastedoccenter.and.dz/logo.png" alt="AND Logo">
                    <h3>AND (Agence Nationale des Déchets)</h3>
                    <p>National Waste Management Agency</p>
                </div>
                
                <div class="association-card">
                    <img src="https://onedd.org/wp-content/uploads/2023/05/CNFE.png" alt="CNFE Logo">
                    <h3>CNFE</h3>
                    <p>National Conservatory for Environmental Training</p>
                </div>

                <div class="association-card golden-partner">
                    <img src="https://www.univ-annaba.dz/wp-content/uploads/2021/04/Logo@2x.png" alt="UBMA Logo">
                    <h3>Université Badji Mokhtar Annaba</h3>
                    <p>Leading Research & Innovation Partner</p>
                </div>

                <div class="association-card">
                    <img src="https://academy-ce.info/wp-content/uploads/2024/04/step-logos-march-18.png" alt="StEP Logo">
                    <h3>StEP Initiative</h3>
                    <p>Solving the E-Waste Problem</p>
                </div>
            </div>
        </div>

        <div class="info-banner">
            <h2>⚠️ Important Information</h2>
            <ul>
                <li>All items submitted for recycling will be properly disposed of or refurbished.</li>
                <li>Once submitted, items cannot be returned.</li>
                <li>Please ensure all personal data is backed up and removed from devices.</li>
                <li>Photos must clearly show the condition of the item.</li>
                <li>If choosing exchange option, trade-in values are final.</li>
            </ul>
        </div>

        <form id="recycleForm" action="recycle.php" method="POST" enctype="multipart/form-data" 
              onsubmit="return showVerificationPopup(event)">
            <div class="form-wrapper">
                <div class="form-section" style="background: #f9f9f9; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="color: #28a745;">📝 Basic Information</h3>
                    <!-- Hidden user info -->
                    <input type="hidden" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Part Name:</label>
                        <input type="text" name="part_name" required placeholder="e.g., Intel Core i7-9700K, RTX 3070" style="border: 1px solid #28a745;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Buying Year:</label>
                        <select name="buying_year" required style="border: 1px solid #28a745;">
                            <option value="">Select Year</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= 2000; $year--) {
                                echo "<option value=\"$year\">$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Category:</label>
                        <select name="category_id" required onchange="updateSubcategories(this.value)" style="border: 1px solid #28a745;">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Subcategory:</label>
                        <select name="subcategory_id" required style="border: 1px solid #28a745;">
                            <option value="">Select Subcategory</option>
                            <?php foreach ($subcategories as $sub): ?>
                                <option value="<?= $sub['subcategory_id'] ?>" 
                                        data-category="<?= $sub['category_id'] ?>">
                                    <?= htmlspecialchars($sub['subcategory_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Condition:</label>
                        <select name="condition" required style="border: 1px solid #28a745;">
                            <option value="Working">Working</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Not Working">Not Working</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Photo:</label>
                        <input type="file" name="photo" accept="image/*" required style="border: 1px solid #28a745;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #444;">Delivery Option:</label>
                        <select name="pickup" style="border: 1px solid #28a745;">
                            <option value="dropoff">Drop-off</option>
                            <option value="pickup">Request Pickup</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3 style="color: #28a745;">💱 Exchange Options</h3>
                    <!-- Exchange section -->
                    <div class="form-group">
                        <label>Exchange Option:</label>
                        <select name="exchange_option" onchange="toggleExchange(this.value)" style="border: 1px solid #28a745;">
                            <option value="no">No Exchange</option>
                            <option value="yes">Exchange with Store Component</option>
                        </select>
                    </div>

                    <div id="exchangeDetails" style="display:none">
                        <div class="form-group">
                            <label>Original Price:</label>
                            <input type="number" name="original_price" min="0" step="0.01" 
                                   onchange="calculatePrice()" style="border: 1px solid #28a745;">
                        </div>

                        <div class="form-group">
                            <label>Store Component:</label>
                            <select name="store_component" onchange="showProductDetails(this.value)" style="border: 1px solid #28a745;">
                                <option value="">Select Component</option>
                                <?php foreach ($store_components as $comp): ?>
                                    <option value="<?= $comp['id'] ?>" 
                                            data-price="<?= $comp['price'] ?>"
                                            data-subcategory="<?= $comp['subcategory_id'] ?>"
                                            data-name="<?= htmlspecialchars($comp['name']) ?>"
                                            data-description="<?= htmlspecialchars($comp['description']) ?>"
                                            data-image="<?= htmlspecialchars($comp['product_image'] ?? 'images/default-product.png') ?>">
                                        <?= htmlspecialchars("{$comp['name']} - {$comp['price']} DZD") ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div id="productDetails" class="product-details" style="display: none;">
                                <div class="product-image">
                                    <img src="" alt="Product Image" id="productImage">
                                </div>
                                <div class="product-info">
                                    <h4 id="productName"></h4>
                                    <p id="productDescription"></p>
                                    <p id="productPrice"></p>
                                </div>
                            </div>
                        </div>

                        <div id="priceCalculation"></div>
                    </div>
                </div>
            </div>
            <button type="submit" class="submit-btn">Submit Request</button>
        </form>
    </div>

    <!-- Verification Popup -->
    <div id="verificationPopup" class="popup">
        <div class="popup-content">
            <h3>⚠️ Final Verification</h3>
            <p>Please confirm you understand:</p>
            <ul>
                <li>This item will be recycled and cannot be returned</li>
                <li>All personal data should be backed up and removed</li>
                <li>The trade-in value (if selected) is final</li>
                <li>Please verify your contact details below:</li>
            </ul>
            
            <div class="form-group">
                <label>Email:</label>
                <div><?= htmlspecialchars($user['email']) ?></div>
            </div>
            
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="tel" id="confirmPhone" value="<?= htmlspecialchars($user['phone']) ?>" 
                       pattern="[0-9\+\-\(\)\s]+" title="Please enter a valid phone number">
                <small>You can update your phone number if needed</small>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="confirmCheck" required>
                <label for="confirmCheck">I understand and agree to proceed</label>
            </div>
            <button onclick="submitIfConfirmed()" class="confirm-btn">Confirm Submission</button>
            <button type="button" onclick="closePopup()" class="cancel-btn">Cancel</button>
        </div>
    </div>

    <script>
        // Add this function at the beginning of your script section
        function updateSubcategories(categoryId) {
            const subcategorySelect = document.querySelector('[name="subcategory_id"]');
            const options = subcategorySelect.getElementsByTagName('option');
            
            for (let option of options) {
                if (option.value === "") { // Skip the placeholder option
                    continue;
                }
                if (option.getAttribute('data-category') === categoryId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            // Reset subcategory selection
            subcategorySelect.value = '';
            
            // Also reset and hide store components when category changes
            updateStoreComponents('');
        }

        function toggleExchange(value) {
            document.getElementById('exchangeDetails').style.display = 
                value === 'yes' ? 'block' : 'none';
        }

        function calculatePrice() {
            const condition = document.querySelector('[name="condition"]').value;
            const originalPrice = parseFloat(document.querySelector('[name="original_price"]').value) || 0;
            const storeComponent = document.querySelector('[name="store_component"]');
            const storePrice = storeComponent.selectedOptions[0]?.dataset.price || 0;

            const rates = { 
                'Working': 0.5,     // 50% of original price
                'Damaged': 0.2,     // 20% of original price
                'Not Working': 0.1  // 10% of original price
            };
            const tradeValue = originalPrice * rates[condition];
            const difference = storePrice - tradeValue;

            document.getElementById('priceCalculation').innerHTML = `
                <p>Trade-in Value: $${tradeValue.toFixed(2)}</p>
                <p>Store Price: $${storePrice}</p>
                <p>Amount to Pay: $${difference.toFixed(2)}</p>
            `;
        }

        function showVerificationPopup(event) {
            event.preventDefault();
            document.getElementById('verificationPopup').style.display = 'flex';
            return false;
        }

        function closePopup() {
            document.getElementById('verificationPopup').style.display = 'none';
        }

        function submitIfConfirmed() {
            if (!document.getElementById('confirmCheck').checked) {
                alert('Please check the confirmation box to proceed');
                return;
            }
            
            const newPhone = document.getElementById('confirmPhone').value;
            if (!newPhone) {
                alert('Please provide a valid phone number');
                return;
            }
            
            // Update phone number
            document.querySelector('input[name="phone"]').value = newPhone;
            
            // Get form data
            const formData = new FormData(document.getElementById('recycleForm'));
            
            // Close verification popup
            closePopup();
            
            // Show loading state
            document.querySelector('.submit-btn').disabled = true;
            
            // Submit form via AJAX
            fetch('recycle.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is successful, regardless of content type
                if (response.ok) {
                    // Show success popup even if JSON parsing fails
                    document.getElementById('successPopup').style.display = 'flex';
                    return;
                }
                throw new Error('Network response was not ok');
            })
            .catch(error => {
                console.error('Error:', error);
                // Only show alert if it's a real error, not a JSON parsing issue
                if (!document.getElementById('successPopup').style.display === 'flex') {
                    alert('An error occurred while submitting the form');
                }
            })
            .finally(() => {
                // Re-enable submit button
                document.querySelector('.submit-btn').disabled = false;
            });
        }

        // Add this new function
        function updateStoreComponents(subcategoryId) {
            const storeComponentSelect = document.querySelector('[name="store_component"]');
            const options = storeComponentSelect.getElementsByTagName('option');
            const productDetails = document.getElementById('productDetails');
            
            for (let option of options) {
                if (option.value === "") {
                    continue;
                }
                if (option.getAttribute('data-subcategory') === subcategoryId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            // Reset store component selection
            storeComponentSelect.value = '';
            productDetails.style.display = 'none';
            document.getElementById('priceCalculation').innerHTML = '';
        }

        // Modify subcategory select to trigger store component update
        document.querySelector('[name="subcategory_id"]').addEventListener('change', function() {
            updateStoreComponents(this.value);
        });

        function closeSuccessPopup() {
            document.getElementById('successPopup').style.display = 'none';
            window.location.href = 'recycle.php';
        }

        function showProductDetails(componentId) {
            const select = document.querySelector('[name="store_component"]');
            const option = select.options[select.selectedIndex];
            const productDetails = document.getElementById('productDetails');
            
            if (!componentId) {
                productDetails.style.display = 'none';
                return;
            }

            // Update product details
            document.getElementById('productImage').src = option.dataset.image;
            document.getElementById('productName').textContent = option.dataset.name;
            document.getElementById('productDescription').textContent = option.dataset.description;
            document.getElementById('productPrice').textContent = `Price: ${option.dataset.price} DZD`;
            
            productDetails.style.display = 'block';
            calculatePrice(); // Update price calculation
        }
    </script>
</body>
</html>
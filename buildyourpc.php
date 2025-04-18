<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db_connect.php';

// Add these at the top of the file after session_start()
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Fetch site settings for theme
$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
    }
}

// Get theme mode and font family from settings
$theme_mode = $settings['theme_mode'] ?? 'light';
$font_family = $settings['font_family'] ?? 'Poppins';

include 'header.php';

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

function fetchSubcategories($categoryId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchProductsBySubcategory($subcategoryIds) {
    global $pdo;
    $ids = implode(',', array_map('intval', $subcategoryIds));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE subcategory_id IN ($ids) AND category_id IN (SELECT id FROM categories WHERE name IN ('hardware'))");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchCategories() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchWilayas() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM wilayas ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = fetchCategories();
$wilayas = fetchWilayas();
$subcategoryOrder = [];
$requiredCategories = ['hardware'];


foreach ($categories as $category) {
    if (in_array($category['name'], $requiredCategories)) {
        $subcategories = fetchSubcategories($category['id']);
        if (!empty($subcategories)) {
            $subcategoryOrder[$category['name']] = $subcategories;
        }
    }
}

if (empty($subcategoryOrder)) {
    echo "<p>No subcategories found for the selected categories.</p>";
} else {
    $productsBySubcategory = [];
    foreach ($subcategoryOrder as $subcategoryList) {
        foreach ($subcategoryList as $subcategory) {
            $productsBySubcategory[$subcategory['id']] = fetchProductsBySubcategory([$subcategory['id']]);
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Check if POST data is received
        error_log("POST data received: " . print_r($_POST, true));

        // Validate and sanitize input
        $user_email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($_POST['address'], ENT_QUOTES, 'UTF-8');
        $wilaya_id = filter_var($_POST['wilaya'], FILTER_VALIDATE_INT);
        $total_price = filter_var($_POST['total_price'], FILTER_VALIDATE_FLOAT);
        $chosen_parts = json_decode($_POST['chosen_parts'], true) ?? [];

        // Debug: Check if required fields are present
        error_log("Validating fields: email=$user_email, phone=$phone, address=$address, wilaya_id=$wilaya_id, total_price=$total_price");

        // Validate required fields
        if (!$user_email || !$phone || !$address || !$wilaya_id || !$total_price) {
            throw new Exception("All fields are required. Please fill in all the information.");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Insert the order
        $stmt = $pdo->prepare("
            INSERT INTO buildyourpc_orders 
            (user_email, phone, address, wilaya_id, total_price, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $user_email,
            $phone,
            $address,
            $wilaya_id,
            $total_price
        ]);

        $order_id = $pdo->lastInsertId();

        if (!$order_id) {
            throw new Exception("Failed to create order.");
        }

        // Insert order details
        $detail_stmt = $pdo->prepare("
            INSERT INTO buildyourpc_order_details 
            (order_id, product_id) 
            VALUES (?, ?)
        ");

        foreach ($chosen_parts as $product_id) {
            if (!is_numeric($product_id) || $product_id <= 0) {
                throw new Exception("Invalid product ID in chosen parts.");
            }
            $detail_stmt->execute([$order_id, $product_id]);
        }

        // Send Telegram notification
        $telegram_message = "New PC Build Order:\n\n";
        $telegram_message .= "Order ID: $order_id\n";
        $telegram_message .= "Email: $user_email\n";
        $telegram_message .= "Phone: $phone\n";
        $telegram_message .= "Address: $address\n";
        $telegram_message .= "Wilaya ID: $wilaya_id\n";
        $telegram_message .= "Total Price: $total_price DZD\n";
        $telegram_message .= "Products:\n";

        foreach ($chosen_parts as $product_id) {
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $telegram_message .= "- " . $product['name'] . "\n";
        }

        $telegram_token = '7322742533:AAEEYMpmOGhkwuOyfU-6Y4c6UtjK09ti9vE';
        $chat_id = '-1002458122628';
        $telegram_url = "https://api.telegram.org/bot$telegram_token/sendMessage";

        $ch = curl_init($telegram_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'text' => $telegram_message
        ]);
        $telegram_result = curl_exec($ch);
        curl_close($ch);

        // Send email confirmation
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
            $mail->setFrom('lokmen13.messabhia@gmail.com', 'EcoTech PC Builder');
            $mail->addAddress($user_email);

            // Get logo URL from settings
            $logo_url = $settings['logo'] ?? '';

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your PC Build Order Confirmation';
            $mail->Body = "
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        " . ($logo_url ? "<img src='http://localhost/lokpixpc/$logo_url' alt='EcoTech Logo' style='max-width: 200px; height: auto;'>" : "") . "
                    </div>
                    <h1 style='color: #2563eb; text-align: center;'>Thank you for your order!</h1>
                    <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin-top: 20px;'>
                        <h2 style='color: #1e40af; margin-bottom: 15px;'>Order Details</h2>
                        <p><strong>Order ID:</strong> $order_id</p>
                        <p><strong>Total Price:</strong> $total_price DZD</p>
                        <h3 style='color: #1e40af; margin-top: 20px; margin-bottom: 15px;'>Selected Products:</h3>
                        <ul style='list-style: none; padding: 0;'>
            ";

            foreach ($chosen_parts as $product_id) {
                $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $mail->Body .= "<li style='padding: 10px; background: #fff; margin-bottom: 5px; border-radius: 4px;'>" . $product['name'] . "</li>";
            }

            $mail->Body .= "
                        </ul>
                    </div>
                    <div style='text-align: center; margin-top: 30px; color: #64748b; font-size: 0.9em;'>
                        <p>If you have any questions, please contact our support team.</p>
                        <p>Thank you for choosing EcoTech PC Builder!</p>
                    </div>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log("Email Error: " . $mail->ErrorInfo);
        }

        // Commit transaction
        $pdo->commit();

        // Display success message
        echo "<script>displayPopup('Order placed successfully! Check your email for confirmation.');</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log the error
        error_log("Order Error: " . $e->getMessage());
        
        // Display error message
        echo "<script>displayPopup('Error placing order: " . addslashes($e->getMessage()) . "');</script>";
    }
} else {
    echo "<script>displayPopup('Please submit the form.');</script>";
}

?>

<link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font_family) ?>:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    /* Theme Variables */
    :root {
        /* Light Mode Colors */
        --background: <?= $settings['background_color'] ?? '#f0f2f5' ?>;
        --text-primary: <?= $settings['text_color'] ?? '#1a1a1a' ?>;
        --text-secondary: #4a5568;
        --card-bg: rgba(255, 255, 255, 0.95);
        --card-border: rgba(255, 255, 255, 0.8);
        --subcategory-bg: rgba(248, 250, 252, 0.8);
        --subcategory-border: rgba(59, 130, 246, 0.1);
        --primary-color: <?= $settings['primary_color'] ?? '#3b82f6' ?>;
        --accent-color: <?= $settings['accent_color'] ?? '#2563eb' ?>;
        --divider-color: #edf2f7;
        --shadow-color: rgba(0, 0, 0, 0.04);
        --shadow-accent: rgba(59, 130, 246, 0.03);
        --hover-bg: rgba(59, 130, 246, 0.06);
        --font-family: '<?= $font_family ?>', system-ui, -apple-system, sans-serif;
        --input-border: rgba(59, 130, 246, 0.2);
        --input-background: rgba(255, 255, 255, 0.9);
        --focus-ring-color: rgba(59, 130, 246, 0.15);
        --input-placeholder: #6b7280;
        --input-hint: #9ca3af;
        --input-focus-bg: rgba(255, 255, 255, 0.02);
        --input-hover-bg: rgba(255, 255, 255, 0.01);
    }

    /* Dark Mode Colors */
    <?php if ($theme_mode === 'dark'): ?>
    :root {
        --background: #121212;
        --text-primary: #f8f9fa;
        --text-secondary: #a0aec0;
        --card-bg: rgba(26, 26, 26, 0.95);
        --card-border: rgba(255, 255, 255, 0.1);
        --subcategory-bg: rgba(31, 31, 31, 0.8);
        --subcategory-border: rgba(59, 130, 246, 0.2);
        --divider-color: #2d3748;
        --shadow-color: rgba(0, 0, 0, 0.2);
        --shadow-accent: rgba(59, 130, 246, 0.05);
        --hover-bg: rgba(59, 130, 246, 0.1);
        --input-background: rgba(17, 24, 39, 0.8);
        --input-border: rgba(75, 85, 99, 0.4);
        --input-placeholder: #6b7280;
        --input-hint: #9ca3af;
        --input-focus-bg: rgba(255, 255, 255, 0.03);
        --input-hover-bg: rgba(255, 255, 255, 0.01);
    }
    <?php endif; ?>

    body {
        font-family: var(--font-family);
        background: var(--background);
        color: var(--text-primary);
        line-height: 1.6;
        min-height: 100vh;
        margin: 0;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .container {
        max-width: 1400px;
        margin: 40px auto;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        padding: 0 20px;
    }

    .categories, .contact-form {
        background: var(--card-bg);
        border-radius: 24px;
        box-shadow: 
            0 20px 40px var(--shadow-color),
            0 8px 16px var(--shadow-accent);
        backdrop-filter: blur(10px);
        border: 1px solid var(--card-border);
        padding: 40px;
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }

    .main-title {
        font-size: 32px;
        margin-bottom: 35px;
        color: var(--text-primary);
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--divider-color);
        position: relative;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .main-title::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100px;
        height: 3px;
        background: linear-gradient(90deg, var(--primary-color), transparent);
        border-radius: 3px;
    }

    .subcategory {
        margin-bottom: 25px;
        padding: 25px;
        border-radius: 16px;
        background: var(--subcategory-bg);
        border: 1px solid var(--subcategory-border);
        transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
    }

    .subcategory:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px var(--hover-bg);
    }

    .subcategory-title {
        font-size: 1.1rem;
        font-weight: 500;
        color: var(--text-secondary);
        transition: all 0.3s ease;
        position: relative;
        padding-left: 20px;
        cursor: pointer;
    }

    .subcategory-title::before {
        content: '→';
        position: absolute;
        left: 0;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .subcategory:hover .subcategory-title::before {
        opacity: 1;
        color: var(--primary-color);
    }

    .subcategory:hover .subcategory-title {
        color: var(--primary-color);
    }

    .product-list select {
        width: 100%;
        padding: 14px 18px;
        border: 1px solid var(--input-border);
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background-color: var(--input-background);
        color: var(--text-primary);
        margin-top: 15px;
        font-family: var(--font-family);
        cursor: pointer;
        -webkit-appearance: none;
        appearance: none;
    }

    .product-list select:hover {
        border-color: var(--primary-color);
        background-color: var(--card-bg);
    }

    .product-list select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px var(--focus-ring-color);
        background-color: var(--card-bg);
    }

    .product-list select option {
        background-color: var(--card-bg);
        color: var(--text-primary);
        padding: 12px;
    }

    .product-details {
        display: none;
        background: var(--card-bg);
        padding: 20px;
        border-radius: 15px;
        margin-top: 15px;
        border: 1px solid var(--card-border);
        transition: all 0.3s ease;
        box-shadow: 0 3px 5px var(--shadow-color);
    }

    .product-details.active {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 20px;
        align-items: start;
    }

    .product-details img {
        width: 100%;
        height: 180px;
        object-fit: contain;
        border-radius: 10px;
        padding: 10px;
        background: var(--card-bg);
        box-shadow: 0 2px 4px var(--shadow-color);
        transition: transform 0.3s ease;
    }

    .product-details .product-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 5px;
    }

    .product-info p {
        margin: 0;
        line-height: 1.4;
        font-size: 0.85rem;
        color: var(--text-primary);
    }

    .product-info p:first-child {
        font-size: 1.05rem;
        font-weight: 600;
        border-bottom: 1px solid var(--divider-color);
        padding-bottom: 6px;
    }

    .product-info p:nth-child(2) {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--primary-color);
        background: color-mix(in srgb, var(--primary-color) 8%, transparent);
        padding: 6px 10px;
        border-radius: 6px;
        width: fit-content;
    }

    .product-info p:nth-child(3) {
        color: var(--text-secondary);
        font-size: 0.85rem;
        background: var(--subcategory-bg);
        padding: 10px;
        border-radius: 6px;
        border-left: 3px solid var(--primary-color);
    }

    .product-info button {
        margin-top: 8px;
        padding: 8px 18px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        width: fit-content;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .product-info button:hover {
        background: var(--accent-color);
        transform: translateY(-1.5px);
        box-shadow: 0 3px 8px var(--shadow-accent);
    }

    @media (max-width: 768px) {
        .product-details.active {
            grid-template-columns: 1fr;
        }

        .product-details img {
            height: 200px;
        }

        .product-info p:first-child {
            font-size: 1.2rem;
        }

        .product-info p:nth-child(2) {
            font-size: 1.1rem;
        }
    }

    .form-style {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .form-style input,
    .form-style select,
    #wilaya {
        width: 100%;
        padding: 14px 18px;
        border: 1px solid var(--input-border);
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background-color: var(--input-background);
        color: var(--text-primary);
        font-family: var(--font-family);
    }

    .form-style input::placeholder,
    .form-style select::placeholder {
        color: var(--input-placeholder);
        opacity: 0.8;
    }

    .form-style input:hover,
    .form-style select:hover,
    #wilaya:hover {
        border-color: var(--primary-color);
        background-color: var(--input-hover-bg);
    }

    .form-style input:focus,
    .form-style select:focus,
    #wilaya:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px var(--focus-ring-color);
        background-color: var(--input-focus-bg);
    }

    .form-style button {
        padding: 14px 28px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        position: relative;
        overflow: hidden;
    }

    .form-style button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(255, 255, 255, 0.2),
            transparent
        );
        transition: 0.5s;
    }

    .form-style button:hover::before {
        left: 100%;
    }

    .form-style button:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.25);
    }

    @media (max-width: 968px) {
        .container {
            grid-template-columns: 1fr;
            margin: 20px auto;
            padding: 0;
        }

        .categories, .contact-form {
            padding: 25px;
            border-radius: 20px;
        }

        .main-title {
            font-size: 24px;
            margin-bottom: 25px;
        }

        .subcategory {
            padding: 20px;
        }

        .product-details img {
            height: 150px;
        }

        .form-style button {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 10px;
        }

        .product-details {
            padding: 15px;
        }

        .subcategory-title {
            font-size: 1rem;
        }
    }

    .input-container {
        flex: 1;
        min-width: 300px;
        width: 100%;
    }

    .description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
    }

    .order-summary {
        margin-top: 2rem;
        padding: 1.5rem;
        background: var(--subcategory-bg);
        border-radius: 12px;
        border: 1px solid var(--card-border);
        color: var(--text-primary);
    }

    .order-summary h3 {
        color: var(--text-primary);
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }

    .total-price {
        font-size: 1.125rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .submit-button {
        width: 100%;
        padding: 14px 28px;
        border: none;
        border-radius: 12px;
        background: var(--primary-color);
        color: #ffffff;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px var(--shadow-accent);
        position: relative;
        overflow: hidden;
        margin-top: 1.5rem;
    }

    .submit-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(255, 255, 255, 0.2),
            transparent
        );
        transition: 0.5s;
    }

    .submit-button:hover::before {
        left: 100%;
    }

    .submit-button:hover {
        background: var(--accent-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.25);
    }

    @media (max-width: 768px) {
        .input-container {
            min-width: 100%;
        }
        
        .form-style input,
        .form-style select,
        #wilaya {
            width: 100%;
        }
    }

    .form-group {
        margin-bottom: 25px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .form-group label {
        width: 100%;
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-hint {
        font-size: 0.875rem;
        color: var(--input-hint);
        margin-top: 0.5rem;
        display: block;
    }

    .required-field::after {
        content: '*';
        color: var(--primary-color);
        margin-left: 4px;
    }

    .select-wrapper {
        position: relative;
        width: 100%;
    }

    .select-wrapper::after {
        content: '›';
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%) rotate(90deg);
        color: var(--primary-color);
        font-size: 1.25rem;
        pointer-events: none;
        transition: all 0.2s ease;
    }

    select {
        appearance: none;
        padding-right: 40px !important;
    }

    select option {
        background-color: var(--input-background);
        color: var(--text-primary);
        padding: 12px;
    }

    select option:checked {
        background-color: var(--primary-color);
        color: white;
    }

    .form-style input:focus ~ label,
    .form-style select:focus ~ label {
        color: var(--primary-color);
        transform: translateY(-1.5rem) scale(0.85);
    }

    /* Modal Styling */
    .modal {
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background: var(--card-bg);
        color: var(--text-primary);
        border: 1px solid var(--card-border);
        box-shadow: 0 25px 50px -12px var(--shadow-color);
    }

    .close {
        color: var(--text-secondary);
    }

    .close:hover {
        color: var(--text-primary);
    }

    /* Back to Top Button */
    .back-to-top {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 4px 15px var(--shadow-color);
    }

    .back-to-top:hover {
        background: var(--accent-color);
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 var(--focus-ring-color);
        }
        70% {
            box-shadow: 0 0 0 10px transparent;
        }
        100% {
            box-shadow: 0 0 0 0 transparent;
        }
    }

    /* Order Summary */
    .order-summary {
        background: var(--subcategory-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
    }

    .order-summary h3 {
        color: var(--text-primary);
    }

    .total-price {
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Submit Button */
    .submit-button {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 4px 6px var(--shadow-accent);
    }

    .submit-button:hover {
        background: var(--accent-color);
    }

    /* Form Labels */
    .form-group label {
        color: var(--text-primary);
    }

    /* Description Text */
    .description {
        color: var(--text-secondary);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .product-details.active {
            grid-template-columns: 1fr;
        }

        .form-style input,
        .form-style select,
        #wilaya {
            font-size: 16px;
            padding: 16px 45px 16px 20px;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 10px;
        }

        .product-details {
            padding: 15px;
        }

        .subcategory-title {
            font-size: 1rem;
        }
    }

    /* Add this to your existing CSS */
    .popup-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .popup-modal.show {
        opacity: 1;
    }

    .popup-content {
        background: var(--card-bg);
        color: var(--text-primary);
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 25px 50px -12px var(--shadow-color);
        position: relative;
        width: 90%;
        max-width: 500px;
        margin: 15% auto;
        text-align: center;
        transform: translateY(-20px);
        opacity: 0;
        transition: all 0.3s ease;
        border: 1px solid var(--card-border);
    }

    .popup-modal.show .popup-content {
        transform: translateY(0);
        opacity: 1;
    }

    .popup-content h3 {
        color: var(--text-primary);
        font-size: 1.5rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .popup-content p {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 1.5rem;
        line-height: 1.5;
    }

    .popup-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--background);
        border: none;
        color: var(--text-secondary);
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1.25rem;
    }

    .popup-close:hover {
        background: var(--input-border);
        color: var(--text-primary);
    }

    .popup-button {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .popup-button:hover {
        background: var(--accent-color);
        transform: translateY(-2px);
    }

    @keyframes slideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<div class="container">
    <div class="categories">
        <div class="main-title">EcoTech PC Builder</div>
        <?php foreach ($subcategoryOrder as $categoryName => $subcategories): ?>
            <form>
                <?php foreach ($subcategories as $subcategory): ?>
                    <div class="subcategory">
                        <div class="subcategory-title" onclick="toggleProductList('<?php echo htmlspecialchars($subcategory['id']); ?>')">
                            <?php echo htmlspecialchars($subcategory['name']); ?>
                        </div>
                        <ul id="product-list-<?php echo htmlspecialchars($subcategory['id']); ?>" class="product-list" style="display: none;">
                            <?php if (!empty($productsBySubcategory[$subcategory['id']])): ?>
                                <select onchange="showProductDetails(this, '<?php echo htmlspecialchars($subcategory['id']); ?>')">
                                    <option value="">Select a product</option>
                                    <?php foreach ($productsBySubcategory[$subcategory['id']] as $product): ?>
                                        <option value="<?php echo htmlspecialchars($product['id']); ?>" 
                                                data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                                                data-price="<?php echo htmlspecialchars($product['price']); ?>   DZD" 
                                                data-description="<?php echo htmlspecialchars($product['description']); ?>" 
                                                data-image="<?php 
                                                    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = :product_id");
                                                    $stmt->execute(['product_id' => $product['id']]);
                                                    $product_image = $stmt->fetchColumn();
                                                    echo htmlspecialchars($product_image ? 'uploads/products/' . $product_image : ''); 
                                                ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> - $<?php echo htmlspecialchars($product['price']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <li>No products available in this subcategory.</li>
                            <?php endif; ?>
                        </ul>
                        <div id="product-details-<?php echo htmlspecialchars($subcategory['id']); ?>" class="product-details" style="margin-top: 20px; display: none;">
                            <h2>Product Details</h2>
                            <img id="product-image-<?php echo htmlspecialchars($subcategory['id']); ?>" src="" alt="" style="max-width: 200px; display: none;">
                            <p id="product-name-<?php echo htmlspecialchars($subcategory['id']); ?>">haha</p>
                            <p id="product-price-<?php echo htmlspecialchars($subcategory['id']); ?>"></p>
                            <p id="product-description-<?php echo htmlspecialchars($subcategory['id']); ?>"></p>
                            <button type="button" id="product-details-button-<?php echo htmlspecialchars($subcategory['id']); ?>" style="display: none;" onclick="redirectToProduct('<?php echo htmlspecialchars($subcategory['id']); ?>')">Details</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="contact-form">
        <form method="POST" action="buildyourpc.php" class="form-style" id="orderForm" onsubmit="return validateForm()">
            <h2 class="main-title">EcoTech Contact Information</h2>
            
            <div class="form-group">
                <label for="email" class="required-field">Email</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your email address">
                <span class="form-hint">We'll send order confirmation to this email</span>
            </div>

            <div class="form-group">
                <label for="phone" class="required-field">Phone</label>
                <input type="tel" id="phone" name="phone" required 
                       placeholder="Enter your phone number">
                <span class="form-hint">For delivery coordination</span>
            </div>

            <div class="form-group">
                <label for="address" class="required-field">Address</label>
                <input type="text" id="address" name="address" required 
                       placeholder="Enter your delivery address">
                <span class="form-hint">Provide complete address for delivery</span>
            </div>

            <div class="form-group">
                <label for="wilaya" class="required-field">Wilaya</label>
                <div class="select-wrapper">
                    <select name="wilaya" id="wilaya" required>
                        <option value="" disabled selected>Choose your Wilaya</option>
                        <?php foreach ($wilayas as $wilaya): ?>
                            <option value="<?= htmlspecialchars($wilaya['id']) ?>">
                                <?= htmlspecialchars($wilaya['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="form-hint">Select your delivery location</span>
            </div>

            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="total-price">
                    Total Price: <span id="total-price">$0.00</span>
                </div>
                <input type="hidden" name="total_price" id="hidden-total-price" value="0">
                <input type="hidden" name="chosen_parts" id="hidden-chosen-parts" value="">
            </div>

            <button type="submit" class="submit-button">Place Order</button>
        </form>

    </div>
</div>
<?php   include'footer.php' ?>
    <button class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>
<script>
let selectedProducts = new Map();
let totalPrice = 0;

function toggleProductList(subcategoryId) {
    var productList = document.getElementById('product-list-' + subcategoryId);
    productList.style.display = productList.style.display === 'none' ? 'block' : 'none';
}

function showProductDetails(select, subcategoryId) {
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.value === "") {
        if (selectedProducts.has(subcategoryId)) {
            totalPrice -= parseFloat(selectedProducts.get(subcategoryId).price);
            selectedProducts.delete(subcategoryId);
        }
        clearProductDetails(subcategoryId);
    } else {
        const productId = selectedOption.value;
        const name = selectedOption.getAttribute('data-name');
        const price = selectedOption.getAttribute('data-price');
        const description = selectedOption.getAttribute('data-description');
        const image = selectedOption.getAttribute('data-image');
        const productDetails = document.getElementById('product-details-' + subcategoryId);

        if (selectedProducts.has(subcategoryId)) {
            totalPrice -= parseFloat(selectedProducts.get(subcategoryId).price);
        }

        selectedProducts.set(subcategoryId, {
            id: productId,
            name: name,
            price: price,
            description: description
        });
        totalPrice += parseFloat(price);

        productDetails.innerHTML = `
            <img id="product-image-${subcategoryId}" src="${image}" alt="${name}">
            <div class="product-info">
                <p id="product-name-${subcategoryId}">Name: ${name}</p>
                <p id="product-price-${subcategoryId}">Price:${price}</p>
                <p id="product-description-${subcategoryId}">Description: ${description}</p>
                <button type="button" id="product-details-button-${subcategoryId}" onclick="redirectToProduct('${subcategoryId}')">Details</button>
            </div>
        `;

        productDetails.style.display = 'grid';
        productDetails.classList.add('active');
    }

    updateTotalPrice();
}

function updateTotalPrice() {
    let total = 0;
    let chosenProductIds = [];
    
    selectedProducts.forEach(product => {
        total += parseFloat(product.price);
        chosenProductIds.push(product.id);
    });
    
    document.getElementById('total-price').textContent = `${total.toFixed(2)}   DZD`;
    document.getElementById('hidden-total-price').value = total.toFixed(2);
    document.getElementById('hidden-chosen-parts').value = JSON.stringify(chosenProductIds);
    
}

function validateForm() {
    // Check if at least one product is selected
    if (selectedProducts.size === 0) {
        displayPopup('Please select at least one product before submitting the order.');
        return false;
    }

    // Validate required fields
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const address = document.getElementById('address').value;
    const wilaya = document.getElementById('wilaya').value;

    if (!email || !phone || !address || !wilaya) {
        displayPopup('Please fill in all required fields.');
        return false;
    }

    return true;
}

function redirectToProduct(subcategoryId) {
    const select = document.querySelector(`#product-list-${subcategoryId} select`);
    const productId = select.value;
    if (productId) {
        window.location.href = 'product.php?id=' + productId;
    }
}

function clearProductDetails(subcategoryId) {
    const productDetails = document.getElementById('product-details-' + subcategoryId);
    const imgElement = document.getElementById('product-image-' + subcategoryId);
    imgElement.style.display = 'none';
    productDetails.style.display = 'none';
    document.getElementById('product-name-' + subcategoryId).textContent = '';
    document.getElementById('product-price-' + subcategoryId).textContent = '';
    document.getElementById('product-description-' + subcategoryId).textContent = '';
    document.getElementById('product-details-button-' + subcategoryId).style.display = 'none';
}

function resetSelections() {
    selectedProducts.clear();
    totalPrice = 0;
    updateTotalPrice();

    const productDetailsSections = document.querySelectorAll('[id^="product-details-"]');
    productDetailsSections.forEach(section => {
        section.style.display = 'none';
    });
    const productImageSections = document.querySelectorAll('[id^="product-image-"]');
    productImageSections.forEach(image => {
        image.style.display = 'none';
    });
}

function displayPopup(message) {
    const popup = document.getElementById('orderPopup');
    const popupMessage = document.getElementById('popupMessage');
    
    // Set the message
    popupMessage.textContent = message;
    
    // Show the popup
    popup.style.display = 'block';
    setTimeout(() => {
        popup.classList.add('show');
    }, 10);

    // Handle click outside
    popup.addEventListener('click', function(e) {
        if (e.target === popup) {
            closePopup();
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });
}

function closePopup() {
    const popup = document.getElementById('orderPopup');
    popup.classList.remove('show');
    setTimeout(() => {
        popup.style.display = 'none';
    }, 300);
}

// Add this JavaScript for the Back to Top button
const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) { // Show button after scrolling 300px
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
</script>

<div id="orderPopup" class="popup-modal">
    <div class="popup-content">
        <button class="popup-close" onclick="closePopup()">&times;</button>
        <h3>Order Status</h3>
        <p id="popupMessage"></p>
        <button class="popup-button" onclick="closePopup()">OK</button>
    </div>
</div>

<?php
session_start();
include 'db_connect.php';
// Include the necessary libraries
require_once('fpdf/fpdf.php'); // Ensure you have the FPDF library
require_once('phpqrcode/qrlib.php'); // Use require_once to avoid multiple inclusions

$isAdmin = false; // Default to false
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $admin = $stmt->fetch();

        if ($admin) {
            $isAdmin = true; // Set true if email exists in the admins table
            $_SESSION['admin_id'] = $admin['id']; // Store admin ID in session
            $_SESSION['admin_role'] = $admin['role']; // Store admin role in session
        }
    } catch (PDOException $e) {
        echo "Error: Unable to verify admin status. " . $e->getMessage();
    }
}

// Check if the user is logged in and is an admin
if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Fetch the order details
$stmt = $pdo->prepare("SELECT orders.id, users.email AS user_email, orders.phone, orders.address, orders.wilaya_id, orders.delivery_type, orders.total_price, orders.order_date, orders.status, orders.tracking_number, orders.qrtoken
                       FROM orders 
                       JOIN users ON orders.user_id = users.id 
                       WHERE orders.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Debugging: Check the value of qrtoken
if ($order) {
   // echo "QR Token: " . htmlspecialchars($order['qrtoken']); // Debugging line
} else {
    echo "Error: Order not found.";
    exit();
}

// Fetch the order items
$stmt_items = $pdo->prepare("SELECT products.name, order_details.quantity, products.price 
                             FROM order_details 
                             JOIN products ON order_details.product_id = products.id 
                             WHERE order_details.order_id = ?");
$stmt_items->execute([$order_id]);
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Fetch Wilaya name (assuming you have a wilayas table)
$stmt_wilaya = $pdo->prepare("SELECT name FROM wilayas WHERE id = ?");
$stmt_wilaya->execute([$order['wilaya_id']]);
$wilaya = $stmt_wilaya->fetch(PDO::FETCH_ASSOC);
$order['wilaya_name'] = $wilaya ? $wilaya['name'] : 'Unknown'; // If no wilaya found, use 'Unknown'

// Handle ticket validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_ticket'])) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'validated' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Set a success message in session
        $_SESSION['validation_success'] = "Order #$order_id has been successfully validated!";
        
        // Return JSON response instead of redirecting immediately
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true, 'message' => "Order #$order_id has been successfully validated!"]);
            exit();
        } else {
            // Fallback for non-AJAX requests
            header("Location: orders.php");
            exit();
        }
    } catch (PDOException $e) {
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'message' => "Error: Unable to validate ticket. " . $e->getMessage()]);
            exit();
        } else {
            echo "Error: Unable to validate ticket. " . $e->getMessage();
            exit();
        }
    }
}

// Handle tracking information submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    $tracking_number = $_POST['tracking_number'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    $additional_info = $_POST['additional_info'];

    try {
        // Update tracking number in the orders table
        $stmt_order = $pdo->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?");
$stmt_order->execute([$tracking_number, $order_id]);


        // Check if tracking information already exists for this order
        $stmt_check = $pdo->prepare("SELECT * FROM tracking_info WHERE order_id = ?");
        $stmt_check->execute([$order_id]);
        $existing_tracking = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existing_tracking) {
            // Update the existing tracking information
            $stmt = $pdo->prepare("UPDATE tracking_info SET tracking_number = ?, status = ?, last_updated = NOW(), location = ?, additional_info = ? WHERE order_id = ?");
            $stmt->execute([$tracking_number, $status, $location, $additional_info, $order_id]);
        } else {
            // Insert new tracking information if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO tracking_info (order_id, tracking_number, status, last_updated, location, additional_info) 
                                    VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$order_id, $tracking_number, $status, $location, $additional_info]);
        }

        // Refresh the page to show updated tracking info
        header("Location: order_details.php?order_id=" . $order_id);
        exit();
    } catch (PDOException $e) {
        echo "Error: Unable to update tracking information. " . $e->getMessage();
        exit();
    }
}


// Fetch tracking information for the order
$stmt_tracking = $pdo->prepare("SELECT * FROM tracking_info WHERE order_id = ?");
$stmt_tracking->execute([$order_id]);
$tracking_info = $stmt_tracking->fetchAll(PDO::FETCH_ASSOC);


// Handle PDF generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf'])) {
    // Define the PDF class
    class PDF extends FPDF
    {
        private $order;  // To store all the order details
        private $order_items; // To store order items
        private $qrtoken; // To store the qrtoken

        public function __construct($order, $order_items)
        {
            parent::__construct();
            $this->order = $order; // Store the order details
            $this->order_items = $order_items; // Store the order items
            $this->qrtoken = $order['qrtoken']; // Store the qrtoken
            // Set default font
            $this->SetFont('Arial', '', 10);
        }

        // Page header
        function Header()
        {
            // Add a gradient-style header
            $this->SetFillColor(67, 97, 238); // Primary color #4361ee
            $this->Rect(0, 0, 210, 25, 'F');
            
            // Add logo if exists (adjust path and size as needed)
            if (file_exists('images/logo.png')) {
                $this->Image('images/logo.png', 10, 5, 15, 15);
            }
            
            // Add header text
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(255, 255, 255);
            $this->SetX(30); // Adjust position after logo
            $this->Cell(0, 10, "EcoTech - Order Invoice", 0, 1, 'C');
            
            // Reset text color for the rest of the document
            $this->SetTextColor(43, 45, 66); // --text #2b2d42
            $this->Ln(15); // Add space after header
        }

        // Page footer
        function Footer()
        {
            $this->SetY(-60); // Position higher up for QR code
            
            // Add QR Code with some styling
            if (!empty($this->qrtoken)) {
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 10, 'Scan to view order details:', 0, 1, 'C');
                
                $url = 'http://localhost/lokpixpc/order_details.php?qrtoken=' . $this->qrtoken;
                $qrFilePath = $this->generateQrCode($url);
                
                if (file_exists($qrFilePath)) {
                    $this->Image($qrFilePath, 90, $this->GetY(), 30, 30);
                    unlink($qrFilePath); // Delete the QR code file after use
                } else {
                    $this->Cell(0, 10, 'Failed to generate QR code', 0, 1, 'C');
                }
            }
            
            // Add footer with styling
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(108, 117, 125); // --text-light #6c757d
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | Generated by EcoTech ' . date('Y-m-d'), 0, 0, 'C');
        }

        // Function to generate a QR code and return the image file path
        function generateQrCode($url)
        {
            // Use a more reliable temp directory path
            $tempDir = dirname(__FILE__) . '/temp';
            
            // Create the temp directory if it doesn't exist
            if (!is_dir($tempDir)) {
                if (!mkdir($tempDir, 0777, true)) {
                    // If we can't create the directory, use the system temp directory
                    $tempDir = sys_get_temp_dir();
                }
            }
            
            $filePath = $tempDir . DIRECTORY_SEPARATOR . 'qr_' . uniqid() . '.png';
            
            // Error handling for QR code generation
            try {
                QRcode::png($url, $filePath, 'L', 4, 4);
                return $filePath;
            } catch (Exception $e) {
                error_log('QR Code Generation Error: ' . $e->getMessage());
                return false;
            }
        }

        // Function to output buyer info with improved styling
        function BuyerInfo()
        {
            // Add a styled section header with gradient effect (no border)
            $this->SetFillColor(72, 149, 239); // --primary-light #4895ef
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 8, "   Buyer Information", 0, 1, 'L', true);
            $this->SetTextColor(43, 45, 66); // --text #2b2d42
            $this->Ln(5);
            
            // Create a two-column layout for customer information
            $this->SetFont('Arial', '', 10);
            
            // Left column
            $this->SetX(20);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Order ID:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 8, $this->order['id'], 0);
            
            // Right column
            $this->SetX(120);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Order Date:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 8, $this->order['order_date'], 0, 1);
            
            // Continue with other info in the same format
            $this->SetX(20);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Email:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 8, $this->order['user_email'], 0);
            
            $this->SetX(120);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Phone:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 8, $this->order['phone'], 0, 1);
            
            $this->SetX(20);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Address:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(140, 8, $this->order['address'], 0, 1);
            
            $this->SetX(20);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 8, "Delivery Type:", 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 8, $this->order['delivery_type'], 0);
            
             
         
            
            // Total with highlight
            $this->Ln(5);
            $this->SetX(20);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(40, 10, "Total Price:", 0);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(63, 55, 201); // --primary-dark #3f37c9
            $this->Cell(50, 10, "$" . $this->order['total_price'], 0, 1);
            $this->SetTextColor(43, 45, 66); // Reset text color
            
            $this->Ln(10); // Add space
        }

        // Draw a table cell with custom styling (gradient-like) with no visible border
        function GradientCell($w, $h, $txt, $align='L', $fill=false, $background='white')
        {
            // Save the current fill color
            $currentFill = $this->FillColor;
            
            if ($background != 'white' && $fill) {
                $this->SetFillColor(...$background);
            }
            
            // Draw cell without visible border
            $this->Cell($w, $h, $txt, 0, 0, $align, $fill);
            
            // Restore the original fill color
            $this->FillColor = $currentFill;
        }

        // Function to output order items table with improved styling
        function OrderTable()
        {
            // Add a styled section header (no border)
            $this->SetFillColor(67, 97, 238); // --primary #4361ee
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 8, "   Order Details", 0, 1, 'L', true);
            $this->SetTextColor(43, 45, 66); // Reset text color
            $this->Ln(5);
            
            // Create custom table with gradient look and no borders
            // Table headers with styling
            $headerBackground = [72, 149, 239]; // --primary-light #4895ef
            $this->SetFillColor(...$headerBackground);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(255, 255, 255);
            
            // Draw table header with no borders
            $this->GradientCell(90, 10, ' Product', 'L', true, $headerBackground);
            $this->GradientCell(25, 10, 'Quantity', 'C', true, $headerBackground);
            $this->GradientCell(35, 10, 'Unit Price', 'R', true, $headerBackground);
            $this->GradientCell(40, 10, 'Total', 'R', true, $headerBackground);
            $this->Ln();
            
            // Add subtle shadow underneath header (gradient effect)
            $this->SetDrawColor(230, 230, 230);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(43, 45, 66); // --text #2b2d42
            
            $fill = false;
            $totalAmount = 0;
            
            foreach ($this->order_items as $item) {
                // Calculate the item total
                $itemTotal = $item['quantity'] * $item['price'];
                $totalAmount += $itemTotal;
                
                // Set alternate row background
                if ($fill) {
                    $rowBackground = [248, 249, 250]; // --bg #f8f9fa
                } else {
                    $rowBackground = [255, 255, 255]; // --bg-card #ffffff
                }
                
                // Draw cells without visible borders
                $this->GradientCell(90, 8, ' ' . $item['name'], 'L', $fill, $rowBackground);
                $this->GradientCell(25, 8, $item['quantity'], 'C', $fill, $rowBackground);
                $this->GradientCell(35, 8, "$" . number_format($item['price'], 2), 'R', $fill, $rowBackground);
                $this->GradientCell(40, 8, "$" . number_format($itemTotal, 2), 'R', $fill, $rowBackground);
                $this->Ln();
                
                // Add subtle separator line
                $this->SetDrawColor(240, 240, 240);
                $this->Line(10, $this->GetY(), 200, $this->GetY());
                
                $fill = !$fill;
            }
            
            // Add a total row with styling (no visible border)
            $totalBackground = [67, 97, 238]; // --primary #4361ee
            $this->SetFillColor(...$totalBackground);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(255, 255, 255);
            $this->GradientCell(150, 10, 'TOTAL', 'R', true, $totalBackground);
            $this->GradientCell(40, 10, "$" . number_format($totalAmount, 2), 'R', true, $totalBackground);
            $this->Ln();
            
            // Add note about payment
            $this->Ln(10);
            $this->SetFont('Arial', 'I', 9);
            $this->SetTextColor(108, 117, 125); // --text-light #6c757d
            $this->Cell(0, 5, 'Thank you for your order! For any questions please contact our customer service.', 0, 1, 'C');
        }
    }

    // Create PDF instance with necessary data
    try {
        $pdf = new PDF($order, $order_items);
        $pdf->AddPage();  
        // Add buyer info
        $pdf->BuyerInfo();

        // Add order table
        $pdf->OrderTable();

        // Output the PDF
        $pdf->Output("D", "Ticket_Order_" . $order['id'] . ".pdf");
        exit();
    } catch (Exception $e) {
        echo "Error generating PDF: " . $e->getMessage();
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['ticket_number'] ?> - EcoTech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3f37c9;
            --success: #4cc9f0;
            --success-dark: #4895ef;
            --danger: #f72585;
            --warning: #f8961e;
            --text: #2b2d42;
            --text-light: #6c757d;
            --bg: #f8f9fa;
            --bg-card: #ffffff;
            --border: #e9ecef;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation */
        .top-nav {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.75rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }

        .nav-brand img {
            height: 40px;
            width: auto;
        }

        .nav-brand h1 {
            background: linear-gradient(45deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .nav-menu a {
            color: var(--text);
            text-decoration: none;
            padding: 0.6rem 0.9rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: var(--bg);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .nav-end {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }

        .back-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            background-color: var(--bg);
        }

        .back-button:hover {
            background-color: var(--primary-light);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-top: 4.5rem;
            padding: 2rem;
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .ticket-header {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .ticket-logo {
            width: 120px;
            height: auto;
            margin-bottom: 1rem;
        }

        .ticket-info h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .ticket-status {
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .ticket-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .ticket-details, .ticket-sidebar {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }

        .detail-value {
            color: var(--dark);
        }

        .products-list {
            margin-top: 2rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .product-image {
            width: 80px;
            height: 80px;
            border-radius: var(--radius);
            object-fit: cover;
            margin-right: 1rem;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .product-price {
            color: var(--secondary);
            font-size: 0.875rem;
        }

        .status-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--light);
            color: var(--dark);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            border: none;
            background: var(--primary);
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-back {
            background: var(--secondary);
            margin-right: 1rem;
        }

        .timeline {
            margin-top: 2rem;
            padding-left: 2rem;
            border-left: 2px solid var(--border);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.4rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid var(--light);
        }

        .timeline-date {
            font-size: 0.875rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius);
        }

        @media (max-width: 768px) {
            .ticket-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <img src="assets/images/logo.png" alt="EcoTech Logo" onerror="this.src='assets/images/placeholder-logo.png'">
            <h1>EcoTech</h1>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="orders.php">Orders</a>
            <a href="products.php">Products</a>
            <a href="categories.php">Categories</a>
            <a href="tickets.php" class="active">Tickets</a>
        </div>
        <div class="nav-end">
            <a href="orders.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
        </div>
    </div>

    <main class="main-content">
        <div class="container">
            <div class="ticket-header">
                <div class="ticket-info">
                    <img src="assets/images/ticket-logo.png" alt="Ticket Logo" class="ticket-logo" 
                         onerror="this.src='assets/images/placeholder-ticket.png'">
                    <h1>Ticket #<?= $ticket['ticket_number'] ?></h1>
                    <p>Created on <?= date('F j, Y, g:i a', strtotime($ticket['created_at'])) ?></p>
                </div>
                <span class="ticket-status status-<?= strtolower($ticket['status']) ?>">
                    <?= ucfirst($ticket['status']) ?>
                </span>
            </div>

            <div class="ticket-grid">
                <div class="ticket-details">
                    <div class="detail-group">
                        <div class="detail-label">Customer Information</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($ticket['user_email']) ?><br>
                            <?= htmlspecialchars($ticket['phone']) ?>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="detail-label">Shipping Address</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($ticket['address']) ?><br>
                            <?= htmlspecialchars($ticket['wilaya_name']) ?>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="detail-label">Order Total</div>
                        <div class="detail-value">
                            <?= number_format($ticket['total_price'], 2) ?> DZD
                        </div>
                    </div>

                    <div class="products-list">
                        <div class="detail-label">Products</div>
                        <?php foreach ($products as $product): ?>
                        <div class="product-item">
                            <img src="uploads/products/<?= $product['image_url'] ?? 'placeholder.jpg' ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="product-image"
                                 onerror="this.src='uploads/products/placeholder.jpg'">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price">
                                    <?= number_format($product['price'], 2) ?> DZD × <?= $product['quantity'] ?><br>
                                    <strong>Total: <?= number_format($product['price'] * $product['quantity'], 2) ?> DZD</strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ticket-sidebar">
                    <form class="status-form" id="statusForm">
                        <div class="form-group">
                            <label class="form-label">Update Status</label>
                            <select class="form-select" name="status" id="ticketStatus">
                                <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $ticket['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="completed" <?= $ticket['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $ticket['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea class="form-textarea" name="notes" id="ticketNotes"><?= htmlspecialchars($ticket['notes'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn">Update Ticket</button>
                        <a href="orders.php" class="btn btn-back">Back to Orders</a>
                    </form>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date"><?= date('F j, Y, g:i a', strtotime($ticket['created_at'])) ?></div>
                            <div class="timeline-content">
                                Ticket created
                            </div>
                        </div>
                        <?php if ($ticket['updated_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?= date('F j, Y, g:i a', strtotime($ticket['updated_at'])) ?></div>
                            <div class="timeline-content">
                                Status updated to <?= ucfirst($ticket['status']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const status = document.getElementById('ticketStatus').value;
            const notes = document.getElementById('ticketNotes').value;
            
            fetch('ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&ticket_id=<?= $ticket['id'] ?>&status=${status}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ticket updated successfully');
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to update ticket');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update ticket');
            });
        });
    </script>
</body>
</html>
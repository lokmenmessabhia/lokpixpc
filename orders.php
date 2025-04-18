<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct
require('fpdf/fpdf.php');
require('phpqrcode/qrlib.php');

// Define PDF class for ticket generation
class OrderTicket extends FPDF {
    private $order;
    private $products;

    function __construct($order, $products) {
        parent::__construct();
        $this->order = $order;
        $this->products = $products;
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(15, 15, 15);
    }

    function Header() {
        // Add logo
        if (file_exists('assets/images/logo.png')) {
            $this->Image('assets/images/logo.png', 15, 15, 40);
        }
        
        // Company Info
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(40); // Move after logo
        $this->Cell(0, 10, 'EcoTech', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(40); // Move after logo
        $this->Cell(0, 5, 'Professional Computer Hardware Solutions', 0, 1, 'L');
        $this->Cell(40); // Move after logo
        $this->Cell(0, 5, 'www.ecotech.com', 0, 1, 'L');
        
        // Order Number and Date
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 20, '', 0, 1); // Add some space
        $this->Cell(0, 10, 'ORDER TICKET #' . $this->order['id'], 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Date: ' . date('F d, Y', strtotime($this->order['order_date'])), 0, 1, 'R');
        
        // Add QR Code
        if (!empty($this->order['qrtoken'])) {
            $qrUrl = 'http://localhost/lokpixpc/order_details.php?qrtoken=' . $this->order['qrtoken'];
            $qrfile = 'temp_qr_' . $this->order['id'] . '.png';
            QRcode::png($qrUrl, $qrfile, QR_ECLEVEL_L, 3);
            $this->Image($qrfile, 170, 15, 25);
            unlink($qrfile);
        }
        
        // Separator Line
        $this->SetDrawColor(230, 230, 230);
        $this->Line(15, 75, 195, 75);
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-30);
        
        // Separator Line
        $this->SetDrawColor(230, 230, 230);
        $this->Line(15, 267, 195, 267);
        
        // Footer Text
        $this->SetY(-25);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Thank you for choosing EcoTech', 0, 1, 'C');
        
        // Page number and generation date
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' | Generated: ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }

    function OrderInfo() {
        // Customer Information Box
        $this->SetFillColor(249, 250, 251);
        $this->Rect(15, 85, 180, 45, 'F');
        
        // Customer Information Title
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 10, 'CUSTOMER INFORMATION', 0, 1);
        
        // Customer Details - Left Column
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, 'Email:', 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 6, $this->order['user_email'], 0);
        
        // Right Column
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, 'Phone:', 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, $this->order['phone'] ?: 'N/A', 0, 1);
        
        // Second Row
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, 'Address:', 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 6, $this->order['address'] ?: 'N/A', 0);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, 'Wilaya:', 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, $this->order['wilaya_name'], 0, 1);
        
        // Order Status
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, 'Status:', 0);
        $this->SetFont('Arial', '', 9);
        $status = ucfirst($this->order['status']);
        
        // Status color coding
        switch(strtolower($this->order['status'])) {
            case 'pending':
                $this->SetTextColor(243, 156, 18); break;
            case 'processing':
                $this->SetTextColor(52, 152, 219); break;
            case 'shipped':
            case 'delivered':
                $this->SetTextColor(46, 204, 113); break;
            case 'cancelled':
                $this->SetTextColor(231, 76, 60); break;
            default:
                $this->SetTextColor(44, 62, 80);
        }
        $this->Cell(0, 6, $status, 0, 1);
        
        $this->Ln(10);
    }

    function ProductsTable() {
        $this->SetTextColor(44, 62, 80);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'ORDER DETAILS', 0, 1);
        
        // Table Header
        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(90, 8, 'Product', 0, 0, 'L', true);
        $this->Cell(30, 8, 'Price', 0, 0, 'R', true);
        $this->Cell(30, 8, 'Quantity', 0, 0, 'C', true);
        $this->Cell(30, 8, 'Total', 0, 1, 'R', true);
        
        // Table Content
        $this->SetTextColor(44, 62, 80);
        $this->SetFont('Arial', '', 9);
        $total = 0;
        $rowCount = 0;
        
        foreach($this->products as $product) {
            // Alternate row colors
            $this->SetFillColor($rowCount % 2 == 0 ? 255 : 249, $rowCount % 2 == 0 ? 255 : 250, $rowCount % 2 == 0 ? 255 : 251);
            
            $this->Cell(90, 7, $product['name'], 0, 0, 'L', true);
            $this->Cell(30, 7, number_format($product['price'], 2) . ' DZD', 0, 0, 'R', true);
            $this->Cell(30, 7, $product['quantity'], 0, 0, 'C', true);
            $subtotal = $product['price'] * $product['quantity'];
            $this->Cell(30, 7, number_format($subtotal, 2) . ' DZD', 0, 1, 'R', true);
            $total += $subtotal;
            $rowCount++;
        }
        
        // Total Section
        $this->Ln(2);
        $this->SetDrawColor(230, 230, 230);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        
        // Subtotal
        $this->SetFont('Arial', '', 9);
        $this->Cell(150, 6, 'Subtotal:', 0, 0, 'R');
        $this->Cell(30, 6, number_format($total, 2) . ' DZD', 0, 1, 'R');
        
        // Total
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(150, 8, 'TOTAL:', 0, 0, 'R');
        $this->Cell(30, 8, number_format($total, 2) . ' DZD', 0, 1, 'R');
    }

    function GenerateTicket() {
        $this->AliasNbPages();
        $this->AddPage();
        $this->OrderInfo();
        $this->ProductsTable();
    }
}

// Check if user is logged in and is an admin

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

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
    header('Content-Type: application/json');
    
    try {
        $order_id = $_GET['id'];
        
        // Get order details including qrtoken
        $stmt = $pdo->prepare("
            SELECT o.*, u.email as user_email, w.name as wilaya_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN wilayas w ON o.wilaya_id = w.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Get order products with primary images
        $stmt = $pdo->prepare("
            SELECT p.*, od.quantity, pi.image_url
            FROM order_details od
            JOIN products p ON od.product_id = p.id
            LEFT JOIN (
                SELECT product_id, MIN(image_url) as image_url
                FROM product_images
                WHERE is_primary = 1
                GROUP BY product_id
            ) pi ON p.id = pi.product_id
            WHERE od.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'order' => $order,
            'products' => $products
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'generate_ticket') {
    try {
        $order_id = $_GET['id'];
        
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, u.email as user_email, w.name as wilaya_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN wilayas w ON o.wilaya_id = w.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Get order products
        $stmt = $pdo->prepare("
            SELECT p.*, od.quantity, pi.image_url
            FROM order_details od
            JOIN products p ON od.product_id = p.id
            LEFT JOIN (
                SELECT product_id, MIN(image_url) as image_url
                FROM product_images
                WHERE is_primary = 1
                GROUP BY product_id
            ) pi ON p.id = pi.product_id
            WHERE od.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate PDF
        $pdf = new OrderTicket($order, $products);
        $pdf->GenerateTicket();
        
        // Output PDF
        $pdf->Output('D', 'Order_' . $order_id . '_Ticket.pdf');
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_status') {
            $order_id = $_POST['order_id'];
            $new_status = $_POST['status'];
            
            // Update order status
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            echo json_encode(['success' => true]);
            exit;
        } 
        elseif ($_POST['action'] === 'validate_order') {
            $order_id = $_POST['order_id'];
            
            try {
                // Check if order exists and is not already validated
                $stmt = $pdo->prepare("SELECT status, is_validated FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    throw new Exception("Order not found");
                }
                
                if ($order['is_validated'] == 1) {
                    throw new Exception("Order is already validated");
                }
                
                // Generate QR token (unique random string)
                $qrtoken = bin2hex(random_bytes(16)); // 32 characters of random hex
                
                // Update order status to validated, set is_validated to 1, and save qrtoken
                $stmt = $pdo->prepare("UPDATE orders SET status = 'validated', is_validated = 1, qrtoken = ? WHERE id = ?");
                $stmt->execute([$qrtoken, $order_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order validated successfully',
                    'qrtoken' => $qrtoken
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                exit;
            }
        }
        elseif ($_POST['action'] === 'delete_order') {
            $order_id = $_POST['order_id'];
            
            $pdo->beginTransaction();
            
            // Delete order details first
            $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // Delete the order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle validation and deletion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['validate_order'])) {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("UPDATE orders SET status = 'validated' WHERE id = ?");
        $stmt->execute([$order_id]);
    } elseif (isset($_POST['delete_order'])) {
        $order_id = (int)$_POST['order_id'];

        try {
            // First, delete the order details associated with the order
            $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
            $stmt->execute([$order_id]);

            // Then, delete the order itself
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);

        } catch (PDOException $e) {
            echo "Error: Unable to delete the order. " . $e->getMessage();
            exit();
        }
    }
}

try {
    $stmt = $pdo->query("
        SELECT o.*, u.email AS user_email, w.name AS wilaya_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN wilayas w ON o.wilaya_id = w.id
        ORDER BY o.order_date DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Unable to fetch orders. " . $e->getMessage();
    exit();
}

// Check for flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - EcoTech</title>
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

        /* Global Styles */
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

        /* Nav brand and menu */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
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

        .nav-menu a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            font-weight: 600;
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

        /* Main Content - adjust to account for fixed header */
        .main-content {
            margin-top: 4.5rem;
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }

        .page-title {
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--text);
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.75rem;
        }

        .section-title {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.4rem;
            color: var(--text);
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            background-color: var(--bg-card);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background-color: var(--primary-light);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background-color: rgba(242, 242, 242, 0.6);
        }

        /* Button Styles */
        .actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            align-items: center;
        }

        .btn-view,
        .btn-validate,
        .btn-ticket,
        .btn-edit,
        .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            color: white;
        }

        .btn-view {
            background-color: var(--primary);
        }

        .btn-validate {
            background-color: var(--success);
        }

        .btn-ticket {
            background-color: var(--warning);
        }

        .btn-edit {
            background-color: var(--primary-light);
        }

        .btn-delete {
            background-color: var(--danger);
        }

        .btn-view:hover,
        .btn-validate:hover,
        .btn-ticket:hover,
        .btn-edit:hover,
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            opacity: 0.9;
        }

        .btn-view:active,
        .btn-validate:active,
        .btn-ticket:active,
        .btn-edit:active,
        .btn-delete:active {
            transform: translateY(0);
        }

        .btn-view i,
        .btn-validate i,
        .btn-ticket i,
        .btn-edit i,
        .btn-delete i {
            font-size: 1rem;
        }

        /* Footer */
        footer {
            background-color: var(--bg-card);
            color: var(--text-light);
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
            border-top: 1px solid var(--border);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav-menu, .nav-end {
                width: 100%;
                justify-content: center;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            th, td {
                padding: 0.75rem;
            }

            .actions {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
        }

        /* Add Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 2rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text);
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger);
            transform: scale(1.1);
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-group {
            padding: 1rem;
            background: var(--bg);
            border-radius: var(--radius-sm);
        }

        .info-label {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: var(--text-light);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .product-card {
            background: var(--bg);
            border-radius: var(--radius-sm);
            overflow: hidden;
            transition: var(--transition);
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .product-details {
            padding: 1rem;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .product-price {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #c3e6cb; color: #1e7e34; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            background: var(--bg-card);
            color: var(--text);
            box-shadow: var(--shadow);
            z-index: 1000;
            transition: var(--transition);
            transform: translateY(100px);
            opacity: 0;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .toast.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 1rem;
                padding: 1rem;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'dash_header.php'; ?>

    <main class="main-content">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <h2 class="page-title">Manage Orders</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['user_email']) ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($order['status']) ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </td>
                    <td><?= number_format($order['total_price'], 2) ?> DZD</td>
                    <td><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></td>
                        <td>
                            <div class="actions">
                            <button type="button" onclick="viewOrder(<?= $order['id'] ?>)" class="btn-view" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <?php if (!$order['is_validated']): ?>
                            <button type="button" onclick="validateOrder(<?= $order['id'] ?>)" class="btn-validate" title="Validate Order">
                                <i class="fas fa-check"></i>
                            </button>
                                <?php endif; ?>
                            
                            <?php if ($order['is_validated']): ?>
                            <button type="button" onclick="generateTicket(<?= $order['id'] ?>)" class="btn-ticket" title="Generate Ticket">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" onclick="updateStatus(<?= $order['id'] ?>)" class="btn-edit" title="Update Status">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button type="button" onclick="deleteOrder(<?= $order['id'] ?>)" class="btn-delete" title="Delete Order">
                                <i class="fas fa-trash"></i>
                            </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Order Details</h2>
                <button class="close-modal" onclick="closeModal('orderModal')">&times;</button>
            </div>
            <div id="orderDetails"></div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Status</h2>
                <button class="close-modal" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <select class="form-select" id="newStatus">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="btn-validate" onclick="saveStatus()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>

    <script>
        let currentOrderId = null;

        function viewOrder(orderId) {
            if (!orderId) return;
            currentOrderId = orderId;
            
            // Show loading state
            const modal = document.getElementById('orderModal');
            document.getElementById('orderDetails').innerHTML = '<div class="loading">Loading...</div>';
            modal.classList.add('show');
            
            // Fetch order details
            fetch(`orders.php?action=get_order_details&id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const products = data.products;
                        
                        let html = `
                            <div class="order-info">
                                <div class="info-group">
                                    <div class="info-label">Customer Information</div>
                                    <div class="info-value">
                                        ${order.user_email}<br>
                                        ${order.phone || 'No phone provided'}
                                    </div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Shipping Address</div>
                                    <div class="info-value">
                                        ${order.address || 'No address provided'}<br>
                                        ${order.wilaya_name || 'No wilaya provided'}
                                    </div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Order Status</div>
                                    <div class="info-value">
                                        <span class="status-badge status-${order.status || 'pending'}">
                                            ${(order.status || 'Pending').charAt(0).toUpperCase() + (order.status || 'pending').slice(1)}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Order Total</div>
                                    <div class="info-value">${order.total_price} DZD</div>
                                </div>
                            </div>

                            <h3>Products</h3>
                            <div class="products-grid">
                        `;
                        
                        products.forEach(product => {
                            html += `
                                <div class="product-card">
                                    <img src="uploads/products/${product.image_url || 'placeholder.jpg'}" 
                                         alt="${product.name}" 
                                         class="product-image"
                                         onerror="this.src='uploads/products/placeholder.jpg'">
                                    <div class="product-details">
                                        <div class="product-name">${product.name}</div>
                                        <div class="product-price">
                                            ${product.price} DZD × ${product.quantity}<br>
                                            <strong>Total: ${(product.price * product.quantity).toFixed(2)} DZD</strong>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        document.getElementById('orderDetails').innerHTML = html;
                    } else {
                        showToast(data.error || 'Failed to load order details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load order details', 'error');
                });
        }

        function updateStatus(orderId) {
            if (!orderId) return;
            currentOrderId = orderId;
            document.getElementById('statusModal').classList.add('show');
        }

        function validateOrder(orderId) {
            if (!orderId) return;
            
            if (confirm('Are you sure you want to validate this order?')) {
                fetch('orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=validate_order&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Order validated successfully', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(data.error || 'Failed to validate order', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to validate order', 'error');
                });
            }
        }

        function deleteOrder(orderId) {
            if (!orderId) return;
            
            if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
                fetch('orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_order&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Order deleted successfully', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.error || 'Failed to delete order', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to delete order', 'error');
                });
            }
        }

        function generateTicket(orderId) {
            if (!orderId) return;
            window.location.href = `orders.php?action=generate_ticket&id=${orderId}`;
        }

        function saveStatus() {
            if (!currentOrderId) return;
            
            const newStatus = document.getElementById('newStatus').value;
            
            fetch('orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&order_id=${currentOrderId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('statusModal');
                    showToast('Status updated successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to update status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update status', 'error');
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>

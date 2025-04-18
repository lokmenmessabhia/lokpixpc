<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct
include 'dash_header.php';

// Fetch site settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log("Error fetching site settings: " . $e->getMessage());
}

// Check if user is logged in and is an admin
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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

// Handle validation and deletion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['validate_request'])) {
        $request_id = (int)$_POST['request_id'];
        
        try {
            // Update the status
            $stmt = $pdo->prepare("UPDATE recycle_requests SET status = 'validated' WHERE id = ?");
            $stmt->execute([$request_id]);

            // Fetch request details for the email
            $stmt = $pdo->prepare("
                SELECT rr.*, u.email as user_email,
                       c.name as category_name, 
                       s.name as subcategory_name
                FROM recycle_requests rr
                JOIN users u ON rr.user_id = u.id
                JOIN categories c ON rr.category_id = c.id
                JOIN subcategories s ON rr.subcategory_id = s.id
                WHERE rr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send validation email
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
                $mail->addAddress($request['user_email']);
                $mail->isHTML(true);
                $mail->Subject = "Recycling Request Validated";

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
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🎉 Recycling Request Validated!</h1>
                        </div>
                        
                        <div class='content'>
                            <h2>Good news! Your recycling request has been validated.</h2>
                            <p>We're pleased to inform you that your recycling request has been reviewed and approved.</p>
                            
                            <div class='details'>
                                <p><strong>Category:</strong> " . htmlspecialchars($request['category_name']) . "</p>
                                <p><strong>Subcategory:</strong> " . htmlspecialchars($request['subcategory_name']) . "</p>
                                <p><strong>Condition:</strong> " . htmlspecialchars($request['component_condition']) . "</p>
                                <p><strong>Delivery Option:</strong> " . htmlspecialchars($request['pickup_option']) . "</p>
                            </div>

                            <p>Our team will be in touch shortly with next steps for " . 
                            ($request['pickup_option'] === 'pickup' ? "collecting your item" : "dropping off your item") . 
                            ".</p>

                            <p>If you have any questions, please don't hesitate to contact our support team.</p>
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
                error_log("Failed to send validation email. Mailer Error: {$mail->ErrorInfo}");
            }

        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            exit();
        }
        $_SESSION['success_msg'] = "Request successfully validated!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['delete_request'])) {
        $request_id = (int)$_POST['request_id'];

        try {
            // Delete the recycle request
            $stmt = $pdo->prepare("DELETE FROM recycle_requests WHERE id = ?");
            $stmt->execute([$request_id]);

        } catch (PDOException $e) {
            echo "Error: Unable to delete the request. " . $e->getMessage();
            exit();
        }
        $_SESSION['success_msg'] = "Request successfully deleted!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch recycle requests from database
try {
    $stmt = $pdo->query("
        SELECT rr.id, rr.email, rr.phone, rr.category_id, rr.subcategory_id, 
               rr.component_condition, rr.photo, rr.pickup_option, rr.submitted_at,
               rr.status, users.email AS user_email,
               c.name AS category_name, 
               s.name AS subcategory_name
        FROM recycle_requests rr
        JOIN users ON rr.user_id = users.id
        JOIN categories c ON rr.category_id = c.id
        JOIN subcategories s ON rr.subcategory_id = s.id
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate validated and pending requests
    $validated_requests = array_filter($requests, function($request) {
        return $request['status'] === 'validated';
    });
    
    $pending_requests = array_filter($requests, function($request) {
        return $request['status'] !== 'validated';
    });
    
} catch (PDOException $e) {
    echo "Error: Unable to fetch recycle requests. " . $e->getMessage();
    exit();
}
?>

<div class="main-content">
    <div class="page-header">
        <h2 class="page-title">Manage Recycling Requests</h2>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_msg'];
            unset($_SESSION['success_msg']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_msg'];
            unset($_SESSION['error_msg']);
            ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Pending Requests</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Condition</th>
                            <th>Pickup Option</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['component_condition']); ?></td>
                                <td><?php echo htmlspecialchars($request['pickup_option']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($request['submitted_at'])); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewRequest(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                        <button type="submit" name="validate_request" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Validate
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                        <button type="submit" name="delete_request" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this request?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Validated Requests</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Condition</th>
                            <th>Pickup Option</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($validated_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['component_condition']); ?></td>
                                <td><?php echo htmlspecialchars($request['pickup_option']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($request['submitted_at'])); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewRequest(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Request Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7">
                        <div class="details-section">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <i class="fas fa-user-circle"></i> User Information
                                </div>
                                <div class="card-body">
                                    <p><strong>User Email:</strong> <span id="userEmail"></span></p>
                                    <p><strong>Contact Email:</strong> <span id="contactEmail"></span></p>
                                    <p><strong>Phone:</strong> <span id="phone"></span></p>
                                </div>
                            </div>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <i class="fas fa-microchip"></i> Component Details
                                </div>
                                <div class="card-body">
                                    <p><strong>Category:</strong> <span id="category"></span></p>
                                    <p><strong>Subcategory:</strong> <span id="subcategory"></span></p>
                                    <p><strong>Condition:</strong> <span id="condition"></span></p>
                                    <p><strong>Pickup Option:</strong> <span id="pickupOption"></span></p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-info-circle"></i> Status
                                </div>
                                <div class="card-body text-center">
                                    <span id="requestStatus" class="badge"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="image-section">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-image"></i> Component Image
                                </div>
                                <div class="card-body p-0">
                                    <div class="image-container">
                                        <img id="componentImage" src="" alt="Component">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <div id="actionButtons"></div>
            </div>
        </div>
    </div>
</div>

    <style>
/* Modern Table Styles */
.table {
    width: 100%;
    margin-bottom: 1rem;
    background-color: var(--bg-card);
    border-radius: 10px;
    overflow: hidden;
    border-collapse: collapse;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.table thead th {
    background-color: var(--primary);
    color: #fff;
    font-weight: 500;
    padding: 15px;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    border: none;
    vertical-align: middle;
}

.table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: all 0.3s ease;
}

.table tbody tr:last-child {
    border-bottom: none;
}

.table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.table td {
    padding: 15px;
    vertical-align: middle;
            color: var(--text);
    font-size: 0.9rem;
        }

/* Card Styles */
.card {
            background: var(--bg-card);
    border-radius: 15px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    border: 1px solid var(--border);
    overflow: hidden;
}

.card-header {
    background: var(--bg-card);
    padding: 1.25rem;
            border-bottom: 1px solid var(--border);
}

.card-header h3 {
    margin: 0;
    color: var(--text);
    font-size: 1.25rem;
    font-weight: 600;
}

.card-body {
    padding: 1.25rem;
}

/* Button Styles */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.btn-info {
    background-color: #0dcaf0;
    color: #fff;
}

.btn-success {
    background-color: #198754;
    color: #fff;
}

.btn-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-secondary {
    background-color: #6c757d;
    color: #fff;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    opacity: 0.9;
}

/* Modal Styles */
.modal {
    display: none;
            position: fixed;
            top: 0;
            left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 1050;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal.show {
    display: block;
    opacity: 1;
}

.modal-dialog {
    position: relative;
    width: 90%;
    max-width: 800px;
    margin: 2rem auto;
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}

.modal.show .modal-dialog {
    transform: translateY(0);
}

.modal-content {
    position: relative;
    background: #ffffff;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.modal-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
            color: var(--primary);

    display: flex;
    align-items: center;
    gap: 0.75rem;
        }

.modal-title i {
            color: var(--primary);
}

.close {
    font-size: 1.5rem;
    color: #000000;
    opacity: 0.5;
    padding: 0;
    margin: 0;
    width: 30px;
    height: 30px;
            display: flex;
            align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: transparent;
    border: none;
    transition: all 0.2s ease;
}

.close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.1);
}

.modal-body {
    background: #ffffff;
    padding: 1.5rem;
}

.modal-footer {
    background: #f8f9fa;
    padding: 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Card styles within modal */
.modal .card {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.modal .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.modal .card-body {
    background: #ffffff;
}

/* Image container within modal */
.modal .image-container {
    background: #f8f9fa;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

/* Status badge within modal */
.modal .badge {
    display: inline-block;
    padding: 0.5rem 1rem;
}

.modal .bg-success {
    background: #198754 !important;
    color: #ffffff;
}

.modal .bg-warning {
    background: #ffc107 !important;
    color: #000000;
}

/* Info sections within modal */
.modal p {
    margin-bottom: 0.75rem;
    color: #212529;
}

.modal strong {
            font-weight: 600;
    color: #000000;
}

.modal span {
    color: #495057;
}

/* Ensure modal is above other content */
.modal {
    z-index: 1050;
}

.modal-backdrop {
    z-index: 1040;
}

.modal-dialog {
    z-index: 1060;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        width: 95%;
        margin: 1rem auto;
    }

    .modal-body {
        padding: 1rem;
    }
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
            display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-success {
    background-color: rgba(25, 135, 84, 0.1);
    border: 1px solid rgba(25, 135, 84, 0.2);
    color: #198754;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

/* Updated Image Container Styles */
.image-section {
    height: 100%;
}

.image-section .card {
    height: 100%;
}

.image-section .card-body {
    height: calc(100% - 48px); /* Subtract header height */
}

.image-container {
            height: 100%;
    display: flex;
            align-items: center;
    justify-content: center;
    background: #f8f9fa;
    overflow: hidden;
}

#componentImage {
    max-width: 100%;
    max-height: 400px;
            object-fit: contain;
    transition: transform 0.3s ease;
}

#componentImage:hover {
    transform: scale(1.05);
}

/* Details Section Styles */
.details-section {
    height: 100%;
}

.details-section .card {
    margin-bottom: 1rem;
}

.details-section .card:last-child {
    margin-bottom: 0;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .image-section {
        margin-top: 1rem;
    }
    
    #componentImage {
        max-height: 300px;
    }
    
    .image-container {
        border-radius: 8px;
    }
}

/* Card Enhancements */
.card {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header i {
    color: var(--primary);
}

.card-body {
                padding: 1rem;
            }

/* Badge Enhancements */
.badge {
    padding: 0.5rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bg-success {
    background: #198754 !important;
}

.bg-warning {
    background: #ffc107 !important;
}

/* Modal Size Adjustment */
.modal-dialog {
    max-width: 1000px;
}

.modal-body {
    padding: 1.5rem;
}

/* Text Styles */
p {
    margin-bottom: 0.75rem;
}

p:last-child {
    margin-bottom: 0;
}

strong {
    font-weight: 500;
    color: #000;
}

span {
    color: #495057;
        }
    </style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('requestModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Close modal when clicking close button
    document.querySelector('.close').onclick = closeModal;
    document.querySelector('[data-dismiss="modal"]').onclick = closeModal;
});

function viewRequest(request) {
    // Update modal content with animation
    const modal = document.getElementById('requestModal');
    const dialog = modal.querySelector('.modal-dialog');
    
    // Reset transform for animation
    dialog.style.transform = 'translateY(-20px)';
    
    // Update content
    document.getElementById('userEmail').textContent = request.user_email || 'N/A';
    document.getElementById('contactEmail').textContent = request.email || 'N/A';
    document.getElementById('phone').textContent = request.phone || 'N/A';
    document.getElementById('category').textContent = request.category_name || 'N/A';
    document.getElementById('subcategory').textContent = request.subcategory_name || 'N/A';
    document.getElementById('condition').textContent = request.component_condition || 'N/A';
    document.getElementById('pickupOption').textContent = request.pickup_option || 'N/A';
    
    // Handle image with loading state
    const imgElement = document.getElementById('componentImage');
    imgElement.style.opacity = '0.5';
    if (request.photo) {
        imgElement.src = request.photo;
        imgElement.onload = function() {
            imgElement.style.opacity = '1';
        }
        imgElement.onerror = function() {
            this.src = 'assets/images/placeholder.png';
            imgElement.style.opacity = '1';
        };
    } else {
        imgElement.src = 'assets/images/placeholder.png';
        imgElement.style.opacity = '1';
    }
    
    // Update status badge with animation
    const statusBadge = document.getElementById('requestStatus');
    statusBadge.style.transform = 'scale(0.9)';
    statusBadge.textContent = (request.status || 'pending').toUpperCase();
    statusBadge.className = 'badge ' + (request.status === 'validated' ? 'bg-success' : 'bg-warning');
    setTimeout(() => {
        statusBadge.style.transform = 'scale(1)';
    }, 50);

    // Update action buttons
    const actionButtons = document.getElementById('actionButtons');
    actionButtons.innerHTML = '';
    
    if (request.status !== 'validated') {
        actionButtons.innerHTML = `
            <button type="button" class="btn btn-success" onclick="validateRequest(${request.id})">
                <i class="fas fa-check"></i> Validate
            </button>
            <button type="button" class="btn btn-danger" onclick="deleteRequest(${request.id})">
                <i class="fas fa-trash"></i> Delete
            </button>
        `;
    }

    // Show modal with animation
    openModal();
    
    // Animate dialog after a brief delay
    setTimeout(() => {
        dialog.style.transform = 'translateY(0)';
    }, 50);
}

function openModal() {
    const modal = document.getElementById('requestModal');
    document.body.style.overflow = 'hidden';
    modal.classList.add('show');
        }

        function closeModal() {
    const modal = document.getElementById('requestModal');
    const dialog = modal.querySelector('.modal-dialog');
    
    // Animate out
    dialog.style.transform = 'translateY(-20px)';
    modal.style.opacity = '0';
    
    // Remove classes after animation
    setTimeout(() => {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        modal.style.opacity = '';
        dialog.style.transform = '';
    }, 300);
}

function validateRequest(id) {
    if (confirm('Are you sure you want to validate this request?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="request_id" value="${id}"><input type="hidden" name="validate_request">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteRequest(id) {
    if (confirm('Are you sure you want to delete this request?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="request_id" value="${id}"><input type="hidden" name="delete_request">`;
        document.body.appendChild(form);
        form.submit();
    }
}
    </script>

<?php include 'footer.php'; ?>
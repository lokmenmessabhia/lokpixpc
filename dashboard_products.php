<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
$isAdmin = false;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        if ($stmt->fetch()) {
            $isAdmin = true;
        }
    } catch (PDOException $e) {
        error_log("Error verifying admin status: " . $e->getMessage());
    }
}

if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle AJAX edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    header('Content-Type: application/json');
    try {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $category_id = $_POST['category_id'];
        $product_id = $_POST['product_id'];
        
        // Validate inputs
        if (empty($title) || empty($description) || empty($price) || !isset($stock)) {
            throw new Exception("All fields are required");
        }
        
        if (!is_numeric($price) || $price <= 0) {
            throw new Exception("Price must be a positive number");
        }
        
        if (!is_numeric($stock) || $stock < 0) {
            throw new Exception("Stock must be a non-negative number");
        }
        
        // Start transaction
        $pdo->beginTransaction();

        // Update product
        $stmt = $pdo->prepare("UPDATE products SET title = ?, description = ?, price = ?, stock = ?, category_id = ? WHERE id = ?");
        $stmt->execute([$title, $description, $price, $stock, $category_id, $product_id]);
        
        // Handle image uploads
        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            $uploadDir = 'uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $uploadedFiles = [];
            
            // Get current primary image
            $stmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1");
        $stmt->execute([$product_id]);
            $hasPrimaryImage = $stmt->fetch();
            
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = $_FILES['photos']['name'][$key];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed)) {
                        continue; // Skip invalid file types
                    }
                    
                    $newFilename = uniqid() . '.' . $ext;
                    $uploadPath = $uploadDir . $newFilename;
                    
                    if (move_uploaded_file($tmp_name, $uploadPath)) {
                        $uploadedFiles[] = $newFilename;
                        
                        // Get next display order
                        $stmt = $pdo->prepare("SELECT MAX(display_order) FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);
                        $maxOrder = $stmt->fetchColumn() ?: 0;
                        
                        // Insert image record
                        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $product_id,
                            $newFilename,
                            !$hasPrimaryImage && count($uploadedFiles) === 1 ? 1 : 0, // First image is primary if no primary exists
                            $maxOrder + 1
                        ]);
                    }
                }
            }
        }
        
        // Handle image deletions
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $imageId) {
                // Get image details before deletion
                $stmt = $pdo->prepare("SELECT image_url, is_primary FROM product_images WHERE id = ? AND product_id = ?");
                $stmt->execute([$imageId, $product_id]);
                $image = $stmt->fetch();
                
                if ($image) {
                    // Delete physical file
                    $filePath = 'uploads/products/' . $image['image_url'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    // Delete database record
                    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                    
                    // If this was the primary image, make the first remaining image primary
                    if ($image['is_primary']) {
                        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY display_order LIMIT 1");
        $stmt->execute([$product_id]);
                    }
                }
            }
        }
        
        // Handle primary image selection
        if (isset($_POST['primary_image'])) {
            // Reset all images to non-primary
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Set selected image as primary
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
            $stmt->execute([$_POST['primary_image'], $product_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
        $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX get product details request
if (isset($_GET['action']) && $_GET['action'] === 'get_product' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        // Get product details with category name
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   GROUP_CONCAT(pi.id) as image_ids, 
                   GROUP_CONCAT(pi.image_url) as image_urls,
                   GROUP_CONCAT(pi.is_primary) as is_primaries,
                   GROUP_CONCAT(pi.display_order) as display_orders
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_images pi ON p.id = pi.product_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Format image data
            $images = [];
            if ($product['image_ids']) {
                $ids = explode(',', $product['image_ids']);
                $urls = explode(',', $product['image_urls']);
                $primaries = explode(',', $product['is_primaries']);
                $orders = explode(',', $product['display_orders']);
                
                for ($i = 0; $i < count($ids); $i++) {
                    $images[] = [
                        'id' => $ids[$i],
                        'url' => $urls[$i],
                        'is_primary' => (bool)$primaries[$i],
                        'display_order' => $orders[$i]
                    ];
                }
            }
            
            $product['images'] = $images;
            unset($product['image_ids'], $product['image_urls'], $product['is_primaries'], $product['display_orders']);
            
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch products with category names and primary images
try {
    $stmt = $pdo->query("
        SELECT 
            p.*,
            c.name as category_name,
            COUNT(o.id) as total_orders,
            pi.image_url as primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_details od ON p.id = od.product_id
        LEFT JOIN orders o ON od.order_id = o.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
    $products = [];
}

// Fetch categories for filter
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

include 'dash_header.php';
?>

    <style>
    .main-content {
        margin-top: 4.5rem;
        padding: 2rem;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .dashboard-title {
        font-size: 1.5rem;
        font-weight: 600;
            color: var(--text);
    }

    .header-actions {
            display: flex;
        gap: 1rem;
        }

    .filter-container {
            display: flex;
            gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 200px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        font-size: 0.95rem;
        transition: var(--transition);
    }

    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    .filter-select {
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        min-width: 150px;
            color: var(--text);
        background: var(--card-bg);
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .product-card {
        background: var(--card-bg);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        overflow: hidden;
            transition: var(--transition);
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .product-details {
        padding: 1.25rem;
    }

    .product-header {
            display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .product-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        margin-right: 1rem;
    }

    .product-category {
        font-size: 0.85rem;
        color: var(--text-secondary);
        background: var(--background);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        white-space: nowrap;
    }

    .product-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        margin: 1rem 0;
    }

    .stat-item {
        text-align: center;
        padding: 0.75rem;
        background: var(--background);
        border-radius: var(--radius);
    }

    .stat-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .product-actions {
            display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .product-actions .btn {
        flex: 1;
        justify-content: center;
    }

    .product-actions .btn-secondary {
        background: #f8f9fa;
        color: #2c3e50;
    }

    .product-actions .btn-secondary:hover {
        background: #e9ecef;
        color: #1a2530;
    }

    .btn {
        display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius);
        font-size: 0.95rem;
            font-weight: 500;
        cursor: pointer;
            transition: var(--transition);
        border: none;
        }

    .btn-primary {
        background: var(--primary);
            color: white;
        box-shadow: 0 2px 4px rgba(67, 97, 238, 0.2);
    }

    .btn-secondary {
        background: #f8f9fa;
        color: #2c3e50;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .btn:hover {
        transform: translateY(-2px);
    }

    .btn-primary:hover {
        background: #3651d1;
        box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
    }

    .btn-secondary:hover {
        background: #e9ecef;
        border-color: #dee2e6;
    }

    .stock-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .in-stock {
        background: rgba(46, 204, 113, 0.1);
        color: var(--success);
    }

    .low-stock {
        background: rgba(241, 196, 15, 0.1);
        color: var(--warning);
    }

    .out-of-stock {
        background: rgba(231, 76, 60, 0.1);
        color: var(--danger);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .page-item {
            padding: 0.5rem 1rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }

    .page-item:hover, .page-item.active {
        background: var(--primary);
            color: white;
        border-color: var(--primary);
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }

        .dashboard-header {
            flex-direction: column;
            gap: 1rem;
        }

        .filter-container {
            flex-direction: column;
        }

        .search-box {
            width: 100%;
        }

        .products-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Modal styles */
    .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
            z-index: 1000;
        overflow-y: auto;
        padding: 2rem 1rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .modal.show {
        opacity: 1;
    }

    .modal-content {
        background: var(--card-bg);
            width: 90%;
        max-width: 800px;
        margin: 0 auto;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        transform: translateY(-20px);
        transition: transform 0.3s ease;
    }

    .modal.show .modal-content {
        transform: translateY(0);
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        border-radius: 1rem 1rem 0 0;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
        margin: 0;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .close-modal {
        background: rgba(255, 255, 255, 0.2);
            border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 1.25rem;
        color: white;
            cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        backdrop-filter: blur(4px);
        }

        .close-modal:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 2rem 1.5rem;
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }

    .modal-body::-webkit-scrollbar {
        width: 8px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: var(--background);
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
            display: block;
            margin-bottom: 0.5rem;
        color: var(--text);
            font-weight: 500;
        font-size: 0.95rem;
        }

    .form-control {
            width: 100%;
        padding: 0.75rem 1rem;
            border: 1px solid var(--border);
        border-radius: 0.5rem;
        background: var(--background);
        color: var(--text);
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            outline: none;
    }

    .form-control::placeholder {
        color: var(--text-secondary);
    }

    textarea.form-control {
        min-height: 120px;
            resize: vertical;
    }

    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 16px;
        padding-right: 2.5rem;
    }

    .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        background: var(--background);
        border-radius: 0 0 1rem 1rem;
        position: sticky;
        bottom: 0;
        z-index: 1;
    }

    .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 2px 4px rgba(67, 97, 238, 0.2);
    }

    .modal-footer .btn-secondary {
        background: #f8f9fa;
        color: #2c3e50;
        border: 1px solid #e9ecef;
    }

    .modal-footer .btn:hover {
        transform: translateY(-2px);
    }

    .modal-footer .btn-primary:hover {
        background: #3651d1;
        box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
    }

    .modal-footer .btn-secondary:hover {
        background: #e9ecef;
        border-color: #dee2e6;
    }

    .text-muted {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-top: 0.5rem;
    }

    /* Image upload styles */
    .image-upload-area {
        border: 2px dashed var(--border);
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .image-upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(var(--primary-rgb), 0.05);
    }

    .image-upload-icon {
        font-size: 2rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .image-upload-text {
        color: var(--text);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .image-upload-subtext {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }

    /* Responsive styles */
        @media (max-width: 768px) {
        .modal {
                padding: 1rem;
            }

        .modal-content {
                width: 100%;
            }

        .modal-body {
            padding: 1.5rem 1rem;
            }

        .modal-footer {
            flex-direction: column;
            }

        .modal-footer .btn {
            width: 100%;
            }
        }

    @media (max-width: 480px) {
        .modal-header {
                padding: 1rem;
            }

        .modal-title {
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }
    }

    /* Toast notification */
    .toast {
        position: fixed;
        top: 1rem;
        right: 1rem;
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        background: var(--card-bg);
        box-shadow: var(--shadow);
        z-index: 1100;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .toast.success {
        background: var(--success);
        color: white;
    }

    .toast.error {
        background: var(--danger);
        color: white;
    }

    .toast.show {
        opacity: 1;
    }

    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .image-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: var(--radius);
        overflow: hidden;
        border: 2px solid var(--border);
    }
    
    .image-item.primary {
        border-color: var(--primary);
    }
    
    .image-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .image-actions {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        display: flex;
        gap: 0.5rem;
    }
    
    .image-action {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: var(--text);
        transition: var(--transition);
    }
    
    .image-action:hover {
        transform: scale(1.1);
    }
    
    .image-action.delete {
        color: var(--danger);
    }
    
    .image-action.primary {
        color: var(--primary);
    }

    .readonly-field {
        padding: 0.75rem 1rem;
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        color: var(--text-secondary);
        font-size: 0.95rem;
        min-height: 42px;
        display: flex;
        align-items: center;
        }
    </style>

<!-- Edit Product Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Product</h3>
            <button class="close-modal">&times;</button>
        </div>
        <form id="editForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="editProductId">
                <input type="hidden" name="category_id" id="editCategory">
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <div class="readonly-field" id="categoryDisplay"></div>
        </div>
                
                <div class="form-group">
                    <label class="form-label" for="editName">Name</label>
                    <input type="text" class="form-control" id="editName" name="name" required>
        </div>
                
                <div class="form-group">
                    <label class="form-label" for="editDescription">Description</label>
                    <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
    </div>

                <div class="form-group">
                    <label class="form-label" for="editPrice">Price</label>
                    <input type="number" class="form-control" id="editPrice" name="price" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="editStock">Stock</label>
                    <input type="number" class="form-control" id="editStock" name="stock" required>
                            </div>
                
                <div class="form-group">
                    <label class="form-label">Current Images</label>
                    <div id="currentImages" class="image-grid"></div>
                    </div>
                    
                <div class="form-group">
                    <label class="form-label" for="editPhotos">Add New Photos</label>
                    <input type="file" class="form-control" id="editPhotos" name="photos[]" accept="image/*" multiple>
                    <small class="text-muted">You can select multiple images</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
                    </div>
                    
<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="main-content">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Product Management</h1>
        <div class="header-actions">
            <a href="add_product.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add New Product
            </a>
        </div>
                    </div>
                    
    <div class="filter-container">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search products...">
        </div>
        <select class="filter-select" id="categoryFilter">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="stockFilter">
            <option value="">All Stock Status</option>
            <option value="in-stock">In Stock</option>
            <option value="low-stock">Low Stock</option>
            <option value="out-of-stock">Out of Stock</option>
        </select>
                    </div>
                    
    <div class="products-grid">
        <?php foreach ($products as $product): ?>
            <div class="product-card" 
                data-id="<?= $product['id'] ?>"
                data-category="<?= $product['category_id'] ?>"
                data-stock="<?= $product['stock'] ?>">
                <img src="<?= $product['primary_image'] ? 'uploads/products/' . htmlspecialchars($product['primary_image']) : 'placeholder.jpg' ?>" 
                    alt="<?= htmlspecialchars($product['name']) ?>" 
                    class="product-image"
                    onerror="this.src='placeholder.jpg'">
                <div class="product-details">
                    <div class="product-header">
                        <h2 class="product-title"><?= htmlspecialchars($product['name']) ?></h2>
                        <span class="product-category">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </span>
                    </div>
                    <div class="product-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($product['price'], 2) ?> DZD</div>
                            <div class="stat-label">Price</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $product['stock'] ?></div>
                            <div class="stat-label">In Stock</div>
                        </div>
                    </div>
                    <div class="product-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $product['total_orders'] ?></div>
                            <div class="stat-label">Orders</div>
                        </div>
                        <div class="stat-item">
                            <?php
                            $stockStatus = '';
                            $stockClass = '';
                            if ($product['stock'] <= 0) {
                                $stockStatus = 'Out of Stock';
                                $stockClass = 'out-of-stock';
                            } elseif ($product['stock'] <= 10) {
                                $stockStatus = 'Low Stock';
                                $stockClass = 'low-stock';
                            } else {
                                $stockStatus = 'In Stock';
                                $stockClass = 'in-stock';
                            }
                            ?>
                            <span class="stock-status <?= $stockClass ?>">
                                <?= $stockStatus ?>
                            </span>
                        </div>
                    </div>
                    <div class="product-actions">
                        <button class="btn btn-secondary" onclick="editProduct(<?= $product['id'] ?>)">
                            <i class="fas fa-edit"></i>
                            Edit
                        </button>
                        <button class="btn btn-secondary" onclick="deleteProduct(<?= $product['id'] ?>)">
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>
                    </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

    <script>
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const productCards = document.querySelectorAll('.product-card');

    function filterProducts() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
        const selectedStock = stockFilter.value;

        productCards.forEach(card => {
            const title = card.querySelector('.product-title').textContent.toLowerCase();
            const category = card.dataset.category;
            const stock = parseInt(card.dataset.stock);
            
            let showCard = title.includes(searchTerm);
            
            if (selectedCategory && category !== selectedCategory) {
                showCard = false;
            }
            
            if (selectedStock) {
                if (selectedStock === 'out-of-stock' && stock > 0) showCard = false;
                if (selectedStock === 'low-stock' && (stock <= 0 || stock > 10)) showCard = false;
                if (selectedStock === 'in-stock' && stock <= 10) showCard = false;
            }
            
            card.style.display = showCard ? 'block' : 'none';
        });
    }

    searchInput.addEventListener('input', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);
    stockFilter.addEventListener('change', filterProducts);

    function editProduct(id) {
        fetch(`dashboard_products.php?action=get_product&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;
                    document.getElementById('editProductId').value = product.id;
                    document.getElementById('editName').value = product.name;
                    document.getElementById('editDescription').value = product.description;
                    document.getElementById('editPrice').value = product.price;
                    document.getElementById('editStock').value = product.stock;
                    document.getElementById('editCategory').value = product.category_id;
                    
                    // Display category name
                    const categoryDisplay = document.getElementById('categoryDisplay');
                    categoryDisplay.textContent = product.category_name || 'Uncategorized';
                    
                    // Update images display
                    const imagesContainer = document.getElementById('currentImages');
                    imagesContainer.innerHTML = '';
                    
                    if (product.images && product.images.length > 0) {
                        product.images.forEach(image => {
                            const imageDiv = document.createElement('div');
                            imageDiv.className = `image-item${image.is_primary ? ' primary' : ''}`;
                            imageDiv.innerHTML = `
                                <img src="uploads/products/${image.url}" alt="Product image">
                                <div class="image-actions">
                                    <button type="button" class="image-action delete" onclick="markImageForDeletion(${image.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" class="image-action primary" onclick="setPrimaryImage(${image.id})">
                                        <i class="fas fa-star"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="existing_images[]" value="${image.id}">
                            `;
                            imagesContainer.appendChild(imageDiv);
                        });
                    }
                    
                    // Show modal with animation
                    const modal = document.getElementById('editModal');
                    modal.style.display = 'block';
                    void modal.offsetWidth;
                    modal.classList.add('show');
            } else {
                    showToast('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Error fetching product details', 'error');
            });
    }

    function closeModal() {
        const modal = document.getElementById('editModal');
        modal.classList.remove('show');
        setTimeout(() => {
                    modal.style.display = 'none';
        }, 300); // Match the CSS transition duration
    }

    // Close modal functionality
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', closeModal);
    });

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('editModal');
        if (e.target === modal) {
            closeModal();
        }
    });

    // Prevent modal close when clicking modal content
    document.querySelector('.modal-content').addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Handle form submission
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('dashboard_products.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal();
                // Reload the page to show updated data
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showToast('Error updating product', 'error');
        });
    });

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    }

    function deleteProduct(id) {
        if (confirm('Are you sure you want to delete this product?')) {
            fetch('delete_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = document.querySelector(`.product-card[data-id="${id}"]`);
                    if (card) {
                        card.remove();
                        showToast('Product deleted successfully', 'success');
                    }
                } else {
                    showToast('Error deleting product: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting product: ' + error, 'error');
            });
        }
    }

    let imagesToDelete = new Set();
    let primaryImageId = null;

    function markImageForDeletion(imageId) {
        const imageDiv = document.querySelector(`input[value="${imageId}"]`).parentNode;
        imageDiv.style.opacity = '0.5';
        imagesToDelete.add(imageId);
        
        // Add hidden input for deletion
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_images[]';
        input.value = imageId;
        document.getElementById('editForm').appendChild(input);
    }

    function setPrimaryImage(imageId) {
        // Remove primary class from all images
        document.querySelectorAll('.image-item').forEach(item => {
            item.classList.remove('primary');
        });
        
        // Add primary class to selected image
        const imageDiv = document.querySelector(`input[value="${imageId}"]`).parentNode;
        imageDiv.classList.add('primary');
        
        // Update hidden input for primary image
        let input = document.querySelector('input[name="primary_image"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'primary_image';
            document.getElementById('editForm').appendChild(input);
        }
        input.value = imageId;
    }
    </script>

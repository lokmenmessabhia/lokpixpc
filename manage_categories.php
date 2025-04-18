<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['email'] != 'lokmen15.messabhia@gmail.com') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

// Success/error message handling
$message = '';
$message_type = '';

// Handle Category Operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Add New Category
    if (isset($_POST['add_category'])) {
        $category_name = htmlspecialchars(trim($_POST['category_name']));
        
        if (!empty($category_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
                $stmt->execute([':name' => $category_name]);
                $message = "Category '{$category_name}' added successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error adding category: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Category name cannot be empty";
            $message_type = "error";
        }
    }
    
    // Update Category
    if (isset($_POST['update_category'])) {
        $category_id = $_POST['category_id'];
        $category_name = htmlspecialchars(trim($_POST['category_name']));
        
        if (!empty($category_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $category_name, ':id' => $category_id]);
                $message = "Category updated successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error updating category: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Category name cannot be empty";
            $message_type = "error";
        }
    }
    
    // Delete Category
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];
        
        try {
            // First delete associated subcategories
            $stmt = $pdo->prepare("DELETE FROM subcategories WHERE category_id = :category_id");
            $stmt->execute([':category_id' => $category_id]);
            
            // Then delete the category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $category_id]);
            
            $message = "Category and its subcategories deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting category: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Add New Subcategory
    if (isset($_POST['add_subcategory'])) {
        $subcategory_name = htmlspecialchars(trim($_POST['subcategory_name']));
        $category_id = $_POST['parent_category_id'];
        $image_path = '';
        
        if (!empty($subcategory_name) && !empty($category_id)) {
            // Handle image upload
            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/subcategories/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                    }
                }
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO subcategories (name, category_id, image_path) VALUES (:name, :category_id, :image_path)");
                $stmt->execute([
                    ':name' => $subcategory_name,
                    ':category_id' => $category_id,
                    ':image_path' => $image_path
                ]);
                $message = "Subcategory '{$subcategory_name}' added successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error adding subcategory: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Subcategory name and parent category must be specified";
            $message_type = "error";
        }
    }
    
    // Update Subcategory
    if (isset($_POST['update_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $subcategory_name = htmlspecialchars(trim($_POST['subcategory_name']));
        $category_id = $_POST['parent_category_id'];
        $image_path = '';
        
        if (!empty($subcategory_name) && !empty($category_id)) {
            // Handle image upload
            if (isset($_FILES['subcategory_image']) && $_FILES['subcategory_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/subcategories/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['subcategory_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['subcategory_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                        
                        // Delete old image if exists
                        $stmt = $pdo->prepare("SELECT image_path FROM subcategories WHERE id = :id");
                        $stmt->execute([':id' => $subcategory_id]);
                        $old_image = $stmt->fetchColumn();
                        if ($old_image && file_exists($old_image)) {
                            unlink($old_image);
                        }
                    }
                }
            }
            
            try {
                if (!empty($image_path)) {
                    $stmt = $pdo->prepare("UPDATE subcategories SET name = :name, category_id = :category_id, image_path = :image_path WHERE id = :id");
                    $stmt->execute([
                        ':name' => $subcategory_name,
                        ':category_id' => $category_id,
                        ':image_path' => $image_path,
                        ':id' => $subcategory_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("UPDATE subcategories SET name = :name, category_id = :category_id WHERE id = :id");
                    $stmt->execute([
                        ':name' => $subcategory_name,
                        ':category_id' => $category_id,
                        ':id' => $subcategory_id
                    ]);
                }
                $message = "Subcategory updated successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error updating subcategory: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Subcategory name and parent category must be specified";
            $message_type = "error";
        }
    }
    
    // Delete Subcategory
    if (isset($_POST['delete_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = :id");
            $stmt->execute([':id' => $subcategory_id]);
            $message = "Subcategory deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting subcategory: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all subcategories with their parent category names
$stmt = $pdo->query("
    SELECT s.id, s.name, s.category_id, c.name as category_name 
    FROM subcategories s
    JOIN categories c ON s.category_id = c.id
    ORDER BY c.name, s.name
");
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subcategories by category_id for easier display
$grouped_subcategories = [];
foreach ($subcategories as $subcategory) {
    $category_id = $subcategory['category_id'];
    if (!isset($grouped_subcategories[$category_id])) {
        $grouped_subcategories[$category_id] = [];
    }
    $grouped_subcategories[$category_id][] = $subcategory;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Admin Dashboard</title>
    <?php include 'dash_header.php'; ?>
    <style>
        /* Root Variables - Extended from header.php */
        :root {
            /* Theme colors */
            --header-bg: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#1a1d21' : '#FFFFFF' ?>;
            --header-text: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#e9ecef' : '#343a40' ?>;
            --header-text-secondary: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#adb5bd' : '#6c757d' ?>;
            --header-accent: <?= htmlspecialchars($settings['primary_color'] ?? '#dc3545') ?>;
            --header-accent-hover: <?= htmlspecialchars($settings['accent_color'] ?? '#c82333') ?>;
            --header-border: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#2d3238' : '#dee2e6' ?>;
            --header-accent-rgb: <?= implode(', ', sscanf($settings['primary_color'] ?? '#dc3545', '#%02x%02x%02x')) ?>;
            
            /* Dark theme specific */
            --surface-bg: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#22262a' : '#FFFFFF' ?>;
            --background: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#1a1d21' : '#f8fafc' ?>;
            --input-bg: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#2d3238' : '#f8fafc' ?>;
            --card-bg: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '#22262a' : '#FFFFFF' ?>;
            
            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            /* Border radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            
            /* Transitions */
            --transition-normal: 0.3s ease;
            
            /* Shadows */
            --shadow-sm: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '0 2px 4px rgba(0, 0, 0, 0.2)' : '0 1px 2px rgba(0, 0, 0, 0.05)' ?>;
            --shadow-md: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '0 4px 6px rgba(0, 0, 0, 0.3)' : '0 4px 6px rgba(0, 0, 0, 0.1)' ?>;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif') ?>;
            background-color: var(--background);
            color: var(--header-text);
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: var(--spacing-xl) auto;
            padding: 0 var(--spacing-md);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--header-border);
        }

        .category-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--header-border);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .category-title {
            color: var(--header-text);
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: var(--spacing-xl);
        }

        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--header-border);
            margin-bottom: var(--spacing-xl);
            gap: var(--spacing-md);
        }

        .nav-tab {
            padding: var(--spacing-md) var(--spacing-lg);
            color: var(--header-text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition-normal);
        }

        .nav-tab:hover {
            color: var(--header-text);
        }

        .nav-tab.active {
            color: var(--header-accent);
            border-bottom: 2px solid var(--header-accent);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--header-text);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            background: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-md);
            color: var(--header-text);
            transition: var(--transition-normal);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--header-accent);
            box-shadow: 0 0 0 2px rgba(var(--header-accent-rgb), 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition-normal);
        }

        .btn-primary {
            background: var(--header-accent);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--header-accent-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--header-text-secondary);
            color: #ffffff;
        }

        .btn-secondary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .category-list {
            display: grid;
            gap: var(--spacing-md);
        }

        .category-item {
            background: var(--surface-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition-normal);
        }

        .category-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .category-info h3 {
            color: var(--header-text);
            margin: 0 0 var(--spacing-xs);
            font-size: 1.1rem;
        }

        .category-info small {
            color: var(--header-text-secondary);
            font-size: 0.875rem;
        }

        .category-actions {
            display: flex;
            gap: var(--spacing-sm);
        }

        .action-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition-normal);
            color: #ffffff;
        }

        .edit-btn {
            background: var(--header-accent);
        }

        .edit-btn:hover {
            background: var(--header-accent-hover);
            transform: scale(1.05);
        }

        .delete-btn {
            background: #dc3545;
        }

        .delete-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .subcategory-item {
            margin-left: var(--spacing-xl);
            margin-top: var(--spacing-sm);
            background: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-md) var(--spacing-lg);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--header-border);
            box-shadow: var(--shadow-md);
            width: 90%;
            max-width: 500px;
            padding: var(--spacing-xl);
            animation: modalSlideIn var(--transition-normal);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--header-border);
        }

        .modal-title {
            color: var(--header-text);
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: var(--input-bg);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition-normal);
            color: var(--header-text);
        }

        .modal-close:hover {
            background: var(--header-accent);
            color: #ffffff;
            transform: rotate(90deg);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--header-border);
        }

        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            border-color: #198754;
            color: #198754;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: #dc3545;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
            }

            .nav-tabs {
                flex-wrap: wrap;
            }

            .category-item {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
            }

            .category-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .modal-content {
                width: 95%;
                margin: var(--spacing-md);
            }
        }

        .modal-tabs {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            border-bottom: 1px solid var(--header-border);
            padding-bottom: var(--spacing-sm);
        }

        .modal-tab {
            padding: var(--spacing-sm) var(--spacing-lg);
            color: var(--header-text-secondary);
            cursor: pointer;
            border-radius: var(--radius-sm);
            transition: var(--transition-normal);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .modal-tab:hover {
            color: var(--header-text);
            background: var(--input-bg);
        }

        .modal-tab.active {
            color: var(--header-accent);
            background: var(--input-bg);
        }

        .modal-tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .modal-tab-content.active {
            display: block;
        }

        .file-input-wrapper {
            position: relative;
            margin-bottom: var(--spacing-sm);
        }

        .file-input-wrapper input[type="file"] {
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .file-input-preview {
            background: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: var(--spacing-sm);
            position: relative;
        }

        .file-input-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--radius-sm);
        }

        .file-name {
            color: var(--header-text-secondary);
            font-size: 0.875rem;
        }

        .add-new-btn {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="dashboard-header">
        <h1>Category Management</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="category-container">
        <h2 class="category-title">Categories & Subcategories</h2>

        <div class="nav-tabs">
            <div class="nav-tab active" data-tab="manage">
                <i class="fas fa-list"></i> Manage Categories
            </div>
            <button class="btn btn-primary add-new-btn" style="margin-left: auto;">
                <i class="fas fa-plus"></i> Add New
            </button>
        </div>

        <!-- Manage Categories Tab -->
        <div class="tab-content active" id="manage">
            <div class="category-section">
                <h3 class="category-section-title">
                    <i class="fas fa-folder"></i> Categories
                </h3>

                <?php if (count($categories) > 0): ?>
                    <ul class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <li class="category-item">
                                <div>
                                    <h3><?= htmlspecialchars($category['name']) ?></h3>
                                    <?php if (isset($grouped_subcategories[$category['id']])): ?>
                                        <small><?= count($grouped_subcategories[$category['id']]) ?> subcategories</small>
                                    <?php else: ?>
                                        <small>No subcategories</small>
                                    <?php endif; ?>
                                </div>
                                <div class="category-actions">
                                    <button class="action-btn edit-btn edit-category-btn" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete-btn delete-category-btn" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </li>

                            <?php if (isset($grouped_subcategories[$category['id']])): ?>
                                <?php foreach ($grouped_subcategories[$category['id']] as $subcategory): ?>
                                    <li class="subcategory-item">
                                        <div>
                                            <h4><?= htmlspecialchars($subcategory['name']) ?></h4>
                                        </div>
                                        <div class="category-actions">
                                            <button class="action-btn edit-btn edit-subcategory-btn" 
                                                    data-id="<?= $subcategory['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($subcategory['name']) ?>"
                                                    data-parent-id="<?= $subcategory['category_id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete-btn delete-subcategory-btn" 
                                                    data-id="<?= $subcategory['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($subcategory['name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No categories found. Add your first category to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Modal -->
        <div class="modal" id="addNewModal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2 class="modal-title">Add New</h2>
                    <span class="modal-close">&times;</span>
                </div>
                
                <div class="modal-tabs">
                    <div class="modal-tab active" data-tab="category">
                        <i class="fas fa-folder"></i> Category
                    </div>
                    <div class="modal-tab" data-tab="subcategory">
                        <i class="fas fa-sitemap"></i> Subcategory
                    </div>
                </div>

                <!-- Category Form -->
                <div class="modal-tab-content active" id="categoryForm">
                    <form method="POST" class="add-category-form">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" required placeholder="Enter category name">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                            <button type="submit" name="add_category" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Subcategory Form -->
                <div class="modal-tab-content" id="subcategoryForm">
                    <form method="POST" class="add-subcategory-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="parent_category_id">Parent Category</label>
                            <select class="form-control" id="parent_category_id" name="parent_category_id" required>
                                <option value="">Select Parent Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subcategory_name">Subcategory Name</label>
                            <input type="text" class="form-control" id="subcategory_name" name="subcategory_name" required placeholder="Enter subcategory name">
                        </div>
                        <div class="form-group">
                            <label for="subcategory_image">Subcategory Image</label>
                            <div class="file-input-wrapper">
                                <input type="file" class="form-control" id="subcategory_image" name="subcategory_image" accept="image/*">
                                <div class="file-input-preview">
                                    <img id="imagePreview" src="" alt="" style="display: none;">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                            <small class="text-muted">Recommended size: 300x300px. Supported formats: JPG, PNG, GIF</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                            <button type="submit" name="add_subcategory" class="btn btn-primary" <?= count($categories) === 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-plus"></i> Add Subcategory
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal" id="editCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Category</h2>
            <span class="modal-close">&times;</span>
        </div>
        <form method="POST" id="edit-category-form">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="form-group">
                <label for="edit_category_name">Category Name</label>
                <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal" id="deleteCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Delete Category</h2>
            <span class="modal-close">&times;</span>
        </div>
        <p>Are you sure you want to delete the category "<span id="delete_category_name"></span>"?</p>
        <p class="alert alert-error">Warning: This will also delete all subcategories associated with this category!</p>
        <form method="POST" id="delete-category-form">
            <input type="hidden" name="category_id" id="delete_category_id">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" name="delete_category" class="btn btn-danger">Delete Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subcategory Modal -->
<div class="modal" id="editSubcategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Subcategory</h2>
            <span class="modal-close">&times;</span>
        </div>
        <form method="POST" id="edit-subcategory-form" enctype="multipart/form-data">
            <input type="hidden" name="subcategory_id" id="edit_subcategory_id">
            <div class="form-group">
                <label for="edit_parent_category_id">Parent Category</label>
                <select class="form-control" id="edit_parent_category_id" name="parent_category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_subcategory_name">Subcategory Name</label>
                <input type="text" class="form-control" id="edit_subcategory_name" name="subcategory_name" required>
            </div>
            <div class="form-group">
                <label for="edit_subcategory_image">Subcategory Image</label>
                <input type="file" class="form-control" id="edit_subcategory_image" name="subcategory_image" accept="image/*">
                <small class="text-muted">Leave empty to keep existing image</small>
                <div id="current_image_preview" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" name="update_subcategory" class="btn btn-primary">Update Subcategory</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Subcategory Modal -->
<div class="modal" id="deleteSubcategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Delete Subcategory</h2>
            <span class="modal-close">&times;</span>
        </div>
        <p>Are you sure you want to delete the subcategory "<span id="delete_subcategory_name"></span>"?</p>
        <form method="POST" id="delete-subcategory-form">
            <input type="hidden" name="subcategory_id" id="delete_subcategory_id">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" name="delete_subcategory" class="btn btn-danger">Delete Subcategory</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality
        const tabs = document.querySelectorAll('.nav-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabs.forEach(tab => tab.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to selected tab and content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Modal functionality
        const modals = document.querySelectorAll('.modal');
        const closeBtns = document.querySelectorAll('.modal-close, .close-modal');
        
        // Close modal when clicking close button or outside modal
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Edit Category Modal
        const editCategoryBtns = document.querySelectorAll('.edit-category-btn');
        editCategoryBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('edit_category_id').value = id;
                document.getElementById('edit_category_name').value = name;
                
                document.getElementById('editCategoryModal').style.display = 'flex';
            });
        });

        // Delete Category Modal
        const deleteCategoryBtns = document.querySelectorAll('.delete-category-btn');
        deleteCategoryBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_category_id').value = id;
                document.getElementById('delete_category_name').textContent = name;
                
                document.getElementById('deleteCategoryModal').style.display = 'flex';
            });
        });

        // Edit Subcategory Modal with image preview
        const editSubcategoryBtns = document.querySelectorAll('.edit-subcategory-btn');
        editSubcategoryBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const parentId = this.getAttribute('data-parent-id');
                const imagePath = this.getAttribute('data-image-path');
                
                document.getElementById('edit_subcategory_id').value = id;
                document.getElementById('edit_subcategory_name').value = name;
                document.getElementById('edit_parent_category_id').value = parentId;
                
                const imagePreview = document.getElementById('current_image_preview');
                if (imagePath) {
                    imagePreview.innerHTML = `
                        <p>Current Image:</p>
                        <img src="${imagePath}" alt="${name}" style="max-width: 200px; max-height: 200px;">
                    `;
                } else {
                    imagePreview.innerHTML = '<p>No image currently set</p>';
                }
                
                document.getElementById('editSubcategoryModal').style.display = 'flex';
            });
        });

        // Delete Subcategory Modal
        const deleteSubcategoryBtns = document.querySelectorAll('.delete-subcategory-btn');
        deleteSubcategoryBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_subcategory_id').value = id;
                document.getElementById('delete_subcategory_name').textContent = name;
                
                document.getElementById('deleteSubcategoryModal').style.display = 'flex';
            });
        });

        // Show Add New Modal
        const addNewBtn = document.querySelector('.add-new-btn');
        const addNewModal = document.getElementById('addNewModal');
        
        addNewBtn.addEventListener('click', function() {
            addNewModal.style.display = 'flex';
        });

        // Modal Tab Switching
        const modalTabs = document.querySelectorAll('.modal-tab');
        const modalContents = document.querySelectorAll('.modal-tab-content');

        modalTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab') + 'Form';
                
                modalTabs.forEach(t => t.classList.remove('active'));
                modalContents.forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Image Preview
        const imageInput = document.getElementById('subcategory_image');
        const imagePreview = document.getElementById('imagePreview');
        const fileName = document.querySelector('.file-name');

        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                fileName.textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                fileName.textContent = 'No file chosen';
                imagePreview.style.display = 'none';
            }
        });

        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                if (!isValid) {
                    event.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        });

        // Disable subcategory form if no categories exist
        const subcategoryForm = document.querySelector('.add-subcategory-form');
        const subcategoryBtn = subcategoryForm.querySelector('button[type="submit"]');
        if (document.querySelectorAll('#parent_category_id option').length <= 1) {
            subcategoryBtn.disabled = true;
            subcategoryBtn.title = 'Please add a category first';
        }

        // Add error class styling
        const style = document.createElement('style');
        style.textContent = `
            .error {
                border-color: var(--error-color) !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
            }
        `;
        document.head.appendChild(style);
    });
</script>
</body>
</html>
<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

// Verify admin status
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$isAdmin = $stmt->fetchColumn() > 0;

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE marketplace_items SET approved = 1 WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                break;
            case 'reject':
                $stmt = $pdo->prepare("UPDATE marketplace_items SET approved = 0 WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                break;
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM marketplace_items WHERE id = ?");
                $stmt->execute([$_POST['item_id']]);
                break;
        }
    }
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$condition = isset($_GET['condition']) ? $_GET['condition'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the base query
$query = "SELECT m.*, c.name as category_name, s.name as subcategory_name 
          FROM marketplace_items m 
          LEFT JOIN categories c ON m.category_id = c.id 
          LEFT JOIN subcategories s ON m.subcategory_id = s.id 
          WHERE 1=1";

$params = [];

// Add filters
if ($status !== 'all') {
    if ($status === 'pending') {
        $query .= " AND m.approved IS NULL";
    } else if ($status === 'approved') {
        $query .= " AND m.approved = 1";
    } else if ($status === 'rejected') {
        $query .= " AND m.approved = 0";
    }
}

if ($category !== 'all') {
    $query .= " AND m.category_id = ?";
    $params[] = $category;
}

if ($condition !== 'all') {
    $query .= " AND m.condition = ?";
    $params[] = $condition;
}

if (!empty($search)) {
    $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY m.created_at DESC";

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Marketplace - Admin Panel</title>
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
            line-height: 1.6;
            background-color: var(--background);
            color: var(--header-text);
            min-height: 100vh;
        }

        .admin-container {
            max-width: 1200px;
            margin: var(--spacing-xl) auto;
            padding: 0 var(--spacing-md);
        }

        .page-title {
            color: var(--header-text);
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--header-border);
        }

        /* Filters Section */
        .filters {
            background: var(--card-bg);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            border: 1px solid var(--header-border);
            margin-bottom: var(--spacing-xl);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .filter-group label {
            color: var(--header-text);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .filter-form select,
        .filter-form input[type="text"] {
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-sm);
            color: var(--header-text);
            font-size: 0.875rem;
            transition: all var(--transition-normal);
        }

        .filter-form select:focus,
        .filter-form input[type="text"]:focus {
            outline: none;
            border-color: var(--header-accent);
            box-shadow: 0 0 0 2px rgba(var(--header-accent-rgb), 0.2);
        }

        .filter-form button {
            background: var(--header-accent);
            color: #ffffff;
            padding: var(--spacing-sm) var(--spacing-xl);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .filter-form button:hover {
            background: var(--header-accent-hover);
            transform: translateY(-1px);
        }

        /* Marketplace Grid */
        .marketplace-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            padding: var(--spacing-md) 0;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            border: 1px solid var(--header-border);
            overflow: hidden;
            transition: transform var(--transition-normal);
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 1px solid var(--header-border);
        }

        .card-content {
            padding: var(--spacing-md);
        }

        .card-title {
            color: var(--header-text);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: var(--spacing-xs);
        }

        .card-price {
            color: var(--header-accent);
            font-size: 1.125rem;
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }

        .price-amount {
            font-weight: 600;
        }

        .price-currency {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-left: 4px;
        }

        .card-details {
            color: var(--header-text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }

        .card-status {
            display: inline-block;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(var(--header-accent-rgb), 0.1);
            color: var(--header-accent);
        }

        .status-approved {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .card-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--header-border);
        }

        .card-actions button {
            flex: 1;
            padding: var(--spacing-sm);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .btn-approve {
            background: #198754;
            color: #ffffff;
        }

        .btn-reject {
            background: #dc3545;
            color: #ffffff;
        }

        .btn-delete {
            background: var(--header-text-secondary);
            color: #ffffff;
        }

        .card-actions button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-container {
                padding: 0 var(--spacing-md);
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .marketplace-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .marketplace-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
            }

            .card-actions button {
                width: 100%;
            }
        }

        /* Modal Styles */
        .listing-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
        }

        .listing-modal-content {
            position: relative;
            background: var(--card-bg);
            margin: 2% auto;
            width: 95%;
            max-width: 1000px;
            border-radius: var(--radius-md);
            border: 1px solid var(--header-border);
            box-shadow: var(--shadow-md);
            max-height: 90vh;
            overflow: hidden;
            animation: modalSlideIn var(--transition-normal);
            display: grid;
            grid-template-columns: 45% 55%;
        }

        .listing-modal-image-section {
            position: relative;
            background: #000;
            height: 90vh;
            overflow: hidden;
            cursor: zoom-in;
        }

        .listing-modal-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.9;
            transition: transform var(--transition-normal);
        }

        .listing-modal-image:hover {
            transform: scale(1.05);
        }

        .listing-modal-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, 
                rgba(0, 0, 0, 0.2) 0%,
                rgba(0, 0, 0, 0.6) 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: var(--spacing-xl);
            color: #ffffff;
        }

        .listing-modal-price {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .listing-modal-category {
            font-size: 1rem;
            opacity: 0.9;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .listing-modal-details {
            padding: var(--spacing-xl);
            overflow-y: auto;
            height: 90vh;
            background: var(--card-bg);
        }

        .listing-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-xl);
        }

        .listing-modal-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--header-text);
            line-height: 1.3;
            margin-right: var(--spacing-xl);
        }

        .listing-modal-close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--input-bg);
            border: 1px solid var(--header-border);
            color: var(--header-text);
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .listing-modal-close:hover {
            background: var(--header-accent);
            color: #ffffff;
            transform: rotate(90deg);
        }

        .listing-modal-status {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-radius: var(--radius-sm);
            font-weight: 500;
            margin-bottom: var(--spacing-xl);
            gap: var(--spacing-sm);
            font-size: 0.875rem;
        }

        .listing-modal-info {
            display: grid;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            background: var(--input-bg);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            border: 1px solid var(--header-border);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--card-bg);
            border-radius: var(--radius-sm);
            border: 1px solid var(--header-border);
            transition: all var(--transition-normal);
        }

        .info-item:hover {
            transform: translateX(var(--spacing-xs));
        }

        .listing-modal-description {
            background: var(--input-bg);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            border: 1px solid var(--header-border);
            color: var(--header-text);
            margin-bottom: var(--spacing-xl);
            line-height: 1.6;
        }

        .listing-modal-actions {
            position: sticky;
            bottom: 0;
            background: var(--card-bg);
            padding: var(--spacing-lg);
            border-top: 1px solid var(--header-border);
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-end;
        }

        .modal-btn {
            padding: var(--spacing-sm) var(--spacing-xl);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .modal-btn-approve {
            background: #198754;
            color: #ffffff;
        }

        .modal-btn-reject {
            background: #dc3545;
            color: #ffffff;
        }

        .modal-btn-delete {
            background: var(--header-text-secondary);
            color: #ffffff;
        }

        .modal-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
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
            .listing-modal-content {
                grid-template-columns: 1fr;
                max-height: 95vh;
                margin: 2.5vh auto;
            }

            .listing-modal-image-section {
                height: 40vh;
            }

            .listing-modal-details {
                height: auto;
                max-height: 55vh;
            }
        }

        .condition-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        .condition-new { background: #198754; color: white; }
        .condition-like-new { background: #20c997; color: white; }
        .condition-very-good { background: #0dcaf0; color: white; }
        .condition-good { background: #ffc107; color: black; }
        .condition-acceptable { background: #fd7e14; color: white; }
        .condition-for-parts { background: #dc3545; color: white; }
        
        .seller-email {
            color: var(--header-accent);
            text-decoration: none;
            transition: all var(--transition-normal);
        }
        .seller-email:hover {
            color: var(--header-accent-hover);
            text-decoration: underline;
        }
        
        .description-title {
            color: var(--header-text);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: var(--spacing-md);
        }
        
        .description-content {
            white-space: pre-line;
            color: var(--header-text);
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .no-description {
            color: var(--header-text-secondary);
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1 class="page-title">Manage Marketplace</h1>
        
        <div class="filters">
            <form class="filter-form" method="GET">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                </div>

                <div class="filter-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
                
                <div class="filter-group">
                    <label for="condition">Condition</label>
                    <select name="condition" id="condition">
                        <option value="all" <?= $condition === 'all' ? 'selected' : '' ?>>All Conditions</option>
                        <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="used" <?= $condition === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="refurbished" <?= $condition === 'refurbished' ? 'selected' : '' ?>>Refurbished</option>
                </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search items...">
                </div>
                
                <button type="submit">Apply Filters</button>
            </form>
        </div>

        <div class="marketplace-grid">
            <?php foreach ($items as $item): ?>
                <div class="card" 
                     data-created-at="<?= htmlspecialchars($item['created_at']) ?>"
                     data-email="<?= htmlspecialchars($item['email']) ?>"
                     data-description="<?= htmlspecialchars($item['description']) ?>"
                     data-subcategory-name="<?= htmlspecialchars($item['subcategory_name']) ?>">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="card-image">
                    <div class="card-content">
                        <h3 class="card-title"><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="card-price">
                            <span class="price-amount"><?= number_format($item['price'], 0, '.', ',') ?></span>
                            <span class="price-currency">DZD</span>
                        </div>
                        <div class="card-details">
                            <div>Category: <?= htmlspecialchars($item['category_name']) ?></div>
                            <div>Condition: <?= ucfirst(htmlspecialchars($item['condition'])) ?></div>
                        </div>
                        <div class="card-status <?= $item['approved'] === null ? 'status-pending' : ($item['approved'] ? 'status-approved' : 'status-rejected') ?>">
                            <?= $item['approved'] === null ? 'Pending' : ($item['approved'] ? 'Approved' : 'Rejected') ?>
                        </div>
                        <div class="card-actions">
                            <?php if ($item['approved'] === null): ?>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-approve">Approve</button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-reject">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this item?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Modal HTML -->
    <div id="listingModal" class="listing-modal">
        <div class="listing-modal-content">
            <div class="listing-modal-image-section">
                <img class="listing-modal-image" src="" alt="">
                <div class="listing-modal-image-overlay">
                    <div class="listing-modal-price"></div>
                    <div class="listing-modal-category"></div>
                </div>
            </div>
            <div class="listing-modal-details">
                <div class="listing-modal-header">
                    <h2 class="listing-modal-title"></h2>
                    <span class="listing-modal-close">&times;</span>
                </div>
                <div class="listing-modal-status"></div>
                <div class="listing-modal-info"></div>
                <div class="listing-modal-description"></div>
                <div class="listing-modal-actions">
                    <form method="POST" style="display: flex; gap: var(--spacing-sm); width: 100%; justify-content: flex-end;">
                        <input type="hidden" name="item_id" value="">
                        <button type="submit" name="action" value="approve" class="modal-btn modal-btn-approve">
                            Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="modal-btn modal-btn-reject">
                            Reject
                        </button>
                        <button type="submit" name="action" value="delete" class="modal-btn modal-btn-delete" onclick="return confirm('Are you sure you want to delete this item?')">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openListingModal(item) {
            const modal = document.getElementById('listingModal');
            modal.style.display = 'block';
            
            // Update modal content
            modal.querySelector('.listing-modal-title').textContent = item.name;
            modal.querySelector('.listing-modal-image').src = item.image_url;
            modal.querySelector('input[name="item_id"]').value = item.id;
            
            // Update price and category in image overlay
            modal.querySelector('.listing-modal-price').innerHTML = `
                <span class="price-amount">${parseInt(item.price).toLocaleString('fr-DZ')}</span>
                <span class="price-currency">DZD</span>
            `;
            modal.querySelector('.listing-modal-category').textContent = `${item.category_name} › ${item.subcategory_name}`;
            
            // Update status badge
            let statusClass = '';
            let statusText = '';
            if (item.approved === null) {
                statusClass = 'status-pending';
                statusText = 'Pending Review';
            } else if (item.approved === 1) {
                statusClass = 'status-approved';
                statusText = 'Approved';
            } else {
                statusClass = 'status-rejected';
                statusText = 'Rejected';
            }
            const statusElement = modal.querySelector('.listing-modal-status');
            statusElement.className = `listing-modal-status ${statusClass}`;
            statusElement.textContent = statusText;
            
            // Update info section
            const infoHtml = `
                <div class="info-item">
                    <strong>Category:</strong> ${item.category_name} ${item.subcategory_name ? `› ${item.subcategory_name}` : ''}
                </div>
                <div class="info-item">
                    <strong>Condition:</strong> <span class="condition-badge condition-${item.condition.toLowerCase().replace(/\s+/g, '-')}">${item.condition}</span>
                </div>
                <div class="info-item">
                    <strong>Seller Email:</strong> <a href="mailto:${item.email}" class="seller-email">${item.email}</a>
                </div>
                <div class="info-item">
                    <strong>Listed:</strong> ${new Date(item.created_at).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
                <div class="info-item">
                    <strong>Item ID:</strong> #${item.id}
                </div>
            `;
            modal.querySelector('.listing-modal-info').innerHTML = infoHtml;
            
            // Update description with proper formatting
            const descriptionHtml = item.description ? 
                `<h3 class="description-title">Description</h3>
                 <div class="description-content">${item.description}</div>` :
                `<div class="description-content no-description">No description provided</div>`;
            modal.querySelector('.listing-modal-description').innerHTML = descriptionHtml;

            // Add condition badge styles
            const style = document.createElement('style');
            style.textContent = `
                .condition-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: var(--radius-sm);
                    font-size: 0.75rem;
                    font-weight: 500;
                    text-transform: uppercase;
                }
                .condition-new { background: #198754; color: white; }
                .condition-like-new { background: #20c997; color: white; }
                .condition-very-good { background: #0dcaf0; color: white; }
                .condition-good { background: #ffc107; color: black; }
                .condition-acceptable { background: #fd7e14; color: white; }
                .condition-for-parts { background: #dc3545; color: white; }
                
                .seller-email {
                    color: var(--header-accent);
                    text-decoration: none;
                    transition: all var(--transition-normal);
                }
                .seller-email:hover {
                    color: var(--header-accent-hover);
                    text-decoration: underline;
                }
                
                .description-title {
                    color: var(--header-text);
                    font-size: 1rem;
                    font-weight: 500;
                    margin-bottom: var(--spacing-md);
                }
                
                .description-content {
                    white-space: pre-line;
                    color: var(--header-text);
                    font-size: 0.875rem;
                    line-height: 1.6;
                }
                
                .no-description {
                    color: var(--header-text-secondary);
                    font-style: italic;
                }
            `;
            document.head.appendChild(style);

            // Show/hide buttons based on status
            const approveBtn = modal.querySelector('.modal-btn-approve');
            const rejectBtn = modal.querySelector('.modal-btn-reject');
            
            if (item.approved === 1) {
                approveBtn.style.display = 'none';
                rejectBtn.style.display = 'block';
            } else if (item.approved === 0) {
                approveBtn.style.display = 'block';
                rejectBtn.style.display = 'none';
            } else {
                approveBtn.style.display = 'block';
                rejectBtn.style.display = 'block';
            }
        }

        // Close modal when clicking the close button or outside
        document.querySelector('.listing-modal-close').addEventListener('click', () => {
            document.getElementById('listingModal').style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            const modal = document.getElementById('listingModal');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Update card click handler to include more data
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('click', function() {
                const itemData = {
                    id: this.querySelector('input[name="item_id"]').value,
                    name: this.querySelector('.card-title').textContent,
                    price: this.querySelector('.price-amount').textContent.replace(/,/g, ''),
                    category_name: this.querySelector('.card-details').textContent.match(/Category: (.*?)(?=\s|$)/)[1],
                    condition: this.querySelector('.card-details').textContent.match(/Condition: (.*?)(?=\s|$)/)[1],
                    image_url: this.querySelector('.card-image').src,
                    approved: this.querySelector('.card-status').classList.contains('status-approved') ? 1 :
                             this.querySelector('.card-status').classList.contains('status-rejected') ? 0 : null,
                    created_at: this.dataset.createdAt || new Date().toISOString(),
                    email: this.dataset.email || 'Not provided',
                    description: this.dataset.description || '',
                    subcategory_name: this.dataset.subcategoryName || ''
                };
                openListingModal(itemData);
            });
        });
    </script>
</body>
</html>
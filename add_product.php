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
        die("Error: " . $e->getMessage());
    }
}

if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        if (empty($_POST['name']) || empty($_POST['description']) || !isset($_POST['price']) || !isset($_POST['stock']) || empty($_POST['category_id']) || empty($_POST['subcategory_id'])) {
            throw new Exception("All fields are required");
        }

        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $category_id = $_POST['category_id'];
        $subcategory_id = $_POST['subcategory_id'];

        if ($price <= 0) {
            throw new Exception("Price must be greater than 0");
        }

        if ($stock < 0) {
            throw new Exception("Stock cannot be negative");
        }

            $pdo->beginTransaction();

        // Insert product with subcategory
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category_id, subcategory_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $stock, $category_id, $subcategory_id]);
            $product_id = $pdo->lastInsertId();

            // Handle image uploads
        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $uploadDir = 'uploads/products/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $uploadedFiles = [];
            $isPrimarySet = false;

            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = $_FILES['photos']['name'][$key];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowed)) {
                        continue;
                    }

                    $newFilename = uniqid() . '.' . $ext;
                    $uploadPath = $uploadDir . $newFilename;

                    if (move_uploaded_file($tmp_name, $uploadPath)) {
                            // Insert image record
                            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $product_id,
                            $newFilename,
                            !$isPrimarySet, // First image is primary
                            count($uploadedFiles) + 1
                        ]);
                        $uploadedFiles[] = $newFilename;
                        $isPrimarySet = true;
                        }
                    }
                }
            }

            $pdo->commit();
            $success_message = "Product added successfully!";
            
        // Redirect after successful addition
        header("Location: dashboard_products.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
        $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Fetch categories for the dropdown
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();

    // Fetch all subcategories
    $stmt = $pdo->query("SELECT * FROM subcategories ORDER BY name");
    $subcategories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading categories: " . $e->getMessage();
    $categories = [];
    $subcategories = [];
}

include 'dash_header.php';
?>

    <style>
    .main-content {
        margin-top: 4.5rem;
        padding: 2rem;
    }

    .page-header {
            margin-bottom: 2rem;
        }

    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text);
    }

    .card {
        background: var(--card-bg);
        border-radius: var(--radius);
        border: 1px solid var(--border);
            padding: 2rem;
        box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

    .form-label {
            display: block;
            margin-bottom: 0.5rem;
        color: var(--text);
        font-weight: 500;
        }

    .form-control {
            width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--background);
        color: var(--text);
        font-size: 0.95rem;
        transition: var(--transition);
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
    }

    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 16px;
        padding-right: 2.5rem;
    }

    .image-upload-area {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 2rem;
            text-align: center;
            cursor: pointer;
        transition: var(--transition);
        margin-bottom: 1rem;
    }

    .image-upload-area:hover {
        border-color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .upload-icon {
        font-size: 2rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .upload-text {
        color: var(--text);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .upload-subtext {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }

    #imagePreview {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: var(--radius);
        overflow: hidden;
        border: 2px solid var(--border);
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .preview-remove {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
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
        color: #dc3545;
        transition: var(--transition);
    }

    .preview-remove:hover {
        transform: scale(1.1);
        background: #dc3545;
        color: white;
    }

    .btn-container {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
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

    .alert {
        padding: 1rem;
        border-radius: var(--radius);
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }

        .card {
            padding: 1.5rem;
        }

        .btn-container {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }
        }
    </style>

        <div class="main-content">
    <div class="page-header">
            <h1 class="page-title">Add New Product</h1>
                </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                <label class="form-label" for="name">Product Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
                </div>

                <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                </div>

                <div class="form-group">
                <label class="form-label" for="price">Price (DZD)</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                <label class="form-label" for="stock">Stock</label>
                <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                </div>

                <div class="form-group">
                <label class="form-label" for="category">Category</label>
                <select class="form-control form-select" id="category" name="category_id" required onchange="updateSubcategories()">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                <label class="form-label" for="subcategory">Subcategory</label>
                <select class="form-control form-select" id="subcategory" name="subcategory_id" required>
                    <option value="">Select a category first</option>
                    </select>
                </div>

                <div class="form-group">
                <label class="form-label">Product Images</label>
                <div class="image-upload-area" onclick="document.getElementById('photos').click()">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">Click to upload images</div>
                    <div class="upload-subtext">or drag and drop them here</div>
                </div>
                <input type="file" id="photos" name="photos[]" accept="image/*" multiple style="display: none" onchange="handleImagePreview(this)">
                <div id="imagePreview"></div>
                </div>

            <div class="btn-container">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Product
                </button>
                <a href="dashboard_products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Products
                </a>
                </div>
            </form>
        </div>
    </div>

    <script>
function handleImagePreview(input) {
    const previewContainer = document.getElementById('imagePreview');
    previewContainer.innerHTML = '';

    if (input.files) {
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="preview-remove" onclick="removePreview(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(previewItem);
            }
            
            reader.readAsDataURL(file);
        });
    }
}

function removePreview(index) {
    const input = document.getElementById('photos');
    const dt = new DataTransfer();
    const { files } = input;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    input.files = dt.files;
    handleImagePreview(input);
}

// Drag and drop functionality
const uploadArea = document.querySelector('.image-upload-area');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    uploadArea.classList.add('highlight');
}

function unhighlight(e) {
    uploadArea.classList.remove('highlight');
}

uploadArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const input = document.getElementById('photos');
    
    input.files = files;
    handleImagePreview(input);
}

// Store subcategories data
const subcategories = <?php echo json_encode($subcategories); ?>;

function updateSubcategories() {
    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');
    const selectedCategoryId = categorySelect.value;

    // Clear current options
            subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';

    // Filter and add subcategories for selected category
    if (selectedCategoryId) {
        const filteredSubcategories = subcategories.filter(
            sub => sub.category_id === parseInt(selectedCategoryId)
        );

        filteredSubcategories.forEach(sub => {
                    const option = document.createElement('option');
            option.value = sub.id;
            option.textContent = sub.name;
                    subcategorySelect.appendChild(option);
        });
    }
}

// Initialize subcategories on page load
document.addEventListener('DOMContentLoaded', updateSubcategories);
    </script>
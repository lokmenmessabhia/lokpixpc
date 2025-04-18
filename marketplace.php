<?php
session_start();
include 'db_connect.php'; // Include your database connection

// User authentication and data fetching
if (isset($_SESSION['userid'])) {
    $user_id = $_SESSION['userid'];
} else {
    $user_id = null;
}

// Check if the user is an admin
$isAdmin = false; // Default to false
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $isAdmin = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        echo "Error: Unable to verify admin status. " . $e->getMessage();
    }
}

// Fetch categories from the database
try {
    $stmt = $pdo->query("SELECT * FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Unable to fetch categories. " . $e->getMessage();
    $categories = [];
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT email, created_at, profile_picture, phone FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Fetch profile picture
try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userProfile && isset($userProfile['profile_picture']) && !empty($userProfile['profile_picture'])) {
            $profilePicture = $userProfile['profile_picture'];
        } else {
            $profilePicture = 'https://i.top4top.io/p_3273sk4691.jpg';
        }
    } else {
        $profilePicture = 'https://i.top4top.io/p_3273sk4691.jpg';
    }
} catch (PDOException $e) {
    echo "Error: Unable to fetch profile picture. " . $e->getMessage();
    $profilePicture = 'https://i.top4top.io/p_3273sk4691.jpg';
}

// Get cart count
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Check email verification status
$emailVerified = false;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT email_verified FROM users WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $emailVerified = (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        echo "Error: Unable to verify email status. " . $e->getMessage();
    }
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

// Handle product submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_product'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $condition = $_POST['condition'];
    $category_id = $_POST['category'];
    $subcategory_id = $_POST['subcategory'];
    $image = $_FILES['image'];

    $image_url = handleFileUpload($image);

    // Update the SQL query to include condition
    $stmt = $pdo->prepare("INSERT INTO marketplace_items (name, price, description, `condition`, image_url, user_id, email, category_id, subcategory_id) 
                          VALUES (:name, :price, :description, :condition, :image_url, :user_id, :email, :category_id, :subcategory_id)");
    $stmt->execute([
        ':name' => $name,
        ':price' => $price,
        ':description' => $description,
        ':condition' => $condition,
        ':image_url' => $image_url,
        ':user_id' => $_SESSION['user_id'],
        ':email' => $_SESSION['email'],
        ':category_id' => $category_id,
        ':subcategory_id' => $subcategory_id
    ]);

    echo "Product submitted for approval!";
}

// Function to handle file uploads
function handleFileUpload($file) {
    $targetDir = "uploads/marketplace/" . $_SESSION['email'] . "/"; // Directory based on user email
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    // Create user directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true); // Create directory with appropriate permissions
    }

    $targetFile = $targetDir . basename($file["name"]); // Full path for the uploaded file
    $uploadOk = 1;

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        echo "File is not an image.";
        $uploadOk = 0;
    }

    // Check file size (e.g., limit to 5MB)
    if ($file["size"] > 5000000) {
        echo "Sorry, your file is too large.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
    } else {
        // Rename the file to avoid conflicts
        $newFileName = uniqid() . '.' . $imageFileType; // Unique file name
        if (move_uploaded_file($file["tmp_name"], $targetDir . $newFileName)) {
            return $targetDir . $newFileName; // Return the path of the uploaded file
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }
    return null; // Return null if upload failed
}

// Handle product approval (admin functionality)
if (isset($_POST['approve'])) {
    if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        header("Location: login.php");
        exit;
    }

    $product_id = $_POST['product_id'];
    $stmt = $pdo->prepare("UPDATE marketplace_items SET approved = 1 WHERE id = :id");
    $stmt->execute([':id' => $product_id]);
}

// Fetch approved products for display
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, s.name as subcategory_name 
        FROM marketplace_items p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        WHERE p.approved = 1
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $approved_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching products: ' . $e->getMessage());
}

// Fetch unapproved products for admin review
$unapproved_products = [];
if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, s.name as subcategory_name 
            FROM marketplace_items p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN subcategories s ON p.subcategory_id = s.id
            WHERE p.approved = 0
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        $unapproved_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die('Error fetching unapproved products: ' . $e->getMessage());
    }
}

// Add this near the top of your PHP file
if (isset($_GET['action']) && $_GET['action'] === 'get_subcategories') {
    header('Content-Type: application/json');
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    
    try {
        // Direct query similar to recycle.php
        $subcategories = $pdo->query("SELECT id, name FROM subcategories WHERE category_id = {$categoryId}")
            ->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the results
        error_log("Fetching subcategories for category $categoryId. Found: " . count($subcategories));
        
        echo json_encode($subcategories);
    } catch (PDOException $e) {
        error_log("Error fetching subcategories: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// Update the fetchSubcategories function to use the same approach
function fetchSubcategories($categoryId) {
    global $pdo;
    try {
        return $pdo->query("SELECT id, name FROM subcategories WHERE category_id = {$categoryId}")
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Near the top of your PHP file, add this to fetch all subcategories
try {
    $stmt = $pdo->query("
        SELECT s.id as subcategory_id, s.name as subcategory_name, s.category_id 
        FROM subcategories s
    ");
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $subcategories = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace</title>
   <style>
   :root {
    --primary-blue: #0275d8;
    --primary-blue-dark: #025aa5;
    --primary-green: #218838;
    --hover-blue: #e7f1ff;
    --text-dark: #2c3e50;
    --text-light: #ffffff;
    --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    color: var(--text-dark);
    background: #f8f9fa;
    line-height: 1.6;
}


/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999; /* Increased z-index to ensure it's above everything */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.close {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.8rem;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.close:hover {
    color: #555;
    background-color: #f0f0f0;
}

/* Form Styles */
.modal-content form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.modal-content h2 {
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    text-align: center;
}

.modal-content label {
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 0.3rem;
}

.modal-content input,
.modal-content select {
    padding: 0.8rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.modal-content input:focus,
.modal-content select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 2px rgba(2, 117, 216, 0.1);
}

#drop-area {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

#drop-area.highlight {
    border-color: var(--primary-blue);
    background-color: var(--hover-blue);
}

#fileLabel {
    color: #666;
    font-size: 0.9rem;
}
.custom-submit-btn {
    background: var(--primary-green);
    color: white;
    padding: 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.custom-submit-btn:hover {
    background: #1a6e2e;
    transform: translateY(-2px);
}


/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-content {
        margin: 10% auto;
        width: 95%;
        padding: 1.5rem;
    }
}

/* Product Grid Layout */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 2rem;
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Product Card Styles */
.product-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.product-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-bottom: 1px solid #eee;
}

.product-card-content {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.product-card h2 {
    font-size: 1.2rem;
    margin: 0 0 0.5rem 0;
    color: var(--text-dark);
}

.product-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.product-card .price {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary-green);
    margin: 1rem 0;
}

.product-card .btn {
    background: var(--primary-blue);
    color: white;
    padding: 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-weight: 500;
    transition: background-color 0.3s ease;
    margin-top: auto;
}

.product-card .btn:hover {
    background: var(--primary-blue-dark);
}

/* Section Headers */
.section-header {
    text-align: center;
    margin: 2rem 0;
    color: var(--text-dark);
}

/* Add Product Button */
#addProductBtn {
    background: var(--primary-green);
    color: white;
    padding: 1rem 2rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 2rem auto;
    display: block;
}

#addProductBtn:hover {
    background: #1a6e2e;
    transform: translateY(-2px);
}

/* Admin Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 2rem 0;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background: var(--primary-blue);
    color: white;
    font-weight: 600;
}

tr:hover {
    background: var(--hover-blue);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1rem;
    }

    .product-card-content {
        padding: 1rem;
    }

    table {
        display: block;
        overflow-x: auto;
    }
}

/* Add these styles for the categories */
.product-categories {
    display: flex;
    gap: 0.5rem;
    margin: 0.5rem 0;
    flex-wrap: wrap;
}

.category, .subcategory {
    font-size: 0.8rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    background: var(--hover-blue);
    color: var(--primary-blue);
}

.subcategory {
    background: #f0f0f0;
    color: #666;
}

/* Update the product card content spacing */
.product-card-content {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Adjust the price margin */
.product-card .price {
    margin: 0.5rem 0;
}

.modal-content textarea {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
    transition: all 0.3s ease;
}

.modal-content textarea:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 2px rgba(2, 117, 216, 0.1);
}

.modal-content textarea::placeholder {
    color: #999;
}

.modal-content select {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    background-color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-content select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 2px rgba(2, 117, 216, 0.1);
}

.modal-content select option {
    padding: 0.8rem;
    font-size: 0.95rem;
}

.modal-content select option:not(:first-child) {
    border-top: 1px solid #eee;
}

/* Style for the condition tag in product display */
.condition-tag {
    display: inline-flex;
    align-items: center;
    background: #28a745;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-left: 0.5rem;
}

.condition-tag::before {
    content: '•';
    margin-right: 0.5rem;
}

/* Different colors for different conditions */
.condition-New { background-color: #28a745; }
.condition-Like-New { background-color: #20c997; }
.condition-Very-Good { background-color: #17a2b8; }
.condition-Good { background-color: #fd7e14; }
.condition-Acceptable { background-color: #dc3545; }
.condition-For-Parts { background-color: #6c757d; }

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    font-weight: 600;
    color: #444;
    display: block;
    margin-bottom: 5px;
}

.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #28a745;
    border-radius: 4px;
    background-color: white;
}

.form-group select:focus {
    outline: none;
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
}
.listing-text {
    font-size: 18px;
    color: #333;
    font-family: 'Poppins', sans-serif;
    margin-bottom: 15px;
    text-align: center;
    font-weight: 500;
  }

  .ad-listing-btn {
    display: inline-block;
    border-radius: 50px;
    background-color: #28a745;
    color: white;
    padding: 12px 30px;
    text-decoration: none;
    font-size: 16px;
    font-family: 'Poppins', sans-serif;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: background-color 0.3s ease, transform 0.3s ease;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  }

  .ad-listing-btn:hover {
    background-color: #218838;
    transform: translateY(-3px);
  }

  .ad-listing-btn:active {
    transform: translateY(1px);
  }
   </style>
    
</head>
<body>
    <?php include 'header.php'; ?>
    <p class="listing-text">Ready to sell something? Post your listing now!</p>
    <a href="#" id="addProductBtn" class="ad-listing-btn" style="border-radius: 30px;">Add Listing</a>


        <h2 class="section-header">For sell Products</h2>
        <div class="product-grid">
            <?php if (!empty($approved_products)): ?>
                <?php foreach ($approved_products as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-card-content">
                            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                            <div class="product-categories">
                                <span class="category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                <span class="subcategory"><?php echo htmlspecialchars($product['subcategory_name']); ?></span>
                            </div>
                            <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
                            <a href="listing.php?id=<?php echo $product['id']; ?>" class="btn">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No approved products found.</p>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true): ?>
            <h2 class="section-header">Products Pending Approval</h2>
            <table>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Subcategory</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($unapproved_products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['subcategory_name']); ?></td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="approve" class="custom-submit-btn">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </main>

    <!-- Modal Structure -->
    <div id="addListingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddListingModal()">&times;</span>
            <h2>Submit a Product for Sale</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <label for="name">Product Name:</label>
                <input type="text" name="name" required>
                
                <label for="price">Price:</label>
                <input type="number" name="price" step="0.01" required>
                
                <label for="condition">Condition:</label>
                <select name="condition" id="condition" required>
                    <option value="">Select condition</option>
                    <option value="New">New - Brand new, unused item</option>
                    <option value="Like New">Like New - Used once or twice, as good as new</option>
                    <option value="Very Good">Very Good - Lightly used, in great condition</option>
                    <option value="Good">Good - Used but well maintained</option>
                    <option value="Acceptable">Acceptable - Shows wear but works fine</option>
                    <option value="For Parts">For Parts - Not working, for repairs/parts only</option>
                </select>
                
                <label for="description">Description:</label>
                <textarea name="description" id="description" rows="4" required 
                          placeholder="Describe your product's features, condition, and any other relevant details"></textarea>
                
                <div class="form-group">
                    <label for="category">Category:</label>
                    <select name="category" id="category" required onchange="updateSubcategories(this.value)">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subcategory">Subcategory:</label>
                    <select name="subcategory" id="subcategory" required>
                        <option value="">Select Subcategory</option>
                        <?php foreach ($subcategories as $sub): ?>
                            <option value="<?= $sub['subcategory_id'] ?>" 
                                    data-category="<?= $sub['category_id'] ?>">
                                <?= htmlspecialchars($sub['subcategory_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <label for="image">Image:</label>
                <div id="drop-area">
                    <input type="file" name="image" id="fileElem" accept="image/*" required style="display:none;">
                    <label for="fileElem" id="fileLabel">Drag and drop an image here or click to select one</label>
                </div>
                <button type="submit" name="submit_product" class="custom-submit-btn">Submit Product</button>
            </form>
        </div>
    </div>

    <script>
        // Get the modal
        var modal = document.getElementById("addListingModal");

        // Get the button that opens the modal
        var btn = document.getElementById("addProductBtn");

        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close")[0];

        // When the user clicks the button, open the modal 
        btn.onclick = function() {
            modal.style.display = "block";
        }

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Drag and Drop Functionality
        let dropArea = document.getElementById('drop-area');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('highlight');
        }

        function unhighlight() {
            dropArea.classList.remove('highlight');
        }

        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            let dt = e.dataTransfer;
            let files = dt.files;

            handleFiles(files);
        }

        // Handle selected files
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                document.getElementById('fileLabel').textContent = file.name;
            }
        }

        // Allow clicking on the drop area to open file dialog
        dropArea.addEventListener('click', () => {
            document.getElementById('fileElem').click();
        });

        // Update label when file is selected
        document.getElementById('fileElem').addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Function to load subcategories
        function loadSubcategories(categoryId) {
            const subcategorySelect = document.getElementById('subcategory');
            
            // Clear and show loading state
            subcategorySelect.innerHTML = '<option value="">Loading...</option>';
            
            // Log the request
            console.log('Fetching subcategories for category:', categoryId);

            // Make the AJAX request
            fetch(`marketplace.php?action=get_subcategories&category_id=${categoryId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    
                    // Clear the dropdown
                    subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';
                    
                    // Add new options
                    if (data && data.length > 0) {
                        data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.id;
                            option.textContent = subcategory.name;
                            subcategorySelect.appendChild(option);
                        });
                    } else {
                        subcategorySelect.innerHTML = '<option value="">No subcategories available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading subcategories:', error);
                    subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                });
        }

        // Add event listener when the document is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category');
            if (categorySelect) {
                console.log('Category select found');
                categorySelect.addEventListener('change', function() {
                    console.log('Category changed to:', this.value);
                    if (this.value) {
                        loadSubcategories(this.value);
                    } else {
                        const subcategorySelect = document.getElementById('subcategory');
                        subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';
                    }
                });
            } else {
                console.error('Category select not found!');
            }
        });

        // Test function
        function testSubcategories() {
            const categorySelect = document.getElementById('category');
            console.log('Testing subcategories');
            console.log('Category select:', categorySelect);
            console.log('Selected value:', categorySelect ? categorySelect.value : 'not found');
            
            if (categorySelect && categorySelect.value) {
                loadSubcategories(categorySelect.value);
            }
        }

        // Menu toggle functions
        function toggleMenu() {
            const sideMenu = document.getElementById('side-menu');
            sideMenu.classList.toggle('show');
            document.body.style.overflow = sideMenu.classList.contains('show') ? 'hidden' : '';
        }

        function closeMenu() {
            const sideMenu = document.getElementById('side-menu');
            sideMenu.classList.remove('show');
            document.body.style.overflow = '';
        }

        function updateCartCount() {
            fetch('get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartCountElements = document.querySelectorAll('#cart-count');
                    cartCountElements.forEach(element => {
                        element.textContent = data.cartCount;
                    });
                })
                .catch(error => console.error('Error fetching cart count:', error));
        }

        // Update cart count immediately and then every 5 seconds
        updateCartCount();
        setInterval(updateCartCount, 5000);

        function openAddListingModal(event) {
            event.preventDefault();
            const modal = document.getElementById('addListingModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            closeMenu(); // Close the side menu if it's open
        }

        function closeAddListingModal() {
            const modal = document.getElementById('addListingModal');
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addListingModal');
            if (event.target == modal) {
                closeAddListingModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddListingModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const conditionTag = document.querySelector('.condition-tag');
            if (conditionTag) {
                const condition = conditionTag.textContent.trim();
                const className = 'condition-' + condition.replace(/\s+/g, '-');
                conditionTag.classList.add(className);
            }
        });

        function updateSubcategories(categoryId) {
            const subcategorySelect = document.getElementById('subcategory');
            const options = subcategorySelect.getElementsByTagName('option');
            
            // First, hide all options except the first one
            for (let i = 1; i < options.length; i++) {
                const option = options[i];
                if (categoryId === '') {
                    option.style.display = 'none';
                } else if (option.getAttribute('data-category') === categoryId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            
            // Reset selection
            subcategorySelect.value = '';
        }

        // Initialize subcategories on page load
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category');
            if (categorySelect) {
                updateSubcategories(categorySelect.value);
            }
        });
    </script>

   <?php include 'footer.php'; ?>
</body>
</html>

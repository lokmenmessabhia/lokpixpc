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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_gold = isset($_POST['is_gold']) ? 1 : 0;

    // Handle file upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $photo = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $photo;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // File uploaded successfully
            } else {
                $error_message = "Error uploading file.";
            }
        } else {
            $error_message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
        }
    }

    if (empty($title)) {
        $error_message = "Title is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO features (title, description, photo, is_gold) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $photo, $is_gold]);
            $success_message = "Feature added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding feature: " . $e->getMessage();
        }
    }
}

// Add this after handling POST for adding features
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['feature_id'] ?? '';
    $title = $_POST['edit_title'] ?? '';
    $description = $_POST['edit_description'] ?? '';
    $is_gold = isset($_POST['edit_is_gold']) ? 1 : 0;
    $current_photo = $_POST['current_photo'] ?? '';
    
    if (empty($title)) {
        $error_message = "Title is required.";
    } else {
        try {
            $photo = $current_photo;
            if (isset($_FILES['edit_photo']) && $_FILES['edit_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['edit_photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    // Delete old photo if exists
                    if (!empty($current_photo) && file_exists($upload_dir . $current_photo)) {
                        unlink($upload_dir . $current_photo);
                    }
                    
                    $photo = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $photo;
                    
                    if (move_uploaded_file($_FILES['edit_photo']['tmp_name'], $upload_path)) {
                        // File uploaded successfully
                    } else {
                        $error_message = "Error uploading file.";
                    }
                } else {
                    $error_message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE features SET title = ?, description = ?, photo = ?, is_gold = ? WHERE id = ?");
            $stmt->execute([$title, $description, $photo, $is_gold, $id]);
            $success_message = "Feature updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating feature: " . $e->getMessage();
        }
    }
}

// Fetch existing features
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            title,
            description,
            COALESCE(photo, '') as photo,
            is_gold,
            created_at
        FROM features 
        ORDER BY created_at DESC
    ");
    $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching features: " . $e->getMessage();
    $features = [];
}

include 'dash_header.php';
?>

<style>
        .main-content {
        margin-top: 4.5rem;
        padding: 2rem;
            max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
            margin-bottom: 2rem;
    }

    .card {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 2rem;
            box-shadow: var(--shadow);
        }

    .card h2 {
        color: var(--text);
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card h2 i {
        color: var(--primary);
    }

    .form-group {
            margin-bottom: 1.5rem;
        }

    .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        color: var(--text);
            font-weight: 500;
        }

    .form-group input,
    .form-group textarea,
    .form-group select {
            width: 100%;
        padding: 0.75rem;
            border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--background);
        color: var(--text);
        font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }

    .form-group textarea {
        min-height: 120px;
            resize: vertical;
        }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
    }

    .btn {
        background: var(--primary);
            color: white;
        padding: 0.75rem 1.5rem;
            border: none;
        border-radius: var(--radius);
            font-size: 1rem;
        font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        }

    .btn:hover {
        background: var(--accent);
            transform: translateY(-2px);
    }

    .btn i {
        font-size: 1.1rem;
    }

    .alert {
        padding: 1rem;
            border-radius: var(--radius);
        margin-bottom: 1.5rem;
    }

    .alert-success {
        background: rgba(46, 204, 113, 0.1);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .alert-error {
        background: rgba(231, 76, 60, 0.1);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .features-list {
        margin-top: 2rem;
    }

    .feature-item {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border);
        transition: var(--transition);
    }

    .feature-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .feature-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .feature-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
            display: flex;
            align-items: center;
        gap: 0.5rem;
    }

    .feature-title i {
            color: var(--primary);
    }

    .feature-actions {
            display: flex;
        gap: 0.5rem;
    }

    .btn-icon {
        background: var(--background);
        color: var(--text);
        width: 32px;
        height: 32px;
        border-radius: var(--radius);
            display: flex;
            align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
        cursor: pointer;
            transition: var(--transition);
        }
        
    .btn-icon:hover {
        background: var(--primary);
            color: white;
        border-color: var(--primary);
    }

    .feature-description {
        color: var(--text-secondary);
        font-size: 0.95rem;
        line-height: 1.5;
    }

        .feature-image {
        width: 100%;
        max-width: 200px;
        height: 150px;
            object-fit: cover;
        border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .file-input-container {
            position: relative;
        margin-bottom: 1rem;
        }

    .file-input-label {
            display: inline-block;
        padding: 0.75rem 1.5rem;
        background: var(--primary);
            color: white;
        border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }

    .file-input-label:hover {
        background: var(--accent);
        }

        input[type="file"] {
            display: none;
        }

    .gold-badge {
        background: linear-gradient(45deg, #FFD700, #FFA500);
        color: #000;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-top: 0.75rem;
            display: inline-block;
    }

    .preview-image {
        max-width: 200px;
        max-height: 150px;
        margin-top: 1rem;
        border-radius: var(--radius);
        display: none;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }

        .grid {
            grid-template-columns: 1fr;
        }

        .feature-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .feature-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        overflow-y: auto;
    }

    .modal-content {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 2rem;
        width: 90%;
        max-width: 600px;
        margin: 2rem auto;
        position: relative;
        box-shadow: var(--shadow);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
        transition: var(--transition);
    }

    .modal-close:hover {
        color: var(--danger);
        transform: scale(1.1);
    }

    .current-image {
        max-width: 200px;
        max-height: 150px;
        margin: 1rem 0;
        border-radius: var(--radius);
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
                width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1001;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid var(--background);
        border-top: 5px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
        }
    </style>

<div class="main-content">
    <div class="grid">
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Feature</h2>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Feature Title</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="photo">Feature Image</label>
                    <div class="file-input-container">
                        <label class="file-input-label" for="photo">
                            <i class="fas fa-upload"></i> Choose Image
                        </label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                    <img id="imagePreview" class="preview-image" src="#" alt="Preview">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_gold" value="1">
                        Mark as Gold Feature
                    </label>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-plus"></i>
                    Add Feature
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-list"></i> Existing Features</h2>
            <div class="features-list">
                <?php foreach ($features as $feature): ?>
                    <div class="feature-item" data-feature-id="<?= $feature['id'] ?>" 
                        data-title="<?= htmlspecialchars($feature['title']) ?>"
                        data-description="<?= htmlspecialchars($feature['description']) ?>"
                        data-photo="<?= htmlspecialchars($feature['photo']) ?>"
                        data-is-gold="<?= $feature['is_gold'] ?>">
                        <div class="feature-header">
                            <div class="feature-title">
                                <?= htmlspecialchars($feature['title']) ?>
                            </div>
                            <div class="feature-actions">
                                <button class="btn-icon" title="Edit" onclick="editFeature(<?= $feature['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" title="Delete" onclick="deleteFeature(<?= $feature['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($feature['photo'])): ?>
                            <img src="uploads/<?= htmlspecialchars($feature['photo']) ?>" alt="<?= htmlspecialchars($feature['title']) ?>" class="feature-image">
                        <?php endif; ?>
                        <div class="feature-description">
                            <?= htmlspecialchars($feature['description']) ?>
                        </div>
                        <?php if ($feature['is_gold']): ?>
                            <span class="gold-badge">
                                <i class="fas fa-star"></i> Gold Feature
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
        </div>
        </div>
    </div>
</div>

<!-- Add this before closing body tag -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h2><i class="fas fa-edit"></i> Edit Feature</h2>
        
        <form id="editForm" method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="feature_id" id="edit_feature_id">
            <input type="hidden" name="current_photo" id="current_photo">
            
            <div class="form-group">
                <label for="edit_title">Feature Title</label>
                <input type="text" id="edit_title" name="edit_title" required>
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="edit_description"></textarea>
            </div>

            <div class="form-group">
                <label for="edit_photo">Feature Image</label>
                <img id="currentImage" class="current-image" src="" alt="Current Image" style="display: none;">
                <div class="file-input-container">
                    <label class="file-input-label" for="edit_photo">
                        <i class="fas fa-upload"></i> Change Image
                    </label>
                    <input type="file" id="edit_photo" name="edit_photo" accept="image/*">
                </div>
                <img id="editImagePreview" class="preview-image" src="#" alt="Preview">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="edit_is_gold" id="edit_is_gold" value="1">
                    Mark as Gold Feature
                </label>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i>
                Save Changes
            </button>
        </form>
    </div>
                            </div>

<div class="loading-overlay">
    <div class="loading-spinner"></div>
                            </div>

<script>
    // Image preview functionality
    const photoInput = document.getElementById('photo');
    const imagePreview = document.getElementById('imagePreview');

    photoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                imagePreview.style.display = 'block';
                imagePreview.src = e.target.result;
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Modal functions
    function openModal() {
        document.getElementById('editModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('editImagePreview').style.display = 'none';
        document.getElementById('editForm').reset();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            closeModal();
        }
    }

    // Edit feature function
    function editFeature(id) {
        openModal();
        const feature = document.querySelector(`[data-feature-id="${id}"]`);
        
        document.getElementById('edit_feature_id').value = id;
        document.getElementById('edit_title').value = feature.dataset.title;
        document.getElementById('edit_description').value = feature.dataset.description;
        document.getElementById('edit_is_gold').checked = feature.dataset.isGold === '1';
        
        const currentPhoto = feature.dataset.photo;
        document.getElementById('current_photo').value = currentPhoto;
        
        const currentImage = document.getElementById('currentImage');
        if (currentPhoto) {
            currentImage.src = `uploads/${currentPhoto}`;
            currentImage.style.display = 'block';
        } else {
            currentImage.style.display = 'none';
        }
    }

    // Edit photo preview
    const editPhotoInput = document.getElementById('edit_photo');
    const editImagePreview = document.getElementById('editImagePreview');

    editPhotoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                editImagePreview.style.display = 'block';
                editImagePreview.src = e.target.result;
                document.getElementById('currentImage').style.display = 'none';
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Show loading overlay during form submission
    document.getElementById('editForm').addEventListener('submit', function() {
        document.querySelector('.loading-overlay').style.display = 'flex';
    });

    // Delete feature function with AJAX
    function deleteFeature(id) {
        if (confirm('Are you sure you want to delete this feature?')) {
            document.querySelector('.loading-overlay').style.display = 'flex';
            
            fetch('delete_feature.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.loading-overlay').style.display = 'none';
                if (data.success) {
                    const feature = document.querySelector(`[data-feature-id="${id}"]`);
                    feature.remove();
                    alert('Feature deleted successfully!');
                } else {
                    alert('Error deleting feature: ' + data.error);
                }
            })
            .catch(error => {
                document.querySelector('.loading-overlay').style.display = 'none';
                alert('Error deleting feature: ' + error);
            });
        }
    }
</script>
</body>
</html>
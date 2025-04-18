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

// Handle add photo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $photo_tmp_name = $_FILES['photo']['tmp_name'];
        $photo_name = basename($_FILES['photo']['name']);
        $photo_path = 'uploads/' . $photo_name;
        move_uploaded_file($photo_tmp_name, $photo_path);

        $caption = trim($_POST['caption']);

        try {
            $stmt = $pdo->prepare("INSERT INTO slider_photos (photo_url, caption) VALUES (?, ?)");
            $stmt->execute([$photo_path, $caption]);
            header("Location: manage_slider.php");
            exit();
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

// Handle delete photo
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $photo_id = (int)$_GET['id'];

    try {
        // Fetch photo details
        $stmt = $pdo->prepare("SELECT photo_url FROM slider_photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($photo) {
            // Delete the photo file from server
            unlink($photo['photo_url']);

            // Delete the photo record from database
            $stmt = $pdo->prepare("DELETE FROM slider_photos WHERE id = ?");
            $stmt->execute([$photo_id]);

            header("Location: manage_slider.php");
            exit();
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Fetch existing slider photos
try {
    $stmt = $pdo->query("SELECT * FROM slider_photos");
    $slider_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Unable to fetch slider photos. " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Slider Photos</title>
    <link rel="stylesheet" href="dashboard.css">
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
            
            /* Shadows for dark theme */
            --shadow-sm: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '0 2px 4px rgba(0, 0, 0, 0.2)' : '0 1px 2px rgba(0, 0, 0, 0.05)' ?>;
            --shadow-md: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '0 4px 6px rgba(0, 0, 0, 0.3)' : '0 4px 6px rgba(0, 0, 0, 0.1)' ?>;
            --shadow-lg: <?= ($settings['theme_mode'] ?? 'dark') === 'dark' ? '0 10px 15px rgba(0, 0, 0, 0.4)' : '0 10px 15px rgba(0, 0, 0, 0.1)' ?>;
            
            /* Spacing and sizing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-xxl: 2.5rem;
            
            /* Border radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 16px;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
            
            /* Component specific */
            --input-focus: var(--header-accent);
            --button-gradient: linear-gradient(135deg, var(--header-accent), var(--header-accent-hover));
        }

        /* Modern CSS Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif') ?>;
            line-height: 1.6;
            background-color: var(--background);
            color: var(--header-text);
            min-height: 100vh;
        }

        /* Main Content Styles */
        .main-content {
            max-width: 1200px;
            margin: var(--spacing-xl) auto;
            padding: 0 var(--spacing-md);
            animation: fadeIn var(--transition-normal);
        }

        /* Page Title */
        .page-title {
            color: var(--header-text);
            margin-bottom: var(--spacing-xl);
            font-size: 1.5rem;
            font-weight: 500;
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--header-border);
        }

        /* Form Styles */
        form {
            background: var(--card-bg);
            padding: var(--spacing-xl);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
            border: 1px solid var(--header-border);
        }

        .input-group {
            margin-bottom: var(--spacing-lg);
        }

        label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--header-text);
            font-weight: 500;
            font-size: 0.95rem;
        }

        input, textarea {
            width: 100%;
            padding: var(--spacing-md);
            background-color: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-sm);
            color: var(--header-text);
            font-size: 0.95rem;
            transition: all var(--transition-normal);
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--header-accent);
            box-shadow: 0 0 0 2px rgba(var(--header-accent-rgb), 0.2);
        }

        /* File Input Styling */
        .file-input-container {
            margin-bottom: var(--spacing-lg);
        }

        input[type="file"] {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }

        input[type="file"] + label {
            background: var(--header-accent);
            color: #ffffff;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-normal);
        }

        input[type="file"] + label:hover {
            background: var(--header-accent-hover);
            transform: translateY(-1px);
        }

        .file-name {
            display: inline-block;
            margin-left: var(--spacing-md);
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: var(--input-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--radius-sm);
            color: var(--header-text-secondary);
            font-size: 0.875rem;
        }

        /* Button Styles */
        button {
            background: var(--header-accent);
            color: #ffffff;
            padding: var(--spacing-sm) var(--spacing-xl);
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-normal);
        }

        button:hover {
            background: var(--header-accent-hover);
            transform: translateY(-1px);
        }

        /* Slider List Styles */
        .slider-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-md);
            padding: var(--spacing-md) 0;
        }

        .slider-list li {
            background: var(--card-bg);
            border-radius: var(--radius-sm);
            border: 1px solid var(--header-border);
            overflow: hidden;
            transition: all var(--transition-normal);
        }

        .slider-list img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-bottom: 1px solid var(--header-border);
        }

        .slider-list .caption {
            padding: var(--spacing-sm);
            color: var(--header-text);
            font-size: 0.875rem;
            text-align: center;
        }

        .slider-list .actions {
            padding: var(--spacing-sm);
            text-align: center;
            border-top: 1px solid var(--header-border);
        }

        .slider-list .actions a {
            color: var(--header-accent);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
            transition: all var(--transition-normal);
        }

        .slider-list .actions a:hover {
            color: var(--header-accent-hover);
            background: rgba(var(--header-accent-rgb), 0.1);
        }

        /* Footer */
        footer {
            text-align: center;
            padding: var(--spacing-xl);
            color: var(--header-text-secondary);
            background-color: var(--surface-bg);
            border-top: 1px solid var(--header-border);
            margin-top: var(--spacing-xxl);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--input-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--header-text-secondary);
            border-radius: var(--radius-sm);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--header-text);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                max-width: 100%;
                padding: 0 var(--spacing-lg);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: var(--spacing-md);
            }

            .slider-list {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            form {
                padding: var(--spacing-lg);
            }

            button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .slider-list {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }

            .slider-list img {
                height: 100px;
            }

            form {
                padding: var(--spacing-md);
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(var(--spacing-md));
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>


<div class="main-content">
    <h2 class="page-title">Manage Slider</h2>

    <!-- Form to upload new photo -->
    <form action="manage_slider.php" method="post" enctype="multipart/form-data">
        <div class="file-input-container">
            <input type="file" id="photo" name="photo" accept="image/*" required>
            <label for="photo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2z"/>
                </svg>
                Choose Photo
            </label>
            <span class="file-name">No file chosen</span>
        </div>

        <div class="input-group">
            <label for="caption">Caption:</label>
            <input type="text" id="caption" name="caption">
        </div>

        <button type="submit" name="action" value="add">Add Photo</button>
    </form>

    <!-- List of existing slider photos -->
    <h2>Existing Slider Photos</h2>
    <ul class="slider-list">
        <?php foreach ($slider_photos as $photo): ?>
            <li>
                <img src="<?php echo htmlspecialchars($photo['photo_url']); ?>" alt="<?php echo htmlspecialchars($photo['caption']); ?>">
                <div class="caption"><?php echo htmlspecialchars($photo['caption']); ?></div>
                <div class="actions">
                    <a href="manage_slider.php?action=delete&id=<?php echo $photo['id']; ?>">Delete</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>



    <script>
        document.getElementById('photo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file selected';
            const fileNameSpan = document.querySelector('.file-name');
            fileNameSpan.textContent = fileName;
        });
    </script>
</body>
</html>
<?php 

include 'db_connect.php';

// Fetch theme settings
$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
    }
}
//  Fetch notifications
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count new notifications
$new_orders_count = count($notifications);
try {
    $sql = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} catch (PDOException $e) {
    // Handle error
}
// Default theme settings
$default_theme = [
    'primary_color' => '#4361ee',
    'secondary_color' => '#6c757d',
    'accent_color' => '#3f37c9',
    'success_color' => '#2ecc71',
    'danger_color' => '#e74c3c',
    'warning_color' => '#f1c40f',
    'info_color' => '#3498db',
    'text_color' => '#2c3e50',
    'text_secondary' => '#6c757d',
    'background_color' => '#f4f6f9',
    'card_background' => '#ffffff',
    'border_color' => '#e9ecef',
    'theme_mode' => 'light',
    'font_family' => 'Poppins'
];

// Merge database settings with defaults
$theme = array_merge($default_theme, $settings);

// Apply dark mode overrides if enabled
if (($theme['theme_mode'] ?? 'light') === 'dark') {
    $theme = array_merge($theme, [
        'background_color' => '#1a1a1a',
        'card_background' => '#2d2d2d',
        'text_color' => '#f8f9fa',
        'text_secondary' => '#adb5bd',
        'border_color' => '#343a40'
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($theme['site_name'] ?? 'Admin Dashboard') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $theme['font_family']) ?>:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary_color']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary_color']) ?>;
            --accent: <?= htmlspecialchars($theme['accent_color']) ?>;
            --success: <?= htmlspecialchars($theme['success_color']) ?>;
            --danger: <?= htmlspecialchars($theme['danger_color']) ?>;
            --warning: <?= htmlspecialchars($theme['warning_color']) ?>;
            --info: <?= htmlspecialchars($theme['info_color']) ?>;
            --text: <?= htmlspecialchars($theme['text_color']) ?>;
            --text-secondary: <?= htmlspecialchars($theme['text_secondary']) ?>;
            --background: <?= htmlspecialchars($theme['background_color']) ?>;
            --card-bg: <?= htmlspecialchars($theme['card_background']) ?>;
            --border: <?= htmlspecialchars($theme['border_color']) ?>;
            --radius: 8px;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: '<?= htmlspecialchars($theme['font_family']) ?>', sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

         /* Top Navigation */
         .top-nav {
            background: var(--card-bg);
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
            box-shadow: var(--shadow);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }

        .nav-brand h1 {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
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
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .nav-menu a:hover {
            background: var(--background);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .nav-end {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }
 /* Notifications */
 .notification-icon {
            background: var(--background);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-icon svg {
            width: 18px;
            height: 18px;
            color: var(--text);
        }

        .notification-icon:hover {
            background: var(--primary);
            transform: scale(1.05);
        }

        .notification-icon:hover svg {
            color: white;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--danger);
            color: white;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border: 2px solid var(--card-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .notification-popup {
            position: fixed;
            top: 4rem;
            right: 1.5rem;
            width: 350px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid var(--border);
            display: none; /* Start hidden */
        }

        .notification-popup.show {
            opacity: 1;
            transform: translateY(0);
        }

        .popup-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
            border-top-left-radius: var(--radius);
            border-top-right-radius: var(--radius);
        }

        .popup-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text);
            font-weight: 600;
        }

        .mark-read-btn {
            background: transparent;
            color: var(--primary);
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .mark-read-btn:hover {
            background: rgba(var(--primary), 0.1);
        }

        .notification-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: #f0f4ff;
        }

        .notification-dot {
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            margin-top: 0.5rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.35rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.35rem;
        }

        .empty-notifications {
            padding: 2.5rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

    </style>
</head>
<body>
<nav class="top-nav">
        <div class="nav-brand">
            <h1><?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?></h1>
        </div>
        <div class="nav-menu">
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="add_admin.php">Admins</a>
            <?php endif; ?>
            <a href="add_feature.php">Features</a>
            <a href="dashboard_products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="manage_recycle.php">Recycling</a>
            <a href="manage_slider.php">Slider</a>
            <a href="manage_marketplace.php">Marketplace</a>
            <a href="manage_categories.php">categories</a>
            <a href="settings.php">Customizing</a>
            <a href="dashboard.php">back to Dashboard</a>
        </div>
        <div class="nav-end">
            <div class="notification-icon" id="notificationIcon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span class="notification-badge"><?php echo $new_orders_count; ?></span>
            </div>
        </div>
    </nav>
    <div class="notification-popup" id="notificationPopup">
    <div class="popup-header">
        <h3>Notifications</h3>
        <button id="markAsRead" class="mark-read-btn">Mark all as read</button>
    </div>
    <div class="notification-list" id="notificationList">
        <!-- Notifications will be dynamically inserted here -->
        <div class="empty-notifications">
            <p>No new notifications</p>
        </div>
    </div>
</div>
    <script>
    document.getElementById('notificationIcon').addEventListener('click', function(event) {
    const popup = document.getElementById('notificationPopup');
    const currentDisplay = window.getComputedStyle(popup).display;

    // Toggle popup visibility with smooth animation
    if (currentDisplay === 'none' || currentDisplay === '') {
        popup.style.display = 'block';
        // Fetch notifications when opening the popup
        fetchNotifications();
        setTimeout(() => popup.classList.add('show'), 10); // Ensure smooth fade-in
    } else {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300); // Hide after animation
    }
    
    event.stopPropagation(); // Prevent click propagation
});

// Close the popup if clicked outside
window.addEventListener('click', function(event) {
    const popup = document.getElementById('notificationPopup');
    const notificationIcon = document.getElementById('notificationIcon');
    
    // Ensure click outside of the notification popup or icon closes the popup
    if (!popup.contains(event.target) && !notificationIcon.contains(event.target)) {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300);
    }
});

// Handle the "Mark All as Read" functionality
document.getElementById('markAsRead').addEventListener('click', function() {
    console.log('Marking all as read...');
    // Make AJAX request to mark all notifications as read in the database
    fetch('dashboard.php?ajax=notifications&action=mark_read')
        .then(response => {
            console.log('Mark as read response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Mark as read data:', data);
            if(data.success) {
                // After successfully marking as read in database, update the UI
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.textContent = '0';
                    notificationBadge.style.display = 'none';
                }
                
                // Update the notification list to show no unread notifications
                document.getElementById('notificationList').innerHTML = 
                    '<div class="empty-notifications"><p>No new notifications</p></div>';
            }
        })
        .catch(error => console.error('Error marking notifications as read:', error));
});

// Function to fetch notifications
function fetchNotifications() {
    console.log('Fetching notifications...');
    fetch('dashboard.php?ajax=notifications')
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Notification data:', data);
            const notificationList = document.getElementById('notificationList');
            notificationList.innerHTML = ''; // Clear existing notifications

            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(notification => {
                    // Create notification item with proper structure to match CSS
                    const notificationItem = document.createElement('div');
                    notificationItem.className = 'notification-item';
                    
                    // Format date to a more readable format
                    const createdDate = new Date(notification.created_at);
                    const formattedDate = createdDate.toLocaleString();
                    
                    notificationItem.innerHTML = `
                        <div class="notification-dot"></div>
                        <div class="notification-content">
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-time">${formattedDate}</div>
                        </div>
                    `;
                    
                    notificationList.appendChild(notificationItem);
                });
                
                // Also update badge
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.textContent = data.length;
                    notificationBadge.style.display = data.length > 0 ? 'flex' : 'none';
                }
            } else {
                notificationList.innerHTML = '<div class="empty-notifications"><p>No new notifications</p></div>';
                
                // Hide badge when no notifications
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            document.getElementById('notificationList').innerHTML = 
                '<div class="empty-notifications"><p>Error loading notifications</p></div>';
        });
}

// Initialize popup as hidden
document.getElementById('notificationPopup').style.display = 'none';

// Initial fetch of notifications
fetchNotifications();

// Fetch notifications every 30 seconds
setInterval(fetchNotifications, 30000);
</script>

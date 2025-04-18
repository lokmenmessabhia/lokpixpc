<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$current_user_email = $_SESSION['email'];

// Get user info if logged in
$user = null;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

try {
    // Get all conversations for the current user
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            m.*,
            CASE 
                WHEN m.sender_email = ? THEN m.receiver_email
                ELSE m.sender_email
            END as other_user_email,
            u.username as other_username,
            u.profile_picture as other_profile_picture,
            p.name as product_name,
            p.image_url as product_image,
            (
                SELECT message 
                FROM messages 
                WHERE (sender_email = m.sender_email AND receiver_email = m.receiver_email)
                   OR (sender_email = m.receiver_email AND receiver_email = m.sender_email)
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE (sender_email = m.sender_email AND receiver_email = m.receiver_email)
                   OR (sender_email = m.receiver_email AND receiver_email = m.sender_email)
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE receiver_email = ? 
                AND sender_email = other_user_email
                AND read_status = 0
            ) as unread_count
        FROM messages m
        LEFT JOIN users u ON (
            CASE 
                WHEN m.sender_email = ? THEN m.receiver_email
                ELSE m.sender_email
            END = u.email
        )
        LEFT JOIN marketplace_items p ON m.product_name = p.name
        WHERE m.sender_email = ? OR m.receiver_email = ?
        GROUP BY other_user_email, m.product_name
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([$current_user_email, $current_user_email, $current_user_email, $current_user_email, $current_user_email]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LokPixPC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base styles */
        :root {
            --primary-blue: #0084ff;
            --light-gray: #f0f2f5;
            --border-color: #e4e6eb;
            --text-dark: #050505;
            --text-gray: #65676b;
        }

        /* Message container styles */
        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 20px;
            height: calc(100% - 140px);
            overflow-y: auto;
            background-color: #fff;
        }

        /* Message styles */
        .message {
            display: flex;
            align-items: flex-start;
            margin: 2px 0;
            max-width: 85%;
            position: relative;
            width: 100%;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-wrapper {
            display: flex;
            align-items: flex-start;
            max-width: 70%;
        }

        .message.sent .message-wrapper {
            flex-direction: row-reverse;
        }

        .chat-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin: 0 8px;
            object-fit: cover;
        }

        .message.sent .chat-avatar {
            margin-left: 8px;
        }

        .message.received .chat-avatar {
            margin-right: 8px;
        }

        .message-content {
            padding: 8px 12px;
            border-radius: 22px;
            font-size: 14px;
            line-height: 1.4;
            position: relative;
            word-wrap: break-word;
            max-width: 100%;
        }

        .message.sent .message-content {
            background-color: #0095F6;
            color: white;
            margin-right: 8px;
        }

        .message.received .message-content {
            background-color: #F0F0F0;
            color: #262626;
            margin-left: 8px;
        }

        /* Time and status styles */
        .message-time {
            font-size: 11px;
            color: #8e8e8e;
            margin-top: 2px;
            margin-bottom: 4px;
        }

        .message-status {
            font-size: 11px;
            color: #8e8e8e;
            margin-top: 2px;
            margin-bottom: 8px;
            text-align: right;
        }

        .message.received .message-status {
            display: none;
        }

        /* Input area styles */
        .message-input-container {
            display: flex;
            align-items: center;
            padding: 16px;
            background-color: #fff;
            border-top: 1px solid #dbdbdb;
        }

        .message-input {
            flex: 1;
            border: 1px solid #dbdbdb;
            border-radius: 22px;
            padding: 8px 12px;
            margin-right: 8px;
            font-size: 14px;
            outline: none;
            resize: none;
            max-height: 100px;
        }

        .message-input:focus {
            border-color: #8e8e8e;
        }

        .send-button {
            background: none;
            border: none;
            color: #0095F6;
            font-weight: 600;
            font-size: 14px;
            padding: 8px;
            cursor: pointer;
            outline: none;
        }

        .send-button:disabled {
            color: #8e8e8e;
            cursor: default;
        }

        /* Chat header styles */
        .chat-header {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #dbdbdb;
            background-color: #fff;
        }

        .chat-header-content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .chat-user {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 16px;
            color: #262626;
        }

        .chat-product {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #8e8e8e;
            margin-top: 4px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 40px auto;
            width: 400px;
            max-width: 90%;
            height: 600px;
            max-height: 90vh;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .close {
            position: absolute;
            right: 16px;
            top: 16px;
            color: #8e8e8e;
            font-size: 20px;
            cursor: pointer;
        }

        /* Conversation list styles */
        .conversations-container {
            background-color: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #dbdbdb;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover {
            background-color: #fafafa;
        }

        .conversation-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 14px;
            color: #262626;
            margin: 0;
        }

        .conversation-time {
            font-size: 12px;
            color: #8e8e8e;
        }

        .conversation-preview {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .product-thumbnail {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            object-fit: cover;
        }

        .preview-text {
            font-size: 14px;
            color: #8e8e8e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background-color: #0095F6;
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="conversations-container">
        <div class="conversation-list">
            <?php foreach ($conversations as $conv): ?>
                <a href="javascript:void(0)" 
                   onclick="openChatModal('<?php echo htmlspecialchars($conv['other_user_email']); ?>', '<?php echo htmlspecialchars($conv['product_name']); ?>')" 
                   class="conversation-item">
                    <img src="<?php echo $conv['other_profile_picture'] ? 'uploads/profiles/' . htmlspecialchars($conv['other_profile_picture']) : 'https://i.top4top.io/p_3273sk4691.jpg'; ?>" 
                         alt="Profile Picture" class="conversation-avatar">
                    
                    <div class="conversation-details">
                        <div class="conversation-header">
                            <span class="conversation-name" data-email="<?php echo htmlspecialchars($conv['other_user_email']); ?>">
                                <?php echo htmlspecialchars($conv['other_username'] ?: explode('@', $conv['other_user_email'])[0]); ?>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="conversation-time">
                                <?php 
                                    $time = strtotime($conv['last_message_time']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) {
                                        echo "Just now";
                                    } elseif ($diff < 3600) {
                                        echo floor($diff/60) . "m";
                                    } elseif ($diff < 86400) {
                                        echo floor($diff/3600) . "h";
                                    } elseif ($diff < 604800) {
                                        echo floor($diff/86400) . "d";
                                    } else {
                                        echo date('M j', $time);
                                    }
                                ?>
                            </span>
                        </div>

                        <div class="conversation-preview">
                            <img src="<?php echo htmlspecialchars($conv['product_image']); ?>" 
                                 alt="Product" class="product-thumbnail">
                            <span class="preview-text"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)) . (strlen($conv['last_message']) > 50 ? '...' : ''); ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="chat-modal" class="modal">
        <div class="modal-content">
            <div class="chat-header">
                <div id="chat-title"></div>
                <span class="close">&times;</span>
            </div>
            
            <div id="messages-container" class="messages-container">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="message-input-container">
                <input type="text" id="message-input" class="message-input" placeholder="Type a message...">
                <button onclick="sendMessage()" class="send-button">
                    Send
                </button>
            </div>

            <input type="hidden" id="chat-user-email">
            <input type="hidden" id="chat-product-name">
        </div>
    </div>

    <script>
    function markMessagesAsRead(userEmail) {
        fetch('mark_messages_read.php?sender_email=' + encodeURIComponent(userEmail))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Messages marked as read, update counts
                    updateHeaderUnreadCount();
                    updateUnreadCount(userEmail);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function updateHeaderUnreadCount() {
        fetch('get_total_unread.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const headerBadge = document.querySelector('.header-unread-count');
                    if (headerBadge) {
                        if (data.unread_count > 0) {
                            headerBadge.textContent = data.unread_count;
                            headerBadge.style.display = 'inline';
                        } else {
                            headerBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function updateUnreadCount(userEmail) {
        fetch('get_unread_count.php?sender_email=' + encodeURIComponent(userEmail))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const conversationName = document.querySelector(`[data-email="${userEmail}"]`);
                    if (conversationName) {
                        // Remove existing badge if any
                        const existingBadge = conversationName.querySelector('.unread-badge');
                        if (existingBadge) {
                            existingBadge.remove();
                        }
                        
                        // Add new badge if there are unread messages
                        if (data.unread_count > 0) {
                            const badge = document.createElement('span');
                            badge.className = 'unread-badge';
                            badge.textContent = data.unread_count;
                            conversationName.appendChild(badge);
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function loadMessages(userEmail) {
        fetch('get_messages.php?seller_email=' + encodeURIComponent(userEmail))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messagesContainer = document.getElementById('messages-container');
                    const wasScrolledToBottom = isScrolledToBottom(messagesContainer);
                    let html = '';

                    // If modal is visible, mark messages as read
                    if (document.getElementById('chat-modal').style.display === 'block') {
                        markMessagesAsRead(userEmail);
                    }

                    data.messages.forEach(message => {
                        const messageClass = message.is_sent ? 'sent' : 'received';
                        const leftOnReadClass = message.left_on_read ? 'left-on-read' : '';
                        const profilePic = message.is_sent ? 
                            '<?php echo htmlspecialchars($user['profile_picture'] ? 'uploads/profiles/' . $user['profile_picture'] : 'https://i.top4top.io/p_3273sk4691.jpg'); ?>' : 
                            message.sender_picture ? 'uploads/profiles/' + message.sender_picture : 'https://i.top4top.io/p_3273sk4691.jpg';
                        
                        html += `
                            <div class="message ${messageClass} ${leftOnReadClass}">
                                <div class="message-wrapper">
                                    <img src="${profilePic}" alt="Profile" class="chat-avatar">
                                    <div class="message-content">
                                        ${message.message}
                                        <div class="message-time">${message.formatted_time}</div>
                                        ${message.show_seen ? `
                                            <div class="message-status">
                                                ${message.seen_text}
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    messagesContainer.innerHTML = html;

                    if (wasScrolledToBottom) {
                        scrollToBottom(messagesContainer);
                    }

                    // Update unread counts
                    updateUnreadCount(userEmail);
                    updateHeaderUnreadCount();
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function openChatModal(userEmail, productName) {
        document.getElementById('chat-user-email').value = userEmail;
        document.getElementById('chat-product-name').value = productName;
        
        // Get user info from the conversation item
        const conversationItem = document.querySelector(`[data-email="${userEmail}"]`).closest('.conversation-item');
        const userName = conversationItem.querySelector('.conversation-name').textContent.trim().split('\n')[0]; // Remove unread count
        const userPic = conversationItem.querySelector('.conversation-avatar').src;
        const productImg = conversationItem.querySelector('.product-thumbnail').src;
        
        document.getElementById('chat-title').innerHTML = `
            <div class="chat-header-content">
                <div class="chat-user">
                    <img src="${userPic}" alt="User Profile" class="chat-avatar" style="width: 24px; height: 24px; margin-right: 8px;">
                    ${userName}
                </div>
                <div class="chat-product">
                    <img src="${productImg}" alt="Product" style="width: 16px; height: 16px; margin-right: 4px; border-radius: 2px;">
                    ${productName}
                </div>
            </div>
        `;
        
        document.getElementById('chat-modal').style.display = 'block';
        
        // Start checking for new messages
        loadMessages(userEmail);
        
        // Start auto-refresh for new messages
        if (window.messageInterval) {
            clearInterval(window.messageInterval);
        }
        window.messageInterval = setInterval(() => {
            if (document.getElementById('chat-modal').style.display === 'block') {
                loadMessages(userEmail);
            }
        }, 3000);
        
        // Focus input
        document.getElementById('message-input').focus();
    }

    function sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        const sellerEmail = document.getElementById('chat-user-email').value;
        const productName = document.getElementById('chat-product-name').value;

        if (!message) return;

        const formData = new FormData();
        formData.append('message', message);
        formData.append('seller_email', sellerEmail);
        formData.append('product_name', productName);

        fetch('send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                loadMessages(sellerEmail);
                
                // Update the last message in the conversation list
                const conversationItem = document.querySelector(`[data-email="${sellerEmail}"]`).closest('.conversation-item');
                const lastMessageEl = conversationItem.querySelector('.preview-text');
                if (lastMessageEl) {
                    lastMessageEl.textContent = message.length > 50 ? message.substring(0, 50) + '...' : message;
                }
                
                // Update the time
                const timeEl = conversationItem.querySelector('.conversation-time');
                if (timeEl) {
                    timeEl.textContent = 'Just now';
                }
                
                // Move conversation to top
                const conversationList = conversationItem.parentElement;
                conversationList.insertBefore(conversationItem, conversationList.firstChild);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending message');
        });
    }

    // Allow sending message with Enter key
    document.getElementById('message-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function isScrolledToBottom(element) {
        return Math.abs(element.scrollHeight - element.scrollTop - element.clientHeight) < 50;
    }

    function scrollToBottom(element) {
        element.scrollTop = element.scrollHeight;
    }

    // Close button functionality
    document.querySelector('.close').onclick = function() {
        document.getElementById('chat-modal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('chat-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Auto-refresh unread counts every 10 seconds
    setInterval(() => {
        const currentSellerEmail = document.getElementById('chat-user-email').value;
        if (currentSellerEmail) {
            updateUnreadCount(currentSellerEmail);
        }
        updateHeaderUnreadCount();
    }, 10000);
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>

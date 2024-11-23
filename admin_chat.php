<?php 
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Fetch all users who have sent messages to the admin
$stmt = $conn->prepare("
    SELECT u.id, u.username, COUNT(m.id) AS unread_messages
    FROM users u
    JOIN messages m ON u.id = m.user_id
    WHERE m.is_read = 0 AND m.sender = 'user'
    GROUP BY u.id
");
$stmt->execute();
$stmt->bind_result($user_id, $username, $unread_messages);

// Fetch the users with unread messages
$users = [];
while ($stmt->fetch()) {
    $users[] = [
        'user_id' => $user_id,
        'username' => $username,
        'unread_messages' => $unread_messages
    ];
}
$stmt->close();

// Handle displaying messages for a selected user
$selected_user = null;
$messages = [];

if (isset($_GET['user_id'])) {
    $selected_user_id = (int)$_GET['user_id'];

    // Fetch messages between the admin and the selected user
    $stmt = $conn->prepare("
        SELECT m.message, m.file_path, m.sender, m.created_at
        FROM messages m
        WHERE m.user_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $stmt->bind_result($message, $file_path, $sender, $created_at);

    while ($stmt->fetch()) {
        $messages[] = [
            'message' => $message,
            'file_path' => $file_path,
            'sender' => $sender,
            'created_at' => $created_at
        ];
    }
    $stmt->close();

    // Mark all messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender = 'user'");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $stmt->close();

    // Fetch selected user's details
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $stmt->bind_result($selected_username);
    $stmt->fetch();
    $stmt->close();

    $selected_user = ['id' => $selected_user_id, 'username' => $selected_username];
}

// Send a message or file to the user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($selected_user)) {
    $message = $_POST['message'];
    $file_path = null;

    // Check if a file was uploaded
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file_name = basename($_FILES['file']['name']);
        $file_path = "uploads/" . $file_name;
        move_uploaded_file($_FILES['file']['tmp_name'], $file_path);
    }

    // Insert the admin's message and file (if uploaded) into the messages table
    $stmt = $conn->prepare("INSERT INTO messages (user_id, message, file_path, sender, is_read) VALUES (?, ?, ?, 'admin', 0)");
    $stmt->bind_param('iss', $selected_user['id'], $message, $file_path);
    $stmt->execute();
    $stmt->close();

    // Return success response for AJAX
    echo json_encode(['success' => true]);
    exit();
}

// Fetch chat messages between user and admin (via AJAX)
if (isset($_GET['fetch_messages']) && isset($_GET['user_id'])) {
    $selected_user_id = (int)$_GET['user_id'];

    $stmt = $conn->prepare("
        SELECT m.message, m.file_path, m.sender, m.created_at
        FROM messages m
        WHERE m.user_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $stmt->bind_result($message, $file_path, $sender, $created_at);

    $messages = [];
    while ($stmt->fetch()) {
        $messages[] = [
            'message' => $message,
            'file_path' => $file_path,
            'sender' => $sender,
            'created_at' => $created_at
        ];
    }
    $stmt->close();

    echo json_encode($messages);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .chat-box {
            border: 1px solid #ccc;
            padding: 10px;
            height: 400px;
            overflow-y: scroll;
            margin-bottom: 20px;
        }

        .user-msg, .admin-msg {
            margin: 10px 0;
            padding: 10px;
            border-radius: 10px;
        }

        .user-msg {
            background-color: #e0f7fa;
            text-align: left;
        }

        .admin-msg {
            background-color: #fff9c4;
            text-align: right;
        }

        .chat-username {
            font-weight: bold;
        }

        .chat-time {
            font-size: 0.8em;
            color: #999;
        }

        .message-input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Admin Chat</h1>

    <!-- List of users with unread messages -->
    <h2>Users</h2>
    <ul>
        <?php foreach ($users as $user): ?>
            <li>
                <a href="admin_chat.php?user_id=<?php echo $user['user_id']; ?>">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['unread_messages'] > 0): ?>
                        <span class="notification"><?php echo $user['unread_messages']; ?></span> <!-- Notification Icon -->
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Chat box for selected user -->
    <?php if ($selected_user): ?>
        <h2>Chat with <?php echo htmlspecialchars($selected_user['username']); ?></h2>
        <div id="chat-box" class="chat-box">
            <!-- Messages will be loaded here dynamically -->
        </div>

        <!-- Send a new message or file -->
        <form id="messageForm" enctype="multipart/form-data">
            <textarea name="message" id="message" class="message-input" placeholder="Type your message here..." required></textarea><br>
            <label for="file">Attach a file (optional):</label>
            <input type="file" name="file" id="file"><br>
            <button type="submit">Send</button>
        </form>
    <?php else: ?>
        <p>Select a user to view and send messages.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>

    <script>
        // Fetch messages and update the chat box
        function fetchMessages() {
            const userId = <?php echo isset($selected_user['id']) ? $selected_user['id'] : 'null'; ?>;
            if (userId) {
                fetch('admin_chat.php?fetch_messages=true&user_id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        const chatBox = document.getElementById('chat-box');
                        chatBox.innerHTML = '';  // Clear chat box

                        data.forEach(chat => {
                            let chatMessage = document.createElement('div');
                            chatMessage.className = chat.sender === 'admin' ? 'admin-msg' : 'user-msg';

                            let messageContent = `<span class="chat-username">${chat.sender === 'admin' ? 'Admin' : '<?php echo htmlspecialchars($selected_user['username']); ?>'}</span>`;
                            messageContent += `<p>${chat.message}</p>`;
                            if (chat.file_path) {
                                messageContent += `<p><a href="${chat.file_path}" target="_blank">View File</a></p>`;
                            }
                            messageContent += `<small class="chat-time">${chat.created_at}</small>`;
                            chatMessage.innerHTML = messageContent;

                            chatBox.appendChild(chatMessage);
                        });

                        chatBox.scrollTop = chatBox.scrollHeight;  // Scroll to the bottom
                    });
            }
        }

        // Submit a new message via AJAX
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            fetch('admin_chat.php?user_id=<?php echo $selected_user['id']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('message').value = '';  // Clear input
                    document.getElementById('file').value = '';  // Clear file input
                    fetchMessages();  // Refresh chat
                }
            });
        });

        // Fetch messages every 3 seconds to keep chat continuous
        setInterval(fetchMessages, 3000);
        fetchMessages();  // Initial load
    </script>
</body>
</html>

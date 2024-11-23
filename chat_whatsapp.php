<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the username of the current user
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

// Handle new message submission via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $file = isset($_FILES['file']['name']) ? $_FILES['file']['name'] : '';

    // Move uploaded file to uploads directory if there's a file
    if ($file) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($file);
        move_uploaded_file($_FILES['file']['tmp_name'], $target_file);
    }

    // Insert the message into the chat table
    $stmt = $conn->prepare("INSERT INTO messages (user_id, message, file, sender) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param('iss', $user_id, $message, $file);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit();
}

// Fetch chat messages between user and admin
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['fetch_messages'])) {
    $stmt = $conn->prepare("SELECT message, file, sender, created_at FROM messages WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($msg_text, $msg_file, $msg_sender, $msg_time);

    $chat_history = [];
    while ($stmt->fetch()) {
        $chat_history[] = [
            'message' => $msg_text,
            'file' => $msg_file,
            'sender' => $msg_sender,
            'time' => $msg_time
        ];
    }
    $stmt->close();

    echo json_encode($chat_history);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Admin</title>
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
            display: block;
            margin-bottom: 5px;
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

        .file-upload {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Chat with Admin</h1>

    <!-- Chat History -->
    <div id="chat-box" class="chat-box"></div>

    <!-- Send New Message -->
    <form id="messageForm" enctype="multipart/form-data">
        <textarea name="message" id="message" class="message-input" placeholder="Type your message" required></textarea><br>
        <label>Attach a file (optional):</label>
        <input type="file" name="file" id="file" class="file-upload" accept="image/*,application/pdf"><br>
        <button type="submit">Send Message</button>
    </form>

    <p><a href="profile.php">Back to Profile</a></p>

    <script>
        // Fetch messages and update the chat box
        function fetchMessages() {
            fetch('chat_whatsapp.php?fetch_messages=true')
                .then(response => response.json())
                .then(data => {
                    const chatBox = document.getElementById('chat-box');
                    chatBox.innerHTML = '';  // Clear chat box

                    data.forEach(chat => {
                        let chatMessage = document.createElement('div');
                        chatMessage.className = chat.sender === 'user' ? 'user-msg' : 'admin-msg';

                        let messageContent = `<span class="chat-username">${chat.sender === 'user' ? '<?php echo $username; ?>' : 'Admin'}</span>`;
                        messageContent += `<p>${chat.message}</p>`;
                        if (chat.file) {
                            messageContent += `<p><a href="uploads/${chat.file}" target="_blank">View Attachment</a></p>`;
                        }
                        messageContent += `<small class="chat-time">${chat.time}</small>`;
                        chatMessage.innerHTML = messageContent;

                        chatBox.appendChild(chatMessage);
                    });

                    chatBox.scrollTop = chatBox.scrollHeight;  // Scroll to the bottom
                });
        }

        // Submit a new message via AJAX
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            fetch('chat_whatsapp.php', {
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

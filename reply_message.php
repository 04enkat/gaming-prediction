<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Check if user_id is set and valid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $message = $_POST['reply'];
    $user_id = $_POST['user_id'];  // Get the user_id from the form
    $file = null;

    // Handle file upload if provided
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file = $_FILES['file']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($file);
        move_uploaded_file($_FILES['file']['tmp_name'], $target_file);
    }

    // Insert admin reply into the database
    $stmt = $conn->prepare("INSERT INTO messages (user_id, message, sender, file) VALUES (?, ?, 'admin', ?)");
    $stmt->bind_param('iss', $user_id, $message, $file);
    $stmt->execute();
    $stmt->close();

    // Redirect back to chat section
    header('Location: admin_panel.php?section=chat');
} else {
    echo "Error: Missing user_id or message.";
}
?>

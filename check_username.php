<?php
include 'db.php';

if (isset($_GET['username'])) {
    $username = $_GET['username'];

    // Check if the username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo 'taken';  // Username is already taken
    } else {
        echo 'available';  // Username is available
    }
    $stmt->close();
}
?>

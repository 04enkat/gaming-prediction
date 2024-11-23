<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo 'Unauthorized request';
    exit();
}

$user_id = $_SESSION['user_id'];
$amount = $_POST['amount'];
$screenshot = $_FILES['screenshot'];

// Handle file upload
$targetDir = "uploads/";
$targetFile = $targetDir . basename($screenshot['name']);
move_uploaded_file($screenshot['tmp_name'], $targetFile);

// Insert deposit request into transactions table
$query = "INSERT INTO transactions (user_id, amount, status, screenshot, transaction_date) VALUES (?, ?, 'pending', ?, NOW())";
$stmt = $conn->prepare($query);
$stmt->bind_param('ids', $user_id, $amount, $targetFile);
$stmt->execute();

$stmt->close();
$conn->close();

echo 'Success';
?>

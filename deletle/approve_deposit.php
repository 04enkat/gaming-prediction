<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

$deposit_id = $_GET['id'];

// Fetch deposit details
$stmt = $conn->prepare("SELECT user_id, amount FROM deposits WHERE id = ? AND status = 'pending'");
$stmt->bind_param('i', $deposit_id);
$stmt->execute();
$stmt->bind_result($user_id, $amount);
$stmt->fetch();
$stmt->close();

if ($user_id && $amount) {
    // Update user's wallet balance
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->bind_param('di', $amount, $user_id);
    $stmt->execute();
    $stmt->close();

    // Mark the deposit as approved
    $stmt = $conn->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?");
    $stmt->bind_param('i', $deposit_id);
    $stmt->execute();
    $stmt->close();

    header('Location: admin_panel.php?section=deposits');
} else {
    echo "Invalid deposit request.";
}
?>

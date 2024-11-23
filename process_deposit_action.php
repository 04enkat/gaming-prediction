<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
    } else {
        die("Invalid action");
    }
    
    $stmt = $conn->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE transaction_id = ?");
    $stmt->bind_param("si", $new_status, $transaction_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: admin_deposit.php');
    exit();
}
?>

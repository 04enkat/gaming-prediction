<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

$deposit_id = $_GET['id'];

// Mark the deposit as rejected
$stmt = $conn->prepare("UPDATE deposits SET status = 'rejected' WHERE id = ?");
$stmt->bind_param('i', $deposit_id);
$stmt->execute();
$stmt->close();

header('Location: admin_panel.php?section=deposits');
?>

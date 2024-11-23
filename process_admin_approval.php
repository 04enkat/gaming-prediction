<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit();
}

$deposit_id = $_POST['deposit_id'];
$action = $_POST['action'];

if ($action == 'approve') {
    $stmt = $conn->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?");
} elseif ($action == 'reject') {
    $stmt = $conn->prepare("UPDATE deposits SET status = 'rejected' WHERE id = ?");
}

$stmt->bind_param("i", $deposit_id);
$stmt->execute();
$stmt->close();

header("Location: admin_deposit.php");
exit();
?>

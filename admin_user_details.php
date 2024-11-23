<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

$user_id = $_GET['user_id'] ?? null;

if ($user_id) {
    // Fetch the user's details
    $stmt = $conn->prepare("SELECT username, email, wallet_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $email, $wallet_balance);
    $stmt->fetch();
    $stmt->close();
} else {
    header('Location: admin_panel.php');  // Redirect back if no user is selected
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>User Details</h1>

    <?php if ($user_id): ?>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
        <p><strong>Wallet Balance:</strong> ₹<?php echo number_format($wallet_balance, 2); ?></p>
    <?php else: ?>
        <p>No user selected.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>

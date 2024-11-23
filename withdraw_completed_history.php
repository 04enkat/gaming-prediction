<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Fetch all completed withdrawals
$stmt = $conn->prepare("
    SELECT t.id, u.username, t.amount, t.status, t.created_at
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.transaction_type = 'withdraw' AND t.status = 'completed'
    ORDER BY t.created_at DESC
");
$stmt->execute();
$stmt->bind_result($transaction_id, $username, $amount, $status, $created_at);

// Fetch completed withdrawals into an array
$withdrawals = [];
while ($stmt->fetch()) {
    $withdrawals[] = [
        'transaction_id' => $transaction_id,
        'username' => $username,
        'amount' => $amount,
        'status' => $status,
        'created_at' => $created_at
    ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Withdrawals</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Completed Withdrawals</h1>

    <?php if (!empty($withdrawals)): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Username</th>
                    <th>Amount (₹)</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr>
                        <td><?php echo $withdrawal['transaction_id']; ?></td>
                        <td><?php echo htmlspecialchars($withdrawal['username']); ?></td>
                        <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                        <td><?php echo $withdrawal['status']; ?></td>
                        <td><?php echo $withdrawal['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No completed withdrawals found.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>

<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all transaction history for the logged-in user with clear transaction types and formatted dates
$query = "
    SELECT id, amount,
           CASE 
               WHEN transaction_type = 'deposit' THEN 'Deposit'
               WHEN transaction_type = 'withdraw' THEN 'Withdraw'
               WHEN transaction_type = 'vip' THEN 'VIP Subscription'
               ELSE 'Other'
           END AS transaction_type,
           status, 
           DATE_FORMAT(transaction_date, '%Y-%m-%d %H:%i:%s') AS transaction_date
    FROM transactions
    WHERE user_id = ?
    ORDER BY transaction_date DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($transaction_id, $amount, $transaction_type, $status, $transaction_date);

// Fetch results into an array for display
$transactions = [];
while ($stmt->fetch()) {
    $transactions[] = [
        'transaction_id' => $transaction_id,
        'amount' => $amount,
        'transaction_type' => $transaction_type,
        'status' => ucfirst($status),
        'transaction_date' => $transaction_date ?? 'N/A'
    ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Transaction History</h1>

    <?php if (!empty($transactions)): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Amount (₹)</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                        <td><?php echo number_format($transaction['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No transaction history found.</p>
    <?php endif; ?>

    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

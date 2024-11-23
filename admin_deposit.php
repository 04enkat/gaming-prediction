<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

// Fetch all pending deposits
$stmt = $conn->prepare("
    SELECT t.id, u.username, t.amount, 
           (t.amount / (SELECT value FROM settings WHERE setting_key = 'usdt_inr_rate' LIMIT 1)) AS amount_usdt, 
           t.created_at, t.status, t.screenshot
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'pending' AND t.transaction_type = 'deposit'
");
$stmt->execute();
$stmt->bind_result($id, $username, $amount, $amount_usdt, $created_at, $status, $screenshot);
$transactions = [];
while ($stmt->fetch()) {
    $transactions[] = [
        'id' => $id,
        'username' => $username,
        'amount' => $amount,
        'amount_usdt' => $amount_usdt,
        'created_at' => $created_at,
        'status' => $status,
        'screenshot' => $screenshot
    ];
}
$stmt->close();

// Handle approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $action = $_POST['action'];

    // Fetch transaction details
    $stmt = $conn->prepare("SELECT user_id, amount FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount);
    if ($stmt->fetch()) {
        $stmt->close();
        
        if ($action === 'approve') {
            // Update user's wallet balance
            $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->bind_param('di', $amount, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update transaction status to approved
            $stmt = $conn->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $transaction_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'reject') {
            // Update transaction status to rejected
            $stmt = $conn->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param('i', $transaction_id);
            $stmt->execute();
            $stmt->close();
        }

        // Redirect to avoid form re-submission
        header('Location: admin_deposit.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Deposit Approvals</title>
</head>
<body>
    <h1>Pending Deposit Requests</h1>
    <table border="1">
        <tr>
            <th>Username</th>
            <th>Amount (INR)</th>
            <th>Amount (USDT)</th>
            <th>Date</th>
            <th>Status</th>
            <th>Screenshot</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($transactions as $transaction): ?>
            <tr>
                <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                <td>₹<?php echo number_format($transaction['amount'], 2); ?></td>
                <td><?php echo number_format($transaction['amount_usdt'], 6); ?> USDT</td>
                <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                <td>
                    <?php if ($transaction['screenshot']): ?>
                        <a href="<?php echo htmlspecialchars($transaction['screenshot']); ?>" target="_blank">View</a>
                    <?php else: ?>
                        No Screenshot
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                        <button type="submit" name="action" value="approve">Approve</button>
                        <button type="submit" name="action" value="reject">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>

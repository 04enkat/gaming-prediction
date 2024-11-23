<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

// Fetch pending withdrawals with screenshot URL
$stmt = $conn->prepare("
    SELECT t.id, u.username, t.amount, 
           (t.amount / (SELECT value FROM settings WHERE setting_key = 'usdt_inr_rate' LIMIT 1)) AS amount_usdt,
           t.usdt_address, t.created_at, t.status, t.screenshot
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'pending' AND t.transaction_type = 'withdraw'
");
$stmt->execute();
$stmt->bind_result($id, $username, $amount, $amount_usdt, $usdt_address, $created_at, $status, $screenshot);
$withdrawals = [];
while ($stmt->fetch()) {
    $withdrawals[] = [
        'id' => $id,
        'username' => $username,
        'amount' => $amount,
        'amount_usdt' => $amount_usdt,
        'usdt_address' => $usdt_address,
        'created_at' => $created_at,
        'status' => $status,
        'screenshot' => $screenshot
    ];
}
$stmt->close();

// Handle approval or rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $action = $_POST['action'];

    // Fetch transaction details for verification
    $stmt = $conn->prepare("SELECT user_id, amount FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount);
    if ($stmt->fetch()) {
        $stmt->close();

        if ($action === 'approve') {
            // Update user's wallet balance and transaction status
            $stmt = $conn->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $transaction_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'reject') {
            // Only update transaction status to rejected
            $stmt = $conn->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param('i', $transaction_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Redirect to avoid form re-submission
        header('Location: admin_withdraw.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Withdrawal Approvals</title>
</head>
<body>
    <h1>Pending Withdrawal Requests</h1>
    <table border="1" cellpadding="8">
        <tr>
            <th>Username</th>
            <th>Amount (INR)</th>
            <th>Equivalent (USDT)</th>
            <th>USDT Address</th>
            <th>Request Date</th>
			<th>screenshot</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($withdrawals as $withdrawal): ?>
            <tr>
                <td><?php echo htmlspecialchars($withdrawal['username']); ?></td>
                <td>₹<?php echo number_format($withdrawal['amount'], 2); ?></td>
                <td><?php echo number_format($withdrawal['amount_usdt'], 6); ?> USDT</td>
                <td><?php echo htmlspecialchars($withdrawal['usdt_address']); ?></td>
                <td><?php echo htmlspecialchars($withdrawal['created_at']); ?></td>
				  <td>
                    <?php if ($withdrawal['screenshot']): ?>
                        <a href="<?php echo htmlspecialchars($withdrawal['screenshot']); ?>" target="_blank">View</a>
                    <?php else: ?>
                        No Screenshot
                    <?php endif; ?>
                </td>
                <td><?php echo ucfirst(htmlspecialchars($withdrawal['status'])); ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="transaction_id" value="<?php echo $withdrawal['id']; ?>">
                        <button type="submit" name="action" value="approve">Approve</button>
                        <button type="submit" name="action" value="reject">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>

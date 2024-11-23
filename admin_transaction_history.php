<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Pagination settings
$limit = 10;  // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search filter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Modify the query to include search and pagination
$query = "
    SELECT 
        t.id AS transaction_id,
        u.username,
        t.amount,
        CASE 
            WHEN t.transaction_type = 'deposit' THEN 'Deposit'
            WHEN t.transaction_type = 'withdraw' THEN 'Withdraw'
            WHEN t.transaction_type = 'vip' THEN 'VIP Subscription'
            ELSE 'Other'
        END AS transaction_type,
        t.status, 
        COALESCE(DATE_FORMAT(t.transaction_date, '%Y-%m-%d %H:%i:%s'), 'N/A') AS transaction_date
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE u.username LIKE ? OR u.id LIKE ?
    ORDER BY t.transaction_date DESC
    LIMIT ? OFFSET ?
";

// Prepare and execute the query with search and pagination
$search_param = '%' . $search . '%';
$stmt = $conn->prepare($query);
$stmt->bind_param('ssii', $search_param, $search_param, $limit, $offset);
$stmt->execute();
$stmt->bind_result($transaction_id, $username, $amount, $transaction_type, $status, $transaction_date);

// Fetch the results into an array
$transactions = [];
while ($stmt->fetch()) {
    $transactions[] = [
        'transaction_id' => $transaction_id,
        'username' => $username,
        'amount' => $amount,
        'transaction_type' => $transaction_type,
        'status' => ucfirst($status),
        'transaction_date' => $transaction_date
    ];
}
$stmt->close();

// Calculate total number of records for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE u.username LIKE ? OR u.id LIKE ?
";
$stmt = $conn->prepare($count_query);
$stmt->bind_param('ss', $search_param, $search_param);
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_records / $limit);  // Total pages
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Transaction History</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Admin - Transaction History</h1>

    <!-- Search Form -->
    <form method="get" action="admin_transaction_history.php">
        <input type="text" name="search" placeholder="Search by Username or ID" value="<?php echo htmlspecialchars($search); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($transactions)): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Username</th>
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
                        <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                        <td><?php echo number_format($transaction['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" <?php if ($i == $page) echo 'class="active"'; ?>>
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>No transaction history found for the given search.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>

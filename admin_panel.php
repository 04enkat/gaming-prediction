<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Fetch basic stats for the admin panel

// 1. Count pending deposits
$stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'deposit' AND status = 'pending'");
$stmt->execute();
$stmt->bind_result($pending_deposits);
$stmt->fetch();
$stmt->close();

// 2. Count pending withdrawals
$stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'withdraw' AND status = 'pending'");
$stmt->execute();
$stmt->bind_result($pending_withdrawals);
$stmt->fetch();
$stmt->close();

// 3. Count unread messages
$stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE is_read = 0 AND sender = 'user'");
$stmt->execute();
$stmt->bind_result($unread_messages);
$stmt->fetch();
$stmt->close();

// 4. Count current online users (active within the last 5 minutes)
$online_threshold = date("Y-m-d H:i:s", strtotime('-5 minutes'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE last_activity >= ?");
$stmt->bind_param('s', $online_threshold);
$stmt->execute();
$stmt->bind_result($online_users);
$stmt->fetch();
$stmt->close();

// 5. Fetch the current active prediction number
$current_prediction_number = 1000 + floor(time() / 60);  // Example: Prediction number changes every minute

// 6. Fetch total bets and outcomes for the current active prediction
$stmt = $conn->prepare("
    SELECT 
        IFNULL(SUM(CASE WHEN bet_choice = 'UP' THEN bet_amount ELSE 0 END), 0) AS up_units,
        IFNULL(SUM(CASE WHEN bet_choice = 'DRAW' THEN bet_amount ELSE 0 END), 0) AS draw_units,
        IFNULL(SUM(CASE WHEN bet_choice = 'DOWN' THEN bet_amount ELSE 0 END), 0) AS down_units,
        COUNT(CASE WHEN outcome = 'win' THEN 1 END) AS win_count,
        COUNT(CASE WHEN outcome = 'loss' THEN 1 END) AS loss_count
    FROM bets
    WHERE prediction_number = ?
");
$stmt->bind_param('i', $current_prediction_number);
$stmt->execute();
$stmt->bind_result($up_units, $draw_units, $down_units, $win_count, $loss_count);
$stmt->fetch();
$stmt->close();

// 7. Fetch the last 5 predictions and outcomes along with total wins and losses
$stmt = $conn->prepare("
    SELECT p.prediction_number, p.winning_result,
           IFNULL(SUM(CASE WHEN b.bet_choice = 'UP' THEN b.bet_amount ELSE 0 END), 0) AS up_units,
           IFNULL(SUM(CASE WHEN b.bet_choice = 'DRAW' THEN b.bet_amount ELSE 0 END), 0) AS draw_units,
           IFNULL(SUM(CASE WHEN b.bet_choice = 'DOWN' THEN b.bet_amount ELSE 0 END), 0) AS down_units,
           COUNT(CASE WHEN b.bet_choice = p.winning_result THEN 1 END) AS total_win,
           COUNT(CASE WHEN b.bet_choice != p.winning_result THEN 1 END) AS total_loss
    FROM predictions p
    LEFT JOIN bets b ON p.prediction_number = b.prediction_number
    GROUP BY p.prediction_number, p.winning_result
    ORDER BY p.prediction_number DESC
    LIMIT 5
");
$stmt->execute();
$stmt->bind_result($prediction_number, $winning_result, $up_units_history, $draw_units_history, $down_units_history, $total_win, $total_loss);

// Store last 5 history
$bet_history = [];
while ($stmt->fetch()) {
    $bet_history[] = [
        'prediction_number' => $prediction_number,
        'winning_result' => $winning_result,
        'up_units' => $up_units_history,
        'draw_units' => $draw_units_history,
        'down_units' => $down_units_history,
        'total_win' => $total_win,
        'total_loss' => $total_loss
    ];
}
$stmt->close();

// Initialize the search query
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_results = [];

// Search functionality by username or user ID
if ($search_query) {
    // Search for users by username or ID
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username LIKE ? OR id = ?");
    $search_query_like = '%' . $search_query . '%';
    $stmt->bind_param('si', $search_query_like, $search_query);
    $stmt->execute();
    $stmt->bind_result($user_id, $username, $email);

    while ($stmt->fetch()) {
        $search_results[] = ['user_id' => $user_id, 'username' => $username, 'email' => $email];
    }
    $stmt->close();
}

// Handle account status updates (ban/unban) and logout actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'ban') {
        $stmt = $conn->prepare("UPDATE users SET account_status = 'banned' WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET account_status = 'active' WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'logout') {
        $stmt = $conn->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect to avoid resubmission
    header('Location: admin_panel.php?search=' . urlencode($search_query));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Admin Panel</h1>

    <!-- Dashboard Overview Section -->
    <h2>Dashboard Overview</h2>
    <p><strong>Pending Deposits:</strong> <?php echo $pending_deposits; ?> (<a href="admin_deposit.php">Review</a>)</p>
    <p><strong>Pending Withdrawals:</strong> <?php echo $pending_withdrawals; ?> (<a href="admin_withdraw.php">Review</a>)</p>
    <p><strong>Unread Messages:</strong> <?php echo $unread_messages; ?> (<a href="admin_chat.php">View Chats</a>)</p>
    <p><strong>Current Online Users:</strong> <?php echo $online_users; ?></p>

    <!-- Current Bets Section -->
    <h2>Current Bets</h2>
    <p><strong>Prediction Number:</strong> <?php echo $current_prediction_number; ?></p>
    <p><strong>UP Units:</strong> <?php echo $up_units; ?></p>
    <p><strong>DRAW Units:</strong> <?php echo $draw_units; ?></p>
    <p><strong>DOWN Units:</strong> <?php echo $down_units; ?></p>
    <p><strong>Total Wins:</strong> <?php echo $win_count; ?></p>
    <p><strong>Total Losses:</strong> <?php echo $loss_count; ?></p>

    <!-- Last 5 Bet History Section -->
    <h2>Last 5 Bet Histories</h2>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Prediction Number</th>
                <th>UP Units</th>
                <th>DRAW Units</th>
                <th>DOWN Units</th>
                <th>Winning Result</th>
                <th>Total Win</th>
                <th>Total Loss</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bet_history as $history): ?>
                <tr>
                    <td><?php echo $history['prediction_number']; ?></td>
                    <td><?php echo $history['up_units']; ?></td>
                    <td><?php echo $history['draw_units']; ?></td>
                    <td><?php echo $history['down_units']; ?></td>
                    <td><?php echo strtoupper($history['winning_result']); ?></td>
                    <td><?php echo $history['total_win']; ?></td>
                    <td><?php echo $history['total_loss']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Search User Section -->
    <h2>Search User</h2>
    <form method="get" action="admin_panel.php">
        <input type="text" name="search" placeholder="Search by username or ID" value="<?php echo htmlspecialchars($search_query); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($search_results)): ?>
        <h3>Search Results</h3>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($search_results as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['username']); ?></td>
                        <td><?php echo htmlspecialchars($result['email']); ?></td>
                        <td>
                            <form method="post" action="admin_panel.php">
                                <input type="hidden" name="user_id" value="<?php echo $result['user_id']; ?>">
                                <button type="submit" name="action" value="ban">Ban</button>
                                <button type="submit" name="action" value="unban">Unban</button>
                                <button type="submit" name="action" value="logout">Logout</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($search_query): ?>
        <p>No users found for '<?php echo htmlspecialchars($search_query); ?>'.</p>
    <?php endif; ?>

    <!-- Buttons for History and User Management -->
    <h2>History and User Management</h2>
    <ul>
        <li><a href="admin_transaction_history.php">Transaction Completed History</a></li>
        <li><a href="withdraw_completed_history.php">Withdraw Completed History</a></li>
        <li><a href="admin_bet_history.php">User Bet History</a></li>
        <li><a href="admin_deposit.php">Manage Deposits</a></li>
        <li><a href="admin_withdraw.php">Manage Withdrawals</a></li>
        <li><a href="admin_users.php">Manage Users</a></li>
        <li><a href="admin_chat.php">Chat with Users</a></li>
    </ul>

    <!-- Log Out -->
    <p><a href="admin_logout.php">Log Out</a></p>
</body>
</html>

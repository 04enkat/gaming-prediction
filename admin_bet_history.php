<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Pagination variables
$limit = 10;  // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;  // Current page number
$offset = ($page - 1) * $limit;  // Offset for SQL query

// Search User Functionality
$search_query = $_GET['search'] ?? '';
$selected_user_id = $_GET['user_id'] ?? null;
$users = [];

// Search for users by username or user ID
if ($search_query) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username LIKE ? OR id = ?");
    $search_query_param = '%' . $search_query . '%';
    $stmt->bind_param('si', $search_query_param, $search_query);
    $stmt->execute();
    $stmt->bind_result($user_id, $username);

    while ($stmt->fetch()) {
        $users[] = ['user_id' => $user_id, 'username' => $username];
    }
    $stmt->close();
}

// Fetch the total number of bets for pagination
$total_bets = 0;
if ($selected_user_id) {
    $stmt_total = $conn->prepare("SELECT COUNT(*) FROM bets WHERE user_id = ?");
    $stmt_total->bind_param('i', $selected_user_id);
} else {
    $stmt_total = $conn->prepare("SELECT COUNT(*) FROM bets");
}
$stmt_total->execute();
$stmt_total->bind_result($total_bets);
$stmt_total->fetch();
$stmt_total->close();

$total_pages = ceil($total_bets / $limit);

// Fetch bets for the selected user or all users with pagination
$bets = [];
if ($selected_user_id) {
    $stmt = $conn->prepare("
        SELECT b.prediction_number, b.bet_amount, b.bet_choice, p.winning_result, 
               IF(b.bet_choice = p.winning_result, 'Win', 'Loss') AS result_status
        FROM bets b
        JOIN predictions p ON b.prediction_number = p.prediction_number
        WHERE b.user_id = ?
        ORDER BY b.prediction_number DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $selected_user_id, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT b.prediction_number, b.bet_amount, b.bet_choice, p.winning_result, 
               IF(b.bet_choice = p.winning_result, 'Win', 'Loss') AS result_status, u.username
        FROM bets b
        JOIN predictions p ON b.prediction_number = p.prediction_number
        JOIN users u ON b.user_id = u.id
        ORDER BY b.prediction_number DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
if ($selected_user_id) {
    $stmt->bind_result($prediction_number, $bet_amount, $bet_choice, $winning_result, $result_status);
} else {
    $stmt->bind_result($prediction_number, $bet_amount, $bet_choice, $winning_result, $result_status, $username);
}

while ($stmt->fetch()) {
    if ($selected_user_id) {
        $bets[] = [
            'prediction_number' => $prediction_number,
            'bet_amount' => $bet_amount,
            'bet_choice' => $bet_choice,
            'winning_result' => $winning_result,
            'result_status' => $result_status
        ];
    } else {
        $bets[] = [
            'prediction_number' => $prediction_number,
            'bet_amount' => $bet_amount,
            'bet_choice' => $bet_choice,
            'winning_result' => $winning_result,
            'result_status' => $result_status,
            'username' => $username
        ];
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Bet History</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>User Bet History</h1>

    <!-- Search User Section -->
    <form method="get" action="admin_bet_history.php">
        <label for="search">Search User by Username or ID:</label>
        <input type="text" id="search" name="search" placeholder="Enter username or ID" value="<?php echo htmlspecialchars($search_query); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($users)): ?>
        <h2>Select a User to View Bet History</h2>
        <form method="get" action="admin_bet_history.php">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <label for="user_id">User:</label>
            <select name="user_id" id="user_id" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['user_id']; ?>" <?php if ($selected_user_id == $user['user_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($user['username']) . " (ID: " . $user['user_id'] . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Show Bet History</button>
        </form>
    <?php elseif ($search_query): ?>
        <p>No users found for '<?php echo htmlspecialchars($search_query); ?>'.</p>
    <?php endif; ?>

    <!-- Display Bet History if Available -->
    <?php if (!empty($bets)): ?>
        <h2>Bet History<?php echo $selected_user_id ? " for User ID: $selected_user_id" : ''; ?></h2>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Prediction Number</th>
                    <th>Bet Amount (₹)</th>
                    <th>Bet Choice</th>
                    <th>Winning Result</th>
                    <th>Win/Loss</th>
                    <?php if (!$selected_user_id): ?>
                        <th>Username</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bets as $bet): ?>
                    <tr>
                        <td><?php echo $bet['prediction_number']; ?></td>
                        <td><?php echo number_format($bet['bet_amount'], 2); ?></td>
                        <td><?php echo $bet['bet_choice']; ?></td>
                        <td><?php echo $bet['winning_result']; ?></td>
                        <td><?php echo $bet['result_status']; ?></td>
                        <?php if (!$selected_user_id): ?>
                            <td><?php echo $bet['username']; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="admin_bet_history.php?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="admin_bet_history.php?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php elseif ($selected_user_id): ?>
        <p>No bet history found for this user.</p>
    <?php else: ?>
        <p>No bet history found.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>

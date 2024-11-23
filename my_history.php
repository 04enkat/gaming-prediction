<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch the user's betting history with win/loss details and pagination
$stmt = $conn->prepare("
    SELECT b.prediction_number, b.bet_choice, b.bet_amount, p.winning_result, 
           CASE 
               WHEN b.bet_choice = p.winning_result AND p.winning_result = 'DRAW' THEN b.bet_amount * 5
               WHEN b.bet_choice = p.winning_result THEN b.bet_amount * 2
               ELSE 0
           END AS win_amount,
           CASE
               WHEN b.bet_choice = p.winning_result THEN 'Win'
               ELSE 'Loss'
           END AS result_status,
           CASE
               WHEN b.bet_choice != p.winning_result THEN b.bet_amount
               ELSE 0
           END AS loss_amount,
           CASE
               WHEN b.bet_choice != p.winning_result THEN b.bet_amount * (u.loss_recovery / 100)
               ELSE 0
           END AS recovery_amount
    FROM bets b
    JOIN predictions p ON b.prediction_number = p.prediction_number
    JOIN users u ON b.user_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.prediction_number DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('iii', $user_id, $limit, $offset);
$stmt->execute();
$stmt->bind_result($prediction_number, $bet_choice, $bet_amount, $winning_result, $win_amount, $result_status, $loss_amount, $recovery_amount);

// Fetch the results into an array
$history = [];
while ($stmt->fetch()) {
    $history[] = [
        'prediction_number' => $prediction_number,
        'bet_choice' => $bet_choice,
        'bet_amount' => $bet_amount,
        'winning_result' => $winning_result,
        'win_amount' => $win_amount,
        'result_status' => $result_status,
        'loss_amount' => $loss_amount,
        'recovery_amount' => $recovery_amount
    ];
}
$stmt->close();

// Get total number of records for pagination
$stmt_total = $conn->prepare("SELECT COUNT(*) FROM bets WHERE user_id = ?");
$stmt_total->bind_param('i', $user_id);
$stmt_total->execute();
$stmt_total->bind_result($total_rows);
$stmt_total->fetch();
$stmt_total->close();

$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Betting History</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>My Betting History</h1>

    <!-- History Table -->
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Prediction Number</th>
                <th>Your Prediction</th>
                <th>Bet Amount (₹)</th>
                <th>Winning Result</th>
                <th>Win Amount (₹)</th>
                <th>Win/Loss</th>
                <th>Loss Amount (₹)</th>
                <th>VIP Recovery (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($history)): ?>
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?php echo $entry['prediction_number']; ?></td>
                        <td><?php echo $entry['bet_choice']; ?></td>
                        <td><?php echo number_format($entry['bet_amount'], 2); ?></td>
                        <td><?php echo $entry['winning_result']; ?></td>
                        <td><?php echo number_format($entry['win_amount'], 2); ?></td>
                        <td><?php echo $entry['result_status']; ?></td>
                        <td><?php echo number_format($entry['loss_amount'], 2); ?></td>
                        <td><?php echo number_format($entry['recovery_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No betting history available.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Links -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="my_history.php?page=<?php echo $page - 1; ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
            <a href="my_history.php?page=<?php echo $page + 1; ?>">Next</a>
        <?php endif; ?>
    </div>

    <!-- Buttons for navigation -->
    <p><a href="index.php">Back to Home</a></p>
</body>
</html>
<!-- Footer Navigation (Include this in all your pages) -->
<footer class="footer-nav">
    <a href="index.php" class="footer-btn">Home</a>
    <a href="game.php" class="footer-btn">Game</a>
    <a href="my_history.php" class="footer-btn">My History</a>
    <a href="profile.php" class="footer-btn">Profile</a>
</footer>

<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details, including VIP level, wallet balances, and expiration
$stmt = $conn->prepare("SELECT wallet_balance, wallet_withdrawable, username, vip_level, vip_expiry, loss_recovery FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();  // Store the result to avoid issues when fetching later
$stmt->bind_result($wallet_balance, $wallet_withdrawable, $username, $vip_level, $vip_expiry, $loss_recovery);
$stmt->fetch();
$stmt->close();

// Check VIP expiration and downgrade if expired
$current_date = date("Y-m-d H:i:s");
if ($vip_expiry && $vip_expiry < $current_date) {
    $vip_level = 0;
    $loss_recovery = 0;
    $stmt = $conn->prepare("UPDATE users SET vip_level = 0, loss_recovery = 0 WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

// Set betting limits based on VIP level
$vip_limits = [
    0 => [10, 100],    // Free users
    1 => [10, 1000],   // VIP 1
    2 => [10, 10000]   // VIP 2
];
$min_bet = $vip_limits[$vip_level][0];
$max_bet = $vip_limits[$vip_level][1];

// Calculate the current prediction number and time left
$prediction_number = 1000 + floor(time() / 60);
$time_left = 60 - (time() % 60);

// Fetch bets and result for the current prediction
$stmt = $conn->prepare("SELECT up_bet, draw_bet, down_bet, winning_result FROM predictions WHERE prediction_number = ?");
$stmt->bind_param('i', $prediction_number);
$stmt->execute();
$stmt->store_result();  // Store result to allow multiple queries in loop if needed
$stmt->bind_result($up_bet, $draw_bet, $down_bet, $winning_result);
$stmt->fetch();
$stmt->close();

// Initialize new prediction if no record exists
if ($up_bet === null) {
    $stmt = $conn->prepare("INSERT INTO predictions (prediction_number) VALUES (?)");
    $stmt->bind_param('i', $prediction_number);
    $stmt->execute();
    $stmt->close();
    $up_bet = $draw_bet = $down_bet = 0;
}

// Calculate and update results when time is up
if ($time_left <= 5 && $winning_result === null) {
    // Determine result based on bets; random if no bets
    if ($up_bet == 0 && $draw_bet == 0 && $down_bet == 0) {
        $result = ['UP', 'DRAW', 'DOWN'][array_rand(['UP', 'DRAW', 'DOWN'])];
    } else {
        $result = ($up_bet * 2 <= $draw_bet * 5 && $up_bet * 2 <= $down_bet * 2) ? 'UP' :
                 (($draw_bet * 5 <= $up_bet * 2 && $draw_bet * 5 <= $down_bet * 2) ? 'DRAW' : 'DOWN');
    }

    // Update the result in the predictions table
    $stmt = $conn->prepare("UPDATE predictions SET winning_result = ? WHERE prediction_number = ?");
    $stmt->bind_param('si', $result, $prediction_number);
    $stmt->execute();
    $stmt->close();
    $winning_result = $result; // For display below

    // Process winnings and apply VIP loss recovery
    $stmt = $conn->prepare("SELECT user_id, bet_choice, bet_amount FROM bets WHERE prediction_number = ?");
    $stmt->bind_param('i', $prediction_number);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($bet_user_id, $bet_choice, $bet_amount);

    while ($stmt->fetch()) {
        if ($bet_choice === $result) {
            // Winning bet: calculate winnings and add to wallet_withdrawable
            $winnings = ($result === 'DRAW') ? $bet_amount * 5 : $bet_amount * 2;
            $stmt_update = $conn->prepare("UPDATE users SET wallet_withdrawable = wallet_withdrawable + ? WHERE id = ?");
            $stmt_update->bind_param('di', $winnings, $bet_user_id);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Losing bet: apply loss recovery if applicable
            $stmt_vip = $conn->prepare("SELECT vip_level, loss_recovery FROM users WHERE id = ?");
            $stmt_vip->bind_param('i', $bet_user_id);
            $stmt_vip->execute();
            $stmt_vip->store_result();
            $stmt_vip->bind_result($loser_vip_level, $loser_loss_recovery);
            $stmt_vip->fetch();
            $stmt_vip->close();

            if ($loser_vip_level >= 1) {
                $recovery_amount = $bet_amount * ($loser_loss_recovery / 100);
                $stmt_recover = $conn->prepare("UPDATE users SET wallet_withdrawable = wallet_withdrawable + ? WHERE id = ?");
                $stmt_recover->bind_param('di', $recovery_amount, $bet_user_id);
                $stmt_recover->execute();
                $stmt_recover->close();
            }
        }
    }
    $stmt->close();
}

// Fetch last 10 predictions for history
$stmt_history = $conn->prepare("SELECT prediction_number, COALESCE(winning_result, 'Pending') AS winning_result FROM predictions ORDER BY prediction_number DESC LIMIT 10");
$stmt_history->execute();
$stmt_history->store_result();
$stmt_history->bind_result($history_prediction_number, $history_winning_result);
$history_data = [];
while ($stmt_history->fetch()) {
    $history_data[] = [
        'prediction_number' => $history_prediction_number,
        'winning_result' => $history_winning_result
    ];
}
$stmt_history->close();

// Handle bet submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bet_choice'], $_POST['bet_amount'])) {
    $bet_choice = $_POST['bet_choice'];
    $bet_amount = $_POST['bet_amount'];

    // Validate bet amount
    if ($bet_amount < $min_bet || $bet_amount > $max_bet) {
        echo "Bet amount must be between ₹$min_bet and ₹$max_bet.";
        exit();
    }

    // Check available balance (wallet + withdrawable)
    $total_balance = $wallet_balance + $wallet_withdrawable;
    if ($total_balance < $bet_amount) {
        echo "Insufficient funds.";
        exit();
    }

    // Deduct from wallet first, then withdrawable balance if needed
    if ($wallet_balance >= $bet_amount) {
        $wallet_balance -= $bet_amount;
    } else {
        $wallet_withdrawable -= ($bet_amount - $wallet_balance);
        $wallet_balance = 0;
    }

    // Update user balance
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ?, wallet_withdrawable = ? WHERE id = ?");
    $stmt->bind_param('ddi', $wallet_balance, $wallet_withdrawable, $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert the bet in bets table
    $stmt = $conn->prepare("INSERT INTO bets (user_id, prediction_number, bet_choice, bet_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iisd', $user_id, $prediction_number, $bet_choice, $bet_amount);
    $stmt->execute();
    $stmt->close();

    // Update the total bet amount for selected option in predictions table
    $bet_column = strtolower($bet_choice) . "_bet";
    $stmt = $conn->prepare("UPDATE predictions SET $bet_column = $bet_column + ? WHERE prediction_number = ?");
    $stmt->bind_param('di', $bet_amount, $prediction_number);
    $stmt->execute();
    $stmt->close();

    // Redirect after placing bet
    header('Location: game.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prediction Game</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Prediction Game</h1>
    
    <!-- Display user details -->
    <p><strong>Welcome, <?php echo htmlspecialchars($username); ?>!</strong></p>
    <p><strong>Total Balance:</strong> ₹<?php echo number_format($wallet_balance, 2); ?></p>
    <p><strong>Withdrawable Balance:</strong> ₹<?php echo number_format($wallet_withdrawable, 2); ?></p>
    <p><strong>VIP Level:</strong> <?php echo $vip_level > 0 ? "VIP $vip_level" : "Free User"; ?> <?php if ($vip_level > 0) echo '(Expires: ' . htmlspecialchars($vip_expiry) . ')'; ?></p>
    <p>Betting Limit: ₹<?php echo $min_bet; ?> - ₹<?php echo $max_bet; ?></p>

    <!-- Prediction and countdown -->
    <p>Prediction Number: <strong><?php echo $prediction_number; ?></strong></p>
    <p>Time left to place bets: <span id="timer"><?php echo max(0, $time_left - 5); ?></span> seconds</p>

    <!-- Betting Form -->
    <form id="betForm" method="post" action="game.php">
        <input type="hidden" name="prediction_number" value="<?php echo $prediction_number; ?>">
        <input type="hidden" id="bet_choice" name="bet_choice" value="">

        <label>Select Your Prediction:</label>
        <div class="predict-buttons">
            <button type="button" onclick="selectPrediction('UP')" class="btn-up">UP</button>
            <button type="button" onclick="selectPrediction('DRAW')" class="btn-draw">DRAW</button>
            <button type="button" onclick="selectPrediction('DOWN')" class="btn-down">DOWN</button>
        </div>

        <!-- Bet amount options based on VIP -->
        <div class="bet-amounts">
            <button type="button" onclick="setBetAmount(10)">10</button>
            <button type="button" onclick="setBetAmount(50)">50</button>
            <button type="button" onclick="setBetAmount(100)">100</button>
            <?php if ($vip_level >= 1): ?>
                <button type="button" onclick="setBetAmount(200)">200</button>
                <button type="button" onclick="setBetAmount(500)">500</button>
            <?php endif; ?>
            <?php if ($vip_level == 2): ?>
                <button type="button" onclick="setBetAmount(2000)">2000</button>
                <button type="button" onclick="setBetAmount(5000)">5000</button>
            <?php endif; ?>
        </div>

        <!-- Bet input and submit -->
        <input type="number" id="bet_amount" name="bet_amount" placeholder="Enter Bet Amount" min="<?php echo $min_bet; ?>" max="<?php echo $max_bet; ?>" required>
        <button type="submit" id="placeBetButton">Place Bet</button>
    </form>

    <!-- Display result -->
    <h2>Winning Result: <strong><?php echo htmlspecialchars($winning_result ?? "Pending"); ?></strong></h2>

    <!-- Prediction History -->
    <h2>Prediction History</h2>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Prediction Number</th>
                <th>Winning Result</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history_data as $history): ?>
                <tr>
                    <td><?php echo $history['prediction_number']; ?></td>
                    <td><?php echo $history['winning_result']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="index.php">Back to Home</a> | <a href="my_history.php">My History</a></p>

    <script>
        function selectPrediction(prediction) {
            document.getElementById('bet_choice').value = prediction;
            document.querySelectorAll('.predict-buttons button').forEach(btn => btn.style.backgroundColor = '#4CAF50');
            document.querySelector(`.btn-${prediction.toLowerCase()}`).style.backgroundColor = '#FF5733';
        }

        function setBetAmount(amount) {
            document.getElementById('bet_amount').value = amount;
        }

        // Countdown and button disabling for last 10 seconds
        let timer = document.getElementById('timer').textContent;
        const countdownInterval = setInterval(() => {
            timer--;
            document.getElementById('timer').textContent = timer;

            if (timer <= 10) {
                document.querySelectorAll('.predict-buttons button, .bet-amounts button, #placeBetButton').forEach(el => el.disabled = true);
            }
            if (timer <= 0) {
                clearInterval(countdownInterval);
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>




<label>Select Your Prediction:</label>
        <div class="predict-buttons">
            <button type="button" onclick="selectPrediction('UP')" class="btn-up">UP</button>
            <button type="button" onclick="selectPrediction('DRAW')" class="btn-draw">DRAW</button>
            <button type="button" onclick="selectPrediction('DOWN')" class="btn-down">DOWN</button>
        </div>
		
		
		
		
		
        function selectPrediction(prediction) {
            document.getElementById('bet_choice').value = prediction;
            document.querySelectorAll('.predict-buttons button').forEach(btn => btn.classList.remove('selected'));
            document.querySelector(`.btn-${prediction.toLowerCase()}`).classList.add('selected');
            checkFormValidity();
        }

        function setBetAmount(amount) {
            document.getElementById('bet_amount').value = amount;
            document.querySelectorAll('.bet-amounts button').forEach(btn => btn.classList.remove('selected'));
            document.querySelector(`button[onclick="setBetAmount(${amount})"]`).classList.add('selected');
            checkFormValidity();
        }

        function checkFormValidity() {
            const betChoice = document.getElementById('bet_choice').value;
            const betAmount = document.getElementById('bet_amount').value;
            document.getElementById('placeBetButton').disabled = !(betChoice && betAmount >= <?php echo $min_bet; ?>);
        }
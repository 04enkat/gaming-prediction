<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $prediction_number = $_POST['prediction_number'];
    $bet_choice = $_POST['bet_choice'];  
    $bet_amount = $_POST['bet_amount'];

    // Ensure bet_choice is set
    if (empty($bet_choice)) {
        echo "Bet choice not set.";
        exit();
    }

    // Fetch the latest wallet balances
    $stmt = $conn->prepare("SELECT wallet_balance, wallet_withdrawable FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($wallet_balance, $wallet_withdrawable);
    $stmt->fetch();
    $stmt->close();

    // Check if sufficient funds are available
    $total_available_balance = $wallet_balance + $wallet_withdrawable;
    if ($total_available_balance < $bet_amount) {
        echo "Insufficient funds.";
        exit();
    }

    // Deduct from wallet_balance first, then wallet_withdrawable if necessary
    if ($wallet_balance >= $bet_amount) {
        $wallet_balance -= $bet_amount;
    } else {
        $remaining_amount = $bet_amount - $wallet_balance;
        $wallet_balance = 0;
        $wallet_withdrawable -= $remaining_amount;
    }

    // Update user's wallet balances
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ?, wallet_withdrawable = ? WHERE id = ?");
    $stmt->bind_param('ddi', $wallet_balance, $wallet_withdrawable, $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert the bet into the `bets` table
    $stmt = $conn->prepare("INSERT INTO bets (user_id, prediction_number, bet_choice, bet_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iisd', $user_id, $prediction_number, $bet_choice, $bet_amount);
    $stmt->execute();
    $stmt->close();

    // Map the bet choice to the corresponding column in predictions
    switch (strtolower($bet_choice)) {
        case 'up':
            $bet_column = "up_bet";
            break;
        case 'draw':
            $bet_column = "draw_bet";
            break;
        case 'down':
            $bet_column = "down_bet";
            break;
        default:
            echo "Invalid bet choice.";
            exit();
    }

    // Update the total bet amounts in the predictions table
    $stmt = $conn->prepare("UPDATE predictions SET $bet_column = $bet_column + ? WHERE prediction_number = ?");
    $stmt->bind_param('di', $bet_amount, $prediction_number);
    $stmt->execute();
    $stmt->close();

    // Redirect back to the game page after placing the bet
    header('Location: game.php');
    exit();
}
?>

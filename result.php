<?php
include 'db.php';

// Fetch the current prediction number (increments every minute)
$prediction_number = 1000 + floor(time() / 60);

// Fetch the bets for the current prediction
$stmt = $conn->prepare("SELECT up_bet, draw_bet, down_bet FROM predictions WHERE prediction_number = ?");
$stmt->bind_param('i', $prediction_number);
$stmt->execute();
$stmt->bind_result($up_bet, $draw_bet, $down_bet);
$stmt->fetch();
$stmt->close();

// Get the current time (seconds in the current minute)
$current_time = time() % 60;

// If it's time to calculate the result (within the last 5 seconds of the minute)
if ($current_time > 55) {
    // Determine the result based on the lowest bet
    if ($up_bet == 0 && $draw_bet == 0 && $down_bet == 0) {
        // If no bets are placed, randomly select UP, DRAW, or DOWN
        $possible_results = ['UP', 'DRAW', 'DOWN'];
        $result = $possible_results[array_rand($possible_results)];
    } else {
        // Select the result with the lowest bet
        if ($up_bet <= $draw_bet && $up_bet <= $down_bet) {
            $result = 'UP';
        } elseif ($draw_bet <= $up_bet && $draw_bet <= $down_bet) {
            $result = 'DRAW';
        } else {
            $result = 'DOWN';
        }
    }

    // Update the result in the `predictions` table
    $stmt = $conn->prepare("UPDATE predictions SET winning_result = ? WHERE prediction_number = ?");
    $stmt->bind_param('si', $result, $prediction_number);
    $stmt->execute();
    $stmt->close();

    // Fetch all the bets for this prediction to calculate winnings
    $stmt = $conn->prepare("SELECT user_id, bet_choice, bet_amount FROM bets WHERE prediction_number = ?");
    $stmt->bind_param('i', $prediction_number);
    $stmt->execute();
    $stmt->bind_result($bet_user_id, $bet_choice, $bet_amount);

    // Array to store winning users and their calculated winnings
    $winners = [];
    while ($stmt->fetch()) {
        if ($bet_choice === $result) {
            // 2x for UP/DOWN, 5x for DRAW
            $winnings = ($result == 'DRAW') ? $bet_amount * 5 : $bet_amount * 2;
            $winners[] = ['user_id' => $bet_user_id, 'winnings' => $winnings];
        }
    }
    $stmt->close();

    // Update each winner's wallet balance
    foreach ($winners as $winner) {
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param('di', $winner['winnings'], $winner['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

?>

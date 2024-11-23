<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details including the profile image path
$stmt = $conn->prepare("
    SELECT username, wallet_balance, wallet_withdrawable, vip_level, vip_expiry, account_status, invite_code, profile_image,
           COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id = ? AND transaction_type = 'game_win' AND status = 'approved'), 0) AS game_winnings
    FROM users
    WHERE id = ?
");
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($username, $wallet_balance, $wallet_withdrawable, $vip_level, $vip_expiry, $account_status, $invite_code, $profile_image, $game_winnings);
$stmt->fetch();
$stmt->close();

// Check if the user is banned
if ($account_status === 'banned') {
    session_destroy();
    header('Location: login.php?error=Your account has been banned.');
    exit();
}

// Update VIP level if expired
$current_date = date("Y-m-d H:i:s");
if ($vip_expiry && $vip_expiry < $current_date) {
    $vip_level = 0;
    $stmt = $conn->prepare("UPDATE users SET vip_level = 0 WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

// Determine the path for the profile image
$image_path = !empty($profile_image) ? "uploads/" . basename(htmlspecialchars($profile_image)) : "uploads/default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .profile-container {
            text-align: center;
            margin-top: 20px;
        }
        .profile-image {
            display: inline-block;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #4CAF50;
        }
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-buttons a {
            margin: 5px;
            padding: 10px 20px;
            display: inline-block;
            text-decoration: none;
            color: #fff;
            background-color: #4CAF50;
            border-radius: 5px;
        }
        .btn-logout {
            padding: 10px 20px;
            background-color: #f44336;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Profile</h1>
        <div class="profile-image">
            <img src="<?php echo $image_path; ?>" alt="Profile Image">
        </div>
        <p>Username: <?php echo htmlspecialchars($username); ?></p>
        <p>Total Wallet Balance: ₹<?php echo number_format($wallet_balance, 2); ?></p>
        <p>Withdrawable Balance: ₹<?php echo number_format($wallet_withdrawable, 2); ?></p>
        <p>VIP Status: <?php echo $vip_level > 0 ? "VIP $vip_level (Expires: $vip_expiry)" : "Free User"; ?></p>

        <!-- VIP Recharge Button -->
        <a href="vip_recharge.php" class="btn">Recharge VIP</a>

        <!-- Transaction History Section -->
        <h2>Transaction History</h2>
        <p><a href="transaction_history.php">View Transaction History</a></p>

        <!-- Navigation Buttons -->
        <div class="profile-buttons">
            <a href="deposit_usdt.php">Deposit USDT</a>
            <a href="withdraw.php">Withdraw</a>
            <a href="chat_whatsapp.php">Chat with Admin</a>
            <a href="settings.php">Settings</a>
        </div>

        <!-- Referral Program Link -->
        <h2>Referral Program</h2>
        <p><a href="referral_program.php">View Referral Program</a></p>

        <p><a href="index.php">Back to Home</a></p>

        <!-- Log Out Button -->
        <form method="post" action="logout.php" style="margin-top: 20px;">
            <button type="submit" class="btn-logout">Log Out</button>
        </form>
    </div>
</body>
</html>

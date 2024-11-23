<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's current VIP status and wallet balance
$stmt = $conn->prepare("SELECT vip_level, vip_expiry, wallet_balance, wallet_withdrawable FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($vip_level, $vip_expiry, $wallet_balance, $wallet_withdrawable);
$stmt->fetch();
$stmt->close();

// VIP pricing, benefits, and loss recovery rates
$vip_options = [
    1 => [
        'price' => 100,
        'duration_days' => 30,
        'benefits' => [
            'Reduced withdrawal fees',
            'Priority support',
            'Access to exclusive games',
            '10% loss recovery on bets'
        ],
        'loss_recovery' => 10  // 10% recovery for VIP 1
    ],
    2 => [
        'price' => 500,
        'duration_days' => 30,
        'benefits' => [
            'All VIP 1 benefits',
            'Higher withdrawal limits',
            'Access to VIP-only tournaments',
            '20% loss recovery on bets'
        ],
        'loss_recovery' => 20  // 20% recovery for VIP 2
    ]
];

// Handle VIP upgrade request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vip_level'])) {
    $requested_vip = intval($_POST['vip_level']);

    if (array_key_exists($requested_vip, $vip_options)) {
        $vip_price = $vip_options[$requested_vip]['price'];

        if ($wallet_balance >= $vip_price) {
            // Deduct amount from user's wallet
            $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $stmt->bind_param('di', $vip_price, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update VIP level, expiry, and loss recovery percentage
            $new_expiry = date("Y-m-d H:i:s", strtotime("+{$vip_options[$requested_vip]['duration_days']} days"));
            $loss_recovery = $vip_options[$requested_vip]['loss_recovery'];
            $stmt = $conn->prepare("UPDATE users SET vip_level = ?, vip_expiry = ?, loss_recovery = ? WHERE id = ?");
            $stmt->bind_param('isii', $requested_vip, $new_expiry, $loss_recovery, $user_id);
            $stmt->execute();
            $stmt->close();

            $vip_level = $requested_vip;
            $vip_expiry = $new_expiry;
            $wallet_balance -= $vip_price;
            $message = "VIP $requested_vip activated successfully!";
        } else {
            $message = "Insufficient balance. Please add funds to your wallet.";
        }
    } else {
        $message = "Invalid VIP level selected.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VIP Recharge</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>VIP Recharge</h1>

    <!-- Display user's current VIP status -->
    <p>Your Current VIP Status: <?php echo $vip_level > 0 ? "VIP $vip_level (Expires: $vip_expiry)" : "Free User"; ?></p>
    <p>Wallet Balance: ₹<?php echo number_format($wallet_balance, 2); ?></p>
    <p>Withdrawable Balance (Game Winnings): ₹<?php echo number_format($wallet_withdrawable, 2); ?></p>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- Display VIP subscription options -->
    <h2>VIP Subscription Options</h2>
    <?php foreach ($vip_options as $level => $vip): ?>
        <div class="vip-option">
            <h3>VIP Level <?php echo $level; ?></h3>
            <p><strong>Price:</strong> ₹<?php echo $vip['price']; ?> for <?php echo $vip['duration_days']; ?> days</p>
            <p><strong>Benefits:</strong></p>
            <ul>
                <?php foreach ($vip['benefits'] as $benefit): ?>
                    <li><?php echo $benefit; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($vip_level < $level): ?>
                <form method="post" action="vip_recharge.php">
                    <input type="hidden" name="vip_level" value="<?php echo $level; ?>">
                    <button type="submit" class="btn">Upgrade to VIP <?php echo $level; ?></button>
                </form>
            <?php else: ?>
                <button disabled class="btn-disabled">Already Active</button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

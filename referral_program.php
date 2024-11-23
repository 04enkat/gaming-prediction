<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, vip_level, vip_expiry, invite_code, wallet_balance FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $vip_level, $vip_expiry, $invite_code, $wallet_balance);
$stmt->fetch();
$stmt->close();

// Generate invite link
$invite_link = "https://yourgame.com/signup.php?referral_code=" . $invite_code;

// Fetch all referrals and their deposit status
$referrals = [];
$total_deposit_referrals = 0;
$wallet_bonus = 10; // ₹10 bonus for each referral

// Retrieve referrals and whether they received a bonus and deposited over ₹200
$stmt = $conn->prepare("
    SELECT u.id, u.username, COALESCE(SUM(t.amount), 0) AS total_deposit, u.referred_bonus_given
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
        AND t.transaction_type = 'deposit'
        AND t.status = 'approved'
    WHERE u.invited_by = ?
    GROUP BY u.id
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($ref_user_id, $ref_username, $ref_total_deposit, $ref_bonus_given);

while ($stmt->fetch()) {
    $referrals[] = [
        'id' => $ref_user_id,
        'username' => $ref_username,
        'total_deposit' => $ref_total_deposit,
        'bonus_given' => $ref_bonus_given
    ];
    if ($ref_total_deposit >= 200) {
        $total_deposit_referrals++;
    }
}
$stmt->close();

// Process bonus for each referral that hasn't received a bonus yet
foreach ($referrals as $referral) {
    if (!$referral['bonus_given']) {
        // Update referral's bonus status and add bonus to user's wallet balance
        $stmt_update = $conn->prepare("UPDATE users SET referred_bonus_given = 1 WHERE id = ?");
        $stmt_update->bind_param('i', $referral['id']);
        $stmt_update->execute();
        $stmt_update->close();

        // Update user's wallet balance
        $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt_wallet->bind_param('di', $wallet_bonus, $user_id);
        $stmt_wallet->execute();
        $stmt_wallet->close();
    }
}

// VIP level based on successful referrals with deposits over ₹200
$new_vip_level = 0;
if ($total_deposit_referrals >= 10) {
    $new_vip_level = 2;
} elseif ($total_deposit_referrals >= 5) {
    $new_vip_level = 1;
}

// Upgrade VIP level if it has increased
if ($new_vip_level > $vip_level) {
    $vip_expiry = date("Y-m-d H:i:s", strtotime('+30 days'));
    $stmt = $conn->prepare("UPDATE users SET vip_level = ?, vip_expiry = ? WHERE id = ?");
    $stmt->bind_param('isi', $new_vip_level, $vip_expiry, $user_id);
    $stmt->execute();
    $stmt->close();
    $vip_level = $new_vip_level;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Referral Program</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Referral Program</h1>
    <p>Share your invite link with friends to earn ₹10 for each referral. VIP status will activate based on deposits made by referrals:</p>
    <p>
        <input type="text" id="inviteLink" value="<?php echo htmlspecialchars($invite_link); ?>" readonly style="width: 80%;">
        <button onclick="copyInviteLink()">Copy Invite Link</button>
    </p>
    <p>If 5 friends deposit at least ₹200, VIP 1 is activated for free; if 10 friends do, VIP 2 is activated for free!</p>
    <p>Current VIP Status: <?php echo $vip_level > 0 ? "VIP $vip_level (Expires: $vip_expiry)" : "Free User"; ?></p>

    <!-- Referral List -->
    <h2>Your Referrals</h2>
    <?php if (!empty($referrals)): ?>
        <table>
            <tr>
                <th>Username</th>
                <th>Total Deposit (₹)</th>
                <th>Bonus Given</th>
            </tr>
            <?php foreach ($referrals as $referral): ?>
                <tr>
                    <td><?php echo htmlspecialchars($referral['username']); ?></td>
                    <td>₹<?php echo number_format($referral['total_deposit'], 2); ?></td>
                    <td><?php echo $referral['bonus_given'] ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No referrals yet.</p>
    <?php endif; ?>

    <script>
        function copyInviteLink() {
            const inviteLink = document.getElementById("inviteLink");
            inviteLink.select();
            document.execCommand("copy");
            alert("Invite link copied!");
        }
    </script>

    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

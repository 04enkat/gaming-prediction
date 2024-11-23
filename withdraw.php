<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch user details for withdrawal verification
$stmt = $conn->prepare("SELECT username, email, wallet_withdrawable, vip_level FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $wallet_withdrawable, $vip_level);
$stmt->fetch();
$stmt->close();

// Define withdrawal limits
$min_withdraw = 500;
$max_withdraw = ($vip_level === 2) ? PHP_INT_MAX : (($vip_level === 1) ? 10000 : 1000);

// Check for minimum approved deposit of ₹200
$has_min_deposit = false;
$deposit_check = $conn->prepare("SELECT 1 FROM transactions WHERE user_id = ? AND transaction_type = 'deposit' AND status = 'approved' AND amount >= 200 LIMIT 1");
$deposit_check->bind_param('i', $user_id);
$deposit_check->execute();
$deposit_check->store_result();
$has_min_deposit = $deposit_check->num_rows > 0;
$deposit_check->close();

if (!$has_min_deposit) {
    $message = "A minimum approved deposit of ₹200 is required before making a withdrawal.";
}

// Handle form submission and redirect to summary page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order']) && $has_min_deposit) {
    $withdraw_amount = floatval($_POST['withdraw_amount']);
    $usdt_address = $_POST['usdt_address'];
    $file_upload_path = "";

    // Photo upload handling
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ["jpg", "jpeg", "png"];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_upload_path = "uploads/" . uniqid() . "." . $file_extension;
            move_uploaded_file($_FILES['photo']['tmp_name'], $file_upload_path);
        } else {
            $message = "Only JPG, JPEG, and PNG formats are accepted.";
        }
    } else {
        $message = "Please upload a photo.";
    }

    // Validate withdrawal amount and balance
    if ($withdraw_amount < $min_withdraw || $withdraw_amount > $max_withdraw) {
        $message = "Withdrawal amount must be between ₹$min_withdraw and ₹$max_withdraw.";
    } elseif ($wallet_withdrawable < $withdraw_amount) {
        $message = "Insufficient withdrawable balance.";
    } elseif ($file_upload_path === "") {
        $message = "Photo upload failed. Try again.";
    } else {
        // Store order details in session for summary
        $_SESSION['withdraw'] = [
            'amount' => $withdraw_amount,
            'usdt_address' => $usdt_address,
            'photo_path' => $file_upload_path,
            'wallet_withdrawable' => $wallet_withdrawable
        ];
        header("Location: withdraw_summary.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdraw Funds</title>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        function convertToUSDT() {
            const inrAmount = document.getElementById('withdraw_amount').value;
            const conversionRate = 83; // Example conversion rate: 1 USDT = 83 INR
            const usdtAmount = inrAmount / conversionRate;
            document.getElementById('usdt_amount').value = usdtAmount.toFixed(2);
        }
    </script>
</head>
<body>
    <h1>Withdraw Funds</h1>
    <p>Withdrawable Balance: ₹<?php echo number_format($wallet_withdrawable, 2); ?></p>
    <p>Network: <strong>Optimism</strong></p>

    <?php if ($message): ?>
        <p style="color: red;"><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if ($has_min_deposit): ?>
        <form method="post" action="withdraw.php" enctype="multipart/form-data">
            <label>Withdrawal Amount (₹):</label>
            <input type="number" name="withdraw_amount" id="withdraw_amount" min="<?php echo $min_withdraw; ?>" max="<?php echo $max_withdraw; ?>" required oninput="convertToUSDT()"><br>

            <label>USDT Equivalent:</label>
            <input type="text" id="usdt_amount" readonly><br>

            <label>USDT Address:</label>
            <input type="text" name="usdt_address" required><br>

            <label>Upload Photo (JPG, JPEG, PNG):</label>
            <input type="file" name="photo" accept=".jpg, .jpeg, .png" required><br>

            <button type="submit" name="submit_order">Submit Order</button><br>
        </form>
    <?php else: ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

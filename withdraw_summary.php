<?php
session_start();
include 'db.php';
require 'PHPMailer/PHPMailerAutoload.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['withdraw'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$withdraw_data = $_SESSION['withdraw'];
$message = "";

// Define conversion rate (1 USDT = 83 INR)
$conversionRate = 83;
$usdtValue = $withdraw_data['amount'] / $conversionRate;

// Fetch email for OTP verification
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

// Handle OTP sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 300;
    $otp_sent = sendOTPEmail($email, $otp);
    $message = $otp_sent ? "OTP sent to your email." : "Failed to send OTP. Try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if ($_POST['otp'] == $_SESSION['otp'] && time() < $_SESSION['otp_expiry']) {
        // Deduct balance and insert transaction
        $stmt = $conn->prepare("UPDATE users SET wallet_withdrawable = wallet_withdrawable - ? WHERE id = ?");
        $stmt->bind_param('di', $withdraw_data['amount'], $user_id);
        $stmt->execute();
        $stmt->close();

        // Insert withdrawal transaction with screenshot path
        $stmt = $conn->prepare("
            INSERT INTO transactions (user_id, transaction_type, amount, usdt_value, usdt_address, screenshot, status)
            VALUES (?, 'withdraw', ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param('iddss', $user_id, $withdraw_data['amount'], $usdtValue, $withdraw_data['usdt_address'], $withdraw_data['photo_path']);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['withdraw'], $_SESSION['otp'], $_SESSION['otp_expiry']);
        $message = "Withdrawal request for ₹{$withdraw_data['amount']} submitted successfully!";
    } else {
        $message = "Invalid or expired OTP. Try again.";
    }
}

// Send OTP Email using PHPMailer
function sendOTPEmail($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bmv.frpbypass@gmail.com';  // Your Gmail address
        $mail->Password = 'ddlp gomg ccnj ayfs';  // Your Gmail password (use App Passwords if 2FA is enabled)
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('no-reply@example.com', 'Your App Name');
        $mail->addAddress($email);
        $mail->isHTML(true);

        $mail->Subject = 'Your OTP Code';
        $mail->Body = "<p>Your OTP code is <strong>$otp</strong>. Use it to complete your withdrawal.</p>";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Withdrawal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Confirm Withdrawal</h1>
    <p><strong>Amount:</strong> ₹<?php echo number_format($withdraw_data['amount'], 2); ?></p>
    <p><strong>Equivalent USDT:</strong> <?php echo number_format($usdtValue, 2); ?> USDT</p>
    <p><strong>USDT Address:</strong> <?php echo htmlspecialchars($withdraw_data['usdt_address']); ?></p>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="post" action="withdraw_summary.php">
        <label>Enter OTP:</label>
        <input type="text" name="otp"><br>

        <?php if (!isset($_POST['send_otp'])): ?>
            <button type="submit" name="send_otp">Send OTP</button><br>
        <?php endif; ?>

        <?php if (isset($_POST['send_otp'])): ?>
            <button type="submit" name="verify_otp">Verify OTP & Submit</button><br>
        <?php endif; ?>
    </form>
    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

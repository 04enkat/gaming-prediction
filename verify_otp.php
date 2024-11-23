<?php
session_start();
include 'db.php';

if (!isset($_SESSION['pending_user_id'])) {
    header('Location: signup.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp_input = $_POST['otp'];
    $user_id = $_SESSION['pending_user_id'];

    // Check if the OTP matches
    $stmt = $conn->prepare("SELECT otp_code FROM users WHERE id = ? AND otp_code = ?");
    $stmt->bind_param('is', $user_id, $otp_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // OTP matches, verify the account
        $stmt->close();
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        // Verification success, log in the user and redirect
        $_SESSION['user_id'] = $user_id;
        unset($_SESSION['pending_user_id']);
        header('Location: profile.php');
        exit();
    } else {
        $message = 'Invalid OTP. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
	<link rel="stylesheet" href="css/signup/style.css">
</head>
<body>
    <h1>Verify OTP</h1>
    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="post" action="verify_otp.php">
        <label for="otp">Enter OTP:</label>
        <input type="text" name="otp" id="otp" required maxlength="6"><br>
        <button type="submit">Verify OTP</button>
    </form>
</body>
</html>

<?php
session_start();
include 'db.php';

$error = "";
$success = "";

// Ensure OTP verification
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    header('Location: login.php');
    exit();
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // Check if passwords are entered and match
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Update the password in the database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed_password, $_SESSION['reset_user_id']);

        if ($stmt->execute()) {
            $success = "Password reset successfully. You may now log in.";
            session_unset(); // Clear session data
            session_destroy();
            header("refresh:3;url=login.php");
            exit();
        } else {
            $error = "Failed to reset password. Please try again.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/signup/style.css">
</head>

<body>
    <div class="container">
        <h1>Reset Password</h1>

        <?php if ($error): ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color:green;"><?php echo $success; ?></p>
        <?php endif; ?>

        <!-- Password Reset Form -->
        <form method="post" action="reset_password.php" class="form-group signup_box glassmorphism">
           
            <input type="password" id="new_password" name="new_password" placeholder="New password" class="form-control" required>

            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" class="form-control" required>

            <button type="submit" class="btn btn-success form-control">Reset Password</button>
        </form>
    </div>
</body>

</html>
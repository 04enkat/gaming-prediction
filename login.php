<?php
session_start();
include 'db.php';
require 'PHPMailer/PHPMailerAutoload.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

// Process login, forgot password, and OTP verification forms
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Login Form Submission
    if (isset($_POST['login'])) {
        $user_input = $_POST['user_input'] ?? null;
        $password = $_POST['password'] ?? null;

        if (empty($user_input) || empty($password)) {
            $error = "Username/Email and password are required!";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password, account_status, ban_reason FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param('ss', $user_input, $user_input);
            $stmt->execute();
            $stmt->bind_result($user_id, $username, $hashed_password, $account_status, $ban_reason);
            $stmt->fetch();
            $stmt->close();

            if ($user_id) {
                if ($account_status === 'banned') {
                    $error = "Your account has been banned. Reason: " . ($ban_reason ?: "No reason provided.");
                } elseif (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    header('Location: index.php');
                    exit();
                } else {
                    $error = "Invalid username/email or password.";
                }
            } else {
                $error = "Invalid username/email or password.";
            }
        }
    }

    // Forgot Password Form Submission
    if (isset($_POST['forgot_password'])) {
        $user_input = $_POST['user_input'] ?? null;
        if (empty($user_input)) {
            $error = "Please enter your registered email or username.";
        } else {
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param('ss', $user_input, $user_input);
            $stmt->execute();
            $stmt->bind_result($user_id, $email);
            $stmt->fetch();
            $stmt->close();

            if ($user_id) {
                $otp = rand(100000, 999999);
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user_id'] = $user_id;
                $_SESSION['otp_expiry'] = time() + 300;

                if (sendOTPEmail($email, $otp)) {
                    $success = "OTP sent to your email. Please enter it below to proceed with the reset.";
                } else {
                    $error = "Failed to send OTP. Try again.";
                }
            } else {
                $error = "Username/Email not found in our records.";
            }
        }
    }

    // OTP Verification Form Submission
    if (isset($_POST['verify_otp'])) {
        $otp_entered = $_POST['otp'] ?? null;

        if ($otp_entered == $_SESSION['reset_otp'] && time() < $_SESSION['otp_expiry']) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid or expired OTP.";
        }
    }
}

// Send OTP Email using PHPMailer
function sendOTPEmail($email, $otp)
{
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'bmv.frpbypass@gmail.com';  // Your Gmail address
    $mail->Password = 'ddlp gomg ccnj ayfs';  // Your Gmail password (use App Passwords if 2FA is enabled)
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('no-reply@example.com', 'Otp send from Prediction Game');
    $mail->addAddress($email);
    $mail->isHTML(true);

    $mail->Subject = 'Your OTP Code';
    $mail->Body = "<p>Your OTP code is <strong>$otp</strong>. Please use it to reset your password.</p>";

    return $mail->send();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/signup/style.css">
</head>

<body>
    <div class="container-fluid signup_container">
        <h1 class="text-white text-center mt-5">Login</h1>

        <?php if ($error): ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color:green;"><?php echo $success; ?></p>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="loginForm" class="d-flex justify-content-center mt-3 align-items-center">
            <div class="signup_box glassmorphism">
                <form method="post" action="login.php" class="form-group">
                    <input type="hidden" name="login" class="form-control">

                    <input type="text" id="user_input" name="user_input" class="form-control mt-3"
                        placeholder="Username or Email" required>


                    <input type="password" id="password" name="password" class="form-control mt-3"
                        placeholder="password" required>

                    <button type="submit" class="mt-3 btn btn-outline-success form-control ">Login</button>
                    <p class="my-2"><a href="javascript:void(0);" onclick="showForgotPassword()">Forgot Password?</a>
                    </p>
                    <p class="text-white">Don't have an account? <a href="signup.php">Sign up here</a>.</p>
                </form>
            </div>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgotPasswordForm" style="display: none; margin-top:-300px; " class="signup_box glassmorphism">
            <h2 class="text-white">Forgot Password</h2>
            <form method="post" action="login.php" class="form-group">
                <input type="hidden" name="forgot_password">
                <input type="text" id="user_input" placeholder="Enter username or email" class="form-control" name="user_input" required>
                <button type="submit" class="form-control btn btn-warning">Send OTP</button>
                <p><a href="javascript:void(0);" onclick="showLogin()" class="btn btn-danger form-control">Back to Login</a></p>
            </form>
        </div>

        <!-- OTP Verification Form -->
        <div id="otpForm" style="display: none;">
            <h2>Enter OTP</h2>
            <form method="post" action="login.php">
                <input type="hidden" name="verify_otp">
                <label for="otp">OTP:</label>
                <input type="text" id="otp" name="otp" class="form-control" required>
                <button type="submit">Verify OTP</button>
                <p><a href="javascript:void(0);" onclick="showForgotPassword()">Back</a></p>
            </form>
        </div>

        <script>
            function showForgotPassword() {
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('forgotPasswordForm').style.display = 'block';
                document.getElementById('otpForm').style.display = 'none';
            }

            function showLogin() {
                document.getElementById('forgotPasswordForm').style.display = 'none';
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('otpForm').style.display = 'none';
            }

            <?php if ($success): ?>
                document.getElementById('forgotPasswordForm').style.display = 'none';
                document.getElementById('otpForm').style.display = 'block';
            <?php endif; ?>
        </script>
    </div>
</body>

</html>
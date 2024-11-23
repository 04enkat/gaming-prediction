<?php
session_start();
include 'db.php';
require 'PHPMailer/PHPMailerAutoload.php';  // Adjust path to your PHPMailer file
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate a random invite code
function generateInviteCode()
{
    return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
}

// Generate a random OTP
function generateOTP()
{
    return rand(100000, 999999);  // 6 digit OTP
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

    $mail->setFrom('no-reply@example.com', 'Your App Name');
    $mail->addAddress($email);
    $mail->isHTML(true);

    $mail->Subject = 'Your OTP Code';
    $mail->Body = "<p>Your OTP code is <strong>$otp</strong>. Please use it to complete your registration.</p>";

    return $mail->send();
}

// Handle form submission
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $invite_code = generateInviteCode();
    $otp_code = generateOTP();

    // Check for a referrer
    $invited_by = null;
    if (isset($_POST['invite_code']) && !empty($_POST['invite_code'])) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE invite_code = ?");
        $stmt->bind_param('s', $_POST['invite_code']);
        $stmt->execute();
        $stmt->bind_result($referrer_id);
        if ($stmt->fetch()) {
            $invited_by = $referrer_id;
        }
        $stmt->close();
    }

    // Check if the email is already registered
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Email already registered. Please try another.";
        $stmt->close();
    } else {
        $stmt->close();

        // Send OTP to the user's email
        if (sendOTPEmail($email, $otp_code)) {
            // Insert user with OTP and invite information
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, otp_code, invite_code, invited_by, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param('sssssi', $username, $email, $password, $otp_code, $invite_code, $invited_by);
            if ($stmt->execute()) {
                $_SESSION['pending_user_id'] = $stmt->insert_id;
                $_SESSION['email'] = $email;
                $stmt->close();
                header('Location: verify_otp.php');
                exit();
            } else {
                $message = "Error during registration. Please try again.";
            }
        } else {
            $message = "Failed to send OTP. Please check your email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/signup/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <script>
        $(document).ready(function () {
            $('#signupBtn').prop('disabled', true);

            function validateForm() {
                var username = $('#username').val().trim();
                var email = $('#email').val().trim();
                var password = $('#password').val().trim();
                var usernameStatus = $('#username-status').text().trim();

                if (username && email && password && usernameStatus === 'Available') {
                    $('#signupBtn').prop('disabled', false);
                } else {
                    $('#signupBtn').prop('disabled', true);
                }
            }

            $('#username').on('input', function () {
                var username = $(this).val().trim();
                if (username.length > 0) {
                    $.ajax({
                        url: 'check_username.php',
                        method: 'GET',
                        data: { username: username },
                        success: function (response) {
                            if (response === 'taken') {
                                $('#username-status').text('Username is already taken').css('color', 'black');
                            } else {
                                $('#username-status').text('Available').css('color', 'white');
                            }
                            validateForm();
                        }
                    });
                } else {
                    $('#username-status').text('');
                }
            });

            $('#email, #password').on('input', function () {
                validateForm();
            });
        });
    </script>
</head>

<body>
    <section class="loginSign-bg" >
        <div class="container-fluid signup_container ">
            <div class="signupBackground">
                <h1 class="text-white mt-4 text-center ">Sign Up</h1>

                <?php if (!empty($message)): ?>
                    <p><?php echo $message; ?></p>
                <?php endif; ?>


                <div class="container d-flex justify-content-center align-items-center mt-3 signup_box glassmorphism ">
                    <form method="post" action="signup.php" class="form-group">
                        <!-- <label for="username" class="label_design ">Username:</label> -->
                        <input type="text" name="username" class="form-control" id="username" placeholder="Username"
                            required>
                        <span id="username-status"></span><br>

                        <!-- <label for="email" class="label_design ">Email:</label> -->
                        <input type="email" name="email" class="form-control" id="email" placeholder="Email Address"
                            required><br>

                        <!-- <label for="password" class="label_design ">Password:</label> -->
                        <input type="password" name="password" class="form-control" id="password" placeholder="Password"
                            required><br>

                        <!-- <label for="invite_code" class="label_design ">Invite Code (optional):</label> -->
                        <input type="text" name="invite_code" class="form-control" id="invite_code"
                            placeholder="Invite code (Optional)" maxlength="6"><br>

                        <button type="submit" id="signupBtn" class="btn btn-outline-danger form-control">Sign Up</button>
                        <p style="color:white" class="my-3">Already i have an account? <a href="login.php">Login</a></p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
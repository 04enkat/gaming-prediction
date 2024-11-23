<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        // Upload new profile image
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["profile_image"]["name"]);
        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file);

        // Update profile image in database
        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->bind_param('si', $target_file, $user_id);
        $stmt->execute();
        $stmt->close();
        $message = "Profile image updated successfully!";
    }

    if (!empty($_POST['username'])) {
        $new_username = $_POST['username'];
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->bind_param('si', $new_username, $user_id);
        $stmt->execute();
        $stmt->close();
        $message = "Username updated successfully!";
    }

    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        // Fetch current password from DB
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($current_password_hash);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($_POST['current_password'], $current_password_hash)) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $new_password_hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $new_password_hash, $user_id);
                $stmt->execute();
                $stmt->close();
                $message = "Password updated successfully!";
            } else {
                $message = "New passwords do not match.";
            }
        } else {
            $message = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#updatebutton').prop('disabled', true); // Disable button initially

            function validateForm() {
                var usernameStatus = $('#username-status').text().trim();
                // Only enable button if username is available
                $('#updatebutton').prop('disabled', usernameStatus !== 'Available');
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
                                $('#username-status').text('Username is already taken').css('color', 'red');
                            } else {
                                $('#username-status').text('Available').css('color', 'green');
                            }
                            validateForm();
                        }
                    });
                } else {
                    $('#username-status').text('');
                    validateForm();
                }
            });
        });
    </script>
</head>
<body>
    <h1>Account Settings</h1>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- Profile Image Update -->
    <form action="settings.php" method="post" enctype="multipart/form-data">
        <label for="profile_image">Update Profile Image:</label>
        <input type="file" name="profile_image" id="profile_image">
        <button type="submit">Upload</button>
    </form>

    <!-- Username Update -->
    <form action="settings.php" method="post">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required>
        <span id="username-status"></span><br>

        <button type="submit" id="updatebutton">Update Username </button>
    </form>

    <!-- Password Update -->
    <form action="settings.php" method="post">
        <label for="current_password">Current Password:</label>
        <input type="password" name="current_password" id="current_password" required>

        <label for="new_password">New Password:</label>
        <input type="password" name="new_password" id="new_password" required>

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
        <small>Password must be at least 8 characters, contain a number and a special character.</small>

        <button type="submit">Update Password</button>
    </form>

    <p><a href="profile.php">Back to Profile</a></p>
</body>
</html>

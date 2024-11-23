<?php
session_start();
include 'db.php';  // Include database connection

// Check if admin is already logged in
if (isset($_SESSION['admin'])) {
    header('Location: admin_panel.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_username = $_POST['email_or_username'];
    $password = $_POST['password'];

    // Check if the input is an email or username
    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE email = ? OR username = ?");
    $stmt->bind_param('ss', $email_or_username, $email_or_username);
    $stmt->execute();
    $stmt->bind_result($admin_id, $hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Verify password
    if (password_verify($password, $hashed_password)) {
        // Set admin session and redirect to admin panel
        $_SESSION['admin'] = $admin_id;
        header('Location: admin_panel.php');
        exit();
    } else {
        $error = "Invalid email/username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Admin Login</h1>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="post" action="admin_login.php">
        <label for="email_or_username">Email or Username:</label>
        <input type="text" id="email_or_username" name="email_or_username" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <p><a href="admin_signup.php">Create an admin account</a></p>
</body>
</html>

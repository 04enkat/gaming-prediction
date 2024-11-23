<?php
session_start();

// Check if there's a success message in the session
if (!isset($_SESSION['message'])) {
    // If there's no message, redirect to the deposit page
    header("Location: deposit_usdt.php");
    exit();
}

// Get the message and then clear it from the session
$message = $_SESSION['message'];
unset($_SESSION['message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Success</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Deposit Successful</h1>
        
        <p><?php echo htmlspecialchars($message); ?></p>
        
        <h3>Next Steps:</h3>
        <ul>
            <li><strong>Upload Screenshot:</strong> Please navigate to your <a href="deposit_usdt.php">Deposit Page</a> and upload the payment screenshot to complete your deposit.</li>
            <li><strong>Confirmation:</strong> Once the screenshot is verified, your deposit will be confirmed, and the balance will be reflected in your wallet.</li>
        </ul>
        
        <a href="deposit_usdt.php" class="btn">Back to Deposit Page</a>
    </div>
</body>
</html>

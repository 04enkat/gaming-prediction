<?php
session_start();
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the user's wallet balance
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($wallet_balance);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Prediction Game</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="position-relative">
    <div class="container-fluid ">



        <div class="row">
            <!-- Display user's wallet balance -->
            <span class="text-end mt-3"><strong>Your Wallet Balance:</strong>
                $<?php echo number_format($wallet_balance, 2); ?>
            </span>
            <div class="col-md-7 col-12 align-content-center">
                <h1 class="display-1 fw-bold text-center lh-1">Welcome to The <span class="text-warning"> Prediction Game</span></h1>
                <p class="text-center mx-5 fs-5 lh-sm my-4">A prediction game website is an interactive platform where users can forecast outcomes of various events, such as sports matches, elections, or entertainment awards.</p>
                <div class="text-center mt-4">
                    <a href="#" class="manual_btn">Let's Play</a>
                </div>
            </div>
            <div class="col-md-5 col-12  ">
                <img src="images/money-ai.png" class="img-fluid" alt="money">
            </div>
        </div>

    </div>
    <div class="row justify-content-evenly pt-3 mb-5 pb-5">
        <!-- Amazon-style Product Sell Box -->
        <div class="col-md-5 product-box">
            <img src="assets/product_image.jpg" alt="Product Image" class="product-image">
            <div class="product-details">
                <h2 class="product-title">Premium Prediction Game Guide</h2>
                <p class="product-description">
                    Learn the best strategies for the prediction game! This guide covers tips and tricks on how to
                    maximize your chances of winning. Buy now and improve your skills.
                </p>
                <p class="product-price">$19.99</p>
                <button class="buy-now-btn">Buy Now</button>
            </div>
        </div>

        <!-- Add more products or information as needed -->
        <div class="col-md-5 product-box">
            <img src="assets/product_image2.jpg" alt="Another Product" class="product-image">
            <div class="product-details">
                <h2 class="product-title">Prediction Game Pro Tools</h2>
                <p class="product-description">
                    Access pro-level tools to analyze the game’s history and make more informed predictions. Perfect for
                    players looking to up their game.
                </p>
                <p class="product-price">$49.99</p>
                <button class="buy-now-btn">Buy Now</button>
            </div>
        </div>
    </div>
    </div>


    <!-- Footer Navigation (Include this in all your pages) -->
    <footer class="footer-nav">
        <a href="index.php" class="footer-btn">Home</a>
        <a href="game.php" class="footer-btn">Game</a>
        <a href="#"><span class="nav-start"></span></a>
        <a href="my_history.php" class="footer-btn">My History</a>
        <a href="profile.php" class="footer-btn">Profile</a>
    </footer>

    </div>
    <script src="assets/bootstrap.min.js"></script>
</body>

</html>
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
    <link rel="stylesheet" href="css/all_style.css">
    <link rel="stylesheet" href="css/fas-all.min.css">
</head>

<body>
    <div class="container-fluid ">



        <div class="row desktop-bg vh-100">
            <!-- Display user's wallet balance -->
            
                <span class="text-end text-white wallet-design mt-3"><img src="images/wallet-solid(1).svg" class="mx-2" alt="wallet" width="24">
                    $<?php echo number_format($wallet_balance, 2); ?>
                </span>
              
            <div class="col-md-7 col-12 align-content-center">
                <h1 class="display-1 fw-bold text-center lh-1 text-white">Welcome to The <span class="text-warning"> Prediction Game</span></h1>
                <p class="text-center mx-5 fs-5 lh-sm my-4 text-white">A prediction game website is an interactive platform where users can forecast outcomes of various events, such as sports matches, elections, or entertainment awards.</p>
                <div class="text-center mt-4">
                    <a href="#" class="manual_btn">Let's Play</a>
                </div>
            </div>
            <div class="col-md-5 col-12  ">
                <img src="images/money-ai.png" class="img-fluid man-img" alt="money" style="filter: drop-shadow(10px 10px 30px black);">
            </div>
        </div>

    </div>
    

    <div class="section-bg">
        <div class="row pt-1 justify-content-around ">

            <div class="col-md-4 col-12 ">
             <div class="card-manual">
                <div class="image text-center">
                 <img class="card-img-top" src="images/guide.svg" alt="guide" title="Prediction Guide" style="filter: drop-shadow(10px 10px 10px black);">
                </div>
                 <div class="card-body">
                     <h2 class="product-title">Premium Prediction Game Guide</h2>
                     <p class="product-description">
                         Learn the best strategies for the prediction game! This guide covers tips and tricks on how to
                         maximize your chances of winning. Buy now and improve your skills.
                     </p>
                     <p class="product-price text-warning text-center">$19.99</p>
                 </div>
                 <div class="card-body text-center">
                     <button class="manual_btn">Buy Now</button>
                 </div>
               </div>
            </div>
            <div class="col-md-4 col-12 ">
             <div class="card-manual">
                 <div class="image text-center">
                     <img class="card-img-top" src="images/tool1.svg" alt="Card image cap" style="filter: drop-shadow(10px 10px 10px black);">
                 </div>
                 <div class="details card-body">
                     <h2 class="product-title ">Prediction Game Pro Tools</h2>
                     <p class="product-description">
                         Access pro-level tools to analyze the game’s history and make more informed predictions. Perfect for
                         players looking to up their game.
                     </p>
                     <p class="product-price text-warning text-center">$49.99</p>
                 </div>
                 <div class="card-body text-center">
                     <button class="manual_btn">Buy Now</button>
                 </div>
               </div>
            </div>
            
         </div>
    </div>
  
    </div>
  
    


    <!-- Footer Navigation (Include this in all your pages) -->
    <footer class="footer-nav">
        <a href="index.php" class="footer-btn">Home</a>
        <a href="game.php" class="footer-btn">Game</a>
        <a href="#"><i class="fa-solid fa-"></i></a>
        <a href="my_history.php" class="footer-btn">My History</a>
        <a href="profile.php" class="footer-btn">Profile</a>
    </footer>

    </div>
    <script src="assets/bootstrap.min.js"></script>
</body>

</html>
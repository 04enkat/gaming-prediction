<?php 
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the latest USDT to INR conversion rate
$usdt_inr_rate = 82.5; // Default rate if not available in settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = 'usdt_inr_rate'");
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($usdt_inr_rate);
    $stmt->fetch();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deposit USDT</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Deposit USDT</h1>

    <p><strong>Scan to Pay:</strong></p>
    <div>
        <img src="IMG-20241022-WA0026.jpg" alt="Scan to Pay" style="width:200px; height:200px;">
        <p>Address: <span id="usdtAddress">0x16f99922D235Be18c162FB81bbcbEAA3F553E547</span> (Optimism)
            <button onclick="copyAddress()">Copy Address</button>
        </p>
    </div>

    <!-- Deposit Form -->
    <form id="depositForm" method="post" action="process_deposit.php" enctype="multipart/form-data">
        <label>Amount (INR):</label>
        <div>
            <!-- Pre-set INR Amount Buttons -->
            <button type="button" class="amount-btn" data-inr="200">₹200</button>
            <button type="button" class="amount-btn" data-inr="500">₹500</button>
            <button type="button" class="amount-btn" data-inr="1000">₹1,000</button>
            <button type="button" class="amount-btn" data-inr="3000">₹3,000</button>
            <button type="button" class="amount-btn" data-inr="5000">₹5,000</button>
        </div>
        <!-- Custom INR Amount Input -->
        <input type="number" id="amount" name="amount" min="200" placeholder="Enter custom amount in INR" required><br>

        <p><strong>Equivalent in USDT:</strong> <span id="usdtValue">0</span> USDT</p>
        <p>Conversion rate: 1 USDT = ₹<?php echo $usdt_inr_rate; ?></p>

        <label>Upload Payment Screenshot:</label>
        <input type="file" name="screenshot" accept="image/*" required><br>

        <button type="submit">Submit Deposit Request</button>
    </form>
    <p><a href="profile.php">Back to Profile</a></p>

    <p id="orderStatus" style="display: none; color: green;">✔️ Deposit request submitted successfully!</p>

    <script>
        $(document).ready(function() {
            const conversionRate = <?php echo $usdt_inr_rate; ?>;

            // Handle click on pre-set INR amount buttons
            $('.amount-btn').on('click', function() {
                const amount = $(this).data('inr');
                $('#amount').val(amount);
                updateUSDTValue(amount);
            });

            // Update USDT equivalent on manual amount input
            $('#amount').on('input', function() {
                const amount = $(this).val();
                updateUSDTValue(amount);
            });

            // Function to update USDT equivalent based on amount in INR
            function updateUSDTValue(amount) {
                if (amount >= 200) {
                    const usdt = (amount / conversionRate).toFixed(2);
                    $('#usdtValue').text(usdt);
                } else {
                    $('#usdtValue').text('0');
                }
            }

            // Handle form submission via AJAX
            $('#depositForm').on('submit', function(event) {
                event.preventDefault();

                const formData = new FormData(this);
                $.ajax({
                    url: 'process_deposit.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        $('#orderStatus').show();
                        $('#depositForm')[0].reset();
                        $('#usdtValue').text('0');
                    },
                    error: function() {
                        alert('Error submitting deposit request. Please try again.');
                    }
                });
            });
        });

        // Copy wallet address to clipboard
        function copyAddress() {
            const address = document.getElementById("usdtAddress").textContent;
            navigator.clipboard.writeText(address).then(() => {
                alert("Address copied to clipboard!");
            }).catch(err => {
                console.error("Failed to copy address: ", err);
            });
        }
    </script>
</body>
</html>

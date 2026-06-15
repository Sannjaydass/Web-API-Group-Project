<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_type = $_POST['booking_type'];
    $amount = $_POST['amount'];
    $trip_id = $_POST['trip_id'];
    
    // Simulate payment processing (Stripe/PayPal integration would go here)
    $payment_status = 'confirmed';
    $transaction_id = 'TXN_' . uniqid();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO bookings (user_id, trip_id, booking_type, booking_reference, amount, status) 
              VALUES (:user_id, :trip_id, :booking_type, :reference, :amount, :status)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':trip_id' => $trip_id,
        ':booking_type' => $booking_type,
        ':reference' => $transaction_id,
        ':amount' => $amount,
        ':status' => $payment_status
    ]);
    
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment - TravelAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-globe-americas"></i>
                <span>Travel<span class="highlight">AI</span></span>
            </div>
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="payment-container">
        <div class="container">
            <div class="payment-wrapper">
                <div class="payment-header">
                    <i class="fas fa-lock"></i>
                    <h2>Secure Checkout</h2>
                    <p>Your payment is encrypted and secure</p>
                </div>

                <?php if(isset($success)): ?>
                <div class="payment-success">
                    <i class="fas fa-check-circle"></i>
                    <h3>Payment Successful!</h3>
                    <p>Your booking has been confirmed. Reference: <?php echo $transaction_id; ?></p>
                    <a href="index.php" class="btn-primary">Return Home</a>
                </div>
                <?php else: ?>
                <div class="payment-grid">
                    <div class="payment-form">
                        <form method="POST" action="" id="paymentForm">
                            <input type="hidden" name="booking_type" value="<?php echo htmlspecialchars($_GET['type'] ?? 'flight'); ?>">
                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($_GET['amount'] ?? 0); ?>">
                            <input type="hidden" name="trip_id" value="<?php echo htmlspecialchars($_GET['trip_id'] ?? 0); ?>">
                            
                            <div class="form-group">
                                <label>Card Number</label>
                                <div class="card-input">
                                    <i class="fas fa-credit-card"></i>
                                    <input type="text" placeholder="1234 5678 9012 3456" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Expiry Date</label>
                                    <input type="text" placeholder="MM/YY" required>
                                </div>
                                <div class="form-group">
                                    <label>CVV</label>
                                    <input type="text" placeholder="123" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Cardholder Name</label>
                                <input type="text" placeholder="John Doe" required>
                            </div>
                            
                            <div class="payment-summary">
                                <h4>Payment Summary</h4>
                                <div class="summary-item">
                                    <span>Booking Type:</span>
                                    <strong><?php echo ucfirst(htmlspecialchars($_GET['type'] ?? 'Flight')); ?></strong>
                                </div>
                                <div class="summary-item total">
                                    <span>Total Amount:</span>
                                    <strong>$<?php echo htmlspecialchars($_GET['amount'] ?? '0'); ?></strong>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-pay">
                                <i class="fas fa-shield-alt"></i> Pay Now
                            </button>
                        </form>
                    </div>
                    
                    <div class="payment-security">
                        <h3><i class="fas fa-shield-alt"></i> Secure Payment</h3>
                        <ul>
                            <li><i class="fas fa-check"></i> SSL Encrypted Connection</li>
                            <li><i class="fas fa-check"></i> PCI DSS Compliant</li>
                            <li><i class="fas fa-check"></i> 24/7 Fraud Monitoring</li>
                            <li><i class="fas fa-check"></i> Money-back Guarantee</li>
                        </ul>
                        <div class="payment-methods">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <i class="fab fa-cc-paypal"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
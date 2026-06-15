<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get user info
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's trips
$query = "SELECT * FROM trips WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's bookings
$query = "SELECT * FROM bookings WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    
    $query = "UPDATE users SET full_name = :full_name, email = :email WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':full_name' => $full_name, ':email' => $email, ':id' => $user_id]);
    
    $_SESSION['full_name'] = $full_name;
    header("Location: profile.php?updated=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TravelAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        <div class="container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></h1>
                <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>

            <?php if(isset($_GET['updated'])): ?>
            <div class="success-message">Profile updated successfully!</div>
            <?php endif; ?>

            <div class="profile-grid">
                <div class="profile-section">
                    <h2><i class="fas fa-user-edit"></i> Personal Information</h2>
                    <form method="POST" action="" class="profile-form">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>

                <div class="profile-section">
                    <h2><i class="fas fa-plane"></i> My Trips</h2>
                    <?php if(empty($trips)): ?>
                    <p class="empty-state">No trips planned yet. <a href="index.php">Plan your first trip →</a></p>
                    <?php else: ?>
                    <div class="trips-list">
                        <?php foreach($trips as $trip): ?>
                        <div class="trip-item">
                            <div class="trip-info">
                                <h3><?php echo htmlspecialchars($trip['destination']); ?></h3>
                                <p><i class="fas fa-calendar"></i> <?php echo $trip['trip_duration']; ?> days</p>
                                <p><i class="fas fa-tag"></i> <?php echo ucfirst($trip['budget_tier']); ?></p>
                            </div>
                            <a href="itinerary.php?trip_id=<?php echo $trip['id']; ?>" class="btn-view">View Itinerary</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="profile-section">
                    <h2><i class="fas fa-ticket-alt"></i> My Bookings</h2>
                    <?php if(empty($bookings)): ?>
                    <p class="empty-state">No bookings yet. Start planning a trip!</p>
                    <?php else: ?>
                    <div class="bookings-list">
                        <?php foreach($bookings as $booking): ?>
                        <div class="booking-item">
                            <span class="booking-type"><?php echo ucfirst($booking['booking_type']); ?></span>
                            <span class="booking-ref">Ref: <?php echo $booking['booking_reference']; ?></span>
                            <span class="booking-amount">$<?php echo $booking['amount']; ?></span>
                            <span class="booking-status <?php echo $booking['status']; ?>"><?php echo $booking['status']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
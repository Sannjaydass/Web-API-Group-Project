<?php
require_once 'config/database.php';
require_once 'jwt_helper.php';

// JWT Session Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['jwt_token'])) {
    $payload = verifyJWT($_SESSION['jwt_token']);
    if (!$payload) {
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
    $_SESSION['expiry_time'] = $payload['exp'];
}

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Traveler';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$remainingSeconds = isset($_SESSION['expiry_time']) ? $_SESSION['expiry_time'] - time() : 900;
if ($remainingSeconds <= 0) {
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit();
}

// Update Profile
$profileMessage = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    $update = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
    $stmt = $pdo->prepare($update);
    if ($stmt->execute([$full_name, $email, $user_id])) {
        $_SESSION['full_name'] = $full_name;
        $profileMessage = '<div class="success-msg">✅ Profile updated successfully!</div>';
        echo '<script>setTimeout(() => window.location.href = "mytrips.php?updated=1", 1000);</script>';
    } else {
        $profileMessage = '<div class="error-msg">❌ Update failed!</div>';
    }
}

// Update Password
$passwordMessage = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password && strlen($new_password) >= 4) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $pdo->prepare($update);
            if ($stmt->execute([$hashed_password, $user_id])) {
                $passwordMessage = '<div class="success-msg">✅ Password updated successfully!</div>';
                echo '<script>setTimeout(() => window.location.href = "mytrips.php?updated=1", 1000);</script>';
            } else {
                $passwordMessage = '<div class="error-msg">❌ Update failed!</div>';
            }
        } else {
            $passwordMessage = '<div class="error-msg">❌ Passwords do not match!</div>';
        }
    } else {
        $passwordMessage = '<div class="error-msg">❌ Current password is incorrect!</div>';
    }
}

// UPDATE TRIP
$tripMessage = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_trip'])) {
    $trip_id = $_POST['trip_id'];
    
    $flight_booked = $_POST['flight_booked'] ?? 'no';
    if ($flight_booked == 'no') {
        $flight_number = null;
        $flight_airline = null;
        $flight_departure = null;
        $flight_arrival = null;
    } else {
        $flight_number = !empty($_POST['flight_number']) ? $_POST['flight_number'] : null;
        $flight_airline = !empty($_POST['flight_airline']) ? $_POST['flight_airline'] : null;
        $flight_departure = !empty($_POST['flight_departure']) ? date('Y-m-d H:i:s', strtotime($_POST['flight_departure'])) : null;
        $flight_arrival = !empty($_POST['flight_arrival']) ? date('Y-m-d H:i:s', strtotime($_POST['flight_arrival'])) : null;
    }
    
    $hotel_booked = $_POST['hotel_booked'] ?? 'no';
    if ($hotel_booked == 'no') {
        $hotel_name = null;
        $hotel_check_in = null;
        $hotel_check_out = null;
        $hotel_check_in_time = null;
        $hotel_room_type = null;
    } else {
        $hotel_name = !empty($_POST['hotel_name']) ? $_POST['hotel_name'] : null;
        $hotel_check_in = !empty($_POST['hotel_check_in']) ? $_POST['hotel_check_in'] : null;
        $hotel_check_out = !empty($_POST['hotel_check_out']) ? $_POST['hotel_check_out'] : null;
        $hotel_check_in_time = !empty($_POST['hotel_check_in_time']) ? $_POST['hotel_check_in_time'] : '14:00';
        $hotel_room_type = !empty($_POST['hotel_room_type']) ? $_POST['hotel_room_type'] : null;
    }
    $special_requests = !empty($_POST['special_requests']) ? $_POST['special_requests'] : null;
    
    $query = "UPDATE trips SET 
        flight_booked = ?, flight_number = ?, flight_airline = ?, 
        flight_departure_time = ?, flight_arrival_time = ?,
        hotel_booked = ?, hotel_name = ?, hotel_check_in = ?, 
        hotel_check_out = ?, hotel_check_in_time = ?, 
        hotel_room_type = ?, special_requests = ?
        WHERE id = ? AND user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $flight_booked, $flight_number, $flight_airline, 
        $flight_departure, $flight_arrival,
        $hotel_booked, $hotel_name, $hotel_check_in, 
        $hotel_check_out, $hotel_check_in_time, 
        $hotel_room_type, $special_requests,
        $trip_id, $user_id
    ]);
    
    if ($result) {
        $tripMessage = '<div class="success-msg">✈️ Trip updated successfully!</div>';
        echo '<script>setTimeout(() => { window.location.href = "mytrips.php?updated=1"; }, 1500);</script>';
    } else {
        $tripMessage = '<div class="error-msg">❌ Update failed!</div>';
    }
}

// Delete Trip
if (isset($_GET['delete_trip'])) {
    $trip_id = $_GET['delete_trip'];
    $query = "DELETE FROM trips WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$trip_id, $user_id]);
    header("Location: mytrips.php?deleted=1");
    exit();
}

// Get user info
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all trips
$query = "SELECT * FROM trips WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$totalTrips = count($trips);
$totalBudget = array_sum(array_column($trips, 'total_cost'));
$budgetCount = count(array_filter($trips, fn($t) => $t['budget_tier'] == 'budget'));
$moderateCount = count(array_filter($trips, fn($t) => $t['budget_tier'] == 'moderate'));
$luxuryCount = count(array_filter($trips, fn($t) => $t['budget_tier'] == 'luxury'));

$flightOptions = [
    ['name' => 'AirAsia', 'code' => 'AK', 'url' => 'https://www.airasia.com/en/gb/book-a-flight/flight'],
    ['name' => 'Malaysia Airlines', 'code' => 'MH', 'url' => 'https://www.malaysiaairlines.com/my/en/book.html'],
    ['name' => 'Batik Air', 'code' => 'OD', 'url' => 'https://booking.batikair.com/'],
    ['name' => 'Singapore Airlines', 'code' => 'SQ', 'url' => 'https://www.singaporeair.com/en_UK/us/home']
];

$hotelOptions = [
    ['name' => 'Agoda', 'url' => 'https://www.agoda.com/search'],
    ['name' => 'Booking.com', 'url' => 'https://www.booking.com/index.html'],
    ['name' => 'Trip.com', 'url' => 'https://www.trip.com/hotels/']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Trips - TravelAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; overflow-x: hidden; }

        .timer-display {
            position: fixed; top: 80px; right: 20px; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 50px;
            font-size: 0.85rem; font-weight: 600; z-index: 1001;
            border: 1px solid rgba(255,255,255,0.2); font-family: monospace;
        }
        .timer-display i { margin-right: 6px; color: #10b981; }

        .bg-gradient {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(102,126,234,0.15) 0%, transparent 50%);
            pointer-events: none; z-index: 0;
        }

        .navbar {
            position: fixed; top: 0; left: 0; right: 0; background: rgba(10,10,10,0.95);
            backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 1000; padding: 1rem 5%;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo i { background: none; color: #667eea; margin-right: 8px; }
        .nav-links a { color: #fff; text-decoration: none; margin-left: 2rem; transition: color 0.3s; }
        .nav-links a:hover { color: #667eea; }

        .container { max-width: 1400px; margin: 0 auto; padding: 100px 5% 4rem; position: relative; z-index: 10; }

        .update-toast {
            position: fixed; top: 100px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; padding: 1rem 2rem; border-radius: 50px;
            z-index: 2000; animation: slideDown 0.5s ease, fadeOut 3s ease 2s forwards;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -50px); } to { opacity: 1; transform: translate(-50%, 0); } }
        @keyframes fadeOut { to { opacity: 0; visibility: hidden; } }

        .profile-section {
            background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1));
            border-radius: 30px; padding: 2rem; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-header { display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem; }
        .profile-avatar { width: 100px; height: 100px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .profile-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
        .stat-card { text-align: center; padding: 0.5rem 1rem; background: rgba(255,255,255,0.05); border-radius: 15px; }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 0.7rem; color: #a0a0a0; }

        .form-card {
            background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 20px;
            padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.1);
        }
        .form-card h3 { color: #ffd700; margin-bottom: 1rem; font-size: 1.2rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-group { flex: 1; margin-bottom: 1rem; }
        .form-group label { display: block; color: rgba(255,255,255,0.8); margin-bottom: 0.5rem; font-size: 0.8rem; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 0.8rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; color: white; font-size: 0.9rem;
        }
        
        /* FIXED: Select dropdown styling */
        .form-group select {
            width: 100%; padding: 0.8rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 12px;
            color: white; font-size: 0.9rem; cursor: pointer;
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
            background-repeat: no-repeat; background-position: right 1rem center;
        }
        .form-group select option {
            background: #1a1a2e; color: white; padding: 0.5rem;
        }
        .form-group select:focus { outline: none; border-color: #ffd700; }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2); border: none;
            padding: 0.8rem 1.5rem; border-radius: 12px; color: white; font-weight: 600;
            cursor: pointer; transition: transform 0.3s;
        }
        .btn-submit:hover { transform: translateY(-2px); }
        .success-msg { background: rgba(16,185,129,0.2); border: 1px solid #10b981; border-radius: 10px; padding: 0.8rem; margin-bottom: 1rem; color: #10b981; }
        .error-msg { background: rgba(239,68,68,0.2); border: 1px solid #ef4444; border-radius: 10px; padding: 0.8rem; margin-bottom: 1rem; color: #ef4444; }

        .budget-stats { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .budget-stat { flex: 1; background: rgba(255,255,255,0.05); border-radius: 20px; padding: 1rem; text-align: center; }
        .budget-stat.budget { border-bottom: 3px solid #10b981; }
        .budget-stat.moderate { border-bottom: 3px solid #f59e0b; }
        .budget-stat.luxury { border-bottom: 3px solid #ef4444; }
        .budget-stat .count { font-size: 1.5rem; font-weight: 700; }

        .trips-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .trip-card {
            background: rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s;
        }
        .trip-card:hover { transform: translateY(-5px); border-color: #667eea; }
        .trip-header { padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .trip-destination { font-size: 1rem; font-weight: 600; }
        .budget-badge { padding: 0.2rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .budget-badge.budget { background: #10b981; }
        .budget-badge.moderate { background: #f59e0b; color: #1a1a2e; }
        .budget-badge.luxury { background: #ef4444; }
        .trip-body { padding: 1rem; }
        .trip-dates { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 0.8rem; font-size: 0.75rem; color: #a0a0a0; }
        .flight-info, .hotel-info { background: rgba(0,0,0,0.2); border-radius: 10px; padding: 0.5rem; margin-bottom: 0.5rem; font-size: 0.75rem; }
        .trip-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
        .trip-price { font-size: 1.1rem; font-weight: 700; color: #10b981; }
        .trip-actions { display: flex; gap: 0.5rem; }
        .btn-edit, .btn-delete { padding: 0.3rem 0.8rem; border-radius: 8px; text-decoration: none; font-size: 0.75rem; transition: all 0.3s; }
        .btn-edit { background: rgba(102,126,234,0.2); border: 1px solid #667eea; color: white; }
        .btn-edit:hover { background: #667eea; }
        .btn-delete { background: rgba(239,68,68,0.2); border: 1px solid #ef4444; color: #ef4444; }
        .btn-delete:hover { background: #ef4444; color: white; }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95); z-index: 2000; align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a2e; border-radius: 20px; padding: 2rem;
            max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .modal-content h2 { color: #ffd700; margin-bottom: 1rem; }
        .close-modal { float: right; font-size: 1.5rem; cursor: pointer; color: #a0a0a0; }
        .close-modal:hover { color: white; }
        
        .flight-suggestions, .hotel-suggestions {
            display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1rem;
        }
        .suggestion-card {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,215,0,0.3);
            border-radius: 12px; padding: 0.8rem; cursor: pointer; transition: all 0.3s;
            flex: 1; text-align: center; min-width: 120px;
        }
        .suggestion-card:hover { background: rgba(255,215,0,0.15); transform: translateY(-2px); }
        .suggestion-card i { font-size: 1.2rem; margin-right: 0.5rem; color: #ffd700; }
        
        .trip-details-info {
            background: rgba(0,0,0,0.3); border-radius: 12px; padding: 0.8rem;
            margin-bottom: 1rem; font-size: 0.85rem;
        }
        
        .empty-state { text-align: center; padding: 3rem; background: rgba(255,255,255,0.05); border-radius: 30px; }
        
        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-stats { justify-content: center; }
            .trips-grid { grid-template-columns: 1fr; }
            .timer-display { top: 70px; right: 10px; font-size: 0.7rem; }
            .flight-suggestions, .hotel-suggestions { flex-direction: column; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="bg-gradient"></div>

<?php if(isset($_GET['updated'])): ?>
<div class="update-toast">
    <i class="fas fa-check-circle"></i> 🎉 Your trip has been updated successfully!
</div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
<div class="update-toast">
    <i class="fas fa-trash-alt"></i> 🗑️ Trip has been deleted successfully!
</div>
<?php endif; ?>

<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><i class="fas fa-globe-americas"></i> Travel<span>AI</span></div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="mytrips.php">My Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="timer-display" id="timerDisplay">
    <i class="fas fa-hourglass-half"></i>
    <span id="timerMinutes">15</span>:<span id="timerSeconds">00</span>
</div>

<div class="container">
    <!-- Profile Section -->
    <div class="profile-section">
        <div class="profile-header">
            <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>
            <div>
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p><i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="profile-stats">
                <div class="stat-card"><div class="stat-number"><?php echo $totalTrips; ?></div><div class="stat-label">Trips</div></div>
                <div class="stat-card"><div class="stat-number">RM <?php echo number_format($totalBudget, 0); ?></div><div class="stat-label">Spent</div></div>
            </div>
        </div>

        <form method="POST" class="form-card">
            <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
            <?php echo $profileMessage; ?>
            <div class="form-row">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            </div>
            <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
        </form>

        <form method="POST" class="form-card">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <?php echo $passwordMessage; ?>
            <div class="form-row">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            </div>
            <button type="submit" name="update_password" class="btn-submit">Change Password</button>
        </form>
    </div>

    <!-- Budget Stats -->
    <div class="budget-stats">
        <div class="budget-stat budget"><div class="count"><?php echo $budgetCount; ?></div><div class="label">Budget Trips</div></div>
        <div class="budget-stat moderate"><div class="count"><?php echo $moderateCount; ?></div><div class="label">Moderate Trips</div></div>
        <div class="budget-stat luxury"><div class="count"><?php echo $luxuryCount; ?></div><div class="label">Luxury Trips</div></div>
    </div>

    <!-- My Trips -->
    <h2 style="margin-bottom: 1rem;"><i class="fas fa-suitcase"></i> My Travel Trips</h2>
    
    <?php if(empty($trips)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt" style="font-size: 3rem;"></i>
            <h3>No Trips Yet</h3>
            <p>Start planning your first adventure!</p>
            <a href="index.php" class="btn-submit" style="display: inline-block; margin-top: 1rem;">Plan a Trip →</a>
        </div>
    <?php else: ?>
        <div class="trips-grid">
            <?php foreach($trips as $trip): ?>
            <div class="trip-card">
                <div class="trip-header">
                    <div class="trip-destination"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($trip['destination']); ?></div>
                    <span class="budget-badge <?php echo $trip['budget_tier']; ?>"><?php echo ucfirst($trip['budget_tier']); ?></span>
                </div>
                <div class="trip-body">
                    <div class="trip-dates">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($trip['start_date'])); ?></span>
                        <span>→ <?php echo $trip['trip_duration']; ?> days →</span>
                        <span><i class="fas fa-calendar-check"></i> <?php echo date('d M Y', strtotime($trip['end_date'])); ?></span>
                    </div>
                    <div class="flight-info">
                        <strong><i class="fas fa-plane"></i> Flight:</strong> 
                        <?php echo $trip['flight_booked'] == 'yes' ? ($trip['flight_airline'] ?? 'Booked') : 'Not booked'; ?>
                    </div>
                    <div class="hotel-info">
                        <strong><i class="fas fa-hotel"></i> Hotel:</strong> 
                        <?php echo $trip['hotel_booked'] == 'yes' ? ($trip['hotel_name'] ?? 'Booked') : 'Not booked'; ?>
                    </div>
                </div>
                <div class="trip-footer">
                    <div class="trip-price">RM <?php echo number_format($trip['total_cost'], 0); ?></div>
                    <div class="trip-actions">
                        <a href="javascript:void(0)" onclick="openEditModal(<?php echo $trip['id']; ?>)" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                        <a href="?delete_trip=<?php echo $trip['id']; ?>" class="btn-delete" onclick="return confirm('⚠️ Delete this trip? This action cannot be undone.')"><i class="fas fa-trash"></i> Delete</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Trip Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Trip</h2>
        <div id="modalMessage"><?php echo $tripMessage; ?></div>
        
        <form method="POST" action="" id="editTripForm">
            <input type="hidden" name="trip_id" id="edit_trip_id">
            
            <!-- Trip Info Display -->
            <div class="trip-details-info" id="tripInfoDisplay">
                <p><i class="fas fa-map-marker-alt"></i> <strong>Destination:</strong> <span id="info_destination"></span></p>
                <p><i class="fas fa-calendar"></i> <strong>Travel Dates:</strong> <span id="info_dates"></span></p>
                <p><i class="fas fa-tag"></i> <strong>Budget Tier:</strong> <span id="info_budget"></span></p>
            </div>
            
            <!-- Flight Section -->
            <div class="form-card">
                <h3><i class="fas fa-plane"></i> ✈️ Flight</h3>
                
                <div class="flight-suggestions">
                    <?php foreach($flightOptions as $flight): ?>
                    <div class="suggestion-card" onclick="selectFlight('<?php echo $flight['name']; ?>', '<?php echo $flight['code']; ?>', '<?php echo $flight['url']; ?>')">
                        <i class="fas fa-plane"></i>
                        <strong><?php echo $flight['name']; ?></strong><br>
                        <small>Click to Book →</small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <label style="color: #ffd700;">📌 Have you booked your flight?</label>
                    <select name="flight_booked" id="edit_flight_booked" onchange="toggleFlightFields()">
                        <option value="no">❌ No, not yet</option>
                        <option value="yes">✅ Yes, I have booked</option>
                    </select>
                </div>
                
                <div id="edit_flight_fields" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Airline</label>
                            <input type="text" name="flight_airline" id="edit_flight_airline" placeholder="e.g., AirAsia">
                        </div>
                        <div class="form-group">
                            <label>Flight Number</label>
                            <input type="text" name="flight_number" id="edit_flight_number" placeholder="e.g., AK 712">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Departure Date & Time</label>
                            <input type="datetime-local" name="flight_departure" id="edit_flight_departure">
                        </div>
                        <div class="form-group">
                            <label>Arrival Date & Time</label>
                            <input type="datetime-local" name="flight_arrival" id="edit_flight_arrival">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hotel Section -->
            <div class="form-card">
                <h3><i class="fas fa-hotel"></i> 🏨 Hotel</h3>
                
                <div class="hotel-suggestions">
                    <?php foreach($hotelOptions as $hotel): ?>
                    <div class="suggestion-card" onclick="window.open('<?php echo $hotel['url']; ?>', '_blank')">
                        <i class="fas fa-hotel"></i>
                        <strong><?php echo $hotel['name']; ?></strong><br>
                        <small>Book Now →</small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <label style="color: #ffd700;">📌 Have you booked your hotel?</label>
                    <select name="hotel_booked" id="edit_hotel_booked" onchange="toggleHotelFields()">
                        <option value="no">❌ No, not yet</option>
                        <option value="yes">✅ Yes, I have booked</option>
                    </select>
                </div>
                
                <div id="edit_hotel_fields" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div class="form-group">
                        <label>Hotel Name</label>
                        <input type="text" name="hotel_name" id="edit_hotel_name" placeholder="Hotel name">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Check-in Date</label>
                            <input type="date" name="hotel_check_in" id="edit_hotel_check_in">
                        </div>
                        <div class="form-group">
                            <label>Check-out Date</label>
                            <input type="date" name="hotel_check_out" id="edit_hotel_check_out">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Check-in Time</label>
                            <input type="text" name="hotel_check_in_time" id="edit_hotel_check_in_time" placeholder="14:00" value="14:00">
                        </div>
                        <div class="form-group">
                            <label>Room Type</label>
                            <input type="text" name="hotel_room_type" id="edit_hotel_room_type" placeholder="Deluxe King">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Special Requests -->
            <div class="form-card">
                <h3><i class="fas fa-comment-dots"></i> Special Requests</h3>
                <textarea name="special_requests" id="edit_special_requests" rows="3" placeholder="Any special requests?" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:12px; color:white; font-size:0.9rem;"></textarea>
            </div>

            <button type="submit" name="update_trip" class="btn-submit" style="width: 100%; margin-top: 0.5rem;">💾 Save Changes</button>
        </form>
    </div>
</div>

<script>
    // Timer
    let timeLeft = <?php echo $remainingSeconds; ?>;
    const minutesSpan = document.getElementById('timerMinutes');
    const secondsSpan = document.getElementById('timerSeconds');
    setInterval(() => {
        timeLeft--;
        if (timeLeft <= 0) window.location.href = 'logout.php?expired=1';
        minutesSpan.textContent = Math.floor(timeLeft / 60).toString().padStart(2, '0');
        secondsSpan.textContent = (timeLeft % 60).toString().padStart(2, '0');
    }, 1000);

    function selectFlight(airline, code, bookingUrl) {
        const flightNumber = code + ' ' + Math.floor(Math.random() * 900 + 100);
        document.getElementById('edit_flight_airline').value = airline;
        document.getElementById('edit_flight_number').value = flightNumber;
        document.getElementById('edit_flight_booked').value = 'yes';
        document.getElementById('edit_flight_fields').style.display = 'block';
        
        const now = new Date();
        const departure = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
        const arrival = new Date(departure.getTime() + 3 * 60 * 60 * 1000);
        document.getElementById('edit_flight_departure').value = departure.toISOString().slice(0, 16);
        document.getElementById('edit_flight_arrival').value = arrival.toISOString().slice(0, 16);
        
        if(confirm(`✈️ ${airline} flight ${flightNumber} added!\n\nDo you want to book this flight now?`)) {
            window.open(bookingUrl, '_blank');
        }
    }

    function openEditModal(tripId) {
        fetch(`get_trip_data.php?id=${tripId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_trip_id').value = data.id;
                document.getElementById('info_destination').innerText = data.destination || '';
                document.getElementById('info_dates').innerText = `${data.start_date} to ${data.end_date} (${data.trip_duration} days)`;
                document.getElementById('info_budget').innerHTML = `<span class="budget-badge ${data.budget_tier}" style="display: inline-block; padding: 0.2rem 0.5rem;">${data.budget_tier.toUpperCase()}</span>`;
                
                document.getElementById('edit_flight_booked').value = data.flight_booked || 'no';
                document.getElementById('edit_flight_airline').value = data.flight_airline || '';
                document.getElementById('edit_flight_number').value = data.flight_number || '';
                document.getElementById('edit_flight_departure').value = data.flight_departure_time || '';
                document.getElementById('edit_flight_arrival').value = data.flight_arrival_time || '';
                
                document.getElementById('edit_hotel_booked').value = data.hotel_booked || 'no';
                document.getElementById('edit_hotel_name').value = data.hotel_name || '';
                document.getElementById('edit_hotel_check_in').value = data.hotel_check_in || '';
                document.getElementById('edit_hotel_check_out').value = data.hotel_check_out || '';
                document.getElementById('edit_hotel_check_in_time').value = data.hotel_check_in_time || '14:00';
                document.getElementById('edit_hotel_room_type').value = data.hotel_room_type || '';
                document.getElementById('edit_special_requests').value = data.special_requests || '';
                
                toggleFlightFields();
                toggleHotelFields();
                document.getElementById('editModal').classList.add('active');
            });
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function toggleFlightFields() {
        const val = document.getElementById('edit_flight_booked').value;
        const fieldsDiv = document.getElementById('edit_flight_fields');
        if (val === 'yes') {
            fieldsDiv.style.display = 'block';
        } else {
            fieldsDiv.style.display = 'none';
            document.getElementById('edit_flight_airline').value = '';
            document.getElementById('edit_flight_number').value = '';
            document.getElementById('edit_flight_departure').value = '';
            document.getElementById('edit_flight_arrival').value = '';
        }
    }

    function toggleHotelFields() {
        const val = document.getElementById('edit_hotel_booked').value;
        const fieldsDiv = document.getElementById('edit_hotel_fields');
        if (val === 'yes') {
            fieldsDiv.style.display = 'block';
        } else {
            fieldsDiv.style.display = 'none';
            document.getElementById('edit_hotel_name').value = '';
            document.getElementById('edit_hotel_check_in').value = '';
            document.getElementById('edit_hotel_check_out').value = '';
            document.getElementById('edit_hotel_room_type').value = '';
        }
    }

    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) closeModal();
    }
</script>
</body>
</html>
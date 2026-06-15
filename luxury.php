<?php
require_once 'config/database.php';
require_once 'jwt_helper.php';

// JWT Session Check - 15 minutes expiry
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
$username = $_SESSION['username'];

$remainingSeconds = isset($_SESSION['expiry_time']) ? $_SESSION['expiry_time'] - time() : 900;
if ($remainingSeconds <= 0) {
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit();
}

// Get selected place and country from URL
$selectedCountry = $_GET['country'] ?? '';
$selectedPlace = $_GET['place'] ?? '';
if (empty($selectedCountry) || empty($selectedPlace)) {
    header("Location: index.php?error=no_place_selected");
    exit();
}

// Coordinates for the place
$placeCoordinates = [
    'Kuala Lumpur' => ['lat' => 3.1390, 'lng' => 101.6869],
    'Penang' => ['lat' => 5.4141, 'lng' => 100.3288],
    'Langkawi' => ['lat' => 6.3500, 'lng' => 99.8000],
    'Bangkok' => ['lat' => 13.7367, 'lng' => 100.5231],
    'Singapore' => ['lat' => 1.3521, 'lng' => 103.8198],
    'Bali' => ['lat' => -8.4095, 'lng' => 115.1889],
    'Tokyo' => ['lat' => 35.6762, 'lng' => 139.6503],
    'Paris' => ['lat' => 48.8566, 'lng' => 2.3522],
    'London' => ['lat' => 51.5074, 'lng' => -0.1278],
    'Sydney' => ['lat' => -33.8688, 'lng' => 151.2093],
    'Dubai' => ['lat' => 25.2048, 'lng' => 55.2708],
    'Rome' => ['lat' => 41.9028, 'lng' => 12.4964]
];

$lat = $placeCoordinates[$selectedPlace]['lat'] ?? 3.1390;
$lng = $placeCoordinates[$selectedPlace]['lng'] ?? 101.6869;

// LUXURY budget tier
$budgetTier = 'luxury';
$dailyBudgetMin = 1380;
$dailyBudgetMax = 3000;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_trip'])) {
    $destination = $selectedPlace . ', ' . $selectedCountry;
    $trip_duration = (int)$_POST['duration'];
    $start_date = $_POST['start_date'];
    $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $trip_duration . ' days'));
    $budget_amount = $trip_duration * $dailyBudgetMax;
    $total_cost = $budget_amount;

    $flight_booked = isset($_POST['flight_booked']) ? $_POST['flight_booked'] : 'no';
    $flight_number = !empty($_POST['flight_number']) ? $_POST['flight_number'] : null;
    $flight_airline = !empty($_POST['flight_airline']) ? $_POST['flight_airline'] : null;
    $flight_departure = !empty($_POST['flight_departure']) ? date('Y-m-d H:i:s', strtotime($_POST['flight_departure'])) : null;
    $flight_arrival = !empty($_POST['flight_arrival']) ? date('Y-m-d H:i:s', strtotime($_POST['flight_arrival'])) : null;

    $hotel_booked = isset($_POST['hotel_booked']) ? $_POST['hotel_booked'] : 'no';
    $hotel_name = !empty($_POST['hotel_name']) ? $_POST['hotel_name'] : null;
    $hotel_check_in = !empty($_POST['hotel_check_in']) ? $_POST['hotel_check_in'] : null;
    $hotel_check_out = !empty($_POST['hotel_check_out']) ? $_POST['hotel_check_out'] : null;
    $hotel_check_in_time = !empty($_POST['hotel_check_in_time']) ? $_POST['hotel_check_in_time'] : '14:00';
    $hotel_room_type = !empty($_POST['hotel_room_type']) ? $_POST['hotel_room_type'] : null;
    $special_requests = !empty($_POST['special_requests']) ? $_POST['special_requests'] : null;

    $query = "INSERT INTO trips (
        user_id, username, budget_tier, destination, destination_lat, destination_lng,
        trip_duration, budget_amount, start_date, end_date, total_cost,
        flight_booked, flight_number, flight_airline, flight_departure_time, flight_arrival_time,
        hotel_booked, hotel_name, hotel_check_in, hotel_check_out, hotel_check_in_time, hotel_room_type, special_requests
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?
    )";

    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $_SESSION['user_id'], $username, $budgetTier, $destination, $lat, $lng,
        $trip_duration, $budget_amount, $start_date, $end_date, $total_cost,
        $flight_booked, $flight_number, $flight_airline, $flight_departure, $flight_arrival,
        $hotel_booked, $hotel_name, $hotel_check_in, $hotel_check_out, $hotel_check_in_time, $hotel_room_type, $special_requests
    ]);

    if ($result) {
        $_SESSION['trip_saved'] = true;
        $_SESSION['trip_destination'] = $destination;
        header("Location: index.php?success=1");
        exit();
    }
}

// LUXURY Hotel data (5-star hotels, premium prices)
$hotelData = [
    'Kuala Lumpur' => [
        ['name' => 'The Ritz-Carlton Kuala Lumpur', 'star' => 5, 'price' => 1200, 'review' => 9.4],
        ['name' => 'Four Seasons Kuala Lumpur', 'star' => 5, 'price' => 1350, 'review' => 9.5],
        ['name' => 'Mandarin Oriental Kuala Lumpur', 'star' => 5, 'price' => 1450, 'review' => 9.3],
        ['name' => 'St. Regis Kuala Lumpur', 'star' => 5, 'price' => 1600, 'review' => 9.6]
    ],
    'Penang' => [
        ['name' => 'Shangri-La Rasa Sayang', 'star' => 5, 'price' => 1100, 'review' => 9.2],
        ['name' => 'Eastern & Oriental Hotel', 'star' => 5, 'price' => 980, 'review' => 9.0],
        ['name' => 'The Lone Pine Hotel', 'star' => 5, 'price' => 890, 'review' => 8.9]
    ],
    'Langkawi' => [
        ['name' => 'The Datai Langkawi', 'star' => 5, 'price' => 1800, 'review' => 9.4],
        ['name' => 'Four Seasons Langkawi', 'star' => 5, 'price' => 2200, 'review' => 9.5],
        ['name' => 'The Ritz-Carlton Langkawi', 'star' => 5, 'price' => 2000, 'review' => 9.3]
    ],
    'Bangkok' => [
        ['name' => 'Mandarin Oriental Bangkok', 'star' => 5, 'price' => 1900, 'review' => 9.5],
        ['name' => 'The Peninsula Bangkok', 'star' => 5, 'price' => 1700, 'review' => 9.4],
        ['name' => 'Four Seasons Bangkok', 'star' => 5, 'price' => 1850, 'review' => 9.6]
    ],
    'Singapore' => [
        ['name' => 'Marina Bay Sands', 'star' => 5, 'price' => 2500, 'review' => 9.5],
        ['name' => 'Raffles Singapore', 'star' => 5, 'price' => 2800, 'review' => 9.6],
        ['name' => 'The Fullerton Bay', 'star' => 5, 'price' => 2300, 'review' => 9.4],
        ['name' => 'Mandarin Oriental Singapore', 'star' => 5, 'price' => 2200, 'review' => 9.3]
    ],
    'Bali' => [
        ['name' => 'AYANA Resort Bali', 'star' => 5, 'price' => 1500, 'review' => 9.4],
        ['name' => 'Four Seasons Bali', 'star' => 5, 'price' => 1800, 'review' => 9.5],
        ['name' => 'The Mulia Bali', 'star' => 5, 'price' => 1600, 'review' => 9.3]
    ],
    'Tokyo' => [
        ['name' => 'Aman Tokyo', 'star' => 5, 'price' => 2800, 'review' => 9.6],
        ['name' => 'Park Hyatt Tokyo', 'star' => 5, 'price' => 2500, 'review' => 9.4],
        ['name' => 'The Ritz-Carlton Tokyo', 'star' => 5, 'price' => 2700, 'review' => 9.5]
    ],
    'Paris' => [
        ['name' => 'Four Seasons Paris', 'star' => 5, 'price' => 3200, 'review' => 9.6],
        ['name' => 'The Ritz Paris', 'star' => 5, 'price' => 3500, 'review' => 9.5],
        ['name' => 'Mandarin Oriental Paris', 'star' => 5, 'price' => 2900, 'review' => 9.4]
    ],
    'London' => [
        ['name' => 'The Ritz London', 'star' => 5, 'price' => 2800, 'review' => 9.5],
        ['name' => 'Claridge\'s', 'star' => 5, 'price' => 3000, 'review' => 9.6],
        ['name' => 'The Savoy', 'star' => 5, 'price' => 2900, 'review' => 9.4],
        ['name' => 'Four Seasons London', 'star' => 5, 'price' => 3100, 'review' => 9.5]
    ],
    'Dubai' => [
        ['name' => 'Burj Al Arab', 'star' => 7, 'price' => 4500, 'review' => 9.6],
        ['name' => 'Atlantis The Palm', 'star' => 5, 'price' => 2500, 'review' => 9.3],
        ['name' => 'Armani Hotel Dubai', 'star' => 5, 'price' => 2800, 'review' => 9.4],
        ['name' => 'One&Only The Palm', 'star' => 5, 'price' => 3200, 'review' => 9.5]
    ],
    'Rome' => [
        ['name' => 'Hotel Eden Rome', 'star' => 5, 'price' => 2200, 'review' => 9.5],
        ['name' => 'The St. Regis Rome', 'star' => 5, 'price' => 2400, 'review' => 9.4],
        ['name' => 'Rocco Forte Rome', 'star' => 5, 'price' => 2100, 'review' => 9.3]
    ]
];

// Get hotels for the selected place
$hotels = $hotelData[$selectedPlace] ?? [
    ['name' => 'Luxury Grand Hotel', 'star' => 5, 'price' => 1800, 'review' => 9.4],
    ['name' => 'Royal Palace Hotel', 'star' => 5, 'price' => 2200, 'review' => 9.5],
    ['name' => 'Elite Suites Resort', 'star' => 5, 'price' => 2500, 'review' => 9.6]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxury Trip - <?php echo htmlspecialchars($selectedPlace); ?> | TravelAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0a;
            color: #fff;
            overflow-x: hidden;
        }

        /* Luxury Gold Animations */
        @keyframes goldGlow {
            0%, 100% { text-shadow: 0 0 10px rgba(255,215,0,0.3); }
            50% { text-shadow: 0 0 25px rgba(255,215,0,0.8), 0 0 10px rgba(255,215,0,0.5); }
        }
        @keyframes goldPulse {
            0%, 100% { border-color: rgba(255,215,0,0.3); box-shadow: 0 0 0 0 rgba(255,215,0,0.2); }
            50% { border-color: rgba(255,215,0,0.8); box-shadow: 0 0 30px 0 rgba(255,215,0,0.4); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shineGold {
            0% { background-position: -100% 0; }
            100% { background-position: 200% 0; }
        }
        @keyframes floatGold {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        @keyframes rotateGold {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: scale(0); }
            50% { opacity: 1; transform: scale(1); }
        }
        @keyframes slideInGold {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .timer-display {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 1001;
            border: 1px solid rgba(255,215,0,0.5);
            font-family: monospace;
        }
        .timer-display i { margin-right: 6px; color: #ffd700; }

        /* Sparkles background */
        .sparkle {
            position: fixed;
            width: 4px;
            height: 4px;
            background: #ffd700;
            border-radius: 50%;
            opacity: 0;
            animation: sparkle 4s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        /* Floating gold particles */
        .gold-particle {
            position: fixed;
            width: 2px;
            height: 2px;
            background: linear-gradient(135deg, #ffd700, #ffb347);
            border-radius: 50%;
            opacity: 0;
            animation: particleFloat 10s linear infinite;
            pointer-events: none;
            z-index: 0;
        }
        @keyframes particleFloat {
            0% { opacity: 0; transform: translateY(0) translateX(0) scale(1); }
            10% { opacity: 0.6; }
            90% { opacity: 0.3; }
            100% { opacity: 0; transform: translateY(-100vh) translateX(100px) scale(0); }
        }

        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(255,215,0,0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(255,200,0,0.05) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(255,215,0,0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(10,10,10,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,215,0,0.3);
            z-index: 1000;
            padding: 1rem 5%;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #ffd700, #ffb347, #ff8c00); -webkit-background-clip: text; background-clip: text; color: transparent; animation: goldGlow 3s infinite; }
        .logo i { background: none; color: #ffd700; margin-right: 8px; }
        .nav-links a { color: #fff; text-decoration: none; margin-left: 2rem; transition: color 0.3s; }
        .nav-links a:hover { color: #ffd700; }

        .container { max-width: 1200px; margin: 0 auto; padding: 100px 5% 4rem; position: relative; z-index: 10; }

        .selected-info {
            background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(255,200,0,0.05));
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(255,215,0,0.3);
            animation: fadeInUp 0.6s ease, goldPulse 4s infinite;
        }
        .selected-info h1 { font-size: 2rem; }
        .selected-info h1 i { color: #ffd700; margin-right: 10px; animation: floatGold 3s ease-in-out infinite; }
        .selected-info p { color: #a0a0a0; }
        .luxury-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ffd700, #ffb347);
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
            animation: floatGold 3s ease-in-out infinite;
            color: #1a1a2e;
            font-weight: 600;
        }

        .form-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,215,0,0.2);
            transition: all 0.4s ease;
            animation: slideInGold 0.5s ease;
        }
        .form-card:hover { border-color: rgba(255,215,0,0.6); transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255,215,0,0.1); }
        .form-card h3 { color: #ffd700; margin-bottom: 1rem; font-size: 1.3rem; }
        .form-card h3 i { margin-right: 10px; }

        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-group { flex: 1; margin-bottom: 1rem; }
        .form-group label { display: block; color: rgba(255,255,255,0.8); margin-bottom: 0.5rem; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,215,0,0.3);
            border-radius: 15px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255,215,0,0.3);
        }

        .flight-card, .hotel-card {
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.4s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .flight-card:hover, .hotel-card:hover { transform: translateY(-5px) scale(1.02); border: 1px solid #ffd700; background: rgba(255,215,0,0.08); box-shadow: 0 5px 20px rgba(255,215,0,0.15); }
        .flight-card.selected, .hotel-card.selected { border: 2px solid #ffd700; background: rgba(255,215,0,0.12); box-shadow: 0 0 25px rgba(255,215,0,0.3); }
        .airline-logo { display: flex; align-items: center; gap: 1rem; }
        .flight-price, .hotel-price { font-size: 1.2rem; font-weight: 700; color: #ffd700; }
        .selected-badge { background: #ffd700; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; margin-left: 10px; color: #1a1a2e; font-weight: bold; }
        .hotel-name { font-size: 1rem; font-weight: 600; }
        .hotel-rating { color: #ffd700; font-size: 0.8rem; margin-top: 0.3rem; }

        /* Gold Luxury Buttons */
        .btn-book {
            background: linear-gradient(135deg, #ffd700, #ffb347, #ff8c00);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: #1a1a2e;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .btn-book::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            transition: left 0.5s;
        }
        .btn-book:hover::before { left: 100%; }
        .btn-book:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(255,215,0,0.5); color: #000; }

        .btn-save {
            background: linear-gradient(135deg, #ffd700, #ffb347, #ff8c00);
            border: none;
            padding: 1rem;
            border-radius: 15px;
            color: #1a1a2e;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .btn-save::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            transition: left 0.5s;
        }
        .btn-save:hover::before { left: 100%; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 5px 30px rgba(255,215,0,0.6); color: #000; }

        .selected-summary {
            background: rgba(255,215,0,0.08);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #ffd700;
        }

        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .timer-display { top: 70px; right: 10px; font-size: 0.7rem; }
            .flight-card, .hotel-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<!-- Sparkles -->
<?php for($i = 0; $i < 60; $i++): ?>
<div class="sparkle" style="left: <?php echo rand(0,100); ?>%; top: <?php echo rand(0,100); ?>%; animation-delay: <?php echo rand(0,8000); ?>ms; animation-duration: <?php echo rand(3,8); ?>s;"></div>
<?php endfor; ?>

<!-- Gold Particles -->
<?php for($i = 0; $i < 80; $i++): ?>
<div class="gold-particle" style="left: <?php echo rand(0,100); ?>%; animation-delay: <?php echo rand(0,10000); ?>ms; animation-duration: <?php echo rand(8,15); ?>s;"></div>
<?php endfor; ?>

<div class="bg-gradient"></div>

<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><i class="fas fa-crown"></i> Travel<span>AI</span></div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="mytrips.php">My Trips</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="timer-display" id="timerDisplay">
    <i class="fas fa-hourglass-half"></i>
    <span id="timerMinutes">15</span>:<span id="timerSeconds">00</span>
</div>

<div class="container">
    <div class="selected-info">
        <h1><i class="fas fa-crown"></i> <?php echo htmlspecialchars($selectedPlace); ?>, <?php echo htmlspecialchars($selectedCountry); ?></h1>
        <p>Luxury Travel Plan - RM <?php echo $dailyBudgetMin; ?>-<?php echo $dailyBudgetMax; ?> per day</p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;"><i class="fas fa-user"></i> Booking for: <strong><?php echo htmlspecialchars($username); ?></strong></p>
        <div class="luxury-badge"><i class="fas fa-crown"></i> ✨ LUXURY TRAVELER ✨</div>
        <p style="font-size: 0.7rem; color: #a0a0a0; margin-top: 0.5rem;">👑 Experience the finest 5-star hotels and premium flights with VIP treatment 👑</p>
    </div>

    <form method="POST" action="" id="tripForm">
        <!-- Trip Dates -->
        <div class="form-card">
            <h3><i class="fas fa-calendar-alt"></i> Trip Dates</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" id="start_date" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
                <div class="form-group">
                    <label>Duration (days)</label>
                    <input type="number" name="duration" id="duration" min="1" max="30" value="3" required>
                </div>
            </div>
        </div>

        <!-- Selected Flight Summary -->
        <div class="form-card">
            <h3><i class="fas fa-plane"></i> ✈️ Selected Flight (Business/First Class)</h3>
            <div class="selected-summary" id="flightSummary">
                <p><i class="fas fa-info-circle"></i> Click on a premium flight below to select</p>
            </div>
        </div>

        <!-- Flight Selection -->
        <div class="form-card">
            <h3><i class="fas fa-search"></i> Choose Your Premium Flight</h3>
            
            <div class="flight-card" onclick="selectFlight('airasia')" id="flight_airasia">
                <div class="airline-logo">
                    <i class="fas fa-plane"></i>
                    <div><strong>AirAsia (Premium Flex)</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 599</div>
                <a href="https://www.airasia.com/en/gb/book-a-flight/flight?or=KUL&ad=<?php echo urlencode($selectedPlace); ?>" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book Business Class</a>
                <span class="selected-badge" id="badge_airasia" style="display: none;">✓</span>
            </div>
            
            <div class="flight-card" onclick="selectFlight('malaysia_airlines')" id="flight_malaysia_airlines">
                <div class="airline-logo">
                    <i class="fas fa-crown"></i>
                    <div><strong>Malaysia Airlines (Business Suite)</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 1199</div>
                <a href="https://www.malaysiaairlines.com/my/en/book.html?origin=KUL&destination=<?php echo urlencode($selectedPlace); ?>" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book Business Class</a>
                <span class="selected-badge" id="badge_malaysia_airlines" style="display: none;">✓</span>
            </div>
            
            <div class="flight-card" onclick="selectFlight('singapore_airlines')" id="flight_singapore_airlines">
                <div class="airline-logo">
                    <i class="fas fa-star"></i>
                    <div><strong>Singapore Airlines (First Class)</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 1499</div>
                <a href="https://www.singaporeair.com/" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book First Class</a>
                <span class="selected-badge" id="badge_singapore_airlines" style="display: none;">✓</span>
            </div>
            
            <input type="hidden" name="flight_booked" id="flight_booked" value="no">
            <input type="hidden" name="flight_airline" id="flight_airline" value="">
            <input type="hidden" name="flight_number" id="flight_number" value="">
            <input type="hidden" name="flight_departure" id="flight_departure" value="">
            <input type="hidden" name="flight_arrival" id="flight_arrival" value="">
        </div>

        <!-- Selected Hotel Summary -->
        <div class="form-card">
            <h3><i class="fas fa-hotel"></i> 🏨 Selected Hotel (5-Star Luxury)</h3>
            <div class="selected-summary" id="hotelSummary">
                <p><i class="fas fa-info-circle"></i> Click on a luxury hotel below to select</p>
            </div>
        </div>

        <!-- Hotel Selection -->
        <div class="form-card">
            <h3><i class="fas fa-search"></i> Choose Your Luxury Hotel in <?php echo htmlspecialchars($selectedPlace); ?></h3>
            
            <?php foreach($hotels as $index => $hotel): 
                $checkInDate = date('Y-m-d', strtotime('+7 days'));
                $checkOutDate = date('Y-m-d', strtotime('+10 days'));
                $agodaUrl = "https://www.agoda.com/search?city=" . urlencode($selectedPlace) . "&checkIn=" . $checkInDate . "&checkOut=" . $checkOutDate . "&stars=5";
            ?>
            <div class="hotel-card" onclick="selectHotel('<?php echo htmlspecialchars($hotel['name']); ?>', <?php echo $hotel['price']; ?>)" id="hotel_<?php echo $index; ?>">
                <div>
                    <div class="hotel-name">🏨 <?php echo htmlspecialchars($hotel['name']); ?></div>
                    <div class="hotel-rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php echo $i <= $hotel['star'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                        <?php endfor; ?>
                        (<?php echo $hotel['review']; ?>★)
                    </div>
                </div>
                <div class="hotel-price">RM <?php echo $hotel['price']; ?>/night</div>
                <a href="<?php echo $agodaUrl; ?>" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book on Agoda</a>
                <span class="selected-badge" id="badge_hotel_<?php echo $index; ?>" style="display: none;">✓</span>
            </div>
            <?php endforeach; ?>
            
            <input type="hidden" name="hotel_booked" id="hotel_booked" value="no">
            <input type="hidden" name="hotel_name" id="hotel_name" value="">
            <input type="hidden" name="hotel_check_in" id="hotel_check_in" value="">
            <input type="hidden" name="hotel_check_out" id="hotel_check_out" value="">
            <input type="hidden" name="hotel_check_in_time" id="hotel_check_in_time" value="14:00">
            <input type="hidden" name="hotel_room_type" id="hotel_room_type" value="">
        </div>

        <!-- Hotel Details Edit -->
        <div class="form-card" id="hotelEditCard" style="display: none;">
            <h3><i class="fas fa-pen"></i> Edit Hotel Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Check-in Date</label>
                    <input type="date" id="edit_check_in">
                </div>
                <div class="form-group">
                    <label>Check-out Date</label>
                    <input type="date" id="edit_check_out">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Check-in Time</label>
                    <input type="text" id="edit_check_in_time" value="14:00">
                </div>
                <div class="form-group">
                    <label>Room Type</label>
                    <input type="text" id="edit_room_type" placeholder="Presidential Suite / Royal Suite">
                </div>
            </div>
        </div>

        <!-- Special Requests -->
        <div class="form-card">
            <h3><i class="fas fa-comment-dots"></i> Special Requests (VIP Concierge)</h3>
            <div class="form-group">
                <textarea name="special_requests" id="special_requests" rows="3" placeholder="Any special requests? (e.g., private transfer, butler service, special occasion celebration, dietary requirements...)" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border:1px solid rgba(255,215,0,0.3); border-radius:15px; color:white; font-size:0.9rem;"></textarea>
            </div>
        </div>

        <!-- Budget Summary -->
        <div class="form-card">
            <div style="background:rgba(0,0,0,0.3); border-radius:15px; padding:1rem; text-align:center;">
                Estimated budget: <strong style="color:#ffd700;">RM <?php echo $dailyBudgetMin; ?> - RM <?php echo $dailyBudgetMax; ?></strong> per day
            </div>
        </div>

        <button type="submit" name="save_trip" class="btn-save">
            <i class="fas fa-crown"></i> Save Luxury Trip & Return Home
        </button>
    </form>
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

    // Flight data
    const flightData = {
        'airasia': { airline: 'AirAsia (Premium Flex)', number: 'AK ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T08:30', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T10:45', strtotime('+7 days')); ?>' },
        'malaysia_airlines': { airline: 'Malaysia Airlines (Business Suite)', number: 'MH ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T10:15', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T12:30', strtotime('+7 days')); ?>' },
        'singapore_airlines': { airline: 'Singapore Airlines (First Class)', number: 'SQ ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T09:00', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T11:30', strtotime('+7 days')); ?>' }
    };

    function selectFlight(flightKey) {
        const data = flightData[flightKey];
        
        document.getElementById('flight_booked').value = 'yes';
        document.getElementById('flight_airline').value = data.airline;
        document.getElementById('flight_number').value = data.number;
        document.getElementById('flight_departure').value = data.departure;
        document.getElementById('flight_arrival').value = data.arrival;
        
        document.getElementById('flightSummary').innerHTML = `
            <p><i class="fas fa-check-circle" style="color:#10b981;"></i> <strong>${data.airline}</strong></p>
            <p><i class="fas fa-barcode"></i> Flight: ${data.number}</p>
            <p><i class="fas fa-takeoff"></i> Depart: ${data.departure}</p>
            <p><i class="fas fa-landing"></i> Arrive: ${data.arrival}</p>
            <p style="margin-top: 0.5rem;"><i class="fas fa-crown"></i> ✨ Premium service with lounge access ✨</p>
        `;
        
        document.querySelectorAll('.flight-card').forEach(c => c.classList.remove('selected'));
        document.getElementById(`flight_${flightKey}`).classList.add('selected');
        ['airasia', 'malaysia_airlines', 'singapore_airlines'].forEach(b => {
            const badge = document.getElementById(`badge_${b}`);
            if (badge) badge.style.display = 'none';
        });
        document.getElementById(`badge_${flightKey}`).style.display = 'inline-block';
        
        alert(`👑 ${data.airline} selected!\nFlight: ${data.number}\n\nEnjoy your premium flight experience with VIP treatment!`);
    }
    
    function selectHotel(hotelName, price) {
        const startDate = document.getElementById('start_date').value;
        const duration = parseInt(document.getElementById('duration').value);
        let checkOut = startDate;
        if (startDate && duration) {
            const end = new Date(startDate);
            end.setDate(end.getDate() + duration);
            checkOut = end.toISOString().split('T')[0];
        }
        
        document.getElementById('hotel_booked').value = 'yes';
        document.getElementById('hotel_name').value = hotelName;
        document.getElementById('hotel_check_in').value = startDate;
        document.getElementById('hotel_check_out').value = checkOut;
        document.getElementById('hotel_room_type').value = 'Presidential Suite';
        
        document.getElementById('edit_check_in').value = startDate;
        document.getElementById('edit_check_out').value = checkOut;
        document.getElementById('edit_room_type').value = 'Presidential Suite';
        document.getElementById('hotelEditCard').style.display = 'block';
        
        document.getElementById('hotelSummary').innerHTML = `
            <p><i class="fas fa-check-circle" style="color:#10b981;"></i> <strong>${hotelName}</strong></p>
            <p><i class="fas fa-calendar-check"></i> Check-in: ${startDate} | Check-out: ${checkOut}</p>
            <p><i class="fas fa-bed"></i> Room: Presidential Suite</p>
            <p><i class="fas fa-tag"></i> Price: RM ${price}/night</p>
            <p style="margin-top: 0.5rem;"><i class="fas fa-concierge-bell"></i> 👑 24/7 Butler service & VIP amenities included 👑</p>
        `;
        
        document.querySelectorAll('.hotel-card').forEach(c => {
            c.classList.remove('selected');
            const badge = c.querySelector('[id^="badge_hotel_"]');
            if (badge) badge.style.display = 'none';
        });
        
        const cards = document.querySelectorAll('.hotel-card');
        for (let i = 0; i < cards.length; i++) {
            if (cards[i].innerText.includes(hotelName)) {
                cards[i].classList.add('selected');
                const badge = cards[i].querySelector('[id^="badge_hotel_"]');
                if (badge) badge.style.display = 'inline-block';
                break;
            }
        }
        
        alert(`👑 ${hotelName} selected!\n\nExperience 5-star luxury at this premium hotel with VIP treatment!`);
    }
    
    function updateHotelDates() {
        if (document.getElementById('hotel_booked').value === 'yes') {
            const startDate = document.getElementById('start_date').value;
            const duration = parseInt(document.getElementById('duration').value);
            if (startDate && duration) {
                const end = new Date(startDate);
                end.setDate(end.getDate() + duration);
                const checkOut = end.toISOString().split('T')[0];
                document.getElementById('hotel_check_in').value = startDate;
                document.getElementById('hotel_check_out').value = checkOut;
                document.getElementById('edit_check_in').value = startDate;
                document.getElementById('edit_check_out').value = checkOut;
            }
        }
    }
    
    document.getElementById('edit_check_in').addEventListener('change', function() {
        document.getElementById('hotel_check_in').value = this.value;
    });
    document.getElementById('edit_check_out').addEventListener('change', function() {
        document.getElementById('hotel_check_out').value = this.value;
    });
    document.getElementById('edit_check_in_time').addEventListener('change', function() {
        document.getElementById('hotel_check_in_time').value = this.value;
    });
    document.getElementById('edit_room_type').addEventListener('change', function() {
        document.getElementById('hotel_room_type').value = this.value;
    });
    
    document.getElementById('start_date').addEventListener('change', updateHotelDates);
    document.getElementById('duration').addEventListener('change', updateHotelDates);
</script>
</body>
</html>
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

// MODERATE budget tier
$budgetTier = 'moderate';
$dailyBudgetMin = 460;
$dailyBudgetMax = 1380;

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

// MODERATE Hotel data (3-5 star hotels, higher prices)
$hotelData = [
    'Kuala Lumpur' => [
        ['name' => 'The Kuala Lumpur Hotel', 'star' => 4, 'price' => 450, 'review' => 8.5],
        ['name' => 'The Face Suites', 'star' => 5, 'price' => 550, 'review' => 8.8],
        ['name' => 'Berjaya Times Square', 'star' => 4, 'price' => 480, 'review' => 8.2]
    ],
    'Penang' => [
        ['name' => 'Eastern & Oriental Hotel', 'star' => 5, 'price' => 750, 'review' => 9.0],
        ['name' => 'Armenian Street Heritage', 'star' => 4, 'price' => 450, 'review' => 8.2],
        ['name' => 'Bayview Hotel', 'star' => 4, 'price' => 380, 'review' => 7.8]
    ],
    'Bangkok' => [
        ['name' => 'Centre Point Hotel', 'star' => 4, 'price' => 500, 'review' => 8.6],
        ['name' => 'Grande Centre Point', 'star' => 5, 'price' => 680, 'review' => 9.0],
        ['name' => 'Novotel Bangkok', 'star' => 4, 'price' => 480, 'review' => 8.3]
    ],
    'Singapore' => [
        ['name' => 'YOTEL Singapore', 'star' => 4, 'price' => 580, 'review' => 8.5],
        ['name' => 'Parkroyal on Pickering', 'star' => 5, 'price' => 950, 'review' => 9.1],
        ['name' => 'Pan Pacific Singapore', 'star' => 5, 'price' => 850, 'review' => 8.9]
    ],
    'Tokyo' => [
        ['name' => 'Shinjuku Granbell', 'star' => 4, 'price' => 680, 'review' => 8.6],
        ['name' => 'Hilton Tokyo', 'star' => 5, 'price' => 950, 'review' => 9.0],
        ['name' => 'Keio Plaza Hotel', 'star' => 4, 'price' => 750, 'review' => 8.7]
    ],
    'Paris' => [
        ['name' => 'Hotel Eiffel Seine', 'star' => 4, 'price' => 780, 'review' => 8.5],
        ['name' => 'Pullman Paris', 'star' => 4, 'price' => 850, 'review' => 8.7],
        ['name' => 'Novotel Paris', 'star' => 4, 'price' => 700, 'review' => 8.2]
    ],
    'London' => [
        ['name' => 'The Strand Palace', 'star' => 4, 'price' => 820, 'review' => 8.6],
        ['name' => 'Park Plaza', 'star' => 4, 'price' => 780, 'review' => 8.4],
        ['name' => 'Marlin Apartments', 'star' => 4, 'price' => 720, 'review' => 8.3]
    ],
    'Dubai' => [
        ['name' => 'Atlantis The Palm', 'star' => 5, 'price' => 1200, 'review' => 9.1],
        ['name' => 'Jumeirah Beach Hotel', 'star' => 5, 'price' => 1100, 'review' => 8.9],
        ['name' => 'Hilton Dubai', 'star' => 4, 'price' => 780, 'review' => 8.4]
    ],
    'Rome' => [
        ['name' => 'The St. Regis Rome', 'star' => 5, 'price' => 880, 'review' => 8.8],
        ['name' => 'Hotel Artemide', 'star' => 4, 'price' => 680, 'review' => 8.6],
        ['name' => 'NH Collection', 'star' => 4, 'price' => 620, 'review' => 8.3]
    ]
];

// Get hotels for the selected place
$hotels = $hotelData[$selectedPlace] ?? [
    ['name' => 'Comfort Hotel', 'star' => 3, 'price' => 350, 'review' => 8.0],
    ['name' => 'Business Stay', 'star' => 3, 'price' => 380, 'review' => 7.8],
    ['name' => 'City Central Hotel', 'star' => 4, 'price' => 450, 'review' => 8.2]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Trip - <?php echo htmlspecialchars($selectedPlace); ?> | TravelAI</title>
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
            border: 1px solid rgba(255,255,255,0.2);
            font-family: monospace;
        }
        .timer-display i { margin-right: 6px; color: #10b981; }

        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(102,126,234,0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(118,75,162,0.15) 0%, transparent 50%);
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 1000;
            padding: 1rem 5%;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo i { background: none; color: #667eea; margin-right: 8px; }
        .nav-links a { color: #fff; text-decoration: none; margin-left: 2rem; transition: color 0.3s; }
        .nav-links a:hover { color: #667eea; }

        .container { max-width: 1200px; margin: 0 auto; padding: 100px 5% 4rem; position: relative; z-index: 10; }

        .selected-info {
            background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1));
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .selected-info h1 { font-size: 2rem; }
        .selected-info h1 i { color: #ffd700; margin-right: 10px; }
        .selected-info p { color: #a0a0a0; }
        .moderate-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .form-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .form-card h3 { color: #ffd700; margin-bottom: 1rem; font-size: 1.3rem; }
        .form-card h3 i { margin-right: 10px; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-group { flex: 1; margin-bottom: 1rem; }
        .form-group label { display: block; color: rgba(255,255,255,0.8); margin-bottom: 0.5rem; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            color: white;
            font-size: 0.9rem;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .flight-card, .hotel-card {
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .flight-card:hover, .hotel-card:hover { transform: translateY(-3px); border: 1px solid #667eea; background: rgba(102,126,234,0.1); }
        .flight-card.selected, .hotel-card.selected { border: 2px solid #10b981; background: rgba(16,185,129,0.1); }
        .airline-logo { display: flex; align-items: center; gap: 1rem; }
        .flight-price, .hotel-price { font-size: 1.2rem; font-weight: 700; color: #10b981; }
        .selected-badge { background: #10b981; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; margin-left: 10px; }
        .hotel-name { font-size: 1rem; font-weight: 600; }
        .hotel-rating { color: #ffd700; font-size: 0.8rem; margin-top: 0.3rem; }

        /* ONLY BUTTONS ARE YELLOW */
        .btn-book {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            transition: transform 0.3s;
        }
        .btn-book:hover { transform: translateY(-2px); }

        .btn-save {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            padding: 1rem;
            border-radius: 15px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: transform 0.3s;
        }
        .btn-save:hover { transform: translateY(-2px); }

        .selected-summary {
            background: rgba(102,126,234,0.15);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #10b981;
        }

        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .timer-display { top: 70px; right: 10px; font-size: 0.7rem; }
            .flight-card, .hotel-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="bg-gradient"></div>

<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><i class="fas fa-globe-americas"></i> Travel<span>AI</span></div>
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
        <h1><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($selectedPlace); ?>, <?php echo htmlspecialchars($selectedCountry); ?></h1>
        <p>Moderate Travel Plan - RM <?php echo $dailyBudgetMin; ?>-<?php echo $dailyBudgetMax; ?> per day</p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;"><i class="fas fa-user"></i> Booking for: <strong><?php echo htmlspecialchars($username); ?></strong></p>
        <div class="moderate-badge"><i class="fas fa-star"></i> MODERATE TRAVELER</div>
        <p style="font-size: 0.7rem; color: #a0a0a0; margin-top: 0.5rem;">Click on any flight or hotel to select, then click Book to visit the website</p>
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
            <h3><i class="fas fa-plane"></i> ✈️ Selected Flight</h3>
            <div class="selected-summary" id="flightSummary">
                <p><i class="fas fa-info-circle"></i> Click on a flight below to select</p>
            </div>
        </div>

        <!-- Flight Selection -->
        <div class="form-card">
            <h3><i class="fas fa-search"></i> Choose Your Flight</h3>
            
            <div class="flight-card" onclick="selectFlight('airasia')" id="flight_airasia">
                <div class="airline-logo">
                    <i class="fas fa-plane"></i>
                    <div><strong>AirAsia</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 199</div>
                <a href="https://www.airasia.com/en/gb/book-a-flight/flight?or=KUL&ad=<?php echo urlencode($selectedPlace); ?>" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book on AirAsia</a>
                <span class="selected-badge" id="badge_airasia" style="display: none;">✓</span>
            </div>
            
            <div class="flight-card" onclick="selectFlight('malaysia_airlines')" id="flight_malaysia_airlines">
                <div class="airline-logo">
                    <i class="fas fa-crown"></i>
                    <div><strong>Malaysia Airlines</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 399</div>
                <a href="https://www.malaysiaairlines.com/my/en/book.html?origin=KUL&destination=<?php echo urlencode($selectedPlace); ?>" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book on MAS</a>
                <span class="selected-badge" id="badge_malaysia_airlines" style="display: none;">✓</span>
            </div>
            
            <div class="flight-card" onclick="selectFlight('batik_air')" id="flight_batik_air">
                <div class="airline-logo">
                    <i class="fas fa-feather"></i>
                    <div><strong>Batik Air</strong><br><small>KUL → <?php echo htmlspecialchars($selectedPlace); ?></small></div>
                </div>
                <div class="flight-price">From RM 299</div>
                <a href="https://booking.batikair.com/" target="_blank" class="btn-book" onclick="event.stopPropagation();">Book on Batik Air</a>
                <span class="selected-badge" id="badge_batik_air" style="display: none;">✓</span>
            </div>
            
            <input type="hidden" name="flight_booked" id="flight_booked" value="no">
            <input type="hidden" name="flight_airline" id="flight_airline" value="">
            <input type="hidden" name="flight_number" id="flight_number" value="">
            <input type="hidden" name="flight_departure" id="flight_departure" value="">
            <input type="hidden" name="flight_arrival" id="flight_arrival" value="">
        </div>

        <!-- Selected Hotel Summary -->
        <div class="form-card">
            <h3><i class="fas fa-hotel"></i> 🏨 Selected Hotel</h3>
            <div class="selected-summary" id="hotelSummary">
                <p><i class="fas fa-info-circle"></i> Click on a hotel below to select, then click "Book on Agoda"</p>
            </div>
        </div>

        <!-- Hotel Selection -->
        <div class="form-card">
            <h3><i class="fas fa-search"></i> Choose Your Hotel in <?php echo htmlspecialchars($selectedPlace); ?></h3>
            
            <?php foreach($hotels as $index => $hotel): 
                $checkInDate = date('Y-m-d', strtotime('+7 days'));
                $checkOutDate = date('Y-m-d', strtotime('+10 days'));
                $agodaUrl = "https://www.agoda.com/search?city=" . urlencode($selectedPlace) . "&checkIn=" . $checkInDate . "&checkOut=" . $checkOutDate;
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
                    <input type="text" id="edit_room_type" placeholder="Deluxe King">
                </div>
            </div>
        </div>

        <!-- Special Requests -->
        <div class="form-card">
            <h3><i class="fas fa-comment-dots"></i> Special Requests</h3>
            <div class="form-group">
                <textarea name="special_requests" id="special_requests" rows="3" placeholder="Any special requests? (e.g., vegetarian meal, wheelchair assistance, late check-in...)" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:15px; color:white; font-size:0.9rem;"></textarea>
            </div>
        </div>

        <!-- Budget Summary -->
        <div class="form-card">
            <div style="background:rgba(0,0,0,0.3); border-radius:15px; padding:1rem; text-align:center;">
                Estimated budget: <strong style="color:#10b981;">RM <?php echo $dailyBudgetMin; ?> - RM <?php echo $dailyBudgetMax; ?></strong> per day
            </div>
        </div>

        <button type="submit" name="save_trip" class="btn-save">
            <i class="fas fa-save"></i> Save Moderate Trip & Return Home
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
        'airasia': { airline: 'AirAsia', number: 'AK ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T08:30', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T10:45', strtotime('+7 days')); ?>' },
        'malaysia_airlines': { airline: 'Malaysia Airlines', number: 'MH ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T10:15', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T12:30', strtotime('+7 days')); ?>' },
        'batik_air': { airline: 'Batik Air', number: 'OD ' + Math.floor(Math.random() * 900 + 100), departure: '<?php echo date('Y-m-d\T15:45', strtotime('+7 days')); ?>', arrival: '<?php echo date('Y-m-d\T18:00', strtotime('+7 days')); ?>' }
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
            <p style="margin-top: 0.5rem;"><i class="fas fa-external-link-alt"></i> Click "Book on ${data.airline}" to book your flight</p>
        `;
        
        document.querySelectorAll('.flight-card').forEach(c => c.classList.remove('selected'));
        document.getElementById(`flight_${flightKey}`).classList.add('selected');
        ['airasia', 'malaysia_airlines', 'batik_air'].forEach(b => {
            const badge = document.getElementById(`badge_${b}`);
            if (badge) badge.style.display = 'none';
        });
        document.getElementById(`badge_${flightKey}`).style.display = 'inline-block';
        
        alert(`✅ ${data.airline} selected!\nFlight: ${data.number}\n\nYou can now click "Book on ${data.airline}" to complete your booking.`);
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
        document.getElementById('hotel_room_type').value = 'Standard Room';
        
        document.getElementById('edit_check_in').value = startDate;
        document.getElementById('edit_check_out').value = checkOut;
        document.getElementById('edit_room_type').value = 'Standard Room';
        document.getElementById('hotelEditCard').style.display = 'block';
        
        document.getElementById('hotelSummary').innerHTML = `
            <p><i class="fas fa-check-circle" style="color:#10b981;"></i> <strong>${hotelName}</strong></p>
            <p><i class="fas fa-calendar-check"></i> Check-in: ${startDate} | Check-out: ${checkOut}</p>
            <p><i class="fas fa-bed"></i> Room: Standard Room</p>
            <p><i class="fas fa-tag"></i> Price: RM ${price}/night</p>
            <p style="margin-top: 0.5rem;"><i class="fas fa-external-link-alt"></i> Click "Book on Agoda" to complete your hotel booking</p>
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
        
        alert(`✅ ${hotelName} selected!\n\nYou can now click "Book on Agoda" to complete your hotel booking.`);
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
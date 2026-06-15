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
    'Cameron Highlands' => ['lat' => 4.4900, 'lng' => 101.3800],
    'Sabah' => ['lat' => 5.9804, 'lng' => 116.0735],
    'Johor Bahru' => ['lat' => 1.4927, 'lng' => 103.7414],
    'Melaka' => ['lat' => 2.1896, 'lng' => 102.2501],
    'Bangkok' => ['lat' => 13.7367, 'lng' => 100.5231],
    'Phuket' => ['lat' => 7.8804, 'lng' => 98.3923],
    'Chiang Mai' => ['lat' => 18.7883, 'lng' => 98.9853],
    'Singapore' => ['lat' => 1.3521, 'lng' => 103.8198],
    'Bali' => ['lat' => -8.4095, 'lng' => 115.1889],
    'Jakarta' => ['lat' => -6.2088, 'lng' => 106.8456],
    'Tokyo' => ['lat' => 35.6762, 'lng' => 139.6503],
    'Osaka' => ['lat' => 34.6937, 'lng' => 135.5023],
    'Kyoto' => ['lat' => 35.0116, 'lng' => 135.7681],
    'Seoul' => ['lat' => 37.5665, 'lng' => 126.9780],
    'Busan' => ['lat' => 35.1796, 'lng' => 129.0756],
    'Beijing' => ['lat' => 39.9042, 'lng' => 116.4074],
    'Shanghai' => ['lat' => 31.2304, 'lng' => 121.4737],
    'Mumbai' => ['lat' => 19.0760, 'lng' => 72.8777],
    'Delhi' => ['lat' => 28.6139, 'lng' => 77.2090],
    'Paris' => ['lat' => 48.8566, 'lng' => 2.3522],
    'London' => ['lat' => 51.5074, 'lng' => -0.1278],
    'Sydney' => ['lat' => -33.8688, 'lng' => 151.2093],
    'Melbourne' => ['lat' => -37.8136, 'lng' => 144.9631],
    'Istanbul' => ['lat' => 41.0082, 'lng' => 28.9784],
    'Cairo' => ['lat' => 30.0444, 'lng' => 31.2357],
    'Dubai' => ['lat' => 25.2048, 'lng' => 55.2708],
    'Rome' => ['lat' => 41.9028, 'lng' => 12.4964],
    'Venice' => ['lat' => 45.4408, 'lng' => 12.3155],
    'Barcelona' => ['lat' => 41.3851, 'lng' => 2.1734],
    'Madrid' => ['lat' => 40.4168, 'lng' => -3.7038],
    'Berlin' => ['lat' => 52.5200, 'lng' => 13.4050],
    'Munich' => ['lat' => 48.1351, 'lng' => 11.5820],
    'Zurich' => ['lat' => 47.3769, 'lng' => 8.5417],
    'Amsterdam' => ['lat' => 52.3676, 'lng' => 4.9041],
    'Athens' => ['lat' => 37.9838, 'lng' => 23.7275],
    'Auckland' => ['lat' => -36.8485, 'lng' => 174.7633],
    'Queenstown' => ['lat' => -45.0312, 'lng' => 168.6626],
    'Rio de Janeiro' => ['lat' => -22.9068, 'lng' => -43.1729],
    'Cape Town' => ['lat' => -33.9249, 'lng' => 18.4241]
];

$lat = $placeCoordinates[$selectedPlace]['lat'] ?? 3.1390;
$lng = $placeCoordinates[$selectedPlace]['lng'] ?? 101.6869;

$budgetTier = 'budget';
$dailyBudgetMin = 230;
$dailyBudgetMax = 460;

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

// Function to generate Agoda search URL for a hotel
function getAgodaHotelUrl($hotelName, $destination, $checkIn, $checkOut) {
    // Encode hotel name for URL
    $encodedHotel = urlencode($hotelName);
    $encodedDest = urlencode($destination);
    
    // Agoda search URL (works with any hotel name)
    return "https://www.agoda.com/search?city=" . $encodedDest . "&checkIn=" . $checkIn . "&checkOut=" . $checkOut . "&los=" . urlencode($hotelName);
}

// COMPLETE Hotel data for ALL places (Budget tier hotels)
$hotelData = [
    // Malaysia
    'Kuala Lumpur' => [
        ['name' => 'The Kuala Lumpur Hotel', 'star' => 4, 'price' => 230, 'review' => 8.5, 'agoda_hid' => 'hotel-kuala-lumpur'],
        ['name' => 'Chinatown Hostel', 'star' => 2, 'price' => 55, 'review' => 7.8, 'agoda_hid' => 'chinatown-hostel'],
        ['name' => 'The Face Suites', 'star' => 5, 'price' => 280, 'review' => 8.8, 'agoda_hid' => 'the-face-suites'],
        ['name' => 'Budget Stay KL', 'star' => 3, 'price' => 120, 'review' => 8.0, 'agoda_hid' => 'budget-stay-kl']
    ],
    'Penang' => [
        ['name' => 'Eastern & Oriental Hotel', 'star' => 5, 'price' => 350, 'review' => 9.0, 'agoda_hid' => 'eastern-oriental-hotel'],
        ['name' => 'Chulia Heritage Hotel', 'star' => 3, 'price' => 80, 'review' => 7.5, 'agoda_hid' => 'chulia-heritage'],
        ['name' => 'Armenian Street Heritage', 'star' => 4, 'price' => 180, 'review' => 8.2, 'agoda_hid' => 'armenian-street']
    ],
    'Langkawi' => [
        ['name' => 'The Datai Langkawi', 'star' => 5, 'price' => 850, 'review' => 9.2, 'agoda_hid' => 'datai-langkawi'],
        ['name' => 'Casa del Mar', 'star' => 4, 'price' => 320, 'review' => 8.7, 'agoda_hid' => 'casa-del-mar'],
        ['name' => 'Langkawi Budget Inn', 'star' => 2, 'price' => 60, 'review' => 7.0, 'agoda_hid' => 'langkawi-budget-inn']
    ],
    'Bangkok' => [
        ['name' => 'Shangri-La Bangkok', 'star' => 5, 'price' => 450, 'review' => 9.1, 'agoda_hid' => 'shangri-la-bangkok'],
        ['name' => 'Budget Hostel Bangkok', 'star' => 2, 'price' => 45, 'review' => 7.2, 'agoda_hid' => 'budget-hostel-bangkok'],
        ['name' => 'Centre Point Hotel', 'star' => 4, 'price' => 280, 'review' => 8.6, 'agoda_hid' => 'centre-point-hotel']
    ],
    'Singapore' => [
        ['name' => 'Marina Bay Sands', 'star' => 5, 'price' => 650, 'review' => 9.3, 'agoda_hid' => 'marina-bay-sands'],
        ['name' => 'Hotel 81', 'star' => 3, 'price' => 120, 'review' => 7.0, 'agoda_hid' => 'hotel-81-singapore'],
        ['name' => 'YOTEL Singapore', 'star' => 4, 'price' => 280, 'review' => 8.5, 'agoda_hid' => 'yotel-singapore']
    ],
    'Bali' => [
        ['name' => 'Ayana Resort', 'star' => 5, 'price' => 500, 'review' => 9.1, 'agoda_hid' => 'ayana-resort-bali'],
        ['name' => 'Bali Budget Inn', 'star' => 2, 'price' => 60, 'review' => 7.2, 'agoda_hid' => 'bali-budget-inn'],
        ['name' => 'The Haven Hotel', 'star' => 4, 'price' => 220, 'review' => 8.4, 'agoda_hid' => 'the-haven-bali']
    ],
    'Tokyo' => [
        ['name' => 'Park Hyatt Tokyo', 'star' => 5, 'price' => 600, 'review' => 9.2, 'agoda_hid' => 'park-hyatt-tokyo'],
        ['name' => 'Capsule Hotel', 'star' => 2, 'price' => 60, 'review' => 7.0, 'agoda_hid' => 'capsule-hotel-tokyo'],
        ['name' => 'Shinjuku Granbell', 'star' => 4, 'price' => 350, 'review' => 8.6, 'agoda_hid' => 'shinjuku-granbell']
    ],
    'Paris' => [
        ['name' => 'Four Seasons Paris', 'star' => 5, 'price' => 900, 'review' => 9.3, 'agoda_hid' => 'four-seasons-paris'],
        ['name' => 'Paris Budget Hotel', 'star' => 2, 'price' => 80, 'review' => 7.0, 'agoda_hid' => 'paris-budget-hotel'],
        ['name' => 'Hotel Eiffel Seine', 'star' => 4, 'price' => 350, 'review' => 8.5, 'agoda_hid' => 'hotel-eiffel-seine']
    ],
    'London' => [
        ['name' => 'The Ritz London', 'star' => 5, 'price' => 800, 'review' => 9.2, 'agoda_hid' => 'ritz-london'],
        ['name' => 'London Hostel', 'star' => 2, 'price' => 70, 'review' => 7.2, 'agoda_hid' => 'london-hostel'],
        ['name' => 'The Strand Palace', 'star' => 4, 'price' => 380, 'review' => 8.6, 'agoda_hid' => 'strand-palace']
    ],
    'Sydney' => [
        ['name' => 'Park Hyatt Sydney', 'star' => 5, 'price' => 700, 'review' => 9.2, 'agoda_hid' => 'park-hyatt-sydney'],
        ['name' => 'Sydney Budget Inn', 'star' => 2, 'price' => 65, 'review' => 7.3, 'agoda_hid' => 'sydney-budget-inn'],
        ['name' => 'The Langham Sydney', 'star' => 4, 'price' => 400, 'review' => 8.8, 'agoda_hid' => 'langham-sydney']
    ],
    'Dubai' => [
        ['name' => 'Burj Al Arab', 'star' => 7, 'price' => 1200, 'review' => 9.4, 'agoda_hid' => 'burj-al-arab'],
        ['name' => 'Dubai Budget Hotel', 'star' => 2, 'price' => 80, 'review' => 7.3, 'agoda_hid' => 'dubai-budget-hotel'],
        ['name' => 'Atlantis The Palm', 'star' => 5, 'price' => 700, 'review' => 9.1, 'agoda_hid' => 'atlantis-the-palm']
    ],
    'Rome' => [
        ['name' => 'Hotel Eden Rome', 'star' => 5, 'price' => 600, 'review' => 9.1, 'agoda_hid' => 'hotel-eden-rome'],
        ['name' => 'Rome Hostel', 'star' => 2, 'price' => 60, 'review' => 7.2, 'agoda_hid' => 'rome-hostel'],
        ['name' => 'The St. Regis Rome', 'star' => 4, 'price' => 400, 'review' => 8.8, 'agoda_hid' => 'st-regis-rome']
    ]
];

// Get hotels for the selected place
$hotels = $hotelData[$selectedPlace] ?? [
    ['name' => 'Budget Hotel', 'star' => 3, 'price' => 150, 'review' => 8.0, 'agoda_hid' => 'budget-hotel'],
    ['name' => 'Economy Stay', 'star' => 2, 'price' => 80, 'review' => 7.5, 'agoda_hid' => 'economy-stay'],
    ['name' => 'Standard Hotel', 'star' => 3, 'price' => 180, 'review' => 8.2, 'agoda_hid' => 'standard-hotel']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Trip - <?php echo htmlspecialchars($selectedPlace); ?> | TravelAI</title>
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
            background: radial-gradient(circle at 20% 50%, rgba(102,126,234,0.15) 0%, transparent 50%);
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

        .form-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .form-card h3 { color: #ffd700; margin-bottom: 1rem; }
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
        
        .btn-book {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            transition: transform 0.3s;
        }
        .btn-book:hover { transform: translateY(-2px); }
        
        .btn-save {
            background: linear-gradient(135deg, #10b981, #059669);
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
        <p>Budget Travel Plan - RM <?php echo $dailyBudgetMin; ?>-<?php echo $dailyBudgetMax; ?> per day</p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;"><i class="fas fa-user"></i> Booking for: <strong><?php echo htmlspecialchars($username); ?></strong></p>
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
                // Generate Agoda booking URL
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
                    <input type="date" id="edit_check_in" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border-radius:15px; color:white;">
                </div>
                <div class="form-group">
                    <label>Check-out Date</label>
                    <input type="date" id="edit_check_out" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border-radius:15px; color:white;">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Check-in Time</label>
                    <input type="text" id="edit_check_in_time" value="14:00" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border-radius:15px; color:white;">
                </div>
                <div class="form-group">
                    <label>Room Type</label>
                    <input type="text" id="edit_room_type" placeholder="Deluxe King" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border-radius:15px; color:white;">
                </div>
            </div>
        </div>

        <!-- Special Requests -->
        <div class="form-card">
            <h3><i class="fas fa-comment-dots"></i> Special Requests</h3>
            <textarea name="special_requests" id="special_requests" rows="3" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.1); border-radius:15px; color:white; border:1px solid rgba(255,255,255,0.2);" placeholder="Any special requests? (e.g., vegetarian meal, wheelchair assistance, late check-in...)"></textarea>
        </div>

        <!-- Budget Summary -->
        <div class="form-card">
            <div style="background:rgba(0,0,0,0.3); border-radius:15px; padding:1rem; text-align:center;">
                Estimated budget: <strong style="color:#10b981;">RM <?php echo $dailyBudgetMin; ?> - RM <?php echo $dailyBudgetMax; ?></strong> per day
            </div>
        </div>

        <button type="submit" name="save_trip" class="btn-save">
            <i class="fas fa-save"></i> Save Trip & Return Home
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
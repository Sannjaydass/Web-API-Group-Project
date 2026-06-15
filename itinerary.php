<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$trip_id = $_GET['trip_id'] ?? 0;

$database = new Database();
$db = $database->getConnection();

// Get trip details
$query = "SELECT * FROM trips WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $trip_id, ':user_id' => $_SESSION['user_id']]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header("Location: index.php");
    exit();
}

// Call API to get itinerary
$api_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/api.php?path=itinerary";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'destination' => $trip['destination'],
    'duration' => $trip['trip_duration'],
    'budgetTier' => $trip['budget_tier']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$itineraryData = json_decode($response, true);
$itinerary = $itineraryData['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Itinerary - <?php echo htmlspecialchars($trip['destination']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

    <div class="itinerary-container">
        <div class="container">
            <div class="itinerary-header">
                <h1><?php echo htmlspecialchars($trip['destination']); ?></h1>
                <div class="trip-meta">
                    <span><i class="fas fa-calendar"></i> <?php echo $trip['trip_duration']; ?> Day Trip</span>
                    <span><i class="fas fa-tag"></i> <?php echo ucfirst($trip['budget_tier']); ?> Budget</span>
                </div>
            </div>

            <div id="map" style="height: 400px; margin-bottom: 2rem; border-radius: 20px;"></div>

            <div class="itinerary-days">
                <?php foreach($itinerary as $day): ?>
                <div class="day-card">
                    <div class="day-header">
                        <div class="day-number">Day <?php echo $day['day']; ?></div>
                        <div class="day-weather">
                            <i class="fas fa-cloud-sun"></i>
                            <?php echo htmlspecialchars($day['weather']); ?>
                            <?php if($day['temp']): ?> | <?php echo $day['temp']; ?>°C<?php endif; ?>
                        </div>
                    </div>
                    <div class="activities">
                        <?php foreach($day['activities'] as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-time">
                                <i class="fas fa-clock"></i> <?php echo $activity['time']; ?>
                            </div>
                            <div class="activity-details">
                                <h3><?php echo htmlspecialchars($activity['activity']); ?></h3>
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="activity-cost">
                                    <i class="fas fa-dollar-sign"></i> $<?php echo $activity['cost']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="booking-actions">
                <a href="payment.php?type=flight&amount=500&trip_id=<?php echo $trip_id; ?>" class="btn-booking">
                    <i class="fas fa-plane"></i> Book Flights
                </a>
                <a href="payment.php?type=hotel&amount=800&trip_id=<?php echo $trip_id; ?>" class="btn-booking">
                    <i class="fas fa-hotel"></i> Book Hotels
                </a>
                <a href="#" class="btn-booking" onclick="window.print()">
                    <i class="fas fa-download"></i> Download Itinerary
                </a>
            </div>
        </div>
    </div>

    <script>
        var map = L.map('map').setView([48.8566, 2.3522], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        L.marker([48.8566, 2.3522]).addTo(map)
            .bindPopup('<?php echo htmlspecialchars($trip['destination']); ?>')
            .openPopup();
    </script>
    <script src="js/main.js"></script>
</body>
</html>
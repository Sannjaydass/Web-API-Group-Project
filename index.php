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
} else {
    $_SESSION['jwt_token'] = generateJWT($_SESSION['user_id'], $_SESSION['username']);
    $payload = verifyJWT($_SESSION['jwt_token']);
    $_SESSION['expiry_time'] = $payload['exp'];
}

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Traveler';

$expiryTime = isset($_SESSION['expiry_time']) ? $_SESSION['expiry_time'] : (time() + 900);
$remainingSeconds = $expiryTime - time();

if ($remainingSeconds <= 0 || $remainingSeconds > 900) {
    $_SESSION['jwt_token'] = generateJWT($_SESSION['user_id'], $_SESSION['username']);
    $payload = verifyJWT($_SESSION['jwt_token']);
    $_SESSION['expiry_time'] = $payload['exp'];
    $remainingSeconds = 900;
}

$showSuccess = isset($_GET['success']) && $_GET['success'] == 1;
$successDestination = isset($_SESSION['trip_destination']) ? $_SESSION['trip_destination'] : '';
if ($showSuccess && $successDestination) {
    echo '<div class="success-toast" style="position: fixed; top: 100px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 1rem 2rem; border-radius: 50px; z-index: 2000; animation: slideDown 0.5s ease, fadeOut 5s ease 3s forwards; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <i class="fas fa-check-circle"></i> 🎉 Trip to ' . htmlspecialchars($successDestination) . ' saved successfully! 🎉
          </div>
          <style>
            @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -50px); } to { opacity: 1; transform: translate(-50%, 0); } }
            @keyframes fadeOut { to { opacity: 0; visibility: hidden; } }
          </style>';
    unset($_SESSION['trip_destination']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelAI - Smart Travel Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDCM1eG2rlrOsRQOWH67d46Dmivo8LJAzQ&callback=initMap" async defer></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; overflow-x: hidden; }

        .timer-display {
            position: fixed; top: 80px; right: 20px; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px); padding: 10px 18px; border-radius: 50px;
            font-size: 1rem; font-weight: 700; z-index: 1001;
            border: 1px solid rgba(102,126,234,0.5); font-family: monospace;
        }
        .timer-display i { margin-right: 8px; color: #10b981; }
        .timer-display .warning { color: #f59e0b; }
        .timer-display .danger { color: #ef4444; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .bg-gradient {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(102,126,234,0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(118,75,162,0.15) 0%, transparent 50%);
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
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { color: #fff; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover { color: #667eea; }

        .hero { min-height: 25vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 7rem 5% 1rem; }
        .hero h1 { font-size: 2.2rem; font-weight: 800; }
        .hero h1 span { background: linear-gradient(135deg, #667eea, #764ba2, #f093fb); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero p { color: #a0a0a0; margin-top: 0.5rem; }

        .container { max-width: 1400px; margin: 0 auto; padding: 1rem 5% 4rem; }
        .search-section { background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1)); border-radius: 40px; padding: 2rem; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.1); }
        .search-box { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .search-box select { flex: 2; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 1rem 1.5rem; color: white; font-size: 1rem; cursor: pointer; }
        .search-box select:focus { outline: none; border-color: #667eea; }
        .search-box select option { background: #1a1a2e; color: white; }

        .selected-place {
            background: rgba(102,126,234,0.2); border-radius: 15px; padding: 0.8rem 1rem;
            margin: 1rem 0; text-align: center; display: none; border: 1px solid rgba(102,126,234,0.3);
        }
        .selected-place i { color: #ffd700; margin-right: 8px; }

        .map-container { margin-bottom: 2rem; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); }
        #map { height: 350px; width: 100%; }

        .weather-result {
            background: rgba(0,0,0,0.5); border-radius: 20px; padding: 1.5rem;
            margin: 1rem 0; display: none; border: 1px solid rgba(102,126,234,0.3);
        }
        .weather-result.active { display: block; animation: slideIn 0.5s ease; }
        .weather-result h3 { margin-bottom: 1rem; color: #ffd700; font-size: 1.3rem; }
        .weather-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .weather-card { text-align: center; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 15px; }
        .weather-card i { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .weather-card .label { font-size: 0.7rem; color: #a0a0a0; }
        .weather-card .value { font-size: 1.2rem; font-weight: 600; }

        .safety-advice {
            background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.2));
            border-radius: 15px; padding: 1rem; margin-top: 1rem; border-left: 4px solid #10b981;
        }
        .safety-advice.warning {
            background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.2));
            border-left-color: #ef4444;
        }
        .safety-advice i { margin-right: 10px; font-size: 1.2rem; }
        .safety-advice.safe i { color: #10b981; }
        .safety-advice.warning i { color: #ef4444; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .places-section h3 { font-size: 1.3rem; margin: 1rem 0; }
        .places-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .place-card {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px; padding: 1rem; display: flex; align-items: center;
            gap: 1rem; cursor: pointer; transition: all 0.3s;
        }
        .place-card:hover { background: rgba(102,126,234,0.2); transform: translateY(-3px); }
        .place-card.selected {
            background: linear-gradient(135deg, rgba(102,126,234,0.3), rgba(118,75,162,0.3));
            border: 2px solid #10b981;
        }
        .place-icon { width: 50px; height: 50px; background: rgba(102,126,234,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .budget-section { margin-top: 2rem; }
        .budget-section h3 { font-size: 1.3rem; margin-bottom: 1rem; text-align: center; }
        .budget-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem; }
        .budget-btn {
            flex: 1; min-width: 160px; padding: 1rem; border-radius: 20px; text-align: center;
            text-decoration: none; font-weight: 600; color: white; transition: all 0.3s; display: block;
        }
        .budget-btn.budget { background: linear-gradient(135deg, #10b981, #059669); }
        .budget-btn.moderate { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .budget-btn.luxury { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .budget-btn:hover { transform: translateY(-3px); filter: brightness(1.1); }
        .budget-btn .price { font-size: 0.65rem; opacity: 0.8; margin-top: 0.3rem; }

        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; margin-left: 10px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .api-credit { text-align: center; margin-top: 2rem; font-size: 0.7rem; color: #a0a0a0; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 1.5rem; }
            .search-box { flex-direction: column; }
            .budget-buttons { flex-direction: column; }
            .timer-display { top: 70px; right: 10px; font-size: 0.75rem; padding: 6px 12px; }
            .weather-grid { grid-template-columns: repeat(2, 1fr); }
            .places-grid { grid-template-columns: 1fr; }
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
    <span style="font-size: 0.7rem; margin-left: 5px;">remaining</span>
</div>

<section class="hero">
    <div class="hero-content">
        <h1>Hi, <span><?php echo htmlspecialchars($user_name); ?></span>! 🇲🇾</h1>
        <p>Select a country, then choose a place to plan your trip</p>
    </div>
</section>

<div class="container">
    <div class="search-section">
        <div class="search-box">
            <select id="countrySelect" onchange="getWeatherAndMap()">
                <option value="">-- Select a Country --</option>
                <option value="Malaysia">🇲🇾 Malaysia</option>
                <option value="Thailand">🇹🇭 Thailand</option>
                <option value="Singapore">🇸🇬 Singapore</option>
                <option value="Indonesia">🇮🇩 Indonesia</option>
                <option value="Vietnam">🇻🇳 Vietnam</option>
                <option value="Philippines">🇵🇭 Philippines</option>
                <option value="Japan">🇯🇵 Japan</option>
                <option value="South Korea">🇰🇷 South Korea</option>
                <option value="China">🇨🇳 China</option>
                <option value="India">🇮🇳 India</option>
                <option value="France">🇫🇷 France</option>
                <option value="United Kingdom">🇬🇧 United Kingdom</option>
                <option value="Australia">🇦🇺 Australia</option>
                <option value="Turkey">🇹🇷 Turkey</option>
                <option value="Egypt">🇪🇬 Egypt</option>
                <option value="UAE">🇦🇪 Dubai</option>
                <option value="Italy">🇮🇹 Italy</option>
                <option value="Spain">🇪🇸 Spain</option>
                <option value="Germany">🇩🇪 Germany</option>
                <option value="Switzerland">🇨🇭 Switzerland</option>
                <option value="Netherlands">🇳🇱 Netherlands</option>
                <option value="Greece">🇬🇷 Greece</option>
                <option value="New Zealand">🇳🇿 New Zealand</option>
                <option value="Brazil">🇧🇷 Brazil</option>
                <option value="South Africa">🇿🇦 South Africa</option>
                <option value="Russia">🇷🇺 Russia</option>
                <option value="Canada">🇨🇦 Canada</option>
                <option value="Mexico">🇲🇽 Mexico</option>
                <option value="Argentina">🇦🇷 Argentina</option>
                <option value="Sweden">🇸🇪 Sweden</option>
                <option value="Norway">🇳🇴 Norway</option>
                <option value="Denmark">🇩🇰 Denmark</option>
                <option value="Finland">🇫🇮 Finland</option>
                <option value="Poland">🇵🇱 Poland</option>
                <option value="Austria">🇦🇹 Austria</option>
                <option value="Portugal">🇵🇹 Portugal</option>
                <option value="Ireland">🇮🇪 Ireland</option>
                <option value="Belgium">🇧🇪 Belgium</option>
                <option value="Czech Republic">🇨🇿 Czech Republic</option>
                <option value="Hungary">🇭🇺 Hungary</option>
                <option value="Croatia">🇭🇷 Croatia</option>
                <option value="Morocco">🇲🇦 Morocco</option>
                <option value="Kenya">🇰🇪 Kenya</option>
                <option value="Sri Lanka">🇱🇰 Sri Lanka</option>
                <option value="Nepal">🇳🇵 Nepal</option>
                <option value="Cambodia">🇰🇭 Cambodia</option>
                <option value="Myanmar">🇲🇲 Myanmar</option>
                <option value="Laos">🇱🇦 Laos</option>
                <option value="Brunei">🇧🇳 Brunei</option>
                <option value="Maldives">🇲🇻 Maldives</option>
                <option value="Jordan">🇯🇴 Jordan</option>
                <option value="Qatar">🇶🇦 Qatar</option>
                <option value="Oman">🇴🇲 Oman</option>
                <option value="Kuwait">🇰🇼 Kuwait</option>
                <option value="Bahrain">🇧🇭 Bahrain</option>
            </select>
        </div>

        <div class="selected-place" id="selectedPlaceDiv">
            <i class="fas fa-map-pin"></i> Selected: <span id="selectedPlaceName">None</span>
        </div>

        <div class="map-container">
            <div id="map"></div>
        </div>

        <div id="weatherResult" class="weather-result">
            <div id="weatherContent"></div>
        </div>

        <div id="placesSection" style="display: none;">
            <h3><i class="fas fa-star"></i> Popular Places in <span id="selectedCountry"></span></h3>
            <p style="font-size: 0.8rem; color: #a0a0a0; margin-bottom: 0.5rem;">✨ Click on any place to select it for your trip</p>
            <div class="places-grid" id="placesGrid"></div>
        </div>

        <div id="budgetSection" style="display: none;">
            <h3>Choose Your Travel Style (RM)</h3>
            <div class="budget-buttons">
                <a href="budget.php" class="budget-btn budget" id="budgetLink">
                    <i class="fas fa-coins"></i> Budget<br><span class="price">RM 230-460/day</span>
                </a>
                <a href="moderate.php" class="budget-btn moderate" id="moderateLink">
                    <i class="fas fa-star"></i> Moderate<br><span class="price">RM 460-1,380/day</span>
                </a>
                <a href="luxury.php" class="budget-btn luxury" id="luxuryLink">
                    <i class="fas fa-crown"></i> Luxury<br><span class="price">RM 1,380+/day</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="api-credit">
        <i class="fas fa-cloud-sun"></i> Weather data by Open-Meteo (Free API) | 🌍 55+ Countries with detailed recommendations
    </div>
</div>

<script>
    // Session Timer
    let timeLeft = <?php echo $remainingSeconds; ?>;
    const minutesSpan = document.getElementById('timerMinutes');
    const secondsSpan = document.getElementById('timerSeconds');
    const timerDisplayDiv = document.getElementById('timerDisplay');
    
    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        minutesSpan.textContent = minutes.toString().padStart(2, '0');
        secondsSpan.textContent = seconds.toString().padStart(2, '0');
        
        if (timeLeft <= 300) {
            minutesSpan.classList.add('warning');
            secondsSpan.classList.add('warning');
            timerDisplayDiv.style.borderColor = '#f59e0b';
        }
        if (timeLeft <= 60) {
            minutesSpan.classList.add('danger');
            secondsSpan.classList.add('danger');
            timerDisplayDiv.style.borderColor = '#ef4444';
        }
        if (timeLeft <= 30) {
            timerDisplayDiv.style.animation = 'pulse 1s infinite';
        }
    }
    
    const timerInterval = setInterval(() => {
        if (timeLeft <= 1) {
            clearInterval(timerInterval);
            window.location.href = 'logout.php?expired=1';
            return;
        }
        timeLeft--;
        updateTimerDisplay();
    }, 1000);
    updateTimerDisplay();

    let map, marker;
    let currentSelectedCountry = '';
    let currentSelectedPlace = '';

    function initMap() {
        const defaultLocation = { lat: 4.2105, lng: 101.9758 };
        map = new google.maps.Map(document.getElementById('map'), {
            zoom: 6, center: defaultLocation,
            styles: [{ featureType: 'all', elementType: 'geometry', stylers: [{ color: '#1a1a2e' }] },
                     { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#16213e' }] }]
        });
        marker = new google.maps.Marker({ position: defaultLocation, map: map, title: 'Malaysia' });
    }

    // Complete country coordinates
    const countryCoordinates = {
        'Malaysia': { lat: 4.2105, lng: 101.9758 },
        'Thailand': { lat: 15.8700, lng: 100.9925 },
        'Singapore': { lat: 1.3521, lng: 103.8198 },
        'Indonesia': { lat: -0.7893, lng: 113.9213 },
        'Vietnam': { lat: 14.0583, lng: 108.2772 },
        'Philippines': { lat: 12.8797, lng: 121.7740 },
        'Japan': { lat: 36.2048, lng: 138.2529 },
        'South Korea': { lat: 35.9078, lng: 127.7669 },
        'China': { lat: 35.8617, lng: 104.1954 },
        'India': { lat: 20.5937, lng: 78.9629 },
        'France': { lat: 46.2276, lng: 2.2137 },
        'United Kingdom': { lat: 55.3781, lng: -3.4360 },
        'Australia': { lat: -25.2744, lng: 133.7751 },
        'Turkey': { lat: 38.9637, lng: 35.2433 },
        'Egypt': { lat: 26.8206, lng: 30.8025 },
        'UAE': { lat: 23.4241, lng: 53.8478 },
        'Italy': { lat: 41.8719, lng: 12.5674 },
        'Spain': { lat: 40.4637, lng: -3.7492 },
        'Germany': { lat: 51.1657, lng: 10.4515 },
        'Switzerland': { lat: 46.8182, lng: 8.2275 },
        'Netherlands': { lat: 52.1326, lng: 5.2913 },
        'Greece': { lat: 39.0742, lng: 21.8243 },
        'New Zealand': { lat: -40.9006, lng: 174.8860 },
        'Brazil': { lat: -14.2350, lng: -51.9253 },
        'South Africa': { lat: -30.5595, lng: 22.9375 },
        'Russia': { lat: 61.5240, lng: 105.3188 },
        'Canada': { lat: 56.1304, lng: -106.3468 },
        'Mexico': { lat: 23.6345, lng: -102.5528 },
        'Argentina': { lat: -38.4161, lng: -63.6167 },
        'Sweden': { lat: 60.1282, lng: 18.6435 },
        'Norway': { lat: 60.4720, lng: 8.4689 },
        'Denmark': { lat: 56.2639, lng: 9.5018 },
        'Finland': { lat: 61.9241, lng: 25.7482 },
        'Poland': { lat: 51.9194, lng: 19.1451 },
        'Austria': { lat: 47.5162, lng: 14.5501 },
        'Portugal': { lat: 39.3999, lng: -8.2245 },
        'Ireland': { lat: 53.4129, lng: -8.2439 },
        'Belgium': { lat: 50.5039, lng: 4.4699 },
        'Czech Republic': { lat: 49.8175, lng: 15.4730 },
        'Hungary': { lat: 47.1625, lng: 19.5033 },
        'Croatia': { lat: 45.1000, lng: 15.2000 },
        'Morocco': { lat: 31.7917, lng: -7.0926 },
        'Kenya': { lat: -1.2864, lng: 36.8172 },
        'Sri Lanka': { lat: 7.8731, lng: 80.7718 },
        'Nepal': { lat: 28.3949, lng: 84.1240 },
        'Cambodia': { lat: 12.5657, lng: 104.9910 },
        'Myanmar': { lat: 21.9162, lng: 95.9560 },
        'Laos': { lat: 19.8563, lng: 102.4955 },
        'Brunei': { lat: 4.5353, lng: 114.7277 },
        'Maldives': { lat: 3.2028, lng: 73.2207 },
        'Jordan': { lat: 30.5852, lng: 36.2384 },
        'Qatar': { lat: 25.3548, lng: 51.1839 },
        'Oman': { lat: 21.4735, lng: 55.9754 },
        'Kuwait': { lat: 29.3117, lng: 47.4818 },
        'Bahrain': { lat: 26.0667, lng: 50.5577 }
    };

    // Complete places data for ALL countries
    const placesData = {
        'Malaysia': [
            { name: 'Kuala Lumpur', icon: '🌆', desc: 'Petronas Twin Towers, Batu Caves, shopping malls' },
            { name: 'Penang', icon: '🏝️', desc: 'George Town heritage, street food paradise' },
            { name: 'Langkawi', icon: '🏖️', desc: 'Cable car, island hopping, duty-free' },
            { name: 'Cameron Highlands', icon: '⛰️', desc: 'Tea plantations, cool climate, strawberry farms' },
            { name: 'Sabah', icon: '🦧', desc: 'Mount Kinabalu, orangutans, Sipadan diving' },
            { name: 'Johor Bahru', icon: '🏙️', desc: 'Legoland, shopping, Desaru beach' },
            { name: 'Melaka', icon: '🏛️', desc: 'Historical city, Jonker Street, Portuguese settlement' }
        ],
        'Thailand': [
            { name: 'Bangkok', icon: '🏙️', desc: 'Grand Palace, floating markets, nightlife' },
            { name: 'Phuket', icon: '🏖️', desc: 'Phi Phi Islands, beautiful beaches, parties' },
            { name: 'Chiang Mai', icon: '🏯', desc: 'Temples, elephant sanctuaries, night market' },
            { name: 'Krabi', icon: '🌊', desc: 'Railay Beach, rock climbing, islands' },
            { name: 'Pattaya', icon: '🎡', desc: 'Water parks, coral islands, nightlife' },
            { name: 'Koh Samui', icon: '🏝️', desc: 'Luxury resorts, palm-fringed beaches' },
            { name: 'Ayutthaya', icon: '🏛️', desc: 'Ancient temples, historical park' }
        ],
        'Singapore': [
            { name: 'Marina Bay Sands', icon: '🏨', desc: 'Infinity pool, sky park, casino' },
            { name: 'Gardens by the Bay', icon: '🌳', desc: 'Supertree Grove, Cloud Forest, Flower Dome' },
            { name: 'Sentosa Island', icon: '🎢', desc: 'Universal Studios, beaches, adventure' },
            { name: 'Chinatown', icon: '🏮', desc: 'Heritage, street food, temples' },
            { name: 'Orchard Road', icon: '🛍️', desc: 'Shopping paradise, luxury brands' },
            { name: 'Singapore Zoo', icon: '🦁', desc: 'Night Safari, River Wonders, bird park' }
        ],
        'Indonesia': [
            { name: 'Bali', icon: '🏝️', desc: 'Beaches, rice terraces, temples, surfing' },
            { name: 'Jakarta', icon: '🏙️', desc: 'Capital city, shopping malls, nightlife' },
            { name: 'Yogyakarta', icon: '🏯', desc: 'Borobudur Temple, Prambanan, culture' },
            { name: 'Komodo Island', icon: '🦎', desc: 'Komodo dragons, pink beach, diving' },
            { name: 'Lombok', icon: '🏖️', desc: 'Volcanoes, surfing, waterfalls' },
            { name: 'Bandung', icon: '⛰️', desc: 'Cool climate, volcanic landscapes, shopping' }
        ],
        'Vietnam': [
            { name: 'Hanoi', icon: '🏙️', desc: 'Old Quarter, Hoan Kiem Lake, street food' },
            { name: 'Ho Chi Minh City', icon: '🌆', desc: 'Notre Dame, Ben Thanh Market, Cu Chi Tunnels' },
            { name: 'Ha Long Bay', icon: '🌊', desc: 'Limestone islands, cruise, kayaking' },
            { name: 'Da Nang', icon: '🏖️', desc: 'Golden Bridge, My Khe Beach, Marble Mountains' },
            { name: 'Hoi An', icon: '🏮', desc: 'Ancient town, lanterns, tailor shops' },
            { name: 'Nha Trang', icon: '🏝️', desc: 'Beaches, mud baths, diving, Vinpearl' }
        ],
        'Philippines': [
            { name: 'Manila', icon: '🏙️', desc: 'Intramuros, Rizal Park, shopping malls' },
            { name: 'Boracay', icon: '🏖️', desc: 'White Beach, water sports, nightlife' },
            { name: 'Cebu', icon: '⛪', desc: 'Magellans Cross, whale sharks, chocolate hills' },
            { name: 'Palawan', icon: '🏝️', desc: 'Underground River, lagoons, island hopping' },
            { name: 'Baguio', icon: '⛰️', desc: 'Cool climate, strawberry farms, parks' },
            { name: 'Davao', icon: '🦅', desc: 'Eagle sanctuary, durian, beaches' }
        ],
        'Japan': [
            { name: 'Tokyo', icon: '🗼', desc: 'Shibuya, Shinjuku, Akihabara, Disneyland' },
            { name: 'Osaka', icon: '🏯', desc: 'Dotonbori, Universal Studios, castle' },
            { name: 'Kyoto', icon: '⛩️', desc: 'Fushimi Inari, Arashiyama bamboo forest, geishas' },
            { name: 'Hokkaido', icon: '⛷️', desc: 'Skiing, snow festivals, seafood, lavender fields' },
            { name: 'Okinawa', icon: '🏖️', desc: 'Tropical beaches, unique culture, diving' },
            { name: 'Hiroshima', icon: '🏛️', desc: 'Peace Memorial, Miyajima Island, oysters' },
            { name: 'Nagoya', icon: '🏰', desc: 'Castle, Toyota museum, shopping' }
        ],
        'South Korea': [
            { name: 'Seoul', icon: '🏙️', desc: 'Myeongdong, Gyeongbokgung Palace, N Seoul Tower' },
            { name: 'Busan', icon: '🏖️', desc: 'Haeundae Beach, Gamcheon Village, seafood' },
            { name: 'Jeju Island', icon: '🏝️', desc: 'Hallasan Mountain, waterfalls, beaches, lava tubes' },
            { name: 'Gyeongju', icon: '🏯', desc: 'Ancient capital, Bulguksa Temple, tombs' },
            { name: 'Incheon', icon: '🌉', desc: 'Chinatown, Songdo Central Park, airport' },
            { name: 'Daegu', icon: '🏙️', desc: 'Textile industry, spas, mountains' }
        ],
        'China': [
            { name: 'Beijing', icon: '🏯', desc: 'Great Wall, Forbidden City, Summer Palace' },
            { name: 'Shanghai', icon: '🌆', desc: 'The Bund, Disneyland, skyscrapers' },
            { name: 'Hong Kong', icon: '🏙️', desc: 'Victoria Harbour, Disneyland, shopping' },
            { name: 'Zhangjiajie', icon: '⛰️', desc: 'Avatar Mountains, glass bridge, canyons' },
            { name: 'Chengdu', icon: '🐼', desc: 'Panda base, spicy food, temples' },
            { name: 'Xi\'an', icon: '🏛️', desc: 'Terracotta Warriors, ancient city wall' },
            { name: 'Guilin', icon: '🏞️', desc: 'Li River, karst mountains, rice terraces' }
        ],
        'India': [
            { name: 'Mumbai', icon: '🌆', desc: 'Gateway of India, Bollywood, beaches' },
            { name: 'Delhi', icon: '🏛️', desc: 'Red Fort, India Gate, Qutub Minar' },
            { name: 'Jaipur', icon: '🏰', desc: 'Hawa Mahal, Amer Fort, pink city' },
            { name: 'Goa', icon: '🏖️', desc: 'Beaches, nightlife, Portuguese culture' },
            { name: 'Kerala', icon: '🌴', desc: 'Backwaters, houseboats, tea gardens, Ayurveda' },
            { name: 'Agra', icon: '🏛️', desc: 'Taj Mahal, Agra Fort, Fatehpur Sikri' },
            { name: 'Varanasi', icon: '🕉️', desc: 'Ganges River, ghats, spiritual ceremonies' }
        ],
        'France': [
            { name: 'Paris', icon: '🗼', desc: 'Eiffel Tower, Louvre Museum, Notre-Dame, Seine cruise' },
            { name: 'French Riviera', icon: '🏖️', desc: 'Nice, Cannes, Monaco, beaches, glamour' },
            { name: 'Provence', icon: '🌸', desc: 'Lavender fields, picturesque villages, wine' },
            { name: 'Bordeaux', icon: '🍷', desc: 'Wine region, vineyards, architecture' },
            { name: 'Mont Saint-Michel', icon: '⛪', desc: 'Medieval abbey island, tides' },
            { name: 'Lyon', icon: '🍽️', desc: 'Gastronomy capital, Roman ruins' },
            { name: 'Strasbourg', icon: '🎄', desc: 'Christmas markets, half-timbered houses' }
        ],
        'United Kingdom': [
            { name: 'London', icon: '🏰', desc: 'Big Ben, London Eye, Buckingham Palace, Tower Bridge' },
            { name: 'Edinburgh', icon: '🏴󠁧󠁢󠁳󠁣󠁴󠁿', desc: 'Edinburgh Castle, Royal Mile, festivals' },
            { name: 'Manchester', icon: '⚽', desc: 'Football, music, vibrant culture' },
            { name: 'Liverpool', icon: '🎵', desc: 'The Beatles, maritime history, museums' },
            { name: 'Bath', icon: '🛁', desc: 'Roman baths, Georgian architecture' },
            { name: 'Cambridge', icon: '📚', desc: 'University, punting, historic buildings' },
            { name: 'Oxford', icon: '📖', desc: 'University, dreaming spires, libraries' }
        ],
        'Australia': [
            { name: 'Sydney', icon: '🏛️', desc: 'Opera House, Harbour Bridge, Bondi Beach' },
            { name: 'Melbourne', icon: '🎨', desc: 'Arts, coffee, sports, Great Ocean Road' },
            { name: 'Gold Coast', icon: '🏖️', desc: 'Surfers Paradise, theme parks, beaches' },
            { name: 'Cairns', icon: '🐠', desc: 'Great Barrier Reef, Daintree Rainforest' },
            { name: 'Perth', icon: '🏝️', desc: 'Swan River, Rottnest Island, quokkas' },
            { name: 'Uluru', icon: '⛰️', desc: 'Sacred red rock, desert landscape, sunset' },
            { name: 'Hobart', icon: '🏛️', desc: 'MONA museum, Mount Wellington, seafood' }
        ],
        'Turkey': [
            { name: 'Istanbul', icon: '🏛️', desc: 'Hagia Sophia, Blue Mosque, Grand Bazaar, Bosphorus' },
            { name: 'Cappadocia', icon: '🎈', desc: 'Hot air balloons, fairy chimneys, cave hotels' },
            { name: 'Antalya', icon: '🏖️', desc: 'Beaches, old town, waterfalls, ruins' },
            { name: 'Pamukkale', icon: '🩵', desc: 'White terraces, thermal pools, Hierapolis' },
            { name: 'Ephesus', icon: '🏯', desc: 'Ancient Roman ruins, library, amphitheater' },
            { name: 'Bodrum', icon: '🏝️', desc: 'Beaches, nightlife, castle, marinas' }
        ],
        'Egypt': [
            { name: 'Cairo', icon: '🏛️', desc: 'Pyramids, Sphinx, Egyptian Museum, Islamic Cairo' },
            { name: 'Luxor', icon: '🏯', desc: 'Valley of Kings, Karnak Temple, hot air balloons' },
            { name: 'Hurghada', icon: '🤿', desc: 'Red Sea diving, beaches, resorts' },
            { name: 'Sharm El Sheikh', icon: '🏖️', desc: 'Coral reefs, Ras Mohammed, diving' },
            { name: 'Aswan', icon: '⛵', desc: 'Nile cruise, Philae Temple, Nubian villages' },
            { name: 'Alexandria', icon: '🏛️', desc: 'Mediterranean coast, library, Roman ruins' }
        ],
        'UAE': [
            { name: 'Dubai', icon: '🏙️', desc: 'Burj Khalifa, Palm Jumeirah, Dubai Mall, desert safari' },
            { name: 'Abu Dhabi', icon: '🕌', desc: 'Sheikh Zayed Mosque, Ferrari World, Louvre' },
            { name: 'Sharjah', icon: '🎨', desc: 'Museums, cultural sites, heritage areas' },
            { name: 'Ras Al Khaimah', icon: '🏔️', desc: 'Mountains, beaches, Jebel Jais' },
            { name: 'Fujairah', icon: '🏖️', desc: 'Beaches, diving, historic forts' }
        ],
        'Italy': [
            { name: 'Rome', icon: '🏛️', desc: 'Colosseum, Vatican, Trevi Fountain, Pantheon' },
            { name: 'Venice', icon: '🛶', desc: 'Canals, gondola rides, St Marks Square, Rialto Bridge' },
            { name: 'Florence', icon: '🎨', desc: 'David, Uffizi, Duomo, Tuscan cuisine' },
            { name: 'Milan', icon: '🛍️', desc: 'Fashion, Duomo, Last Supper, shopping' },
            { name: 'Naples', icon: '🍕', desc: 'Pizza, Pompeii, Amalfi Coast, Vesuvius' },
            { name: 'Pisa', icon: '🗼', desc: 'Leaning Tower, cathedral, baptistery' },
            { name: 'Cinque Terre', icon: '🏘️', desc: 'Colorful coastal villages, hiking trails' }
        ],
        'Spain': [
            { name: 'Barcelona', icon: '🏯', desc: 'Sagrada Familia, Park Guell, beach, Gothic Quarter' },
            { name: 'Madrid', icon: '🏛️', desc: 'Prado Museum, Royal Palace, nightlife, Retiro Park' },
            { name: 'Seville', icon: '💃', desc: 'Alcazar, flamenco, Plaza de España, cathedral' },
            { name: 'Granada', icon: '🏰', desc: 'Alhambra, Sierra Nevada, tapas' },
            { name: 'Valencia', icon: '🏖️', desc: 'City of Arts, beaches, paella' },
            { name: 'Ibiza', icon: '🎵', desc: 'Beaches, clubs, parties, sunset' },
            { name: 'Mallorca', icon: '🏝️', desc: 'Beaches, mountains, cathedral, villages' }
        ],
        'Germany': [
            { name: 'Berlin', icon: '🏛️', desc: 'Brandenburg Gate, Berlin Wall, museums, nightlife' },
            { name: 'Munich', icon: '🍺', desc: 'Oktoberfest, castles, BMW museum, English Garden' },
            { name: 'Hamburg', icon: '🚢', desc: 'Port, Reeperbahn, miniature wonderland' },
            { name: 'Cologne', icon: '⛪', desc: 'Cathedral, chocolate museum, Rhine River' },
            { name: 'Frankfurt', icon: '🏙️', desc: 'Financial hub, skyline, Römer square' },
            { name: 'Black Forest', icon: '🌲', desc: 'Forests, cuckoo clocks, hiking, waterfalls' },
            { name: 'Neuschwanstein', icon: '🏰', desc: 'Fairytale castle, Alps, Disney inspiration' }
        ],
        'Switzerland': [
            { name: 'Zurich', icon: '🏙️', desc: 'Lake Zurich, Old Town, shopping, museums' },
            { name: 'Geneva', icon: '🌊', desc: 'Lake Geneva, Jet d\'Eau, UN, Red Cross' },
            { name: 'Interlaken', icon: '⛰️', desc: 'Jungfraujoch, adventure sports, hiking, paragliding' },
            { name: 'Lucerne', icon: '🌉', desc: 'Chapel Bridge, mountain views, lake cruises' },
            { name: 'Zermatt', icon: '⛷️', desc: 'Matterhorn, skiing, hiking, car-free village' },
            { name: 'Bern', icon: '🏛️', desc: 'Old town, bear park, Zytglogge clock' }
        ],
        'Netherlands': [
            { name: 'Amsterdam', icon: '🌷', desc: 'Canals, museums, tulips, red light district' },
            { name: 'Rotterdam', icon: '🏗️', desc: 'Modern architecture, port, markets' },
            { name: 'The Hague', icon: '🏛️', desc: 'Peace Palace, beach at Scheveningen, government' },
            { name: 'Utrecht', icon: '🏰', desc: 'Dom Tower, canals, old city center' },
            { name: 'Keukenhof', icon: '🌷', desc: 'Tulip gardens (spring only), flowers' },
            { name: 'Giethoorn', icon: '🚣', desc: 'Venice of the North, canals, thatched roofs' }
        ],
        'Greece': [
            { name: 'Athens', icon: '🏛️', desc: 'Acropolis, Parthenon, Plaka, ancient ruins' },
            { name: 'Santorini', icon: '🏝️', desc: 'White buildings, blue domes, sunset, volcano' },
            { name: 'Mykonos', icon: '🎵', desc: 'Beaches, nightlife, windmills, shopping' },
            { name: 'Crete', icon: '🏖️', desc: 'Palace of Knossos, Elafonissi beach, Samaria Gorge' },
            { name: 'Rhodes', icon: '🏰', desc: 'Medieval town, beaches, ancient ruins' },
            { name: 'Corfu', icon: '🏝️', desc: 'Venetian architecture, beaches, green landscapes' }
        ],
        'New Zealand': [
            { name: 'Auckland', icon: '🌆', desc: 'Sky Tower, harbors, volcanoes, wineries' },
            { name: 'Queenstown', icon: '⛷️', desc: 'Adventure sports, Milford Sound, bungee jumping' },
            { name: 'Rotorua', icon: '🌋', desc: 'Geothermal, Maori culture, hot springs' },
            { name: 'Wellington', icon: '🏛️', desc: 'Te Papa museum, cable car, craft beer' },
            { name: 'Christchurch', icon: '🏙️', desc: 'Garden city, rebuild after quake, punting' },
            { name: 'Mount Cook', icon: '⛰️', desc: 'Highest peak, hiking, stargazing' }
        ],
        'Brazil': [
            { name: 'Rio de Janeiro', icon: '🏖️', desc: 'Christ the Redeemer, Copacabana, Sugarloaf, samba' },
            { name: 'Sao Paulo', icon: '🌆', desc: 'Arts, dining, nightlife, museums' },
            { name: 'Iguazu Falls', icon: '💧', desc: 'Massive waterfalls, national park, boat rides' },
            { name: 'Salvador', icon: '🎵', desc: 'Pelourinho, capoeira, beaches, African culture' },
            { name: 'Amazon Rainforest', icon: '🌳', desc: 'Jungle tours, wildlife, river cruises' },
            { name: 'Florianopolis', icon: '🏖️', desc: 'Beaches, lagoons, hiking, surfing' }
        ],
        'South Africa': [
            { name: 'Cape Town', icon: '🏔️', desc: 'Table Mountain, Robben Island, beaches, winelands' },
            { name: 'Johannesburg', icon: '🌆', desc: 'Apartheid Museum, Soweto, shopping' },
            { name: 'Kruger Park', icon: '🦁', desc: 'Big Five safari, wildlife, game drives' },
            { name: 'Durban', icon: '🏖️', desc: 'Golden Mile beaches, Indian influence, markets' },
            { name: 'Garden Route', icon: '🌲', desc: 'Scenic drive, coastal towns, forests, lagoons' }
        ],
        'Russia': [
            { name: 'Moscow', icon: '🏛️', desc: 'Red Square, Kremlin, St Basils Cathedral' },
            { name: 'St Petersburg', icon: '🏰', desc: 'Hermitage Museum, Winter Palace, canals' },
            { name: 'Lake Baikal', icon: '💧', desc: 'Deepest lake, ice skating, hiking' },
            { name: 'Sochi', icon: '🏖️', desc: 'Black Sea resort, mountains, Formula 1' }
        ],
        'Canada': [
            { name: 'Toronto', icon: '🗼', desc: 'CN Tower, Niagara Falls, museums' },
            { name: 'Vancouver', icon: '🏔️', desc: 'Mountains, ocean, Stanley Park' },
            { name: 'Banff', icon: '⛰️', desc: 'Rocky Mountains, Lake Louise, skiing' },
            { name: 'Montreal', icon: '🏛️', desc: 'Old port, Notre-Dame, festivals' },
            { name: 'Quebec City', icon: '🏰', desc: 'Old town, Château Frontenac, winter carnival' }
        ],
        'Mexico': [
            { name: 'Cancun', icon: '🏖️', desc: 'Beaches, Mayan ruins, nightlife' },
            { name: 'Mexico City', icon: '🏛️', desc: 'Pyramids, museums, Frida Kahlo' },
            { name: 'Tulum', icon: '🏝️', desc: 'Beach ruins, cenotes, eco-resorts' },
            { name: 'Cabo San Lucas', icon: '🏖️', desc: 'Arch rock, diving, whale watching' },
            { name: 'Guadalajara', icon: '🏛️', desc: 'Mariachi, tequila, colonial architecture' }
        ],
        'Argentina': [
            { name: 'Buenos Aires', icon: '💃', desc: 'Tango, European architecture, steak' },
            { name: 'Patagonia', icon: '⛰️', desc: 'Glaciers, mountains, hiking' },
            { name: 'Iguazu Falls', icon: '💧', desc: 'Massive waterfalls, jungle' },
            { name: 'Mendoza', icon: '🍷', desc: 'Wine region, Andes views' }
        ],
        'Sweden': [
            { name: 'Stockholm', icon: '🏛️', desc: 'Archipelago, Gamla Stan, ABBA museum' },
            { name: 'Gothenburg', icon: '🏙️', desc: 'Canals, seafood, Liseberg' },
            { name: 'Malmö', icon: '🌉', desc: 'Turning Torso, parks, bridge to Denmark' },
            { name: 'Kiruna', icon: '❄️', desc: 'Icehotel, Northern Lights, midnight sun' }
        ],
        'Norway': [
            { name: 'Oslo', icon: '🏛️', desc: 'Viking museum, opera house, fjords' },
            { name: 'Bergen', icon: '🏔️', desc: 'Bryggen wharf, funicular, seafood' },
            { name: 'Tromsø', icon: '❄️', desc: 'Northern Lights, Arctic Cathedral' },
            { name: 'Stavanger', icon: '🏔️', desc: 'Pulpit Rock, oil museum' }
        ],
        'Denmark': [
            { name: 'Copenhagen', icon: '🧜‍♀️', desc: 'Little Mermaid, Tivoli, Nyhavn' },
            { name: 'Aarhus', icon: '🏛️', desc: 'ARoS museum, old town, cafes' },
            { name: 'Odense', icon: '📖', desc: 'Hans Christian Andersen museum' }
        ],
        'Finland': [
            { name: 'Helsinki', icon: '🏛️', desc: 'Suomenlinna, design district, saunas' },
            { name: 'Rovaniemi', icon: '🎅', desc: 'Santa Claus Village, Northern Lights' },
            { name: 'Tampere', icon: '🏭', desc: 'Sauna capital, lake views' }
        ],
        'Poland': [
            { name: 'Krakow', icon: '🏯', desc: 'Wawel Castle, salt mines, Auschwitz' },
            { name: 'Warsaw', icon: '🏙️', desc: 'Old town, palace of culture' },
            { name: 'Gdansk', icon: '🌊', desc: 'Baltic coast, amber, Solidarity museum' },
            { name: 'Wroclaw', icon: '🏛️', desc: 'Dwarf statues, market square' }
        ],
        'Austria': [
            { name: 'Vienna', icon: '🎵', desc: 'Schönbrunn, opera, cafes' },
            { name: 'Salzburg', icon: '🎶', desc: 'Sound of Music, fortress, Mozart' },
            { name: 'Innsbruck', icon: '⛰️', desc: 'Alps, ski jumping, golden roof' },
            { name: 'Hallstatt', icon: '🏘️', desc: 'Picturesque village, lake, salt mine' }
        ],
        'Portugal': [
            { name: 'Lisbon', icon: '🏛️', desc: 'Belem Tower, trams, pastel de nata' },
            { name: 'Porto', icon: '🍷', desc: 'Port wine, Douro Valley, bridges' },
            { name: 'Algarve', icon: '🏖️', desc: 'Beaches, cliffs, caves' },
            { name: 'Sintra', icon: '🏰', desc: 'Pena Palace, castles, gardens' }
        ],
        'Ireland': [
            { name: 'Dublin', icon: '🍺', desc: 'Guinness, Trinity College, pubs' },
            { name: 'Galway', icon: '🎵', desc: 'Traditional music, Cliffs of Moher' },
            { name: 'Cork', icon: '🏛️', desc: 'Blarney Castle, English Market' },
            { name: 'Killarney', icon: '🏞️', desc: 'National park, lakes, Ring of Kerry' }
        ],
        'Belgium': [
            { name: 'Brussels', icon: '🍫', desc: 'Grand Place, waffles, chocolate, Manneken Pis' },
            { name: 'Bruges', icon: '🏰', desc: 'Canals, medieval architecture, lace' },
            { name: 'Ghent', icon: '🏛️', desc: 'Castle, graffiti alley, student vibe' },
            { name: 'Antwerp', icon: '💎', desc: 'Diamond district, fashion, Rubens' }
        ],
        'Czech Republic': [
            { name: 'Prague', icon: '🏰', desc: 'Charles Bridge, castle, beer' },
            { name: 'Cesky Krumlov', icon: '🏘️', desc: 'UNESCO town, castle, river' },
            { name: 'Karlovy Vary', icon: '💧', desc: 'Hot springs, spa town' }
        ],
        'Hungary': [
            { name: 'Budapest', icon: '🌉', desc: 'Chain Bridge, thermal baths, Parliament' },
            { name: 'Lake Balaton', icon: '💧', desc: 'Largest lake, beaches, wine' },
            { name: 'Eger', icon: '🍷', desc: 'Castle, wine cellars, Baroque buildings' }
        ],
        'Croatia': [
            { name: 'Dubrovnik', icon: '🏰', desc: 'Game of Thrones, walls, Adriatic' },
            { name: 'Split', icon: '🏛️', desc: 'Diocletian Palace, ferries' },
            { name: 'Plitvice Lakes', icon: '💧', desc: 'Waterfalls, lakes, national park' },
            { name: 'Hvar', icon: '🏝️', desc: 'Island, nightlife, lavender fields' }
        ],
        'Morocco': [
            { name: 'Marrakech', icon: '🏛️', desc: 'Souks, gardens, palaces' },
            { name: 'Casablanca', icon: '🕌', desc: 'Hassan II Mosque, coastal city' },
            { name: 'Fes', icon: '🏯', desc: 'Medina, tanneries, ancient university' },
            { name: 'Chefchaouen', icon: '💙', desc: 'Blue city, mountain views' }
        ],
        'Kenya': [
            { name: 'Nairobi', icon: '🦒', desc: 'Giraffe Centre, national park' },
            { name: 'Maasai Mara', icon: '🦁', desc: 'Great Migration, safari' },
            { name: 'Mombasa', icon: '🏖️', desc: 'Beaches, old town, Fort Jesus' },
            { name: 'Tsavo', icon: '🐘', desc: 'Red elephants, national park' }
        ],
        'Sri Lanka': [
            { name: 'Colombo', icon: '🏙️', desc: 'Capital, markets, temples' },
            { name: 'Kandy', icon: '🕉️', desc: 'Temple of Tooth, lake, tea' },
            { name: 'Galle', icon: '🏯', desc: 'Dutch fort, beaches, cafes' },
            { name: 'Sigiriya', icon: '🪨', desc: 'Lion rock fortress, frescoes' }
        ],
        'Nepal': [
            { name: 'Kathmandu', icon: '🕉️', desc: 'Durbar Square, temples, monkeys' },
            { name: 'Pokhara', icon: '🏔️', desc: 'Himalayas views, paragliding' },
            { name: 'Mount Everest', icon: '⛰️', desc: 'Base camp trek, world highest peak' }
        ],
        'Cambodia': [
            { name: 'Siem Reap', icon: '🏯', desc: 'Angkor Wat, temples, floating village' },
            { name: 'Phnom Penh', icon: '🏛️', desc: 'Royal Palace, killing fields' },
            { name: 'Sihanoukville', icon: '🏖️', desc: 'Beaches, islands' }
        ],
        'Myanmar': [
            { name: 'Bagan', icon: '🏯', desc: 'Temples, pagodas, hot air balloons' },
            { name: 'Yangon', icon: '🕉️', desc: 'Shwedagon Pagoda, colonial buildings' },
            { name: 'Inle Lake', icon: '💧', desc: 'Floating gardens, leg rowers' }
        ],
        'Laos': [
            { name: 'Luang Prabang', icon: '🏯', desc: 'Waterfalls, temples, alms giving' },
            { name: 'Vientiane', icon: '🕉️', desc: 'Capital, That Luang stupa' },
            { name: 'Vang Vieng', icon: '🏞️', desc: 'Limestone karsts, tubing' }
        ],
        'Brunei': [
            { name: 'Bandar Seri Begawan', icon: '🕌', desc: 'Omar Ali Saifuddien Mosque, water village' },
            { name: 'Kuala Belait', icon: '🏖️', desc: 'Beaches, oil town' },
            { name: 'Temburong', icon: '🌳', desc: 'Rainforest, canopy walk' }
        ],
        'Maldives': [
            { name: 'Male', icon: '🏙️', desc: 'Capital, fish market, mosque' },
            { name: 'Maafushi', icon: '🏖️', desc: 'Budget island, beaches, diving' },
            { name: 'Resort Islands', icon: '🏝️', desc: 'Overwater bungalows, luxury' }
        ],
        'Jordan': [
            { name: 'Petra', icon: '🏛️', desc: 'Ancient city, rock-cut architecture' },
            { name: 'Wadi Rum', icon: '🏜️', desc: 'Desert valley, jeep tours, camping' },
            { name: 'Dead Sea', icon: '💧', desc: 'Lowest point, floating, mud' },
            { name: 'Amman', icon: '🏙️', desc: 'Citadel, Roman theater, markets' }
        ],
        'Qatar': [
            { name: 'Doha', icon: '🏙️', desc: 'Museum of Islamic Art, souq, skyscrapers' },
            { name: 'Katara', icon: '🎨', desc: 'Cultural village, amphitheater' },
            { name: 'Desert Safari', icon: '🏜️', desc: 'Dune bashing, camel rides' }
        ],
        'Oman': [
            { name: 'Muscat', icon: '🕌', desc: 'Grand Mosque, Muttrah Souq, forts' },
            { name: 'Salalah', icon: '🌴', desc: 'Frankincense, monsoon season' },
            { name: 'Wahiba Sands', icon: '🏜️', desc: 'Desert camping, Bedouin' }
        ],
        'Kuwait': [
            { name: 'Kuwait City', icon: '🏙️', desc: 'Towers, Grand Mosque, souq' },
            { name: 'Failaka Island', icon: '🏝️', desc: 'Ancient ruins, beaches' }
        ],
        'Bahrain': [
            { name: 'Manama', icon: '🏙️', desc: 'Bab Al Bahrain, souq, museums' },
            { name: 'Bahrain Fort', icon: '🏯', desc: 'UNESCO site, ancient civilization' },
            { name: 'Tree of Life', icon: '🌳', desc: 'Solitary tree in desert' }
        ]
    };

    function selectPlace(placeName, placeIcon, countryName, element) {
        currentSelectedPlace = placeName;
        currentSelectedCountry = countryName;
        
        document.querySelectorAll('.place-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        if (element) {
            element.classList.add('selected');
        }
        
        document.getElementById('selectedPlaceName').innerHTML = `${placeIcon} ${placeName}, ${countryName}`;
        document.getElementById('selectedPlaceDiv').style.display = 'block';
        
        updateBudgetLinks(countryName, placeName);
        
        alert(`✨ You selected ${placeName}, ${countryName}!\n\nClick Budget, Moderate, or Luxury to plan your trip.`);
    }
    
    function updateBudgetLinks(country, place) {
        const encodedCountry = encodeURIComponent(country);
        const encodedPlace = encodeURIComponent(place);
        
        if (place) {
            document.getElementById('budgetLink').href = `budget.php?country=${encodedCountry}&place=${encodedPlace}`;
            document.getElementById('moderateLink').href = `moderate.php?country=${encodedCountry}&place=${encodedPlace}`;
            document.getElementById('luxuryLink').href = `luxury.php?country=${encodedCountry}&place=${encodedPlace}`;
        } else {
            document.getElementById('budgetLink').href = `budget.php?country=${encodedCountry}`;
            document.getElementById('moderateLink').href = `moderate.php?country=${encodedCountry}`;
            document.getElementById('luxuryLink').href = `luxury.php?country=${encodedCountry}`;
        }
    }

    async function getWeatherAndMap() {
        currentSelectedCountry = document.getElementById('countrySelect').value;
        const country = currentSelectedCountry;
        
        currentSelectedPlace = '';
        document.getElementById('selectedPlaceDiv').style.display = 'none';
        
        if (!country) {
            document.getElementById('weatherResult').classList.remove('active');
            document.getElementById('placesSection').style.display = 'none';
            document.getElementById('budgetSection').style.display = 'none';
            return;
        }
        
        const coords = countryCoordinates[country];
        const places = placesData[country] || [{ name: country, icon: '🌍', desc: 'Explore beautiful destinations' }];
        
        if (coords) {
            map.setCenter({ lat: coords.lat, lng: coords.lng });
            map.setZoom(6);
            marker.setPosition({ lat: coords.lat, lng: coords.lng });
            marker.setTitle(country);
            
            const weatherDiv = document.getElementById('weatherContent');
            weatherDiv.innerHTML = `<div style="text-align:center; padding:20px;"><div class="loading-spinner"></div> Loading weather for ${country}...</div>`;
            document.getElementById('weatherResult').classList.add('active');
            
            try {
                const apiUrl = `https://api.open-meteo.com/v1/forecast?latitude=${coords.lat}&longitude=${coords.lng}&current_weather=true&hourly=temperature_2m,relative_humidity_2m,wind_speed_10m,uv_index&timezone=auto`;
                const response = await fetch(apiUrl);
                const weatherData = await response.json();
                
                const current = weatherData.current_weather || {};
                const hourly = weatherData.hourly || {};
                
                const temp = Math.round(current.temperature || 25);
                const wind = Math.round(current.windspeed || 10);
                const humidity = hourly.relative_humidity_2m ? Math.round(hourly.relative_humidity_2m[0]) : 65;
                const uv = hourly.uv_index ? Math.round(hourly.uv_index[0]) : 6;
                
                const weatherCodes = {
                    0: 'Clear Sky', 1: 'Mainly Clear', 2: 'Partly Cloudy',
                    3: 'Overcast', 45: 'Foggy', 51: 'Light Drizzle',
                    61: 'Rain', 63: 'Rain', 65: 'Heavy Rain',
                    71: 'Snow', 95: 'Thunderstorm'
                };
                const desc = weatherCodes[current.weathercode] || 'Good Weather';
                
                let safe = true;
                let advice = "✅ Good weather for traveling! ";
                
                if (temp > 35) { safe = false; advice = "⚠️ EXTREME HEAT! Stay hydrated and avoid midday sun!"; }
                else if (temp < 5) { safe = false; advice = "⚠️ FREEZING! Pack warm clothes."; }
                else if (wind > 30) { safe = false; advice = "⚠️ STRONG WINDS! Be careful outdoors."; }
                else if (current.weathercode >= 61 && current.weathercode <= 65) { safe = false; advice = "⚠️ RAIN EXPECTED! Bring an umbrella."; }
                else if (temp > 30) { advice = "✅ Warm weather! Wear light clothes and use sunscreen."; }
                else if (temp < 15) { advice = "✅ Cool weather! Bring a jacket."; }
                else { advice = `✅ Perfect weather! Enjoy your trip to ${country}!`; }
                
                const safetyClass = safe ? 'safe' : 'warning';
                const safetyIcon = safe ? 'fa-check-circle' : 'fa-exclamation-triangle';
                
                weatherDiv.innerHTML = `
                    <h3><i class="fas fa-cloud-sun"></i> Current Weather in ${country}</h3>
                    <div class="weather-grid">
                        <div class="weather-card"><i class="fas fa-thermometer-half"></i><div class="value">${temp}°C</div><div class="label">Temperature</div></div>
                        <div class="weather-card"><i class="fas fa-cloud-sun"></i><div class="value">${desc}</div><div class="label">Condition</div></div>
                        <div class="weather-card"><i class="fas fa-tint"></i><div class="value">${humidity}%</div><div class="label">Humidity</div></div>
                        <div class="weather-card"><i class="fas fa-wind"></i><div class="value">${wind} km/h</div><div class="label">Wind Speed</div></div>
                        <div class="weather-card"><i class="fas fa-sun"></i><div class="value">${uv}</div><div class="label">UV Index</div></div>
                    </div>
                    <div class="safety-advice ${safetyClass}">
                        <i class="fas ${safetyIcon}"></i>
                        <strong>AI Travel Safety Advice:</strong> ${advice}
                    </div>
                    <div style="text-align: center; margin-top: 10px; font-size: 0.7rem; color: #a0a0a0;">
                        <i class="fas fa-database"></i> Weather data by Open-Meteo (Free API)
                    </div>
                `;
            } catch (error) {
                weatherDiv.innerHTML = `<div style="text-align:center; padding:20px; color:#ef4444;">❌ Weather data temporarily unavailable. Please try again.</div>`;
            }
            
            document.getElementById('selectedCountry').textContent = country;
            const placesGrid = document.getElementById('placesGrid');
            placesGrid.innerHTML = '';
            
            places.forEach(place => {
                const card = document.createElement('div');
                card.className = 'place-card';
                card.onclick = (function(p, i, c, elem) {
                    return function() {
                        selectPlace(p, i, c, elem);
                    };
                })(place.name, place.icon, country, card);
                card.innerHTML = `<div class="place-icon">${place.icon}</div><div><strong>${place.name}</strong><br><small>${place.desc}</small></div>`;
                placesGrid.appendChild(card);
            });
            
            document.getElementById('placesSection').style.display = 'block';
            document.getElementById('budgetSection').style.display = 'block';
            updateBudgetLinks(country, '');
        }
    }
</script>
</body>
</html>
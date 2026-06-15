<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config/database.php';

// Free OpenWeatherMap API (get your free key from openweathermap.org)
define('OPENWEATHER_API_KEY', 'YOUR_API_KEY_HERE'); // Replace with your key

class TravelAPI {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get weather for destination
    public function getWeather($city) {
        if (OPENWEATHER_API_KEY == 'YOUR_API_KEY_HERE') {
            // Return mock data if no API key
            return [
                'success' => true,
                'data' => [
                    ['date' => date('Y-m-d'), 'temp' => 22, 'description' => 'Sunny', 'icon' => '01d'],
                    ['date' => date('Y-m-d', strtotime('+1 day')), 'temp' => 23, 'description' => 'Partly cloudy', 'icon' => '02d'],
                    ['date' => date('Y-m-d', strtotime('+2 day')), 'temp' => 21, 'description' => 'Sunny', 'icon' => '01d']
                ]
            ];
        }
        
        $url = "https://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($city) . 
               "&appid=" . OPENWEATHER_API_KEY . "&units=metric";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            $forecast = [];
            foreach (array_slice($data['list'], 0, 5) as $item) {
                $forecast[] = [
                    'date' => $item['dt_txt'],
                    'temp' => round($item['main']['temp']),
                    'description' => $item['weather'][0]['description'],
                    'icon' => $item['weather'][0]['icon']
                ];
            }
            return ['success' => true, 'data' => $forecast];
        }
        return ['success' => false, 'error' => 'Weather data unavailable'];
    }
    
    // Generate AI itinerary
    public function generateItinerary($destination, $duration, $budgetTier) {
        $weather = $this->getWeather($destination);
        
        // Popular attractions database
        $attractions = [
            'paris' => [
                ['Eiffel Tower', 'Iconic iron lattice tower', 17, '48.8584° N, 2.2945° E'],
                ['Louvre Museum', "World's largest art museum", 17, '48.8606° N, 2.3376° E'],
                ['Notre-Dame Cathedral', 'Gothic architecture masterpiece', 0, '48.8530° N, 2.3499° E'],
                ['Seine River Cruise', 'Romantic boat tour', 15, '48.8566° N, 2.3522° E'],
                ['Montmartre', 'Artistic hilltop village', 0, '48.8867° N, 2.3431° E'],
                ['Arc de Triomphe', 'Historic monument', 13, '48.8738° N, 2.2950° E'],
                ['Sacre-Coeur', 'White basilica with panoramic views', 0, '48.8867° N, 2.3431° E'],
                ['Orsay Museum', 'Impressionist masterpieces', 14, '48.8600° N, 2.3250° E']
            ],
            'tokyo' => [
                ['Shibuya Crossing', 'Famous scramble crossing', 0, '35.6595° N, 139.7007° E'],
                ['Senso-ji Temple', 'Ancient Buddhist temple', 0, '35.7147° N, 139.7967° E'],
                ['Tokyo Tower', 'Eiffel Tower-inspired landmark', 12, '35.6586° N, 139.7454° E'],
                ['Meiji Shrine', 'Peaceful Shinto shrine', 0, '35.6764° N, 139.6994° E'],
                ['Shinjuku Gyoen', 'Beautiful national garden', 5, '35.6850° N, 139.7090° E'],
                ['Akihabara', 'Electronics and anime district', 0, '35.6984° N, 139.7732° E'],
                ['Tokyo Disneyland', 'Magical theme park', 80, '35.6329° N, 139.8804° E'],
                ['TeamLab Planets', 'Digital art museum', 25, '35.6500° N, 139.8000° E']
            ],
            'new york' => [
                ['Times Square', "Iconic commercial intersection", 0, '40.7580° N, 73.9855° W'],
                ['Central Park', 'Urban oasis', 0, '40.7851° N, 73.9683° W'],
                ['Statue of Liberty', 'Freedom symbol', 24, '40.6892° N, 74.0445° W'],
                ['Empire State Building', 'Art Deco skyscraper', 44, '40.7488° N, 73.9858° W'],
                ['Brooklyn Bridge', 'Historic suspension bridge', 0, '40.7061° N, 73.9969° W'],
                ['Metropolitan Museum', 'World-class art museum', 25, '40.7794° N, 73.9632° W'],
                ['Rockefeller Center', 'Famous complex with Top of the Rock', 38, '40.7587° N, 73.9787° W'],
                ['Broadway', 'Theatre district', 50, '40.7587° N, 73.9855° W']
            ],
            'london' => [
                ['Big Ben', 'Iconic clock tower', 0, '51.5007° N, 0.1246° W'],
                ['London Eye', 'Giant observation wheel', 30, '51.5033° N, 0.1195° W'],
                ['British Museum', 'World history museum', 0, '51.5194° N, 0.1270° W'],
                ['Tower of London', 'Historic castle', 29, '51.5081° N, 0.0759° W'],
                ['Buckingham Palace', 'Royal residence', 30, '51.5014° N, 0.1419° W'],
                ['Hyde Park', 'Royal park', 0, '51.5073° N, 0.1657° W']
            ],
            'bali' => [
                ['Uluwatu Temple', 'Oceanfront temple', 5, '8.8291° S, 115.0849° E'],
                ['Tegallalang Rice Terraces', 'Beautiful rice paddies', 2, '8.4290° S, 115.2767° E'],
                ['Ubud Monkey Forest', 'Sacred monkey sanctuary', 8, '8.5183° S, 115.2595° E'],
                ['Tanah Lot', 'Sea temple', 5, '8.6212° S, 115.0868° E'],
                ['Mount Batur', 'Active volcano sunrise trek', 50, '8.2421° S, 115.3753° E'],
                ['Seminyak Beach', 'Beautiful sunset beach', 0, '8.6846° S, 115.1621° E']
            ]
        ];
        
        $cityKey = strtolower(strtok($destination, ' '));
        $cityAttractions = $attractions[$cityKey] ?? $attractions['paris'];
        
        // Adjust costs based on budget tier
        $costMultiplier = $budgetTier === 'budget' ? 0.5 : ($budgetTier === 'moderate' ? 1 : 2.5);
        
        $itinerary = [];
        $maxDays = min($duration, 7); // Limit to 7 days for display
        
        for ($day = 1; $day <= $maxDays; $day++) {
            $dayActivities = [];
            $indices = array_rand($cityAttractions, min(3, count($cityAttractions)));
            if (!is_array($indices)) $indices = [$indices];
            
            $times = ['09:00 - Morning', '12:30 - Lunch', '15:00 - Afternoon', '19:00 - Evening'];
            
            for ($i = 0; $i < count($indices); $i++) {
                $attraction = $cityAttractions[$indices[$i]];
                $dayActivities[] = [
                    'time' => $times[$i] ?? $times[0],
                    'activity' => $attraction[0],
                    'description' => $attraction[1],
                    'cost' => round($attraction[2] * $costMultiplier, 2),
                    'location' => $attraction[3] ?? ''
                ];
            }
            
            // Add meal suggestions
            $meals = [
                'Breakfast at local café',
                'Lunch at traditional restaurant',
                'Dinner at popular local spot'
            ];
            
            $itinerary[] = [
                'day' => $day,
                'weather' => $weather['success'] && isset($weather['data'][$day-1]) ? $weather['data'][$day-1]['description'] : 'Perfect weather expected',
                'temp' => $weather['success'] && isset($weather['data'][$day-1]) ? $weather['data'][$day-1]['temp'] : null,
                'activities' => $dayActivities,
                'meals' => $meals
            ];
        }
        
        return ['success' => true, 'data' => $itinerary];
    }
}

// API Router
$api = new TravelAPI($pdo);
$path = isset($_GET['path']) ? $_GET['path'] : '';

switch($path) {
    case 'weather':
        if (isset($_GET['city'])) {
            echo json_encode($api->getWeather($_GET['city']));
        }
        break;
        
    case 'itinerary':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            echo json_encode($api->generateItinerary(
                $input['destination'],
                $input['duration'],
                $input['budgetTier']
            ));
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid API endpoint', 'available' => ['weather', 'itinerary']]);
}
?>
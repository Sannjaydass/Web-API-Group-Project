<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$apiKey = 'AIzaSyDCM1eG2rlrOsRQOWH67d46Dmivo8LJAzQ';
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;
$city = $_GET['city'] ?? 'Unknown';

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'error' => 'No coordinates provided']);
    exit;
}

$url = "https://weather.googleapis.com/v1/currentConditions:lookup?" . http_build_query([
    'key' => $apiKey,
    'location.latitude' => $lat,
    'location.longitude' => $lng,
    'unitsSystem' => 'METRIC'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'temp' => $data['temperature']['degrees'] ?? null,
        'desc' => ucfirst($data['weatherCondition']['description']['text'] ?? 'Unknown'),
        'humidity' => $data['relativeHumidity'] ?? null,
        'wind' => $data['wind']['speed']['value'] ?? null,
        'uv_index' => $data['uvIndex'] ?? null,
        'feels_like' => $data['feelsLikeTemperature']['degrees'] ?? null,
        'pressure' => $data['airPressure']['meanSeaLevelMillibars'] ?? null,
        'precipitation' => $data['precipitation']['probability']['percent'] ?? 0
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Weather API error', 'code' => $httpCode]);
}
?>
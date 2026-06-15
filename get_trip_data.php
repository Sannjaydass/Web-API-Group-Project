<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$trip_id = $_GET['id'] ?? 0;
$query = "SELECT * FROM trips WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$trip_id, $_SESSION['user_id']]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if ($trip) {
    // Format dates for datetime-local input
    if ($trip['flight_departure_time']) {
        $trip['flight_departure_time'] = date('Y-m-d\TH:i', strtotime($trip['flight_departure_time']));
    }
    if ($trip['flight_arrival_time']) {
        $trip['flight_arrival_time'] = date('Y-m-d\TH:i', strtotime($trip['flight_arrival_time']));
    }
    echo json_encode($trip);
} else {
    echo json_encode(['error' => 'Trip not found']);
}
?>
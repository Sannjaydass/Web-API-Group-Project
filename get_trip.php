<?php
require_once 'config/database.php';

$tripId = $_GET['id'];
$query = "SELECT * FROM trips WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$tripId, $_SESSION['user_id']]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($trip);
?>
<?php
header("Content-Type: application/json");
require_once "konek.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$lux = isset($_POST['lux']) ? floatval($_POST['lux']) : null;
$ppfd = isset($_POST['ppfd']) ? floatval($_POST['ppfd']) : null;
$dli_cumulative = isset($_POST['dli_cumulative']) ? floatval($_POST['dli_cumulative']) : 0;
$dli_daily = isset($_POST['dli_daily']) ? floatval($_POST['dli_daily']) : 0;
$led_brightness = isset($_POST['led_brightness']) ? intval($_POST['led_brightness']) : 0;
$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
$humidity = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
$battery_voltage = isset($_POST['battery_voltage']) ? floatval($_POST['battery_voltage']) : null;
$wifi_rssi = isset($_POST['wifi_rssi']) ? intval($_POST['wifi_rssi']) : null;
$sampling_interval_sec = isset($_POST['sampling_interval_sec']) ? intval($_POST['sampling_interval_sec']) : 60;
$sensor_status = isset($_POST['sensor_status']) ? $_POST['sensor_status'] : 'OK';
$notes = isset($_POST['notes']) ? $_POST['notes'] : null;

if ($lux === null || $ppfd === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing lux or ppfd"]);
    exit;
}

$sql = "INSERT INTO light_monitoring 
        (timestamp, lux, ppfd, dli_cumulative, dli_daily, led_brightness,
         temperature, humidity, battery_voltage, wifi_rssi,
         sampling_interval_sec, sensor_status, notes) 
        VALUES 
        (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ddddiiddiss",
    $lux, $ppfd, $dli_cumulative, $dli_daily, $led_brightness,
    $temperature, $humidity, $battery_voltage, $wifi_rssi,
    $sampling_interval_sec, $sensor_status, $notes
);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "OK"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
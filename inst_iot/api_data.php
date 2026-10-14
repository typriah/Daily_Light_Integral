<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

include 'konek.php';

$table_limit  = isset($_GET['table_limit'])  ? intval($_GET['table_limit'])  : 10;
$table_offset = isset($_GET['table_offset']) ? intval($_GET['table_offset']) : 0;
$chart_limit  = isset($_GET['chart_limit'])  ? intval($_GET['chart_limit'])  : 50;
$chart_offset = isset($_GET['chart_offset']) ? intval($_GET['chart_offset']) : 0;

$response = [
    'latest'      => null,
    'table'       => [],
    'table_total' => 0,
    'chart'       => []
];

$res = $conn->query("SELECT timestamp, lux, ppfd, dli_daily, temperature, humidity, sensor_status FROM light_monitoring ORDER BY timestamp DESC LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $response['latest'] = [
        'lux'           => (float)$row['lux'],
        'ppfd'          => (float)$row['ppfd'],
        'dli_daily'     => (float)$row['dli_daily'],
        'temperature'   => $row['temperature'] !== null ? (float)$row['temperature'] : null,
        'humidity'      => $row['humidity'] !== null ? (float)$row['humidity'] : null,
        'sensor_status' => $row['sensor_status']
    ];
}

$countRes = $conn->query("SELECT COUNT(*) AS total FROM light_monitoring");
if ($countRow = $countRes->fetch_assoc()) {
    $response['table_total'] = (int)$countRow['total'];
}

$stmt = $conn->prepare("SELECT timestamp, lux, ppfd, dli_daily, temperature, humidity, sensor_status FROM light_monitoring ORDER BY timestamp DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $table_limit, $table_offset);
$stmt->execute();
$tRes = $stmt->get_result();
while ($row = $tRes->fetch_assoc()) {
    $response['table'][] = [
        'timestamp'     => $row['timestamp'],
        'lux'           => (float)$row['lux'],
        'ppfd'          => (float)$row['ppfd'],
        'dli_daily'     => (float)$row['dli_daily'],
        'temperature'   => $row['temperature'] !== null ? (float)$row['temperature'] : null,
        'humidity'      => $row['humidity'] !== null ? (float)$row['humidity'] : null,
        'sensor_status' => $row['sensor_status']
    ];
}
$stmt->close();

$stmt2 = $conn->prepare("SELECT timestamp, lux, ppfd, dli_daily, temperature, humidity FROM (SELECT * FROM light_monitoring ORDER BY timestamp DESC LIMIT ? OFFSET ?) AS sub ORDER BY timestamp ASC");
$stmt2->bind_param("ii", $chart_limit, $chart_offset);
$stmt2->execute();
$cRes = $stmt2->get_result();
while ($row = $cRes->fetch_assoc()) {
    $response['chart'][] = [
        'timestamp'   => $row['timestamp'],
        'lux'         => (float)$row['lux'],
        'ppfd'        => (float)$row['ppfd'],
        'dli_daily'   => (float)$row['dli_daily'],
        'temperature' => $row['temperature'] !== null ? (float)$row['temperature'] : null,
        'humidity'    => $row['humidity'] !== null ? (float)$row['humidity'] : null
    ];
}
$stmt2->close();

echo json_encode($response);
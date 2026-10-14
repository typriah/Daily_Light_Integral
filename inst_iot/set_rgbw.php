<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Gunakan POST.']);
    exit;
}

define('MCU_ENDPOINT', 'http://10.189.5.162/set_light');
define('MCU_TIMEOUT', 3);

include 'konek.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Body JSON tidak valid.']);
    exit;
}

$channels = ['red', 'green', 'blue', 'white'];
$values   = [];

foreach ($channels as $ch) {
    if (!isset($data[$ch])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Field '$ch' wajib diisi."]);
        exit;
    }
    $val = intval($data[$ch]);
    if ($val < 0 || $val > 100) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Nilai '$ch' harus antara 0–100."]);
        exit;
    }
    $values[$ch] = $val;
}

$red   = $values['red'];
$green = $values['green'];
$blue  = $values['blue'];
$white = $values['white'];

$createTable = "
    CREATE TABLE IF NOT EXISTS rgbw_settings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        red_pct     TINYINT UNSIGNED NOT NULL,
        green_pct   TINYINT UNSIGNED NOT NULL,
        blue_pct    TINYINT UNSIGNED NOT NULL,
        white_pct   TINYINT UNSIGNED NOT NULL,
        applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        source      VARCHAR(50) DEFAULT 'dashboard',
        status      VARCHAR(20) DEFAULT 'sent'
    )
";
$conn->query($createTable);

$stmt = $conn->prepare(
    "INSERT INTO rgbw_settings (red_pct, green_pct, blue_pct, white_pct, source)
     VALUES (?, ?, ?, ?, 'dashboard')"
);

$saved = false;
$dbError = '';

if ($stmt) {
    $stmt->bind_param('iiii', $red, $green, $blue, $white);
    $saved   = $stmt->execute();
    $dbError = $saved ? '' : $stmt->error;
    $insertId = $conn->insert_id;
    $stmt->close();
} else {
    $dbError = $conn->error;
}

$mcuResult  = null;
$mcuSuccess = false;
$mcuError   = '';

if (MCU_ENDPOINT !== null) {
    $payload = json_encode([
        'r' => $red,
        'g' => $green,
        'b' => $blue,
        'w' => $white,
    ]);

    $ch = curl_init(MCU_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => MCU_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => MCU_TIMEOUT,
    ]);

    $mcuRaw  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mcuError = curl_error($ch);
    curl_close($ch);

    $mcuSuccess = ($httpCode >= 200 && $httpCode < 300);
    $mcuResult  = $mcuRaw ? json_decode($mcuRaw, true) : null;

    if ($saved && isset($insertId)) {
        $newStatus = $mcuSuccess ? 'applied' : 'db_only';
        $upd = $conn->prepare("UPDATE rgbw_settings SET status = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $newStatus, $insertId);
            $upd->execute();
            $upd->close();
        }
    }
}

if (!$saved) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan ke database.',
        'detail'  => $dbError,
    ]);
    exit;
}

$responseData = [
    'success'  => true,
    'message'  => $mcuSuccess
        ? 'Pengaturan berhasil disimpan dan dikirim ke lampu.'
        : (MCU_ENDPOINT !== null
            ? 'Pengaturan disimpan di database, namun gagal dikirim ke lampu (' . ($mcuError ?: "HTTP $httpCode") . ').'
            : 'Pengaturan berhasil disimpan di database (MCU endpoint belum dikonfigurasi).'),
    'applied'  => [
        'red'   => $red,
        'green' => $green,
        'blue'  => $blue,
        'white' => $white,
    ],
    'db_saved'    => $saved,
    'mcu_reached' => $mcuSuccess,
    'timestamp'   => date('Y-m-d H:i:s'),
];

http_response_code(200);
echo json_encode($responseData);
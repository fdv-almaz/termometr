<?php
require_once 'db.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

date_default_timezone_set('Europe/Warsaw');

// Database connection
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) {
    if (!headers_sent()) http_response_code(500);
    echo "Error: Database connection failed";
    error_log("Database connection error: " . $conn->connect_error);
    exit;
}

$conn->set_charset("utf8mb4");

// Get configuration parameters
$stmt = $conn->prepare("SELECT param_name, param_data FROM config WHERE param_name IN ('ULcorr', 'DOMcorr', 'PRESScorr')");
if (!$stmt) {
    if (!headers_sent()) http_response_code(500);
    echo "Error: Prepare failed";
    error_log("Prepare failed: " . $conn->error);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

$ULcorr = 0;
$DOMcorr = 0;
$PRESScorr = 0;

while ($row = $result->fetch_assoc()) {
    switch ($row['param_name']) {
        case 'ULcorr':
            $ULcorr = (float)$row['param_data'];
            break;
        case 'DOMcorr':
            $DOMcorr = (float)$row['param_data'];
            break;
        case 'PRESScorr':
            $PRESScorr = (float)$row['param_data'];
            break;
    }
}
$stmt->close();

// Get latest data
$stmt = $conn->prepare("SELECT id, dev_id, tempUL, tempDOM, pressure, inserted FROM data ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    if (!headers_sent()) http_response_code(500);
    echo "Error: Prepare failed";
    error_log("Prepare failed: " . $conn->error);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    if (!headers_sent()) http_response_code(404);
    echo "Error: No data found";
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Apply corrections (safe arithmetic instead of eval)
$tempUL_corrected = floatval($row['tempUL']) + $ULcorr;
$tempDOM_corrected = floatval($row['tempDOM']) + $DOMcorr;
$pressure_corrected = ($row['pressure'] !== null) ? floatval($row['pressure']) + $PRESScorr : 0;

// Check if data is stale (older than 600 seconds)
$inserted_time = new DateTime($row['inserted']);
$current_time = new DateTime();
$interval = $current_time->getTimestamp() - $inserted_time->getTimestamp();

$device_id = $row['dev_id'];
if ($interval > 600) {
    $device_id = "--";
}

// Format output: device_id,timestamp,tempInside,tempOutside,corrInside,corrOutside,pressure
printf("%s,%s,%.2f,%.2f,%+.1f,%+.1f,%.1f",
    $device_id,
    $row['inserted'],
    $tempDOM_corrected,
    $tempUL_corrected,
    $DOMcorr,
    $ULcorr,
    $pressure_corrected
);
?>

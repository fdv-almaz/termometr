<?php
// Set error handling before anything else
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    exit;
}, E_ALL);

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
$conn->query("SET SESSION time_zone='+02:00'");

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
$stmt = $conn->prepare("SELECT id, dev_id, dev_time, tempUL, tempDOM, pressure, inserted FROM data ORDER BY id DESC LIMIT 1");
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

// Check if data is stale (older than 600 seconds)
$inserted_time = new DateTime($row['inserted']);
$current_time = new DateTime();
$interval = $current_time->getTimestamp() - $inserted_time->getTimestamp();

$device_id = $row['dev_id'];
if ($interval > 600) {
    $device_id = "--";
}

// Format output: device_id,timestamp,tempInside,tempOutside,corrInside,corrOutside,presscorr
// Format correction values: use appropriate format for zero values
$corrInsideStr = ($DOMcorr == 0) ? '0' : sprintf('%+.6f', $DOMcorr);
$corrOutsideStr = ($ULcorr == 0) ? '0' : sprintf('%+.6f', $ULcorr);
// For pressure, output empty string if it's NULL in the database, else the correction value
$presscorrStr = ($row['pressure'] === null) ? '' : (($PRESScorr == 0) ? '0' : sprintf('%+.6f', $PRESScorr));

printf("%s,%s,%.2f,%.2f,%s,%s,%s",
    $device_id,
    $row['dev_time'],
    $tempDOM_corrected,
    $tempUL_corrected,
    $corrInsideStr,
    $corrOutsideStr,
    $presscorrStr
);
?>

<?php
require_once 'db.php';

header('Content-Type: application/json');

// Authenticate API key
if ($api_config['enable_auth']) {
    $provided_key = $_GET['api_key'] ?? '';
    if ($provided_key !== $api_config['api_key']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

// Validate and sanitize input
$dev_id = filter_var($_GET['dev_id'] ?? null, FILTER_VALIDATE_INT);
$dev_time = $_GET['time'] ?? date('H:i:s');
$tempUL = filter_var($_GET['temperatureUL'] ?? null, FILTER_VALIDATE_FLOAT);
$tempDOM = filter_var($_GET['temperatureDOM'] ?? null, FILTER_VALIDATE_FLOAT);
$press = filter_var($_GET['press'] ?? null, FILTER_VALIDATE_FLOAT);

// Validate data ranges
if ($dev_id === false || $tempUL === false || $tempDOM === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    error_log("Invalid input: dev_id=$dev_id, tempUL=$tempUL, tempDOM=$tempDOM");
    exit;
}

if ($tempUL < $api_config['max_temp_range']['min'] ||
    $tempUL > $api_config['max_temp_range']['max'] ||
    $tempDOM < $api_config['max_temp_range']['min'] ||
    $tempDOM > $api_config['max_temp_range']['max']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Temperature out of valid range']);
    error_log("Temperature out of range: tempUL=$tempUL, tempDOM=$tempDOM");
    exit;
}

if ($press !== false && ($press < $api_config['max_pressure_range']['min'] ||
    $press > $api_config['max_pressure_range']['max'])) {
    error_log("Pressure out of range: $press, using NULL");
    $press = null;
}

// Database connection
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    error_log("Database connection error: " . $conn->connect_error);
    exit;
}

$conn->set_charset("utf8mb4");

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    error_log("Prepare failed: " . $conn->error);
    exit;
}

$stmt->bind_param("isdd", $dev_id, $dev_time, $tempUL, $tempDOM);
if ($press !== null) {
    $stmt->bind_param("isddd", $dev_id, $dev_time, $tempUL, $tempDOM, $press);
} else {
    $stmt->bind_param("isdd", $dev_id, $dev_time, $tempUL, $tempDOM);
}

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Data saved']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
    error_log("Insert failed: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>
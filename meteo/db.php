<?php
// Database configuration
$config = array(
  'db_host' => getenv('DB_HOST') ?: 'localhost',
  'db_user' => getenv('DB_USER') ?: 'meteo_user',
  'db_pass' => getenv('DB_PASS') ?: '',
  'db_name' => getenv('DB_NAME') ?: 'meteo',
);

// API Configuration
$api_key_env = getenv('API_KEY');
$enable_auth = (bool)getenv('ENABLE_AUTH') ?: true;

// Validate API key is set when authentication is enabled
if ($enable_auth && empty($api_key_env)) {
  error_log("CRITICAL: API_KEY environment variable must be set when ENABLE_AUTH is enabled");
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
  exit;
}

$api_config = array(
  'api_key' => $api_key_env ?: '',
  'enable_auth' => $enable_auth,
  'max_temp_range' => array('min' => -50, 'max' => 100),
  'max_pressure_range' => array('min' => 300, 'max' => 1200),
);

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Create logs directory if not exists
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
  mkdir($log_dir, 0755, true);
}
?>

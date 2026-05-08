<?php
/**
 * Example Configuration File
 *
 * Copy this file to db.php and update with your actual values.
 * Better yet, use environment variables for sensitive data.
 *
 * Environment variables (recommended for production):
 *   DB_HOST     - Database host
 *   DB_USER     - Database user
 *   DB_PASS     - Database password
 *   DB_NAME     - Database name
 *   API_KEY     - API authentication key
 *   ENABLE_AUTH - Enable API authentication (true/false)
 */

// Database configuration
$config = array(
  'db_host' => getenv('DB_HOST') ?: 'localhost',
  'db_user' => getenv('DB_USER') ?: 'meteo_user',
  'db_pass' => getenv('DB_PASS') ?: 'change_me_in_production',
  'db_name' => getenv('DB_NAME') ?: 'meteo',
);

// API Configuration
$api_config = array(
  'api_key' => getenv('API_KEY') ?: 'change_this_to_random_key',
  'enable_auth' => (bool)getenv('ENABLE_AUTH') ?: true,
  'max_temp_range' => array('min' => -50, 'max' => 100),
  'max_pressure_range' => array('min' => 300, 'max' => 1200),
);

/**
 * Production Checklist:
 *
 * [ ] Change all default values above
 * [ ] Use HTTPS instead of HTTP
 * [ ] Set file permissions to 600 for .env file
 * [ ] Enable error logging but hide errors from users
 * [ ] Set up database backups
 * [ ] Test API authentication
 * [ ] Monitor logs for suspicious activity
 */
?>

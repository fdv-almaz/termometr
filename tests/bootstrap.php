<?php
/**
 * PHPUnit Bootstrap File
 * Sets up test environment and database
 */

// Set timezone before any database operations
date_default_timezone_set('Europe/Warsaw');

// Set test environment variables
putenv('DB_HOST=localhost');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_NAME=meteo_test');
putenv('API_KEY=test_api_key_12345');
putenv('ENABLE_AUTH=true');

// Define test database name
define('TEST_DB_NAME', 'meteo_test');

// Include autoloader (if using composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Create test database connection class
class TestDatabase {
    private static $instance = null;
    private $conn;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Connect without selecting database
        $this->conn = new mysqli(
            getenv('DB_HOST'),
            getenv('DB_USER'),
            getenv('DB_PASS')
        );

        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function getConnection() {
        // Ensure timezone is set for database connection
        $this->conn->query("SET SESSION time_zone='+02:00'");
        return $this->conn;
    }

    public function createTestDatabase() {
        // Drop existing test database
        $this->conn->query("DROP DATABASE IF EXISTS " . TEST_DB_NAME);

        // Create new test database
        if (!$this->conn->query("CREATE DATABASE " . TEST_DB_NAME)) {
            throw new Exception("Failed to create test database: " . $this->conn->error);
        }

        // Select test database
        $this->conn->select_db(TEST_DB_NAME);

        // Load schema
        $this->loadSchema();
    }

    private function loadSchema() {
        $schemaPath = __DIR__ . '/../mysql/meteoDB_create.sql';
        if (!file_exists($schemaPath)) {
            throw new Exception("Schema file not found: $schemaPath");
        }

        $schema = file_get_contents($schemaPath);

        // Execute multi-statement SQL
        if (!$this->conn->multi_query($schema)) {
            throw new Exception("Failed to load schema: " . $this->conn->error);
        }

        // Clear result set
        while ($this->conn->next_result()) {
            if ($result = $this->conn->use_result()) {
                $result->free();
            }
        }

        // Set MySQL timezone to match PHP timezone
        $this->conn->query("SET SESSION time_zone='+02:00'");
    }

    public function truncateData() {
        $this->conn->query("TRUNCATE TABLE data");
        $this->conn->query("TRUNCATE TABLE config");
        $this->resetConfig();
    }

    private function resetConfig() {
        // Reset config to default values (zero for tests)
        $sql = "INSERT INTO config (param_name, param_data, comment) VALUES
                ('ULcorr', '0', 'Outside temperature correction'),
                ('DOMcorr', '0', 'Inside temperature correction'),
                ('PRESScorr', '0', 'Pressure correction')";
        $this->conn->query($sql);
    }
}

// Initialize test database
try {
    $testDb = TestDatabase::getInstance();
    // Only create/recreate on first run
    static $initialized = false;
    if (!$initialized) {
        $testDb->createTestDatabase();
        $initialized = true;
    }
} catch (Exception $e) {
    echo "Warning: Could not initialize test database: " . $e->getMessage() . "\n";
    echo "Some tests may be skipped.\n";
}

// Helper function to get test database connection
function getTestConnection() {
    return TestDatabase::getInstance()->getConnection();
}

// Helper function to truncate test data
function resetTestDatabase() {
    TestDatabase::getInstance()->truncateData();
}

// Create logs directory
$logsDir = __DIR__ . '/../meteo/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Set error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logsDir . '/test_error.log');
?>

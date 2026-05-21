<?php
/**
 * Base TestCase class for all unit tests
 */

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {
    protected $connection;

    protected function setUp(): void {
        parent::setUp();

        // Get test database connection
        $this->connection = getTestConnection();

        // Ensure timezone is set for this connection
        $this->connection->query("SET SESSION time_zone='+02:00'");

        // Reset database before each test - do it twice to ensure clean state
        resetTestDatabase();
        // Give DB a moment to settle
        usleep(10000);
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up thoroughly
        try {
            $this->connection->query("SET FOREIGN_KEY_CHECKS=0");
            $this->connection->query("TRUNCATE TABLE data");
            $this->connection->query("TRUNCATE TABLE config");
            $this->connection->query("SET FOREIGN_KEY_CHECKS=1");
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
        }

        // Final reset
        resetTestDatabase();
    }

    /**
     * Make HTTP request to API endpoint
     */
    protected function makeApiRequest($endpoint, $params = [], $method = 'GET') {
        // Simulate HTTP request to API endpoint using subprocess
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = [];

        if ($method === 'GET') {
            $_GET = $params;
        }

        // Build query string
        $queryString = http_build_query($params, '', '&');

        // Set up environment from phpunit.xml or use defaults
        // NOTE: getenv() can return false if not set, so we need explicit fallback
        $dbHost = (getenv('DB_HOST') !== false) ? getenv('DB_HOST') : 'localhost';
        $dbUser = (getenv('DB_USER') !== false) ? getenv('DB_USER') : 'root';
        $dbPass = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : '';
        $dbName = (getenv('DB_NAME') !== false) ? getenv('DB_NAME') : 'meteo_test';
        $apiKey = (getenv('API_KEY') !== false) ? getenv('API_KEY') : 'test_api_key_12345';
        $enableAuth = (getenv('ENABLE_AUTH') !== false) ? getenv('ENABLE_AUTH') : 'true';

        // Create a wrapper script that will execute the endpoint
        $wrapperScript = '<?php ' . "\n";
        $wrapperScript .= "error_reporting(E_ALL); " . "\n";
        $wrapperScript .= "ini_set('display_errors', '0'); " . "\n";
        $wrapperScript .= "ini_set('log_errors', '1'); " . "\n";
        $wrapperScript .= "set_error_handler(function(\$errno, \$errstr, \$errfile, \$errline) { " . "\n";
        $wrapperScript .= "    error_log(\"Wrapper Error [\$errno]: \$errstr in \$errfile:\$errline\"); " . "\n";
        $wrapperScript .= "}); " . "\n";
        $wrapperScript .= "date_default_timezone_set('Europe/Warsaw'); " . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_HOST=$dbHost", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_USER=$dbUser", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_PASS=$dbPass", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_NAME=$dbName", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("API_KEY=$apiKey", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("ENABLE_AUTH=$enableAuth", true)) . "\n";
        $wrapperScript .= sprintf('$_GET = %s; ', var_export($params, true)) . "\n";
        $wrapperScript .= sprintf('$_SERVER["REQUEST_METHOD"] = %s; ', var_export($method, true)) . "\n";
        $wrapperScript .= 'ob_start(); ' . "\n";
        $wrapperScript .= sprintf('try { include %s; } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); } ', var_export(__DIR__ . '/../../meteo/' . $endpoint, true)) . "\n";
        $wrapperScript .= 'echo ob_get_clean(); ' . "\n";
        $wrapperScript .= '?>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'phpt_');
        file_put_contents($tmpFile, $wrapperScript);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            // Ensure output is always a string (not null)
            $output = $output === null ? '' : $output;
            return [
                'output' => $output,
                'json' => json_decode($output, true)
            ];
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Insert test data into database
     */
    protected function insertTestData($devId = 1, $tempUL = 22.5, $tempDOM = 21.0, $pressure = 1013.25) {
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES (?, ?, ?, ?, ?, NOW())"
        );

        $time = date('H:i:s');
        $stmt->bind_param("isddd", $devId, $time, $tempUL, $tempDOM, $pressure);

        return $stmt->execute();
    }

    /**
     * Get last inserted data from database
     */
    protected function getLastData() {
        $result = $this->connection->query("SELECT * FROM data ORDER BY inserted DESC LIMIT 1");
        return $result->fetch_assoc();
    }

    /**
     * Set configuration parameter
     */
    protected function setConfig($paramName, $value) {
        $stmt = $this->connection->prepare("UPDATE config SET param_data = ? WHERE param_name = ?");
        return $stmt->bind_param("ss", $value, $paramName) && $stmt->execute();
    }

    /**
     * Get configuration parameter
     */
    protected function getConfig($paramName) {
        $stmt = $this->connection->prepare("SELECT param_data FROM config WHERE param_name = ?");
        $stmt->bind_param("s", $paramName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['param_data'] : null;
    }

    /**
     * Parse CSV response
     */
    protected function parseCSV($csvString) {
        $parts = str_getcsv($csvString, ',', '"', '\\');
        return [
            'deviceId' => $parts[0] ?? null,
            'timestamp' => $parts[1] ?? null,
            'tempInside' => floatval($parts[2] ?? 0),
            'tempOutside' => floatval($parts[3] ?? 0),
            'corrInside' => floatval($parts[4] ?? 0),
            'corrOutside' => floatval($parts[5] ?? 0),
            'pressure' => ($parts[6] ?? '') === '' ? '' : floatval($parts[6] ?? 0)
        ];
    }
}
?>

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

        // Reset database before each test
        resetTestDatabase();
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up
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

        // Set up environment - use phpunit.xml environment variables
        $dbHost = (!empty(getenv('DB_HOST'))) ? getenv('DB_HOST') : 'localhost';
        $dbUser = (!empty(getenv('DB_USER'))) ? getenv('DB_USER') : 'root';
        $dbPass = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : '';
        $dbName = (!empty(getenv('DB_NAME'))) ? getenv('DB_NAME') : 'meteo_test';
        $apiKey = (!empty(getenv('API_KEY'))) ? getenv('API_KEY') : 'test_api_key_12345';
        $enableAuth = (!empty(getenv('ENABLE_AUTH'))) ? getenv('ENABLE_AUTH') : 'true';

        // Create a wrapper script that will execute the endpoint
        $wrapperScript = '<?php ' . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_HOST=$dbHost", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_USER=$dbUser", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_PASS=$dbPass", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("DB_NAME=$dbName", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("API_KEY=$apiKey", true)) . "\n";
        $wrapperScript .= sprintf('putenv(%s); ', var_export("ENABLE_AUTH=$enableAuth", true)) . "\n";
        $wrapperScript .= sprintf('$_GET = %s; ', var_export($params, true)) . "\n";
        $wrapperScript .= sprintf('$_SERVER["REQUEST_METHOD"] = %s; ', var_export($method, true)) . "\n";
        $wrapperScript .= 'ob_start(); ' . "\n";
        $wrapperScript .= sprintf('include %s; ', var_export(__DIR__ . '/../../meteo/' . $endpoint, true)) . "\n";
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
            'pressure' => floatval($parts[6] ?? 0)
        ];
    }
}
?>

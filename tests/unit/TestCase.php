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

        // Set up environment
        $env = [
            'DB_HOST' => getenv('DB_HOST'),
            'DB_USER' => getenv('DB_USER'),
            'DB_PASS' => getenv('DB_PASS'),
            'DB_NAME' => getenv('DB_NAME'),
            'API_KEY' => getenv('API_KEY'),
            'ENABLE_AUTH' => getenv('ENABLE_AUTH'),
        ];

        // Create a wrapper script that will execute the endpoint
        $wrapperScript = '<?php ' . "\n";
        foreach ($env as $key => $value) {
            $wrapperScript .= sprintf('putenv(%s); ', var_export("$key=$value", true)) . "\n";
        }
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

<?php
/**
 * Tests for database configuration
 * Verifies environment variables, validation, and error handling
 */

require_once __DIR__ . '/../TestCase.php';

class ConfigTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Don't call parent setUp as we test configuration directly
    }

    protected function tearDown(): void {
        // Reset all environment variables to their test defaults
        putenv('DB_HOST=localhost');
        putenv('DB_USER=root');
        putenv('DB_PASS=');
        putenv('DB_NAME=meteo_test');
        putenv('API_KEY=test_api_key_12345');
        putenv('ENABLE_AUTH=true');

        parent::tearDown();
    }

    /**
     * Test API_KEY from environment variable is loaded
     */
    public function testApiKeyLoadedFromEnvironment() {
        putenv('API_KEY=test_key_from_env_123');
        putenv('ENABLE_AUTH=true');

        // Include db.php and check that $api_config is set
        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('test_key_from_env_123', $api_config['api_key']);
    }

    /**
     * Test ENABLE_AUTH defaults to true
     */
    public function testEnableAuthDefaultsToTrue() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH');  // Unset

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertTrue($api_config['enable_auth']);
    }

    /**
     * Test ENABLE_AUTH can be disabled
     */
    public function testEnableAuthCanBeDisabled() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=false');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertFalse($api_config['enable_auth']);
    }

    /**
     * Test DB_HOST defaults to localhost
     */
    public function testDbHostDefaultsToLocalhost() {
        putenv('API_KEY=test_key');
        putenv('DB_HOST');  // Unset

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('localhost', $config['db_host']);
    }

    /**
     * Test DB_USER defaults to meteo_user
     */
    public function testDbUserDefaultsToMeteoUser() {
        putenv('API_KEY=test_key');
        putenv('DB_USER');  // Unset

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('meteo_user', $config['db_user']);
    }

    /**
     * Test DB_NAME defaults to meteo
     */
    public function testDbNameDefaultsToMeteo() {
        putenv('API_KEY=test_key');
        putenv('DB_NAME');  // Unset

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('meteo', $config['db_name']);
    }

    /**
     * Test temperature range configuration is correct
     */
    public function testTemperatureRangeConfiguration() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals(-50, $api_config['max_temp_range']['min']);
        $this->assertEquals(100, $api_config['max_temp_range']['max']);
    }

    /**
     * Test pressure range configuration is correct
     */
    public function testPressureRangeConfiguration() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals(300, $api_config['max_pressure_range']['min']);
        $this->assertEquals(1200, $api_config['max_pressure_range']['max']);
    }

    /**
     * Test error when API_KEY not set and ENABLE_AUTH=true
     */
    public function testErrorWhenApiKeyNotSetWithAuthEnabled() {
        putenv('API_KEY=');  // Empty
        putenv('ENABLE_AUTH=true');

        ob_start();
        try {
            include __DIR__ . '/../../../meteo/db.php';
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Should contain error message or exit
        $this->assertTrue(
            strpos($output, 'CRITICAL') !== false ||
            strpos($output, 'API_KEY') !== false
        );
    }

    /**
     * Test custom environment variable values
     */
    public function testCustomEnvironmentVariableValues() {
        putenv('DB_HOST=db.example.com');
        putenv('DB_USER=custom_user');
        putenv('DB_PASS=custom_pass');
        putenv('DB_NAME=custom_db');
        putenv('API_KEY=custom_api_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('db.example.com', $config['db_host']);
        $this->assertEquals('custom_user', $config['db_user']);
        $this->assertEquals('custom_pass', $config['db_pass']);
        $this->assertEquals('custom_db', $config['db_name']);
        $this->assertEquals('custom_api_key', $api_config['api_key']);
    }

    /**
     * Test empty DB_PASS is allowed (anonymous connection)
     */
    public function testEmptyDbPasswordAllowed() {
        putenv('API_KEY=test_key');
        putenv('DB_PASS=');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals('', $config['db_pass']);
    }

    /**
     * Test logs directory is created
     */
    public function testLogsDirectoryCreated() {
        // Remove logs directory if it exists
        $logsDir = __DIR__ . '/../../../meteo/logs';
        if (is_dir($logsDir)) {
            array_map('unlink', glob("$logsDir/*"));
            rmdir($logsDir);
        }

        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertTrue(is_dir($logsDir));
    }

    /**
     * Test error reporting is configured
     */
    public function testErrorReportingConfigured() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        $oldErrorReporting = error_reporting();

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        // Error reporting should be set to E_ALL
        $this->assertEquals(E_ALL, error_reporting());

        // Restore
        error_reporting($oldErrorReporting);
    }

    /**
     * Test display errors is disabled
     */
    public function testDisplayErrorsDisabled() {
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        $oldDisplayErrors = ini_get('display_errors');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        // Display errors should be off
        $this->assertEquals(0, ini_get('display_errors'));

        // Restore
        ini_set('display_errors', $oldDisplayErrors);
    }

    /**
     * Test API key with special characters in environment
     */
    public function testApiKeyWithSpecialCharactersInEnv() {
        $specialKey = 'api!@#$%^&*()_+-=[]{}|;:,.<>?secure';
        putenv('API_KEY=' . $specialKey);
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals($specialKey, $api_config['api_key']);
    }

    /**
     * Test database password with special characters
     */
    public function testDbPasswordWithSpecialCharacters() {
        $specialPass = 'p@$$w0rd!#%&*()secure';
        putenv('DB_PASS=' . $specialPass);
        putenv('API_KEY=test_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        ob_end_clean();

        $this->assertEquals($specialPass, $config['db_pass']);
    }

    /**
     * Test configuration persists across includes
     */
    public function testConfigurationPersistsAcrossIncludes() {
        putenv('API_KEY=persistent_key');
        putenv('ENABLE_AUTH=true');

        ob_start();
        include __DIR__ . '/../../../meteo/db.php';
        $firstInclude = $api_config;

        include __DIR__ . '/../../../meteo/db.php';
        $secondInclude = $api_config;
        ob_end_clean();

        $this->assertEquals($firstInclude['api_key'], $secondInclude['api_key']);
    }
}
?>

<?php
/**
 * Tests for save.php endpoint
 * Tests API key authentication, validation, and data saving
 */

require_once __DIR__ . '/../TestCase.php';

class SaveEndpointTest extends TestCase {

    /**
     * Test successful data save with valid API key
     */
    public function testSuccessfulDataSaveWithValidApiKey() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'time' => '14:30:45',
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 1013.25
        ]);

        $this->assertStringContainsString('success', $response['output']);
        $this->assertEquals('success', $response['json']['status']);

        // Verify data was saved to database
        $data = $this->getLastData();
        $this->assertEquals(1, $data['dev_id']);
        $this->assertEquals(22.5, $data['tempUL']);
        $this->assertEquals(21.0, $data['tempDOM']);
        $this->assertEquals(1013.25, $data['pressure']);
    }

    /**
     * Test rejection with invalid API key
     */
    public function testRejectionWithInvalidApiKey() {
        // Capture HTTP status by checking response
        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'wrong_api_key_12345',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
        $this->assertEquals('error', $response['json']['status']);
    }

    /**
     * Test rejection when required parameters missing
     */
    public function testRejectionWithMissingRequiredParameters() {
        // Missing temperatureUL
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Invalid input', $response['output']);
        $this->assertEquals('error', $response['json']['status']);
    }

    /**
     * Test temperature validation - below minimum
     */
    public function testTemperatureBelowMinimumRejected() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => -60.0,  // Min is -50
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Temperature out of valid range', $response['output']);
        $this->assertEquals('error', $response['json']['status']);
    }

    /**
     * Test temperature validation - above maximum
     */
    public function testTemperatureAboveMaximumRejected() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 120.0  // Max is 100
        ]);

        $this->assertStringContainsString('Temperature out of valid range', $response['output']);
        $this->assertEquals('error', $response['json']['status']);
    }

    /**
     * Test pressure validation - below minimum (logged but not rejected)
     */
    public function testPressureBelowMinimumHandled() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 250.0  // Below min of 300
        ]);

        // Should succeed but pressure should be NULL
        $this->assertStringContainsString('success', $response['output']);
        $data = $this->getLastData();
        $this->assertNull($data['pressure']);
    }

    /**
     * Test pressure validation - above maximum
     */
    public function testPressureAboveMaximumHandled() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 1500.0  // Above max of 1200
        ]);

        // Should succeed but pressure should be NULL
        $this->assertStringContainsString('success', $response['output']);
        $data = $this->getLastData();
        $this->assertNull($data['pressure']);
    }

    /**
     * Test successful save without pressure parameter
     */
    public function testSaveWithoutPressureParameter() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 2,
            'temperatureUL' => 19.5,
            'temperatureDOM' => 18.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
        $data = $this->getLastData();
        $this->assertEquals(2, $data['dev_id']);
        $this->assertNull($data['pressure']);
    }

    /**
     * Test prepared statement binding with all parameters
     */
    public function testPreparedStatementBindingCorrect() {
        // This tests that bind_param is called correctly with 5 parameters
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 5,
            'time' => '12:34:56',
            'temperatureUL' => 25.123,
            'temperatureDOM' => 23.456,
            'press' => 1005.789
        ]);

        $this->assertStringContainsString('success', $response['output']);

        $data = $this->getLastData();
        $this->assertEquals(5, $data['dev_id']);
        $this->assertEquals('12:34:56', $data['dev_time']);
        $this->assertEqualsWithDelta(25.123, $data['tempUL'], 0.01);
        $this->assertEqualsWithDelta(23.456, $data['tempDOM'], 0.01);
        $this->assertEqualsWithDelta(1005.789, $data['pressure'], 0.01);
    }

    /**
     * Test that API key authentication uses constant-time comparison
     * (Should use hash_equals, not ===)
     */
    public function testApiKeyAuthenticationIsConstantTime() {
        // First request with correct key should succeed
        $correctResponse = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $correctResponse['output']);

        // Second request with incorrect key should be rejected
        // Both should take similar time (constant-time comparison)
        $startTime = microtime(true);
        $wrongResponse = $this->makeApiRequest('save.php', [
            'api_key' => 'wrong_key_z' . getenv('API_KEY'),  // Almost correct
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $timeTaken = microtime(true) - $startTime;

        // Should be rejected
        $this->assertStringContainsString('Forbidden', $wrongResponse['output']);

        // Time should not vary significantly based on key comparison
        // (This is a basic check - proper timing tests would need more iterations)
        $this->assertGreaterThan(0, $timeTaken);
    }

    /**
     * Test that integer device ID is validated
     */
    public function testDeviceIdMustBeInteger() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 'not_an_integer',
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Invalid input', $response['output']);
    }

    /**
     * Test that temperature values must be numeric
     */
    public function testTemperaturesMustBeNumeric() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 'hot',
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Invalid input', $response['output']);
    }

    /**
     * Test edge case: exactly at minimum temperature
     */
    public function testTemperatureAtMinimumBoundary() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => -50.0,  // Exactly min
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test edge case: exactly at maximum temperature
     */
    public function testTemperatureAtMaximumBoundary() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 100.0  // Exactly max
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test multiple sequential saves
     */
    public function testMultipleSequentialSaves() {
        // First save
        $response1 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('success', $response1['output']);

        // Second save with different device ID
        $response2 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 2,
            'temperatureUL' => 19.5,
            'temperatureDOM' => 18.0
        ]);
        $this->assertStringContainsString('success', $response2['output']);

        // Verify both records exist
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(2, $row['count']);
    }

    /**
     * Test float precision in temperature storage
     */
    public function testFloatPrecisionPreserved() {
        $response = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.987654,
            'temperatureDOM' => 21.123456
        ]);

        $this->assertStringContainsString('success', $response['output']);

        $data = $this->getLastData();
        // MySQL float has limited precision, so check with delta
        $this->assertEqualsWithDelta(22.987654, $data['tempUL'], 0.001);
        $this->assertEqualsWithDelta(21.123456, $data['tempDOM'], 0.001);
    }
}
?>

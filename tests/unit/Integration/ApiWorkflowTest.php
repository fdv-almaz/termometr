<?php
/**
 * Integration tests for complete API workflows
 * Tests full save -> retrieve -> correction cycle
 */

require_once __DIR__ . '/../TestCase.php';

class ApiWorkflowTest extends TestCase {

    /**
     * Test complete workflow: Arduino saves data, display retrieves it
     */
    public function testCompleteArduinoWorkflow() {
        // Step 1: Sensor Arduino sends data
        $sensorData = [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'time' => '14:30:45',
            'temperatureUL' => 19.5,
            'temperatureDOM' => 21.3,
            'press' => 1010.5
        ];

        $saveResponse = $this->makeApiRequest('save.php', $sensorData);
        $this->assertStringContainsString('success', $saveResponse['output']);

        // Step 2: Display board fetches latest data
        $getResponse = $this->makeApiRequest('getlast.php', []);
        $this->assertNotEmpty($getResponse['output']);

        // Step 3: Parse CSV and verify data
        $data = $this->parseCSV(trim($getResponse['output']));
        $this->assertEquals('1', $data['deviceId']);
        $this->assertEquals(19.5, $data['tempOutside']);
        $this->assertEquals(21.3, $data['tempInside']);

        // Step 4: Verify data persisted in database
        $dbRecord = $this->getLastData();
        $this->assertEquals(1, $dbRecord['dev_id']);
        $this->assertEquals(19.5, $dbRecord['tempUL']);
        $this->assertEqualsWithDelta(1010.5, $dbRecord['pressure'], 0.1);
    }

    /**
     * Test multi-device workflow: multiple devices sending data
     */
    public function testMultiDeviceWorkflow() {
        // Device 1 sends data
        $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 20.5,
            'temperatureDOM' => 19.5
        ]);

        // Device 2 sends data
        $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 2,
            'temperatureUL' => 25.3,
            'temperatureDOM' => 24.1
        ]);

        // Device 3 sends data
        $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 3,
            'temperatureUL' => 18.2,
            'temperatureDOM' => 17.8
        ]);

        // Verify all records exist
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(3, $row['count']);

        // Get last should return the most recent (whichever device ID)
        $lastResponse = $this->makeApiRequest('getlast.php', []);
        $lastData = $this->parseCSV(trim($lastResponse['output']));
        $this->assertNotEmpty($lastData['deviceId']);
    }

    /**
     * Test temperature correction workflow
     */
    public function testTemperatureCorrectionWorkflow() {
        // Set custom corrections
        $this->setConfig('ULcorr', '2.0');
        $this->setConfig('DOMcorr', '-1.5');

        // Save data
        $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 20.0,
            'temperatureDOM' => 22.0
        ]);

        // Retrieve and verify corrections are included
        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEquals(2.0, $data['corrOutside']);
        $this->assertEquals(-1.5, $data['corrInside']);
    }

    /**
     * Test authentication workflow: valid and invalid sequences
     */
    public function testAuthenticationWorkflow() {
        // Valid API key succeeds
        $validResponse = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('success', $validResponse['output']);

        // Invalid key fails
        $invalidResponse = $this->makeApiRequest('save.php', [
            'api_key' => 'wrong_key_123',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('Forbidden', $invalidResponse['output']);

        // Verify only one record was saved (valid request only)
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(1, $row['count']);
    }

    /**
     * Test validation workflow: invalid data rejected, then valid accepted
     */
    public function testValidationWorkflow() {
        // Temperature out of range - should fail
        $invalidResponse = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 150.0,  // Too high
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('Temperature out of valid range', $invalidResponse['output']);

        // Valid data - should succeed
        $validResponse = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('success', $validResponse['output']);

        // Verify only valid record was saved
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(1, $row['count']);
    }

    /**
     * Test time series workflow: device sends multiple readings
     */
    public function testTimeSeriesWorkflow() {
        $devId = 1;
        $temperatures = [
            ['out' => 20.0, 'in' => 19.0],
            ['out' => 20.5, 'in' => 19.5],
            ['out' => 21.0, 'in' => 20.0],
            ['out' => 21.5, 'in' => 20.5],
            ['out' => 22.0, 'in' => 21.0],
        ];

        // Send 5 readings from same device
        foreach ($temperatures as $i => $temps) {
            sleep(1);  // Small delay between readings
            $response = $this->makeApiRequest('save.php', [
                'api_key' => getenv('API_KEY'),
                'dev_id' => $devId,
                'time' => sprintf('%02d:%02d:%02d', 14, 30 + $i, 0),
                'temperatureUL' => $temps['out'],
                'temperatureDOM' => $temps['in']
            ]);
            $this->assertStringContainsString('success', $response['output']);
        }

        // Verify all 5 records exist
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data WHERE dev_id = $devId");
        $row = $result->fetch_assoc();
        $this->assertEquals(5, $row['count']);

        // Verify last reading is returned
        $lastResponse = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($lastResponse['output']));
        $this->assertEquals(22.0, $data['tempOutside']);
        $this->assertEquals(21.0, $data['tempInside']);
    }

    /**
     * Test data persistence workflow
     */
    public function testDataPersistenceWorkflow() {
        // Save initial data
        $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 1013.25
        ]);

        // Get count
        $result1 = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row1 = $result1->fetch_assoc();
        $count1 = $row1['count'];

        // Reset database (clears data)
        resetTestDatabase();

        // Count should be 0 after reset
        $result2 = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row2 = $result2->fetch_assoc();
        $count2 = $row2['count'];

        $this->assertGreater($count1, 0);
        $this->assertEquals(0, $count2);
    }

    /**
     * Test error handling workflow
     */
    public function testErrorHandlingWorkflow() {
        // Missing required parameter
        $response1 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1
            // Missing temperatureUL and temperatureDOM
        ]);
        $this->assertStringContainsString('Invalid input', $response1['output']);

        // Invalid type
        $response2 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 'not_an_int',
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('Invalid input', $response2['output']);

        // Out of range
        $response3 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 250.0  // Too high
        ]);
        $this->assertStringContainsString('Temperature out of valid range', $response3['output']);

        // Verify no data was saved
        $result = $this->connection->query("SELECT COUNT(*) as count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(0, $row['count']);
    }

    /**
     * Test concurrent device workflow (sequential simulation)
     */
    public function testConcurrentDeviceSimulation() {
        $devices = [
            ['id' => 1, 'out' => 20.5, 'in' => 19.5],
            ['id' => 2, 'out' => 22.3, 'in' => 21.2],
            ['id' => 3, 'out' => 18.9, 'in' => 17.8],
            ['id' => 4, 'out' => 25.1, 'in' => 24.0],
            ['id' => 5, 'out' => 19.7, 'in' => 18.6],
        ];

        // All devices send data (simulating concurrent requests)
        foreach ($devices as $device) {
            $response = $this->makeApiRequest('save.php', [
                'api_key' => getenv('API_KEY'),
                'dev_id' => $device['id'],
                'temperatureUL' => $device['out'],
                'temperatureDOM' => $device['in']
            ]);
            $this->assertStringContainsString('success', $response['output']);
        }

        // Verify all devices' data was saved
        $result = $this->connection->query("SELECT COUNT(DISTINCT dev_id) as device_count FROM data");
        $row = $result->fetch_assoc();
        $this->assertEquals(5, $row['device_count']);
    }

    /**
     * Test complete cycle with pressure variations
     */
    public function testPressureVariationWorkflow() {
        // Valid pressure
        $response1 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 1013.25
        ]);
        $this->assertStringContainsString('success', $response1['output']);

        // Out of range pressure (will be set to NULL)
        $response2 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 2,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0,
            'press' => 2000.0  // Too high
        ]);
        $this->assertStringContainsString('success', $response2['output']);

        // No pressure parameter
        $response3 = $this->makeApiRequest('save.php', [
            'api_key' => getenv('API_KEY'),
            'dev_id' => 3,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $this->assertStringContainsString('success', $response3['output']);

        // Verify handling
        $result = $this->connection->query("SELECT * FROM data ORDER BY dev_id");
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }

        $this->assertNotNull($records[0]['pressure']);  // Device 1: has pressure
        $this->assertNull($records[1]['pressure']);     // Device 2: NULL (out of range)
        $this->assertNull($records[2]['pressure']);     // Device 3: NULL (not provided)
    }
}
?>

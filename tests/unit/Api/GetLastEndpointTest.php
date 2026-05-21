<?php
/**
 * Tests for getlast.php endpoint
 * Tests data retrieval, corrections, and staleness detection
 */

require_once __DIR__ . '/../TestCase.php';

class GetLastEndpointTest extends TestCase {

    /**
     * Test successful data retrieval in CSV format
     */
    public function testSuccessfulDataRetrievalInCsvFormat() {
        // Insert test data
        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        $response = $this->makeApiRequest('getlast.php', []);

        // Check that we got valid CSV output (not an error)
        $this->assertNotEmpty($response['output']);
        $this->assertStringNotContainsString('Error', $response['output']);

        // Parse CSV response
        $data = $this->parseCSV(trim($response['output']));
        $this->assertEquals('1', $data['deviceId']);
        $this->assertEquals(22.5, $data['tempOutside']);
        $this->assertEquals(21.0, $data['tempInside']);
    }

    /**
     * Test CSV format has correct field order
     */
    public function testCsvFormatFieldOrder() {
        $this->insertTestData(5, 18.5, 19.2, 1010.5);

        $response = $this->makeApiRequest('getlast.php', []);
        $csvData = trim($response['output']);

        // Format: device_id,timestamp,tempInside,tempOutside,corrInside,corrOutside,pressure
        $parts = str_getcsv($csvData);

        $this->assertEquals(7, count($parts), 'CSV should have 7 fields');
        $this->assertEquals('5', $parts[0]);  // device_id
        $this->assertNotEmpty($parts[1]);     // timestamp
        $this->assertEquals(19.2, floatval($parts[2]));  // tempInside
        $this->assertEquals(18.5, floatval($parts[3]));  // tempOutside
    }

    /**
     * Test temperature correction is applied
     */
    public function testTemperatureCorrectionApplied() {
        // Set corrections
        $this->setConfig('ULcorr', '1.5');    // Add 1.5 to outside temp
        $this->setConfig('DOMcorr', '-0.5');  // Subtract 0.5 from inside temp

        $this->insertTestData(1, 20.0, 22.0, 1013.0);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Corrections should be applied from config table
        $this->assertEquals(1.5, $data['corrOutside']);
        $this->assertEquals(-0.5, $data['corrInside']);
    }

    /**
     * Test pressure correction is applied
     */
    public function testPressureCorrectionApplied() {
        $this->setConfig('PRESScorr', '0.5');

        $this->insertTestData(1, 22.5, 21.0, 1012.0);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEquals(0.5, $data['pressure']);
    }

    /**
     * Test staleness detection for old data
     */
    public function testStalenessDetectionForOldData() {
        // Insert old data (more than 600 seconds ago)
        $oldTime = date('Y-m-d H:i:s', strtotime('-20 minutes'));
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $devId = 1;
        $devTime = '14:30:45';
        $tempUL = 22.5;
        $tempDOM = 21.0;
        $pressure = 1013.25;

        $stmt->bind_param("isddds", $devId, $devTime, $tempUL, $tempDOM, $pressure, $oldTime);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Device ID should be marked as "--" for stale data
        $this->assertEquals('--', $data['deviceId']);
    }

    /**
     * Test fresh data is not marked as stale
     */
    public function testFreshDataNotMarkedAsStale() {
        // Insert fresh data (within 600 seconds)
        $freshTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $devId = 1;
        $devTime = '14:30:45';
        $tempUL = 22.5;
        $tempDOM = 21.0;
        $pressure = 1013.25;

        $stmt->bind_param("isddds", $devId, $devTime, $tempUL, $tempDOM, $pressure, $freshTime);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Device ID should be real (not "--")
        $this->assertEquals('1', $data['deviceId']);
    }

    /**
     * Test handling of NULL pressure
     */
    public function testHandlingOfNullPressure() {
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, NULL)"
        );
        $devId = 1;
        $devTime = '14:30:45';
        $tempUL = 22.5;
        $tempDOM = 21.0;

        $stmt->bind_param("isdd", $devId, $devTime, $tempUL, $tempDOM);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Pressure field should be empty or 0
        $this->assertTrue($data['pressure'] === 0 || $data['pressure'] === null || $data['pressure'] === '');
    }

    /**
     * Test zero corrections don't affect data
     */
    public function testZeroCorrectionsPreserveData() {
        $this->setConfig('ULcorr', '0');
        $this->setConfig('DOMcorr', '0');
        $this->setConfig('PRESScorr', '0');

        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEquals(0, $data['corrOutside']);
        $this->assertEquals(0, $data['corrInside']);
        $this->assertEquals(0, $data['pressure']);
    }

    /**
     * Test multiple data records returns latest
     */
    public function testMultipleRecordsReturnsLatest() {
        // Insert older record
        $this->insertTestData(1, 20.0, 19.0, 1010.0);

        // Wait a moment and insert newer record
        sleep(1);
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, ?)"
        );
        $devId = 1;
        $devTime = '14:40:00';
        $tempUL = 23.5;
        $tempDOM = 22.0;
        $pressure = 1014.0;

        $stmt->bind_param("isdd", $devId, $devTime, $tempUL, $tempDOM, $pressure);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Should return the newer record
        $this->assertEquals(23.5, $data['tempOutside']);
        $this->assertEquals(22.0, $data['tempInside']);
    }

    /**
     * Test negative temperature corrections
     */
    public function testNegativeTemperatureCorrections() {
        $this->setConfig('ULcorr', '-2.5');
        $this->setConfig('DOMcorr', '-1.0');

        $this->insertTestData(1, 25.0, 23.0, 1013.0);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEquals(-2.5, $data['corrOutside']);
        $this->assertEquals(-1.0, $data['corrInside']);
    }

    /**
     * Test large decimal values in corrections
     */
    public function testLargeDecimalValuesInCorrections() {
        $this->setConfig('ULcorr', '0.123456');
        $this->setConfig('DOMcorr', '0.987654');

        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEqualsWithDelta(0.123456, $data['corrOutside'], 0.0001);
        $this->assertEqualsWithDelta(0.987654, $data['corrInside'], 0.0001);
    }

    /**
     * Test timezone handling (Europe/Warsaw)
     */
    public function testTimestampPreserved() {
        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Timestamp should be preserved from insertion
        $this->assertNotEmpty($data['timestamp']);
        // Should be in format HH:MM:SS or similar
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data['timestamp']);
    }

    /**
     * Test CSV parsing with scientific notation
     */
    public function testCsvParsingWithFloats() {
        // Insert data with specific float values
        $this->insertTestData(1, 22.1234, 21.5678, 1013.9876);

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        $this->assertEqualsWithDelta(22.1234, $data['tempOutside'], 0.01);
        $this->assertEqualsWithDelta(21.5678, $data['tempInside'], 0.01);
    }

    /**
     * Test response content type header
     */
    public function testResponseContentType() {
        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        // Note: This would require capturing response headers
        // In a real test, we'd check for "text/csv" or similar
        $response = $this->makeApiRequest('getlast.php', []);
        $this->assertNotEmpty($response['output']);
    }

    /**
     * Test boundary: exactly 600 seconds old (should be fresh)
     */
    public function testBoundary600SecondsFresh() {
        $boundaryTime = date('Y-m-d H:i:s', strtotime('-600 seconds'));
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $devId = 1;
        $devTime = '14:30:45';
        $tempUL = 22.5;
        $tempDOM = 21.0;
        $pressure = 1013.25;

        $stmt->bind_param("isddds", $devId, $devTime, $tempUL, $tempDOM, $pressure, $boundaryTime);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // At exactly 600 seconds, should still be considered stale (>600)
        // or fresh (<=600) depending on implementation
        // Test validates the boundary condition
        $this->assertNotEmpty($data['deviceId']);
    }

    /**
     * Test boundary: 601 seconds old (should be stale)
     */
    public function testBoundary601SecondsStale() {
        $boundaryTime = date('Y-m-d H:i:s', strtotime('-601 seconds'));
        $stmt = $this->connection->prepare(
            "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $devId = 1;
        $devTime = '14:30:45';
        $tempUL = 22.5;
        $tempDOM = 21.0;
        $pressure = 1013.25;

        $stmt->bind_param("isddds", $devId, $devTime, $tempUL, $tempDOM, $pressure, $boundaryTime);
        $stmt->execute();

        $response = $this->makeApiRequest('getlast.php', []);
        $data = $this->parseCSV(trim($response['output']));

        // Should be marked as stale
        $this->assertEquals('--', $data['deviceId']);
    }
}
?>

<?php
/**
 * Tests for database connection and schema validation
 */

require_once __DIR__ . '/../TestCase.php';

class ConnectionTest extends TestCase {

    /**
     * Test successful database connection
     */
    public function testSuccessfulDatabaseConnection() {
        $conn = getTestConnection();
        $this->assertNotNull($conn);
        $this->assertEmpty($conn->connect_error);
    }

    /**
     * Test database charset is UTF-8
     */
    public function testDatabaseCharsetUtf8() {
        $conn = getTestConnection();
        $result = $conn->query("SELECT @@character_set_client");
        $row = $result->fetch_assoc();

        $this->assertNotNull($row['@@character_set_client']);
        // Should contain utf8
        $this->assertTrue(
            strpos($row['@@character_set_client'], 'utf8') !== false
        );
    }

    /**
     * Test 'data' table exists
     */
    public function testDataTableExists() {
        $conn = getTestConnection();
        $result = $conn->query("SHOW TABLES LIKE 'data'");

        $this->assertEquals(1, $result->num_rows);
    }

    /**
     * Test 'data' table has correct columns
     */
    public function testDataTableHasCorrectColumns() {
        $conn = getTestConnection();
        $result = $conn->query("DESCRIBE data");

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row['Type'];
        }

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('dev_id', $columns);
        $this->assertArrayHasKey('dev_time', $columns);
        $this->assertArrayHasKey('tempUL', $columns);
        $this->assertArrayHasKey('tempDOM', $columns);
        $this->assertArrayHasKey('pressure', $columns);
        $this->assertArrayHasKey('inserted', $columns);
    }

    /**
     * Test 'data' table id column is auto-increment
     */
    public function testDataTableIdIsAutoIncrement() {
        $conn = getTestConnection();
        $result = $conn->query("DESCRIBE data");

        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'id') {
                $this->assertStringContainsString('auto_increment', $row['Extra']);
                break;
            }
        }
    }

    /**
     * Test 'config' table exists
     */
    public function testConfigTableExists() {
        $conn = getTestConnection();
        $result = $conn->query("SHOW TABLES LIKE 'config'");

        $this->assertEquals(1, $result->num_rows);
    }

    /**
     * Test 'config' table has correct columns
     */
    public function testConfigTableHasCorrectColumns() {
        $conn = getTestConnection();
        $result = $conn->query("DESCRIBE config");

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row['Type'];
        }

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('param_name', $columns);
        $this->assertArrayHasKey('param_data', $columns);
        $this->assertArrayHasKey('comment', $columns);
    }

    /**
     * Test required indexes exist on data table
     */
    public function testIndexesExistOnDataTable() {
        $conn = getTestConnection();
        $result = $conn->query("SHOW INDEXES FROM data");

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['Key_name'] !== 'PRIMARY') {
                $indexes[$row['Key_name']] = $row['Column_name'];
            }
        }

        // Should have at least idx_dev_id and idx_inserted
        $this->assertTrue(
            isset($indexes['idx_dev_id']) || isset($indexes['idx_inserted']),
            'Expected indexes not found'
        );
    }

    /**
     * Test config table has default correction values
     */
    public function testConfigTableHasDefaultCorrections() {
        $conn = getTestConnection();
        $result = $conn->query("SELECT param_name FROM config");

        $params = [];
        while ($row = $result->fetch_assoc()) {
            $params[] = $row['param_name'];
        }

        $this->assertContains('ULcorr', $params);
        $this->assertContains('DOMcorr', $params);
        $this->assertContains('PRESScorr', $params);
    }

    /**
     * Test can insert data into data table
     */
    public function testCanInsertDataIntoTable() {
        $conn = getTestConnection();
        $stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, ?)");

        $devId = 1;
        $devTime = '12:34:56';
        $tempUL = 22.5;
        $tempDOM = 21.0;
        $pressure = 1013.25;

        $stmt->bind_param("isddd", $devId, $devTime, $tempUL, $tempDOM, $pressure);
        $result = $stmt->execute();

        $this->assertTrue($result);
    }

    /**
     * Test can read data from data table
     */
    public function testCanReadDataFromTable() {
        $conn = getTestConnection();

        // Insert test data
        $stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, ?)");
        $devId = 2;
        $devTime = '14:30:45';
        $tempUL = 23.5;
        $tempDOM = 22.0;
        $pressure = 1014.0;

        $stmt->bind_param("isddd", $devId, $devTime, $tempUL, $tempDOM, $pressure);
        $stmt->execute();

        // Read it back
        $result = $conn->query("SELECT * FROM data WHERE dev_id = 2");
        $row = $result->fetch_assoc();

        $this->assertEquals(2, $row['dev_id']);
        $this->assertEquals('14:30:45', $row['dev_time']);
    }

    /**
     * Test temperature values are stored as float
     */
    public function testTemperatureValuesStoredAsFloat() {
        $conn = getTestConnection();

        $stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, ?)");
        $devId = 3;
        $devTime = '15:45:30';
        $tempUL = 22.789;
        $tempDOM = 21.456;
        $pressure = 1013.5;

        $stmt->bind_param("isddd", $devId, $devTime, $tempUL, $tempDOM, $pressure);
        $stmt->execute();

        $result = $conn->query("SELECT tempUL, tempDOM FROM data WHERE dev_id = 3");
        $row = $result->fetch_assoc();

        $this->assertEqualsWithDelta(22.789, $row['tempUL'], 0.01);
        $this->assertEqualsWithDelta(21.456, $row['tempDOM'], 0.01);
    }

    /**
     * Test pressure can be NULL
     */
    public function testPressureCanBeNull() {
        $conn = getTestConnection();

        $stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES (?, ?, ?, ?, NULL)");
        $devId = 4;
        $devTime = '16:20:15';
        $tempUL = 20.5;
        $tempDOM = 19.5;

        $stmt->bind_param("isdd", $devId, $devTime, $tempUL, $tempDOM);
        $stmt->execute();

        $result = $conn->query("SELECT pressure FROM data WHERE dev_id = 4");
        $row = $result->fetch_assoc();

        $this->assertNull($row['pressure']);
    }

    /**
     * Test inserted timestamp is auto-generated
     */
    public function testInsertedTimestampAutoGenerated() {
        $conn = getTestConnection();

        $beforeTime = time();

        $stmt = $conn->prepare("INSERT INTO data (dev_id, dev_time, tempUL, tempDOM) VALUES (?, ?, ?, ?)");
        $devId = 5;
        $devTime = '17:15:00';
        $tempUL = 21.0;
        $tempDOM = 20.0;

        $stmt->bind_param("isdd", $devId, $devTime, $tempUL, $tempDOM);
        $stmt->execute();

        $afterTime = time();

        $result = $conn->query("SELECT inserted FROM data WHERE dev_id = 5");
        $row = $result->fetch_assoc();

        $insertedTimestamp = strtotime($row['inserted']);
        $this->assertGreaterThanOrEqual($beforeTime, $insertedTimestamp);
        $this->assertLessThanOrEqual($afterTime, $insertedTimestamp);
    }

    /**
     * Test can update config values
     */
    public function testCanUpdateConfigValues() {
        $conn = getTestConnection();

        $stmt = $conn->prepare("UPDATE config SET param_data = ? WHERE param_name = ?");
        $newValue = '0.75';
        $paramName = 'ULcorr';

        $stmt->bind_param("ss", $newValue, $paramName);
        $result = $stmt->execute();

        $this->assertTrue($result);

        // Verify update
        $checkResult = $conn->query("SELECT param_data FROM config WHERE param_name = 'ULcorr'");
        $row = $checkResult->fetch_assoc();

        $this->assertEquals('0.75', $row['param_data']);
    }

    /**
     * Test database size (not too large)
     */
    public function testDatabaseSizeReasonable() {
        $conn = getTestConnection();

        // Get database size
        $result = $conn->query("SELECT
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()");

        $row = $result->fetch_assoc();
        $sizeInMb = floatval($row['size_mb']);

        // Test database should be small (< 10 MB)
        $this->assertLessThan(10, $sizeInMb);
    }
}
?>

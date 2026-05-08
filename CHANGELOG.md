# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2024-05-08

### 🔒 Security Improvements

- **CRITICAL**: Removed `eval()` function from `getlast.php` (prevents code injection)
- **CRITICAL**: Fixed SQL injection vulnerability in `save.php` (now uses prepared statements)
- Removed hardcoded WiFi credentials from sketches (moved to EEPROM)
- Removed hardcoded server IP/URL from sketches (moved to EEPROM)
- Added API key authentication to `save.php`
- Added input validation and type checking in PHP
- Added temperature and pressure range validation
- Improved error logging (errors no longer exposed to clients)

### 🎯 Features

- Added EEPROM configuration system for both ESP32 boards
- Added API authentication via `api_key` parameter
- Added configurable database connection via environment variables
- Added `EEPROM_CONFIG_GUIDE.md` for setup instructions
- Added structured data parsing instead of magic array indices
- Added support for multiple sensor devices
- Enabled deep sleep mode (can be activated in sketches)
- Added battery voltage calculation and display
- Added pressure range validation

### 🔧 Refactoring

- **Arduino**: Reorganized code into modular functions
  - `readSensors()`: Async sensor reading with timeout
  - `connectWiFi()`: Improved WiFi connection logic
  - `sendData()`: Clean data transmission
  - `loadConfig()`: EEPROM configuration loading
  
- **Arduino**: Created `SensorData` and `Config` structures for type safety

- **Arduino Display**: Refactored 200+ line `setup()` into helper functions
  - `parseServerResponse()`: Safe CSV parsing with bounds checking
  - `drawBatteryIcon()`: Battery level visualization
  - `drawWiFiIcon()`: WiFi signal strength visualization
  - `displayTemperatures()`: Temperature display logic
  - `displayError()`: Error message display

- **PHP**: Replaced deprecated `mysqli_*` functions with OOP MySQLi
- **PHP**: Improved datetime handling (removed unnecessary format/parse cycles)
- **PHP**: Removed `TimeToSec()` helper (now using native DateTime methods)
- **PHP**: Applied DRY principle to battery icon selection code

### 📚 Documentation

- Completely rewrote `README.md` with comprehensive guides
- Added `EEPROM_CONFIG_GUIDE.md` for device configuration
- Added API documentation with examples
- Added database schema documentation
- Added troubleshooting section
- Added security notes and best practices

### 🗄️ Database

- Added missing indexes on `data` table
  - `idx_dev_id`: For querying by device
  - `idx_inserted`: For time-based queries
  - `idx_dev_id_inserted`: Composite index for common queries
- Improved primary key definition in `data` table
- Added proper collation specification

### 📋 Code Quality

- Improved variable naming for clarity
- Added descriptive comments for complex sections
- Fixed off-by-one errors in array bounds
- Improved error handling and recovery
- Added proper type casting for numeric values

### ⚠️ Breaking Changes

- WiFi credentials moved from code to EEPROM
- Server URL moved from code to EEPROM
- API endpoint now requires `api_key` parameter
- Database configuration now via environment variables
- CSV response format slightly changed (field order)

### 🔄 Migration Notes

For existing installations:
1. Update database schema to add indexes
2. Configure EEPROM on each ESP32 device
3. Update server environment variables
4. Test API authentication with new `api_key` parameter
5. Update client code to handle new CSV field positions

## [1.0.0] - Initial Release

- Basic dual-channel thermometer functionality
- Sensor board: DS18B20 temperature + BMP280 pressure
- Display board: e-ink display with WiFi connectivity
- MySQL database for data storage
- PHP backend for data management
- Arduino sketches for both boards

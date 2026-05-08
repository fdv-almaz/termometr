# Thermometer - WiFi Remote Temperature Display System

A distributed home temperature monitoring system using ESP32 microcontrollers, web backend, and e-ink displays.

## 🎯 Features

- **Dual-channel temperature sensing** (outside and inside)
- **Atmospheric pressure monitoring** via BMP280 sensor
- **Low-power e-ink display** for remote viewing
- **Multiple sensor support** via EEPROM configuration
- **Configurable temperature corrections** stored in database
- **Secure API** with authentication
- **Deep sleep support** for battery-powered operation

## 📋 Requirements

### Hardware

- **Sensor Board**: ESP32 + DS18B20 (×2) + BMP280
- **Display Board**: ESP32 + GxEPD 2.13" e-ink display (TTGO T5 V2.4.1+)
- **Server**: Linux with Apache/Nginx and PHP 7.4+
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **WiFi Network**: 2.4GHz (ESP32 doesn't support 5GHz)

### Software

- Arduino IDE 1.8.19+ with ESP32 board support (v2.0.0+)
- PHP 7.4 or later
- MySQL/MariaDB CLI client

### Required Libraries

For Sensor Board:
- `ESP32Time`
- `OneWire`
- `DallasTemperature`
- `Adafruit_BMP280`

For Display Board:
- `GxEPD`
- `GxDEPG0213BN`
- All GxEPD dependencies

## 🚀 Quick Start

### 1. Database Setup

```bash
# Create database and user
mysql -u root -p < mysql/meteoDB_create.sql

# Or manually:
mysql -u root -p
CREATE DATABASE meteo CHARACTER SET utf8mb4;
CREATE USER 'meteo_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON meteo.* TO 'meteo_user'@'localhost';
```

### 2. Web Backend Setup

```bash
# Copy files to your web server
cp -r meteo/ /var/www/html/
chown -R www-data:www-data /var/www/html/meteo/
mkdir -p /var/www/html/meteo/logs
chmod 755 /var/www/html/meteo/logs

# Configure environment
cat > /var/www/html/meteo/.env << EOF
DB_HOST=localhost
DB_USER=meteo_user
DB_PASS=your_secure_password
DB_NAME=meteo
API_KEY=your_api_key_here
ENABLE_AUTH=true
EOF
chmod 600 /var/www/html/meteo/.env
```

### 3. Arduino Setup

1. Install Arduino IDE and ESP32 board support
2. Install required libraries via Library Manager
3. **Important**: Configure EEPROM on each ESP32 before uploading main sketch

See `EEPROM_CONFIG_GUIDE.md` for EEPROM configuration instructions.

4. Upload sensor board sketch to first ESP32
5. Upload display board sketch to second ESP32

## 📡 API Documentation

### GET /meteo/save.php

Saves temperature data from sensor board.

**Parameters**:
- `api_key` (required): Authentication key
- `dev_id` (required): Device ID (1-255)
- `time` (optional): Time in HH:MM:SS format
- `temperatureUL` (required): Outside temperature (-50 to +100°C)
- `temperatureDOM` (required): Inside temperature (-50 to +100°C)
- `press` (optional): Pressure (300-1200 hPa)

**Example**:
```
GET /meteo/save.php?api_key=abc123&dev_id=1&time=14:30:45&temperatureUL=5.2&temperatureDOM=21.3&press=1013.25
```

**Response**:
```json
{"status": "success", "message": "Data saved"}
```

### GET /meteo/getlast.php

Retrieves latest temperature data for display.

**Response Format** (CSV):
```
device_id,timestamp,tempInside,tempOutside,corrInside,corrOutside,pressure
```

**Example**:
```
1,2024-05-08 14:30:45,21.30,5.20,+0.0,-1.5,1013.2
```

## 🔧 Configuration

### Temperature Corrections

Corrections are stored in `config` table and applied server-side:

```sql
INSERT INTO config (param_name, param_data, comment) VALUES
('ULcorr', '-1.5', 'Outside temperature correction'),
('DOMcorr', '+0.5', 'Inside temperature correction'),
('PRESScorr', '-3.0', 'Pressure correction');
```

### Sleep Intervals

- **Sensor Board**: Set `sleep_minutes` in code (default: 1 minute between readings)
- **Display Board**: Set `update_interval_minutes` in code (default: 10 minutes)

## 🔐 Security

⚠️ **Important Security Notes**:

- Change `API_KEY` in `.env` file immediately
- Use HTTPS in production
- Store EEPROM data securely
- Don't commit configuration files to version control
- Set proper file permissions on web directories
- Enable database backups

## 📊 Database Schema

### data table
- `id`: Auto-increment primary key
- `dev_id`: Device identifier
- `dev_time`: Time from device
- `tempUL`: Outside temperature
- `tempDOM`: Inside temperature
- `pressure`: Atmospheric pressure
- `inserted`: Server-side timestamp

### config table
- `id`: Primary key
- `param_name`: Configuration parameter name
- `param_data`: Parameter value
- `comment`: Description

## 🐛 Troubleshooting

**Display shows "No WiFi"**
- Check EEPROM SSID and password
- Verify WiFi credentials are correct
- Check WiFi signal strength (at least -80 dBm)

**Data not saving**
- Check API key in request
- Verify PHP error logs: `/var/www/html/meteo/logs/error.log`
- Test database connection: `mysql -u meteo_user -p meteo -e "SELECT 1"`

**Temperature values seem wrong**
- Check sensor wiring
- Verify DS18B20 pull-up resistor (4.7kΩ)
- Check BMP280 I2C address (0x76 or 0x77)

**Display shows old data**
- Sensor board may have connection issues
- Check network connectivity
- Verify API key matches in both sketches

## 📝 License

Original design by Dzmitry Frantskevich

**Latest improvements** (v2.0.0 - 2024):
- Security fixes (SQL injection prevention, eval() removal)
- Configuration management via EEPROM
- API authentication
- Improved error handling
- Data validation
- Code refactoring

## 🤝 Contributing

Contributions welcome. Please ensure:
- Code follows existing style
- Security vulnerabilities are reported privately
- Changes are well-tested
- Documentation is updated

## 📞 Support

For issues, questions, or suggestions, please create a GitHub issue or contact the maintainers.

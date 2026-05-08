# EEPROM Configuration Guide

This project requires configuration stored in the EEPROM of each ESP32 device. Instead of hardcoding WiFi credentials and server URLs in the sketch, they are read from EEPROM at startup.

## Configuration Parameters

### Sensor Board (board_with_sensors_data_sent_to_the_server)

| Address | Size | Parameter | Example | Description |
|---------|------|-----------|---------|-------------|
| 0 | 32 bytes | SSID | `MyWiFi` | WiFi network name |
| 32 | 64 bytes | Password | `secure_password` | WiFi password |
| 96 | 96 bytes | Server URL | `http://192.168.1.100/meteo/save.php` | Full URL to data endpoint |
| 192 | 1 byte | Device ID | `1` | Unique sensor identifier |
| 196 | 32 bytes | API Key | `your_api_key_here` | API authentication key |

### Display Board (screen_to_get_temperatures_from_server_eInk)

| Address | Size | Parameter | Example | Description |
|---------|------|-----------|---------|-------------|
| 0 | 32 bytes | SSID | `MyWiFi` | WiFi network name |
| 32 | 64 bytes | Password | `secure_password` | WiFi password |
| 96 | 96 bytes | Server URL | `http://192.168.1.100/meteo/getlast.php` | Full URL to data retrieval endpoint |

## How to Configure EEPROM

### Option 1: Using Arduino IDE Serial Monitor

1. Create a temporary sketch with EEPROM writing code:

```cpp
#include <EEPROM.h>

void setup() {
  Serial.begin(115200);
  
  // Configure sensor board
  const char* ssid = "MyWiFi";
  const char* password = "secure_password";
  const char* server_url = "http://192.168.1.100/meteo/save.php";
  uint8_t device_id = 1;
  const char* api_key = "your_api_key_here";
  
  EEPROM.begin(256);
  
  EEPROM.writeBytes(0, (uint8_t*)ssid, 32);
  EEPROM.writeBytes(32, (uint8_t*)password, 64);
  EEPROM.writeBytes(96, (uint8_t*)server_url, 96);
  EEPROM.write(192, device_id);
  EEPROM.writeBytes(196, (uint8_t*)api_key, 32);
  
  EEPROM.commit();
  EEPROM.end();
  
  Serial.println("EEPROM configured successfully");
}

void loop() {}
```

2. Upload this sketch to your ESP32
3. Open Serial Monitor at 115200 baud
4. Verify "EEPROM configured successfully" message

### Option 2: Via Web Interface (Future)

A web configuration interface can be added for easier updates without recompilation.

## Environment Variables for PHP Backend

Set these via `.env` file or system environment variables:

```bash
export DB_HOST=localhost
export DB_USER=meteo_user
export DB_PASS=your_secure_password
export DB_NAME=meteo
export API_KEY=your_api_key_here
export ENABLE_AUTH=true
```

Or in Apache/PHP-FPM configuration:

```apache
SetEnv DB_HOST localhost
SetEnv DB_USER meteo_user
SetEnv DB_PASS your_secure_password
SetEnv DB_NAME meteo
SetEnv API_KEY your_api_key_here
SetEnv ENABLE_AUTH true
```

## Database Setup

1. Create database and user:

```sql
CREATE DATABASE meteo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'meteo_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON meteo.* TO 'meteo_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Import schema:

```bash
mysql -u meteo_user -p meteo < mysql/meteoDB_create.sql
```

## Security Notes

- **Never commit EEPROM data or .env files to version control**
- **Use strong, unique API keys**
- **Keep EEPROM passwords and API keys safe**
- **Use HTTPS in production** (requires certificate)
- **Change default values before deploying to production**

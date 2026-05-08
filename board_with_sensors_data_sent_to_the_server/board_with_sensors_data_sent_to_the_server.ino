#include <ESP32Time.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <SPI.h>
#include <Adafruit_BMP280.h>
#include <EEPROM.h>

#define BMP_SCK  (13)
#define BMP_MISO (12)
#define BMP_MOSI (11)
#define BMP_CS   (10)
#define ONE_WIRE_BUS 15

// EEPROM configuration addresses
#define EEPROM_SSID_ADDR 0
#define EEPROM_PASS_ADDR 32
#define EEPROM_URL_ADDR 96
#define EEPROM_DEV_ID_ADDR 192
#define EEPROM_API_KEY_ADDR 196
#define EEPROM_SIZE 256

// Configuration structures
struct SensorData {
  float tempOutside;
  float tempInside;
  float pressure;
  String timestamp;
};

struct Config {
  char ssid[32];
  char password[64];
  char server_url[96];
  uint8_t device_id;
  char api_key[32];
};

Adafruit_BMP280 bmp;
Adafruit_Sensor *bmp_temp = bmp.getTemperatureSensor();
Adafruit_Sensor *bmp_pressure = bmp.getPressureSensor();

HTTPClient http;

const uint8_t sleep_minutes = 1;
const uint8_t wifi_timeout_seconds = 20;
const uint8_t sensor_read_timeout_seconds = 10;

unsigned long lastSensorReadTime = 0;
const long sensor_interval = 60000;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

Config config;
SensorData sensorData;
char timeBuffer[20] = "";

void setTimezone(const char* timezone) {
  Serial.printf("Setting Timezone to %s\n", timezone);
  setenv("TZ", timezone, 1);
  tzset();
}

bool getLocalTime(String &timeStr) {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) {
    Serial.println("Failed to obtain time");
    return false;
  }
  strftime(timeBuffer, sizeof(timeBuffer), "%H:%M:%S", &timeinfo);
  timeStr = String(timeBuffer);
  return true;
}

bool loadConfig() {
  EEPROM.begin(EEPROM_SIZE);
  EEPROM.readBytes(EEPROM_SSID_ADDR, config.ssid, 32);
  EEPROM.readBytes(EEPROM_PASS_ADDR, config.password, 64);
  EEPROM.readBytes(EEPROM_URL_ADDR, config.server_url, 96);
  EEPROM.read(EEPROM_DEV_ID_ADDR);
  EEPROM.readBytes(EEPROM_API_KEY_ADDR, config.api_key, 32);
  EEPROM.end();

  if (strlen(config.ssid) == 0) {
    Serial.println("Warning: Configuration not set. Please update EEPROM.");
    return false;
  }
  return true;
}

bool initBMP280() {
  if (!bmp.begin(0x76)) {
    Serial.println("Error: BMP280 not found. Check wiring or I2C address.");
    return false;
  }
  bmp.setSampling(Adafruit_BMP280::MODE_NORMAL,
                  Adafruit_BMP280::SAMPLING_X2,
                  Adafruit_BMP280::SAMPLING_X16,
                  Adafruit_BMP280::FILTER_X16,
                  Adafruit_BMP280::STANDBY_MS_500);
  return true;
}

bool readSensors() {
  sensors.setWaitForConversion(false);
  sensors.requestTemperatures();

  unsigned long startTime = millis();
  while (!sensors.isConversionAvailable(0) && (millis() - startTime) < sensor_read_timeout_seconds * 1000) {
    delay(50);
  }

  if (!sensors.isConversionAvailable(0)) {
    Serial.println("Sensor read timeout");
    return false;
  }

  sensorData.tempOutside = sensors.getTempCByIndex(0);
  sensorData.tempInside = sensors.getTempCByIndex(1);

  sensors_event_t temp_event, pressure_event;
  bmp_temp->getEvent(&temp_event);
  bmp_pressure->getEvent(&pressure_event);
  sensorData.pressure = pressure_event.pressure;

  return true;
}

bool connectWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(config.ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(config.ssid, config.password);

  uint8_t attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < wifi_timeout_seconds) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    return true;
  }

  Serial.println("Failed to connect to WiFi");
  return false;
}

bool sendData() {
  String fullUrl = String(config.server_url) + "?" +
                   "api_key=" + String(config.api_key) +
                   "&dev_id=" + String(config.device_id) +
                   "&time=" + sensorData.timestamp +
                   "&temperatureUL=" + String(sensorData.tempOutside, 1) +
                   "&temperatureDOM=" + String(sensorData.tempInside, 1) +
                   "&press=" + String(sensorData.pressure, 1);

  Serial.print("Sending to: ");
  Serial.println(fullUrl);

  http.begin(fullUrl);
  int httpCode = http.GET();

  bool success = false;
  if (httpCode > 0) {
    Serial.printf("HTTP Response: %d\n", httpCode);
    if (httpCode == HTTP_CODE_OK) {
      String response = http.getString();
      Serial.println("Response: " + response);
      success = true;
    }
  } else {
    Serial.printf("HTTP Error: %s\n", http.errorToString(httpCode).c_str());
  }

  http.end();
  return success;
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n\nESP32 Thermometer Sensor starting...");
  Serial.printf("Free Heap: %d\n", ESP.getFreeHeap());

  // Initialize sensors
  sensors.begin();
  if (!initBMP280()) {
    Serial.println("Warning: BMP280 initialization failed. Continuing anyway.");
  }

  // Load configuration from EEPROM
  if (!loadConfig()) {
    Serial.println("Error: Configuration not loaded. Device will not function.");
    Serial.println("Please update EEPROM with WiFi credentials and server URL.");
  }

  // Configure time
  configTime(0, 0, "europe.pool.ntp.org");
  setTimezone("CET-1CEST,M3.5.0,M10.5.0/3");

  String timeStr;
  if (!getLocalTime(timeStr)) {
    Serial.println("Warning: Failed to get NTP time. Using fallback.");
    ESP32Time rtc;
    rtc.setTime(1750802400);
  } else {
    Serial.print("Time: ");
    Serial.println(timeStr);
  }

  Serial.println("Setup complete.");
}

void loop() {
  if ((millis() - lastSensorReadTime) >= sensor_interval) {
    lastSensorReadTime = millis();

    Serial.println("\n--- Reading sensors ---");

    // Read sensor data
    if (!readSensors()) {
      Serial.println("Error: Sensor read failed.");
      return;
    }

    // Get timestamp
    if (!getLocalTime(sensorData.timestamp)) {
      Serial.println("Error: Could not get timestamp.");
      return;
    }

    Serial.printf("Data: Out=%.1f°C, In=%.1f°C, Press=%.1f hPa\n",
                  sensorData.tempOutside,
                  sensorData.tempInside,
                  sensorData.pressure);

    // Send data via WiFi
    if (connectWiFi()) {
      if (sendData()) {
        Serial.println("Data sent successfully");
      } else {
        Serial.println("Data send failed");
      }

      WiFi.disconnect(true);
      Serial.println("WiFi disconnected");
    } else {
      Serial.println("WiFi connection failed");
    }

    // Uncomment for deep sleep mode (saves battery)
    // Serial.println("Entering deep sleep...");
    // esp_sleep_enable_timer_wakeup(sleep_minutes * 60000000);
    // esp_deep_sleep_start();
  }

  delay(100);
}

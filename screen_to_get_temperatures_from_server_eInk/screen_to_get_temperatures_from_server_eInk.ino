#include <GxEPD.h>
#include <GxDEPG0213BN/GxDEPG0213BN.h>
#include <GxIO/GxIO_SPI/GxIO_SPI.h>
#include <GxIO/GxIO.h>
#include <SD.h>
#include <FS.h>
#include <SPI.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <Fonts/TomThumb.h>
#include <Fonts/Org_01.h>
#include <Fonts/Picopixel.h>
#include <Fonts/FreeSans9pt7b.h>
#include <Fonts/FreeSansBold9pt7b.h>
#include <Fonts/FreeSansBold18pt7b.h>
#include "avatar_bitmap.h"
#include "wifi_bitmap.h"
#include "battery_bitmap.h"
#include <HTTPClient.h>
#include <EEPROM.h>

// Battery voltage thresholds
#define BATT_MAX 4.2
#define BATT_75p 4.0
#define BATT_50p 3.6
#define BATT_25p 3.2
#define BATT_MIN 3.0
#define BATT_PIN 35
#define BTN 39

// EEPROM config addresses
#define EEPROM_SSID_ADDR 0
#define EEPROM_PASS_ADDR 32
#define EEPROM_URL_ADDR 96
#define EEPROM_SIZE 256

// Data structure for server response
struct DisplayData {
  String deviceId;
  String timestamp;
  float tempInside;
  float tempOutside;
  float corrInside;
  float corrOutside;
  float pressure;
  bool isStale;
};

struct Config {
  char ssid[32];
  char password[64];
  char server_url[96];
};

GxIO_Class io(SPI, SS, 17, 16);
GxEPD_Class display(io, 16, 4);

HTTPClient http;

const uint8_t update_interval_minutes = 10;
const uint8_t wifi_connect_timeout = 60;

uint8_t bootCount = 0;
Config config;
DisplayData displayData;

bool loadConfig() {
  EEPROM.begin(EEPROM_SIZE);
  EEPROM.readBytes(EEPROM_SSID_ADDR, config.ssid, 32);
  EEPROM.readBytes(EEPROM_PASS_ADDR, config.password, 64);
  EEPROM.readBytes(EEPROM_URL_ADDR, config.server_url, 96);
  EEPROM.end();

  if (strlen(config.ssid) == 0) {
    Serial.println("Warning: WiFi config not set in EEPROM");
    return false;
  }
  return true;
}

float getBatteryVoltage() {
  uint16_t raw = analogRead(BATT_PIN);
  int vref = 1100;
  return ((float)raw / 4095.0) * 2.0 * 3.3 * (vref / 1000.0);
}

float getBatteryPercent(float voltage) {
  if (voltage <= BATT_MIN) return 0;
  if (voltage >= BATT_MAX) return 100;
  return (100.0 - ((BATT_MAX - voltage) / ((BATT_MAX - BATT_MIN) / 100.0)));
}

void drawBatteryIcon(float voltage) {
  if (voltage <= BATT_MIN)
    display.drawBitmap(2, 6, battEmpty, 51, 23, GxEPD_BLACK);
  else if (voltage <= BATT_25p)
    display.drawBitmap(2, 6, batt25, 51, 23, GxEPD_BLACK);
  else if (voltage <= BATT_50p)
    display.drawBitmap(2, 6, batt50, 51, 23, GxEPD_BLACK);
  else if (voltage <= BATT_75p)
    display.drawBitmap(2, 6, batt75, 51, 23, GxEPD_BLACK);
  else
    display.drawBitmap(2, 6, batt100, 51, 23, GxEPD_BLACK);
}

void drawWiFiIcon(int rssi) {
  if (rssi >= 0)
    display.drawBitmap(217, 6, wifiSignalEmpty, 32, 23, GxEPD_BLACK);
  else if (rssi < -75)
    display.drawBitmap(217, 6, wifiSignal100, 32, 23, GxEPD_BLACK);
  else if (rssi <= -50)
    display.drawBitmap(217, 6, wifiSignal75, 32, 23, GxEPD_BLACK);
  else if (rssi <= -25)
    display.drawBitmap(217, 6, wifiSignal50, 32, 23, GxEPD_BLACK);
  else
    display.drawBitmap(217, 6, wifiSignal25, 32, 23, GxEPD_BLACK);
}

bool parseServerResponse(const String &payload) {
  // Format: device_id,timestamp,tempInside,tempOutside,corrInside,corrOutside,pressure
  int commaIndex[6] = {0};
  int commaCount = 0;

  for (int i = 0; i < payload.length() && commaCount < 6; i++) {
    if (payload[i] == ',') {
      commaIndex[commaCount++] = i;
    }
  }

  if (commaCount < 6) {
    Serial.println("Error: Invalid response format");
    return false;
  }

  displayData.deviceId = payload.substring(0, commaIndex[0]);
  displayData.timestamp = payload.substring(commaIndex[0] + 1, commaIndex[1]);
  displayData.tempInside = payload.substring(commaIndex[1] + 1, commaIndex[2]).toFloat();
  displayData.tempOutside = payload.substring(commaIndex[2] + 1, commaIndex[3]).toFloat();
  displayData.corrInside = payload.substring(commaIndex[3] + 1, commaIndex[4]).toFloat();
  displayData.corrOutside = payload.substring(commaIndex[4] + 1, commaIndex[5]).toFloat();
  displayData.pressure = payload.substring(commaIndex[5] + 1).toFloat();
  displayData.isStale = (displayData.deviceId == "--");

  return true;
}

bool connectWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(config.ssid);

  WiFi.persistent(true);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.begin(config.ssid, config.password);

  uint8_t timeout = wifi_connect_timeout;
  while (WiFi.status() != WL_CONNECTED && timeout > 0) {
    delay(500);
    Serial.print(".");
    timeout--;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected!");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());
    return true;
  }

  Serial.println("WiFi connection failed");
  return false;
}

bool fetchData() {
  http.begin(config.server_url);
  int httpCode = http.GET();

  if (httpCode != HTTP_CODE_OK) {
    Serial.printf("HTTP Error: %d\n", httpCode);
    http.end();
    return false;
  }

  String payload = http.getString();
  http.end();

  return parseServerResponse(payload);
}

void drawLayout() {
  display.setTextColor(GxEPD_BLACK);
  display.setRotation(1);

  display.drawLine(0, 31, 250, 31, 0);
  display.drawLine(125, 31, 125, 110, 0);
  display.drawLine(0, 110, 250, 110, 0);
}

void drawBatteryInfo(float voltage) {
  float percent = getBatteryPercent(voltage);
  display.setFont(&Org_01);
  display.setCursor(60, 19);
  display.printf("%.2fV / %.0f%%", voltage, percent);
  drawBatteryIcon(voltage);
}

void drawWiFiInfo(int rssi) {
  display.setFont(&Org_01);
  display.setCursor(170, 19);
  display.printf("%3d dBm", rssi);
  drawWiFiIcon(rssi);
}

void displayError(const String &error) {
  display.fillRect(80, 50, 85, 30, GxEPD_WHITE);
  display.setFont(&FreeSans9pt7b);
  display.setCursor(85, 70);
  display.print(error);
  display.drawRect(80, 50, 85, 30, GxEPD_BLACK);
  display.updateWindow(0, 0, 250, 128, true);
}

void displayTemperatures() {
  display.setFont(&FreeSans9pt7b);
  display.setCursor(5, 50);
  display.print("outside");
  display.setCursor(133, 50);
  display.print("inside");

  display.fillCircle(155, 17, 11, 0);
  display.setTextColor(GxEPD_WHITE);
  display.setCursor(150, 23);
  display.setFont(&FreeSansBold9pt7b);
  display.print(displayData.deviceId);
  display.drawCircle(155, 17, 10, 1);
  display.drawCircle(155, 17, 9, 1);

  display.setFont(&FreeSansBold18pt7b);
  display.setTextColor(GxEPD_BLACK);
  display.setCursor(20, 90);
  display.printf("%.2f", displayData.tempInside);
  display.setCursor(140, 90);
  display.printf("%.2f", displayData.tempOutside);

  display.setFont(&Org_01);
  display.setCursor(10, 122);
  display.print(displayData.timestamp);

  if (abs(displayData.corrInside) > 0.01) {
    display.setCursor(25, 100);
    display.print("corr: ");
    display.printf("%+.1f", displayData.corrInside);
  }

  if (abs(displayData.corrOutside) > 0.01) {
    display.setCursor(165, 100);
    display.print("corr: ");
    display.printf("%+.1f", displayData.corrOutside);
  }

  if (displayData.pressure > 0) {
    display.setRotation(0);
    display.setCursor(34, 241);
    display.printf("%.1f hPa", displayData.pressure);
    display.setRotation(1);
  }
}

void setup(void)
{
  Serial.begin(115200);
  Serial.println("\n\nESP32 E-ink Display starting...");

  display.init();

  if (bootCount < 1) {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setRotation(1);
    display.update();
  }
  bootCount++;
  Serial.printf("Boot count: %d\n", bootCount);

  pinMode(BATT_PIN, INPUT);
  pinMode(BTN, INPUT_PULLDOWN);

  // Load configuration from EEPROM
  if (!loadConfig()) {
    Serial.println("Error: Configuration not loaded. Please update EEPROM.");
  }

  // Get battery voltage
  float battery_voltage = getBatteryVoltage();
  Serial.printf("Battery: %.2fV\n", battery_voltage);

  // Draw layout
  drawLayout();
  drawBatteryInfo(battery_voltage);

  display.updateWindow(0, 0, 250, 128, true);

  // Connect to WiFi
  if (!connectWiFi()) {
    displayError("No WiFi!");
    display.setCursor(173, 23);
    display.print("SLEEP");
    display.updateWindow(170, 6, 79, 23, true);

    display.powerDown();
    esp_sleep_enable_timer_wakeup(60000000 * 1);
    esp_deep_sleep_start();
    return;
  }

  // Draw WiFi info
  int rssi = WiFi.RSSI();
  drawWiFiInfo(rssi);
  display.setCursor(170, 122);
  display.setFont(&Org_01);
  display.printf("%-15s", WiFi.localIP().toString().c_str());

  // Fetch data from server
  if (!fetchData()) {
    displayError("No server");
    WiFi.disconnect();
    display.powerDown();
    esp_sleep_enable_timer_wakeup(60000000 * 1);
    esp_deep_sleep_start();
    return;
  }

  // Display temperatures
  displayTemperatures();
  display.updateWindow(0, 0, 250, 128, true);

  // Show warning if data is stale
  if (displayData.isStale) {
    delay(2000);
    display.fillRect(170, 6, 79, 23, GxEPD_WHITE);
    display.setFont(&FreeSans9pt7b);
    display.setCursor(173, 23);
    display.print("OLD");
    display.updateWindow(170, 6, 79, 23, true);
  }

  // Disconnect and sleep
  WiFi.disconnect();
  display.powerDown();

  Serial.println("Entering deep sleep...");
  esp_sleep_enable_timer_wakeup(update_interval_minutes * 60000000);
  esp_deep_sleep_start();
}

void loop()
{}

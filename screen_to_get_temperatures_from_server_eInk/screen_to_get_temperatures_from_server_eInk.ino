#include <GxEPD.h>
#include <GxDEPG0213BN/GxDEPG0213BN.h>    // 2.13" b/w 128x250, SSD1680, TTGO T5 V2.4.1, V2.3.1
#include <GxIO/GxIO_SPI/GxIO_SPI.h>
#include <GxIO/GxIO.h>
#include <SD.h>
#include <FS.h>
#include <SPI.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <Fonts/TomThumb.h>
#include <Fonts/Org_01.h>
#include <Fonts/Picopixel.h>
#include <Fonts/Tiny3x3a2pt7b.h>
#include <Fonts/FreeSans9pt7b.h>
#include <Fonts/FreeSansBold9pt7b.h>
#include <Fonts/FreeSansBold18pt7b.h>
#include "avatar_bitmap.h"
#include "wifi_bitmap.h"
#include "battery_bitmap.h"
#include <StringAction.h>
#include <HTTPClient.h>

#define BATT_MAX 4.2
#define BATT_75p 4.0
#define BATT_50p 3.6
#define BATT_25p 3.2
#define BATT_MIN 3.0
#define BATT_0p BATT_MIN
#define BATT_PIN 35
#define BTN 39

HTTPClient http;
StringAction s;

// for SPI pin definitions see e.g.:
// C:\Users\xxx\Documents\Arduino\hardware\espressif\esp32\variants\lolin32\pins_arduino.h
GxIO_Class io(SPI, /*CS=5*/ SS, /*DC=*/ 17, /*RST=*/ 16); // arbitrary selection of 17, 16
GxEPD_Class display(io, /*RST=*/ 16, /*BUSY=*/ 4); // arbitrary selection of (16), 4
/*
#define NTP_OFFSET  19800 // In seconds
#define NTP_INTERVAL 60 * 1000    // In miliseconds
#define NTP_ADDRESS  "europe.pool.ntp.org"
*/
/*
WiFiUDP ntpUDP;
const int oneWireBus = 15;
OneWire oneWire(oneWireBus);
DallasTemperature sensors(&oneWire);

struct config {
  uint8_t zadanie;
} conf;
*/

const uint8_t tmin = 10;  // время обновления информации на экране

const char* ssid     = "Sadovaya7";
const char* password = "shadow_warrior";
/*
const int httpPort  = 80;
const int httpsPort = 443;
*/
uint8_t ConnectTimeout = 60;
uint16_t v; int vref = 1100; // battery V
RTC_DATA_ATTR uint32_t bootCount = 0;
RTC_DATA_ATTR int dutyMeter = 0;
RTC_DATA_ATTR int ulpStarted = 0;
//char buffer[20] = "";
//String sbuffer="";

void setup(void)
{
  Serial.begin(115200);
  display.init();
  //  display.init(115200); // enable diagnostic output on Serial
   // disable diagnostic output on Serial
  Serial.println();
  Serial.print("boot counter: ");
  if(bootCount < 1) 
  {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setRotation(1);
//    display.updateWindow(0, 0, 250, 128, true);
    display.update();
  }
  Serial.println(++bootCount);

  pinMode(BATT_PIN, INPUT);
  pinMode(BTN, INPUT_PULLDOWN);

  display.setTextColor(GxEPD_BLACK);
  display.setRotation(1);

//  display.setFont(&FreeMonoBold12pt7b);
//  display.setFont(&FreeSans9pt7b);
//  display.setCursor(0, 45);
//  display.print("Width     : "); display.println(display.width());
//  display.print("Height    : "); display.println(display.height());
//  display.print("Rotation : "); display.println(display.getRotation());
  display.drawLine(0, 31, 250, 31, 0);
  display.drawLine(125, 31, 125, 110, 0);
  display.drawLine(0, 110, 250, 110, 0);

  v = analogRead(BATT_PIN);
  float battery_voltage = ((float)v / 4095.0) * 2.0 * 3.3 * (vref / 1000.0);
  String voltage = "" + String(battery_voltage) + "V";
//  display.print("Battery   : "); display.printf("%2d%%\n", (int)map(battery_voltage, 2.75, 4.22, 0, 100));
//  display.print("  "); display.print(analogRead(BATT_PIN)/565.32); display.print(" / "); display.println(voltage);

//  display.drawBitmap(125, 0, fdv, 125, 125, 1);
  display.setFont(&Org_01);
  display.setCursor(60, 19);
  display.print(voltage); 
  Serial.print(voltage); 
//  float battery_voltage_perc = 100-((battery_voltage-BATT_MIN)/(BATT_MAX-BATT_MIN)/100);
  float battery_voltage_perc = (100-((BATT_MAX - battery_voltage))/((BATT_MAX-BATT_MIN)/100));
// float battery_voltage_perc = map(battery_voltage, BATT_MIN, BATT_MAX, 0, 100);
  display.printf(" / %.1f%%", battery_voltage_perc);
  Serial.printf(" / %f%% / %f / %d / %f\n", battery_voltage_perc, battery_voltage, v, v/565.32);

  if(battery_voltage <= BATT_MIN)
    display.drawBitmap(2, 6, battEmpty, 51, 23, GxEPD_BLACK);
  if(battery_voltage > BATT_MIN && battery_voltage <= BATT_25p)
    display.drawBitmap(2, 6, batt25, 51, 23, GxEPD_BLACK);
  if(battery_voltage >= BATT_25p && battery_voltage <= BATT_50p)
    display.drawBitmap(2, 6, batt50, 51, 23, GxEPD_BLACK);
  if(battery_voltage >= BATT_50p && battery_voltage <= BATT_75p)
    display.drawBitmap(2, 6, batt75, 51, 23, GxEPD_BLACK);
  if(battery_voltage >= BATT_75p)
    display.drawBitmap(2, 6, batt100, 51, 23, GxEPD_BLACK);

  display.drawBitmap(217, 6, wifiSignal0, 32, 23, GxEPD_BLACK);
  display.updateWindow(0, 0, 250, 128, true);

  WiFi.persistent(true);
  WiFi.mode(WIFI_STA); // switch off AP
  //WiFi.setAutoConnect(true);
  WiFi.setAutoReconnect(true);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED)
  {
    delay(500);
    Serial.print(".");
    Serial.print(WiFi.status());
    if (--ConnectTimeout <= 0)
    {
      Serial.println();
      Serial.println("WiFi connect timeout");

      display.fillRect(170, 6, 79, 23, GxEPD_WHITE);
      display.setFont(&FreeSans9pt7b);

      display.fillRect(80, 50, 85, 30, GxEPD_WHITE);
      display.setCursor(85, 70);
      display.print("No Wi-Fi!");
      display.drawRect(80, 50, 85, 30, GxEPD_BLACK);
      

      display.setCursor(173, 23);
      display.print("SLEEP :(");

      display.setFont(&Org_01);
      display.setCursor(10, 122);
      display.print("OLD DATA");
      display.updateWindow(0, 0, 250, 128, true);
 
      display.powerDown();
      esp_sleep_enable_timer_wakeup(60000000*1); // перезапуск через одну минуту
      esp_deep_sleep_start();  

      return;
    }
  }
  Serial.println();
  Serial.println("WiFi connected");
  int rssi = WiFi.RSSI();
  Serial.println(rssi);
  Serial.println(WiFi.localIP());

  display.setCursor(170, 19);
  display.printf("%3d dBm", rssi); 
  display.setCursor(170, 122);
  display.printf("%-15s", WiFi.localIP().toString().c_str()); 

  if(rssi == 0)
   display.drawBitmap(217, 6, wifiSignalEmpty, 32, 23, GxEPD_BLACK);
  if(rssi < 0)
   display.drawBitmap(217, 6, wifiSignal25, 32, 23, GxEPD_BLACK);
  if(rssi <= -25)
   display.drawBitmap(217, 6, wifiSignal50, 32, 23, GxEPD_BLACK);
  if(rssi <= -50)
   display.drawBitmap(217, 6, wifiSignal75, 32, 23, GxEPD_BLACK);
  if(rssi <= -75)
   display.drawBitmap(217, 6, wifiSignal100, 32, 23, GxEPD_BLACK);

  String data_arr[6];
  String payload;
  http.begin("http://192.168.7.2/meteo/getlast.php");
  int httpCode = http.GET();
  if (httpCode == HTTP_CODE_OK) {
        payload = http.getString();
        Serial.println(payload);
  } else {
      Serial.printf("[HTTP] GET... failed, error: %s\n", http.errorToString(httpCode).c_str());
      display.fillRect(80, 50, 85, 30, GxEPD_WHITE);
      display.setCursor(85, 70);
      display.print("No server");
      display.drawRect(80, 50, 85, 30, GxEPD_BLACK);
      http.end();
      WiFi.disconnect();
      display.powerDown();
      esp_sleep_enable_timer_wakeup(60000000*1);  // перезапуск через одну минуту
      esp_deep_sleep_start();  
  }
  http.end();
  
  s.split(payload, data_arr, ",");

  display.setFont(&FreeSans9pt7b);
  display.setCursor(5, 50);
  display.print("outside");
  display.setCursor(133, 50);
  display.print("inside");

  display.fillCircle(155, 17, 11, 0);
  display.setTextColor(GxEPD_WHITE);
  display.setCursor(150, 23);
  display.setFont(&FreeSansBold9pt7b);
  display.print(data_arr[0]); // вывод номера сенсора
  display.drawCircle(155, 17, 10, 1);
  display.drawCircle(155, 17, 9, 1);

  display.setFont(&FreeSansBold18pt7b);
  display.setTextColor(GxEPD_BLACK);
  display.setCursor(20, 90);
  display.cp437(false);
  display.printf("%-2.02f", data_arr[2].toFloat()); // inside
  display.setCursor(140, 90);
  display.printf("%-2.02f", data_arr[3].toFloat()); // outside
  display.setFont(&Org_01);
  display.setCursor(10, 122);
  display.print(data_arr[1]);

  if(data_arr[4] != "0")
  {
    display.setCursor(25, 100);
    display.print("correction: ");
    display.print(data_arr[4]);
  }
  if(data_arr[5] != "+0")
  {
    display.setCursor(165, 100);
    display.print("correction: ");
    display.print(data_arr[5]);
  }

  display.updateWindow(0, 0, 250, 128, true);
  display.fillRect(170, 6, 79, 23, GxEPD_WHITE);
  display.setFont(&FreeSans9pt7b);
  display.setCursor(173, 23);
  display.print("SLEEP...");
  display.updateWindow(170, 6, 79, 23, true);
  if(data_arr[0] == "--") 
  {
    sleep(2);
    display.fillRect(170, 6, 79, 23, GxEPD_WHITE);
    display.setCursor(173, 23);
    display.print("old data");
    display.updateWindow(170, 6, 79, 23, true);
  }
/*
  if(digitalRead(BTN))
  {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setRotation(1);
    display.updateWindow(0, 0, 250, 128, true);
  }
*/
//  display.updateWindow(0, 0, 250, 128, true);

  WiFi.disconnect();
  display.powerDown();
  esp_sleep_enable_timer_wakeup(60000000*tmin);
  esp_deep_sleep_start();  
}

void loop()
{ }

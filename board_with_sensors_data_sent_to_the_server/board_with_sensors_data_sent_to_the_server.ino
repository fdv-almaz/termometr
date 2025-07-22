#include <ESP32Time.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <SPI.h>
#include <Adafruit_BMP280.h>

#define BMP_SCK  (13)
#define BMP_MISO (12)
#define BMP_MOSI (11)
#define BMP_CS   (10)

Adafruit_BMP280 bmp; // use I2C interface
Adafruit_Sensor *bmp_temp = bmp.getTemperatureSensor();
Adafruit_Sensor *bmp_pressure = bmp.getPressureSensor();

HTTPClient http;

const uint8_t tmin = 1;  // на сколько минут отправлять устройство в сон

const char* ssid = "Sadovaya7";          // Замените на имя вашей Wi-Fi сети
const char* password = "shadow_warrior";  // Замените на пароль от вашей Wi-Fi сети

const char* baseUrl = "http://192.168.7.2/meteo/save.php"; // Замените на вашу базовую WEB-ссылку

unsigned long lastCallTime = 0;
unsigned long reboot_lastCallTime = 0;
const long interval = 60000; // 60 секунд * 1000 миллисекунд = 1 минута
const long reboot_interval = 3600000; // 3600 секунд * 1000 миллисекунд = 1 час

// Переменные для хранения динамических данных
float temperatureUL = 0.0;
float temperatureDOM = 0.0;
String deviceId = "ESP32_Sensor_01";
String dev_id = "1";

char buffer[20] = "";

const int oneWireBus = 15;
OneWire oneWire(oneWireBus);
DallasTemperature sensors(&oneWire);

void setTimezone(String timezone){
  Serial.printf("  Setting Timezone to %s\n",timezone.c_str());
  setenv("TZ",timezone.c_str(),1);  //  Now adjust the TZ.  Clock settings are adjusted to show the new local time
  tzset();
}

String takeLocalTime(void){
  struct tm timeinfo;
  if(!getLocalTime(&timeinfo)){
    Serial.println("Failed to obtain time 1");
    return "failed";
  }
  strftime(buffer, 20,"%H:%M:%S", &timeinfo);

  return buffer;
}

void setup() {
  Serial.begin(115200);
  Serial.print("Free Heap: ");
  Serial.println(ESP.getFreeHeap());
  

  Serial.println("ESP32 starting up...");
  sensors.begin();

  configTime(0, 0, "europe.pool.ntp.org");
  setTimezone("CET-1CEST,M3.5.0,M10.5.0/3");
  String formattedTime = takeLocalTime();
  if(formattedTime == "failed")
  {
    Serial.println("Set fake time");
    ESP32Time rtc;
    rtc.setTime(1750802400);
  }
  Serial.print("Time: ");
  Serial.println(formattedTime);
  BMPinit();
}

void loop() {
  String formattedTime;

  // Проверяем, прошло ли достаточно времени с последнего вызова
  /*if (millis() - lastCallTime >= interval)*/ {
    Serial.println("\n--- Time to send data! ---");

    // --- Обновление динамических данных (пример) ---
    // Здесь вы будете читать данные с ваших датчиков
    sensors.requestTemperatures(); 
    temperatureUL = sensors.getTempCByIndex(0); // улица
    temperatureDOM = sensors.getTempCByIndex(1); // дом
    // deviceId остается тем же, но может быть динамическим при необходимости
    // ------------------------------------------------

    Serial.print("Current Data: Temp UL=");
    Serial.print(temperatureUL);
    Serial.print("C, Temp DOM=");
    Serial.print(temperatureDOM);
    Serial.print("C, DeviceID=");
    Serial.println(dev_id);

    callUrlWithDynamicData(temperatureUL, temperatureDOM, deviceId, dev_id); // Вызываем функцию для отправки данных

    lastCallTime = millis(); // Обновляем время последнего вызова
    Serial.println("--- Data sent, waiting for next interval ---");
  }
  if (millis() - reboot_lastCallTime >= reboot_interval) {
    Serial.println("\n--- Time to reboot! ---");
    reboot_lastCallTime = millis();
    ESP.restart();
  }
  // Здесь может выполняться другой ваш неблокирующий код
  // Например, чтение датчиков, обработка данных, переход в спящий режим и т.д.
  // delay(100); // Небольшая задержка, чтобы избежать "голодания" других задач
}

void callUrlWithDynamicData(float tempUL, float tempDOM, String devId, String dev_id) {
  sensors_event_t temp_event, pressure_event;
  bmp_temp->getEvent(&temp_event);
  bmp_pressure->getEvent(&pressure_event);

  // 1. Подключаемся к Wi-Fi
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);
  WiFi.mode(WIFI_STA); // Устанавливаем режим Station, если не установлено
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) { // Ограничиваем количество попыток
    delay(500);
    Serial.print(".");
    attempts++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());

    // Формируем полную WEB-ссылку с динамическими параметрами
    String fullUrl = String(baseUrl) + "?" +
                     "dev_id=" + dev_id +
                     "&time=" + takeLocalTime() +
                     "&temperatureUL=" + String(tempUL, 1) +
                     "&temperatureDOM=" + String(tempDOM) +
                     "&id=" + devId + 
                     "&press=" + String(pressure_event.pressure);

    Serial.print("Calling URL: ");
    Serial.println(fullUrl);

    http.begin(fullUrl);
    int httpCode = http.GET(); // Выполняем GET-запрос

    if (httpCode > 0) {
      Serial.printf("[HTTP] GET... code: %d\n", httpCode);
      if (httpCode == HTTP_CODE_OK) {
        String payload = http.getString();
        Serial.println("Server Response: " + payload);
      }
    } else {
      Serial.printf("[HTTP] GET... failed, error: %s\n", http.errorToString(httpCode).c_str());
    }
    http.end(); // Освобождаем ресурсы
  } else {
    Serial.println("Failed to connect to WiFi after multiple attempts.");
  }

  // 2. Отключаемся от Wi-Fi после выполнения запроса (или попытки)
  Serial.print("Disconnecting from WiFi...");
  WiFi.disconnect(true); // true = отключает Wi-Fi полностью, включая память SSID
  Serial.println(" Done.");

  //getBMP();

  Serial.println("--- SLEEP ---");
  esp_sleep_enable_timer_wakeup(60000000*tmin);
  esp_deep_sleep_start();  
}

void BMPinit(void)
{
   unsigned status;
  //status = bmp.begin(BMP280_ADDRESS_ALT, BMP280_CHIPID);
  status = bmp.begin(0x76);
  if (!status) {
    Serial.println(F("Could not find a valid BMP280 sensor, check wiring or "
                      "try a different address!"));
    Serial.print("SensorID was: 0x"); Serial.println(bmp.sensorID(),16);
    Serial.print("        ID of 0xFF probably means a bad address, a BMP 180 or BMP 085\n");
    Serial.print("   ID of 0x56-0x58 represents a BMP 280,\n");
    Serial.print("        ID of 0x60 represents a BME 280.\n");
    Serial.print("        ID of 0x61 represents a BME 680.\n");
    //while (1) delay(10);
    return;
  }

  /* Default settings from datasheet. */
  bmp.setSampling(Adafruit_BMP280::MODE_NORMAL,     /* Operating Mode. */
                  Adafruit_BMP280::SAMPLING_X2,     /* Temp. oversampling */
                  Adafruit_BMP280::SAMPLING_X16,    /* Pressure oversampling */
                  Adafruit_BMP280::FILTER_X16,      /* Filtering. */
                  Adafruit_BMP280::STANDBY_MS_500); /* Standby time. */

  bmp_temp->printSensorDetails();
}

void getBMP(void)
{
  sensors_event_t temp_event, pressure_event;
  bmp_temp->getEvent(&temp_event);
  bmp_pressure->getEvent(&pressure_event);
  
  Serial.print(F("Temperature = "));
  Serial.print(temp_event.temperature);
  Serial.println(" *C");

  Serial.print(F("Pressure = "));
  Serial.print(pressure_event.pressure);
  Serial.println(" hPa");

  Serial.println();
}

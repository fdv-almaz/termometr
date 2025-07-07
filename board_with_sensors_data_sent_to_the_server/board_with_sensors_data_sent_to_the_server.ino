#include <ESP32Time.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <HTTPClient.h>
//#include <NTPClient.h>

//#define NTP_OFFSET  19800 // In seconds
//#define NTP_INTERVAL 60 * 1000    // In miliseconds
//#define NTP_ADDRESS  "europe.pool.ntp.org"
//WiFiUDP ntpUDP;
//NTPClient timeClient(ntpUDP, NTP_ADDRESS, NTP_OFFSET, NTP_INTERVAL);

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
//  timeClient.begin();

  configTime(0, 0, "europe.pool.ntp.org");
  setTimezone("CET-1CEST,M3.5.0,M10.5.0/3");
 // timeClient.update();
  String formattedTime = takeLocalTime();
  if(formattedTime == "failed")
  {
    Serial.println("Set fake time");
    ESP32Time rtc;
    rtc.setTime(1750802400);
  }
  Serial.print("Time: ");
  Serial.println(formattedTime);
}

void loop() {
  String formattedTime;
  // Проверяем, прошло ли достаточно времени с последнего вызова
  if (millis() - lastCallTime >= interval) {
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
                     "&id=" + devId;

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
/*
  Serial.println("--- SLEEP ---");
  esp_sleep_enable_timer_wakeup(60000000*tmin);
  esp_deep_sleep_start();  
*/

}

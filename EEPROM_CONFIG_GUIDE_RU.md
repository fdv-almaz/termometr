# Гайд по конфигурации EEPROM

Этот проект требует конфигурации, хранящейся в памяти EEPROM каждого микроконтроллера ESP32. Вместо жесткого кодирования данных WiFi и URL сервера в скетче, они считываются из EEPROM при запуске.

## 📋 Параметры конфигурации

### Плата датчиков (board_with_sensors_data_sent_to_the_server)

| Адрес | Размер | Параметр | Пример | Описание |
|-------|--------|----------|--------|---------|
| 0 | 32 байта | SSID | `MyWiFi` | Имя WiFi сети |
| 32 | 64 байта | Пароль | `надежный_пароль` | Пароль WiFi |
| 96 | 96 байт | URL сервера | `http://192.168.1.100/meteo/save.php` | Полный URL для отправки данных |
| 192 | 1 байт | ID устройства | `1` | Уникальный идентификатор датчика |
| 196 | 32 байта | API ключ | `ваш_секретный_ключ` | Ключ аутентификации API |

### Плата дисплея (screen_to_get_temperatures_from_server_eInk)

| Адрес | Размер | Параметр | Пример | Описание |
|-------|--------|----------|--------|---------|
| 0 | 32 байта | SSID | `MyWiFi` | Имя WiFi сети |
| 32 | 64 байта | Пароль | `надежный_пароль` | Пароль WiFi |
| 96 | 96 байт | URL сервера | `http://192.168.1.100/meteo/getlast.php` | Полный URL для получения данных |

## 🔧 Способ 1: Конфигурация через Arduino IDE (Рекомендуется)

### Шаг 1: Создать скетч для конфигурации

1. Откройте Arduino IDE
2. Создайте новый скетч
3. Скопируйте код ниже и замените значения на ваши:

```cpp
#include <EEPROM.h>

void setup() {
  Serial.begin(115200);
  delay(2000);  // Ждем инициализации serial монитора
  
  Serial.println("\n\n=== EEPROM Configuration Tool ===\n");
  
  // === КОНФИГУРАЦИЯ ДЛЯ ПЛАТЫ ДАТЧИКОВ ===
  // Раскомментируйте для платы датчиков и измените значения
  
  /*
  const char* ssid = "MyWiFi";
  const char* password = "надежный_пароль";
  const char* server_url = "http://192.168.1.100/meteo/save.php";
  uint8_t device_id = 1;
  const char* api_key = "ваш_секретный_ключ_api";
  
  Serial.println("Configuring Sensor Board...");
  Serial.printf("SSID: %s\n", ssid);
  Serial.printf("Password: %s\n", password);
  Serial.printf("Server URL: %s\n", server_url);
  Serial.printf("Device ID: %d\n", device_id);
  Serial.printf("API Key: %s\n", api_key);
  
  EEPROM.begin(256);
  EEPROM.writeBytes(0, (uint8_t*)ssid, 32);
  EEPROM.writeBytes(32, (uint8_t*)password, 64);
  EEPROM.writeBytes(96, (uint8_t*)server_url, 96);
  EEPROM.write(192, device_id);
  EEPROM.writeBytes(196, (uint8_t*)api_key, 32);
  EEPROM.commit();
  EEPROM.end();
  
  Serial.println("\n✓ Sensor Board EEPROM configured successfully!");
  */
  
  // === КОНФИГУРАЦИЯ ДЛЯ ПЛАТЫ ДИСПЛЕЯ ===
  // Раскомментируйте для платы дисплея и измените значения
  
  /*
  const char* ssid = "MyWiFi";
  const char* password = "надежный_пароль";
  const char* server_url = "http://192.168.1.100/meteo/getlast.php";
  
  Serial.println("Configuring Display Board...");
  Serial.printf("SSID: %s\n", ssid);
  Serial.printf("Password: %s\n", password);
  Serial.printf("Server URL: %s\n", server_url);
  
  EEPROM.begin(256);
  EEPROM.writeBytes(0, (uint8_t*)ssid, 32);
  EEPROM.writeBytes(32, (uint8_t*)password, 64);
  EEPROM.writeBytes(96, (uint8_t*)server_url, 96);
  EEPROM.commit();
  EEPROM.end();
  
  Serial.println("\n✓ Display Board EEPROM configured successfully!");
  */
}

void loop() {
  delay(10000);
}
```

### Шаг 2: Загрузить и выполнить

1. Выберите правильную плату ESP32 (Tools → Board)
2. Выберите COM порт устройства
3. Раскомментируйте нужную секцию (Датчик или Дисплей)
4. Отредактируйте значения параметров
5. Нажмите "Upload" (Загрузить)
6. Откройте Serial Monitor (Tools → Serial Monitor)
7. Установите скорость 115200 baud
8. Убедитесь, что видите сообщение "configured successfully"

### Шаг 3: Загрузить основной скетч

После успешной конфигурации:
1. Загрузите основной скетч (board_with_sensors... или screen_to_get...)
2. Проверьте Serial Monitor для подтверждения конфигурации загружена

## 🔧 Способ 2: Конфигурация через WebUI (в будущем)

Веб-интерфейс для конфигурации EEPROM может быть добавлен для облегчения обновлений без перекомпиляции.

## ⚙️ Переменные окружения для веб-части

Установите эти переменные через файл `.env` или переменные окружения системы:

### Вариант 1: Файл .env (Рекомендуется для development)

Создайте файл `/var/www/html/meteo/.env`:

```bash
DB_HOST=localhost
DB_USER=meteo_user
DB_PASS=надежный_пароль
DB_NAME=meteo
API_KEY=ваш_секретный_ключ_api
ENABLE_AUTH=true
```

Затем измените владельца:
```bash
chmod 600 /var/www/html/meteo/.env
```

### Вариант 2: Переменные Apache

Добавьте в конфиг Apache (например, `/etc/apache2/sites-available/000-default.conf`):

```apache
<Directory /var/www/html/meteo>
    SetEnv DB_HOST localhost
    SetEnv DB_USER meteo_user
    SetEnv DB_PASS надежный_пароль
    SetEnv DB_NAME meteo
    SetEnv API_KEY ваш_секретный_ключ_api
    SetEnv ENABLE_AUTH true
</Directory>
```

Затем перезагрузите Apache:
```bash
sudo systemctl reload apache2
```

### Вариант 3: Переменные PHP-FPM

Добавьте в конфиг PHP-FPM (например, `/etc/php/7.4/fpm/pool.d/www.conf`):

```ini
env[DB_HOST] = localhost
env[DB_USER] = meteo_user
env[DB_PASS] = надежный_пароль
env[DB_NAME] = meteo
env[API_KEY] = ваш_секретный_ключ_api
env[ENABLE_AUTH] = true
```

Затем перезагрузите PHP-FPM:
```bash
sudo systemctl reload php7.4-fpm
```

## 🔐 Проверка конфигурации

### Проверить загрузку EEPROM на ESP32

1. Откройте Serial Monitor при скорости 115200
2. Нажмите кнопку Reset на плате
3. Вы должны увидеть логи со строками типа:
   - "SSID: MyWiFi" (для платы датчиков)
   - "Server URL: http://..." (для обеих плат)

### Проверить подключение к веб-части

```bash
# Тестовый запрос к save.php
curl "http://192.168.1.100/meteo/save.php?api_key=ваш_ключ&dev_id=1&temperatureUL=20&temperatureDOM=22"

# Результат должен быть JSON:
# {"status": "success", "message": "Data saved"}

# Тестовый запрос к getlast.php
curl "http://192.168.1.100/meteo/getlast.php"

# Результат должен быть CSV:
# 1,2024-05-08 14:30:45,21.30,5.20,+0.0,-1.5,1013.2
```

## 🔒 Рекомендации по безопасности

| Элемент | Рекомендация | Почему важно |
|---------|-------------|-------------|
| **API ключ** | Используйте случайные 32+ символов | Предотвращает несанкционированный доступ |
| **Пароль БД** | Минимум 12 символов, спецсимволы | Защита от brute-force атак |
| **Пароль WiFi** | Минимум 12 символов, WPA2/WPA3 | Предотвращает перехват трафика |
| **HTTPS** | Используйте в production | Шифрование данных в транспорте |
| **Права доступа** | 600 для .env файла | Только владелец может читать |
| **Резервные копии** | Регулярно делайте бэкапы | Восстановление при сбое |

## 🆘 Решение проблем

### Проблема: "Configuration not loaded"

**Причина:** EEPROM не был настроен или стёрся

**Решение:**
1. Перезагрузите конфиг-скетч
2. Проверьте Serial Monitor для подтверждения
3. Перезагрузите основной скетч

### Проблема: "Failed to connect to WiFi"

**Проверьте:**
- SSID в EEPROM совпадает с именем вашей сети
- Пароль введен без опечаток
- Если сеть скрыта - добавьте дополнительный параметр в код

### Проблема: "Server connection failed"

**Проверьте:**
- URL сервера верен (включая http:// или https://)
- IP адрес правильный (`ping 192.168.1.100`)
- Сервер работает (`curl http://192.168.1.100/meteo/save.php`)
- API ключ совпадает с переменной окружения на сервере

### Проблема: "401 Forbidden"

**Причина:** API ключ неверный

**Решение:**
1. Проверьте API_KEY в EEPROM (для платы датчиков)
2. Проверьте API_KEY в переменных окружения сервера
3. Убедитесь, что ENABLE_AUTH = true в конфигурации

## 📝 Чек-лист первого запуска

- [ ] Создана база данных MySQL
- [ ] Создан пользователь БД с правильными правами
- [ ] PHP скрипты скопированы на сервер
- [ ] Установлены переменные окружения
- [ ] Первый ESP32 (датчик) настроен через EEPROM
- [ ] Второй ESP32 (дисплей) настроен через EEPROM
- [ ] Загружены основные скетчи на обе платы
- [ ] Serial Monitor показывает успешное подключение
- [ ] Данные сохраняются в БД
- [ ] Дисплей отображает температуры

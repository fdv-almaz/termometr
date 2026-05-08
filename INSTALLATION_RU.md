# Инструкция по установке системы Thermometer

Полная пошаговая инструкция для установки и запуска системы мониторинга температуры.

## 📋 Содержание

1. [Предварительные требования](#предварительные-требования)
2. [Подготовка сервера](#подготовка-сервера)
3. [Установка базы данных](#установка-базы-данных)
4. [Установка веб-приложения](#установка-веб-приложения)
5. [Конфигурация ESP32](#конфигурация-esp32)
6. [Первый запуск](#первый-запуск)
7. [Тестирование системы](#тестирование-системы)

## 🔧 Предварительные требования

### Сервер (Linux)

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install apache2 php php-mysql mysql-server curl git

# Или для nginx + PHP-FPM
sudo apt-get install nginx php-fpm php-mysql mysql-server curl git
```

### Локальная машина

- Arduino IDE (https://www.arduino.cc/en/software)
- USB кабель для ESP32
- Текстовый редактор (VSCode, nano, vim)

### Оборудование

- Два микроконтроллера ESP32
- Датчик температуры DS18B20 (×2)
- Датчик давления BMP280
- E-ink дисплей GxEPD 2.13"
- Резистор pull-up 4.7kΩ для OneWire
- WiFi маршрутизатор (2.4 ГГц)

## 🖥️ Подготовка сервера

### Шаг 1: Проверить IP адрес сервера

```bash
ip addr show
# или
hostname -I
```

Запомните IP адрес (например, 192.168.1.100)

### Шаг 2: Создать директорию для проекта

```bash
sudo mkdir -p /var/www/html/meteo
cd /var/www/html/meteo
sudo chown -R $USER:$USER .
```

### Шаг 3: Скопировать файлы проекта

```bash
# Клонировать или скопировать файлы
git clone https://github.com/fdv-almaz/termometr.git .
# или
cp -r ~/termometr/* /var/www/html/meteo/
```

### Шаг 4: Создать директорию логов

```bash
mkdir -p /var/www/html/meteo/logs
chmod 755 /var/www/html/meteo/logs
sudo chown www-data:www-data /var/www/html/meteo/logs
```

### Шаг 5: Проверить PHP

```bash
php -v
# Должна быть версия 7.4+
```

## 🗄️ Установка базы данных

### Шаг 1: Войти в MySQL

```bash
mysql -u root -p
# Введите пароль root
```

### Шаг 2: Создать базу данных

```sql
CREATE DATABASE meteo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### Шаг 3: Создать пользователя

```sql
CREATE USER 'meteo_user'@'localhost' IDENTIFIED BY 'ваш_надежный_пароль';
GRANT ALL PRIVILEGES ON meteo.* TO 'meteo_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Шаг 4: Импортировать схему

```bash
cd /var/www/html/meteo
mysql -u meteo_user -p meteo < mysql/meteoDB_create.sql
```

Введите пароль пользователя `meteo_user`.

### Шаг 5: Проверить таблицы

```bash
mysql -u meteo_user -p meteo -e "SHOW TABLES;"
```

Должны быть таблицы `config` и `data`.

## 🌐 Установка веб-приложения

### Шаг 1: Создать конфиг-файл

```bash
cp /var/www/html/meteo/meteo/example.config.php /var/www/html/meteo/meteo/db.php
```

### Шаг 2: Отредактировать конфиг

```bash
nano /var/www/html/meteo/meteo/db.php
```

Измените значения на ваши:

```php
$config = array(
  'db_host' => 'localhost',
  'db_user' => 'meteo_user',
  'db_pass' => 'ваш_надежный_пароль',  // ← Измените это
  'db_name' => 'meteo',
);

$api_config = array(
  'api_key' => 'ваш_секретный_ключ_api_32_символа',  // ← И это
  'enable_auth' => true,
  // ...
);
```

Сохраните файл (Ctrl+X → Y → Enter)

### Шаг 3: Установить права доступа

```bash
sudo chown -R www-data:www-data /var/www/html/meteo
chmod -R 755 /var/www/html/meteo
chmod 600 /var/www/html/meteo/meteo/db.php
```

### Шаг 4: Проверить Apache конфиг

```bash
# Для Apache2
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### Шаг 5: Проверить работу веб-приложения

```bash
# Получить последние данные (будет ошибка - данных нет)
curl "http://localhost/meteo/getlast.php"

# Отправить тестовые данные
curl "http://localhost/meteo/save.php?api_key=ваш_ключ&dev_id=1&temperatureUL=20.5&temperatureDOM=21.3&press=1013.25"
```

## 💾 Конфигурация ESP32

### Шаг 1: Установить Arduino IDE

1. Скачайте с https://www.arduino.cc/en/software
2. Установите по инструкции для вашей ОС

### Шаг 2: Добавить поддержку ESP32

1. Откройте Arduino IDE
2. File → Preferences
3. В поле "Additional Boards Manager URLs" добавьте:
   ```
   https://dl.espressif.com/dl/package_esp32_index.json
   ```
4. OK
5. Tools → Board → Boards Manager
6. Найдите "esp32" и установите последнюю версию

### Шаг 3: Установить библиотеки

1. Tools → Manage Libraries
2. Установите эти библиотеки:
   - ESP32Time
   - OneWire
   - DallasTemperature
   - Adafruit_BMP280
   - GxEPD (и зависимости)
   - Adafruit_BMP280

### Шаг 4: Загрузить EEPROM конфиг-скетч

1. Создайте новый скетч
2. Скопируйте код из `EEPROM_CONFIG_GUIDE_RU.md`
3. Измените значения на ваши:
   - SSID (имя WiFi)
   - Пароль WiFi
   - URL сервера (http://192.168.1.100/meteo/save.php)
   - API ключ
4. Выберите плату ESP32
5. Выберите COM порт
6. Sketch → Upload
7. Tools → Serial Monitor (скорость 115200)
8. Проверьте сообщение "configured successfully"

### Шаг 5: Загрузить основные скетчи

**Для платы датчиков:**
1. Откройте `board_with_sensors_data_sent_to_the_server/board_with_sensors_data_sent_to_the_server.ino`
2. Выберите правильный COM порт
3. Sketch → Upload
4. Serial Monitor должен показать логи подключения

**Для платы дисплея:**
1. Откройте `screen_to_get_temperatures_from_server_eInk/screen_to_get_temperatures_from_server_eInk.ino`
2. Выберите правильный COM порт (второй ESP32)
3. Sketch → Upload
4. Serial Monitor должен показать логи подключения

## 🚀 Первый запуск

### На плате датчиков

1. Подключите датчики (DS18B20, BMP280)
2. Подключите питание
3. Наблюдайте в Serial Monitor:
   - "Connecting to WiFi: MyWiFi"
   - Должно появиться "WiFi connected!"
   - Затем "Data sent successfully"

### На плате дисплея

1. Подключите питание
2. Наблюдайте в Serial Monitor:
   - "Connecting to WiFi: MyWiFi"
   - "WiFi connected!"
   - На дисплее должны появиться значения температур

### На веб-сервере

1. Проверьте, что данные попали в БД:

```bash
mysql -u meteo_user -p meteo -e "SELECT * FROM data ORDER BY id DESC LIMIT 5;"
```

Должны быть строки с вашими данными.

## ✅ Тестирование системы

### Тест 1: Проверить сохранение данных

```bash
# Отправить данные с датчика
curl "http://192.168.1.100/meteo/save.php?api_key=ваш_ключ&dev_id=1&temperatureUL=15.5&temperatureDOM=22.3&press=1012.0"

# Получить последние данные
curl "http://192.168.1.100/meteo/getlast.php"
```

### Тест 2: Проверить корректность коррекций

```bash
# Посмотреть коррекции в БД
mysql -u meteo_user -p meteo -e "SELECT * FROM config;"

# Должно быть:
# ULcorr, -1.5
# DOMcorr, +0
# PRESScorr, -3
```

### Тест 3: Проверить дисплей

1. Принудительно разбудите плату дисплея
2. Она должна подключиться к WiFi
3. Получить данные с сервера
4. Отобразить на дисплее
5. Перейти в режим сна

### Тест 4: Проверить логирование ошибок

```bash
# Посмотреть логи ошибок
tail -f /var/www/html/meteo/logs/error.log

# Должны быть логи об успешном сохранении
```

## 🔐 Безопасность

### Обязательные действия

```bash
# 1. Измените пароль MySQL
mysql -u root -p

# 2. Добавьте firewall правила
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# 3. Установите HTTPS (Let's Encrypt)
sudo apt-get install certbot python3-certbot-apache
sudo certbot certonly --apache -d ваш.домен.ру

# 4. Настройте резервные копии
sudo crontab -e
# Добавьте строку:
# 0 3 * * * mysqldump -u meteo_user -p'пароль' meteo | gzip > /backups/meteo_$(date +\%Y\%m\%d).sql.gz
```

## 🐛 Решение проблем при установке

### Apache не видит PHP

```bash
sudo a2enmod php7.4
sudo systemctl reload apache2
```

### Ошибка "Permission denied" для логов

```bash
sudo chown -R www-data:www-data /var/www/html/meteo/logs
sudo chmod 755 /var/www/html/meteo/logs
```

### Ошибка подключения к БД

```bash
# Проверить, запущена ли MySQL
sudo systemctl status mysql

# Проверить логин
mysql -u meteo_user -p meteo -e "SELECT 1;"
```

### ESP32 не загружается

1. Проверьте USB кабель
2. Выберите правильную плату (Tools → Board → ESP32 Dev Module)
3. Выберите правильный COM порт (Tools → Port)
4. Проверьте драйверы CH340 для USB

## 📞 Дополнительная помощь

- Посмотрите `TROUBLESHOOTING_RU.md` для решения проблем в работе
- Посмотрите `EEPROM_CONFIG_GUIDE_RU.md` для деталей конфигурации
- Проверьте логи: `/var/www/html/meteo/logs/error.log`
- Используйте Serial Monitor в Arduino IDE для отладки ESP32

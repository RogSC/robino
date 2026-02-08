# Инструкция по проверке доставки запросов Telegram вебхука

Этот документ поможет проверить, доходят ли запросы от Telegram бота до вашего приложения Laravel.

## Что было настроено

1. **Отдельный лог-канал для Telegram** - [`storage/logs/telegram.log`](storage/logs/telegram.log)
2. **Middleware для логирования** - [`app/Http/Middleware/LogTelegramRequests.php`](app/Http/Middleware/LogTelegramRequests.php)
3. **Улучшенное логирование в контроллере** - [`app/Http/Controllers/TelegramWebhookController.php`](app/Http/Controllers/TelegramWebhookController.php)
4. **CSRF исключение** для маршрута `/telegram/*` в [`bootstrap/app.php`](bootstrap/app.php)
5. **Тестовая команда** - `telegram:webhook:test`

## Пошаговая проверка

### Шаг 1: Проверка локальной работы вебхука

Запустите тестовую команду для проверки, что вебхук работает локально:

```bash
cd luna

# Запустите локальный сервер (если еще не запущен)
php artisan serve

# В другом терминале запустите тест
php artisan telegram:webhook:test
```

**Ожидаемый результат:**
```
Testing webhook at: http://127.0.0.1:8000/telegram/webhook
...
✓ Webhook test successful!

Check the logs at:
  storage/logs/telegram.log
  storage/logs/laravel.log
```

### Шаг 2: Проверка логов

Откройте лог-файл Telegram в реальном времени:

```bash
# Windows (PowerShell)
Get-Content storage\logs\telegram.log -Wait -Tail 50

# Windows (CMD)
powershell -command "Get-Content storage\logs\telegram.log -Wait -Tail 50"

# Или просто откройте файл в текстовом редакторе
```

**Что искать в логах:**

1. **Входящий запрос:**
```
[timestamp] local.INFO: Incoming Telegram webhook request
```

2. **Начало обработки:**
```
[timestamp] local.INFO: === START: Processing Telegram webhook ===
```

3. **Данные сообщения:**
```
[timestamp] local.INFO: Processing message {"chat_id":123456789,"user_id":123456789,"text":"/start"}
```

4. **Успешное завершение:**
```
[timestamp] local.INFO: === END: Successfully processed webhook ===
```

### Шаг 3: Регистрация вебхука в Telegram

Убедитесь, что ваш сервер доступен через интернет (HTTPS обязателен для продакшн):

```bash
# Установите вебхук
php artisan telegram:webhook:set

# Проверьте статус
php artisan telegram:webhook:info
```

### Шаг 4: Проверка реальных запросов от Telegram

1. **Откройте бота в Telegram** и отправьте команду `/start`

2. **Сразу же проверьте логи:**
   ```bash
   # Последние 50 строк лога
   tail -50 storage/logs/telegram.log
   
   # Или в Windows
   powershell -command "Get-Content storage\logs\telegram.log -Tail 50"
   ```

3. **Проверьте информацию о вебхуке:**
   ```bash
   php artisan telegram:webhook:info
   ```
   
   Обратите внимание на:
   - `Pending update count` - должно быть 0 или малое число
   - `Last error date` - должно быть "None"
   - `Last error message` - должно быть "None"

## Типичные проблемы и решения

### Проблема 1: Запросы не доходят до приложения

**Симптомы:**
- В логах `telegram.log` пусто после отправки команды боту
- `php artisan telegram:webhook:info` показывает растущий `pending_update_count`

**Решения:**

1. **Проверьте HTTPS:**
   ```bash
   curl -I https://yourdomain.com/telegram/webhook
   ```
   Должен быть валидный SSL сертификат

2. **Проверьте доступность сервера:**
   ```bash
   curl -X POST https://yourdomain.com/telegram/webhook \
     -H "Content-Type: application/json" \
     -d '{"message":{"text":"test"}}'
   ```

3. **Проверьте firewall/security groups:**
   - Убедитесь, что порт 443 (HTTPS) открыт
   - Telegram использует определенные IP диапазоны, проверьте их в документации

4. **Проверьте nginx/apache конфигурацию:**
   - Убедитесь, что запросы правильно проксируются на Laravel

### Проблема 2: Запросы доходят, но возвращается ошибка

**Симптомы:**
- В логах есть запись "Incoming Telegram webhook request"
- Но далее идет ошибка или прерывание

**Решения:**

1. **Проверьте полный лог Laravel:**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

2. **Проверьте подключение к базе данных:**
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. **Проверьте, что все сервисы зарегистрированы:**
   ```bash
   php artisan about
   ```

### Проблема 3: 419 CSRF Token Mismatch

**Симптомы:**
- Ошибка 419 в ответе
- В логах nginx/apache видны POST запросы, но они отклоняются

**Решение:**
Убедитесь, что маршрут исключен из CSRF защиты в [`bootstrap/app.php`](bootstrap/app.php):
```php
$middleware->validateCsrfTokens(except: [
    'telegram/*',
]);
```

### Проблема 4: 500 Internal Server Error

**Симптомы:**
- Вебхук возвращает 500 ошибку
- В telegram.log есть записи об ошибках

**Решения:**

1. **Проверьте детали ошибки в логах:**
   ```bash
   grep "ERROR" storage/logs/telegram.log
   grep "ERROR" storage/logs/laravel.log
   ```

2. **Проверьте права доступа к логам:**
   ```bash
   chmod -R 775 storage/logs
   ```

3. **Очистите кеш:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

## Дополнительные инструменты для отладки

### 1. Просмотр маршрутов

```bash
php artisan route:list | grep telegram
```

### 2. Проверка конфигурации

```bash
php artisan tinker
>>> config('telegram.bot_token')
>>> config('telegram.webhook_url')
```

### 3. Мониторинг логов в реальном времени

```bash
# Linux/Mac
tail -f storage/logs/telegram.log

# Windows PowerShell
Get-Content storage\logs\telegram.log -Wait -Tail 50
```

### 4. Симуляция запроса от Telegram

Используйте тестовую команду с кастомными параметрами:

```bash
# Тест команды /start
php artisan telegram:webhook:test --text="/start"

# Тест команды /help
php artisan telegram:webhook:test --text="/help"

# Тест добавления еды
php artisan telegram:webhook:test --text="Chicken 150g"
```

### 5. Curl-тест вебхука

```bash
curl -X POST http://127.0.0.1:8000/telegram/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 123456789,
    "message": {
      "message_id": 1,
      "from": {
        "id": 123456789,
        "is_bot": false,
        "first_name": "Test",
        "username": "testuser"
      },
      "chat": {
        "id": 123456789,
        "first_name": "Test",
        "type": "private"
      },
      "date": 1234567890,
      "text": "/start"
    }
  }'
```

## Структура лога при успешном запросе

При успешной обработке запроса вы должны увидеть примерно следующее в `storage/logs/telegram.log`:

```
[2026-02-08 17:45:00] local.INFO: Incoming Telegram webhook request
[2026-02-08 17:45:00] local.INFO: === START: Processing Telegram webhook ===
[2026-02-08 17:45:00] local.DEBUG: Received update data
[2026-02-08 17:45:00] local.INFO: Processing message {"chat_id":123456789,"user_id":123456789,"text":"/start"}
[2026-02-08 17:45:00] local.DEBUG: Registering/updating user
[2026-02-08 17:45:00] local.INFO: User registered/updated
[2026-02-08 17:45:00] local.DEBUG: Checking access control
[2026-02-08 17:45:00] local.INFO: Processing command {"text":"/start"}
[2026-02-08 17:45:00] local.INFO: === END: Successfully processed webhook ===
[2026-02-08 17:45:00] local.INFO: Telegram webhook response {"status":200}
```

## Полезные ссылки

- [Telegram Bot API - Webhooks](https://core.telegram.org/bots/api#setwebhook)
- [Laravel Logging Documentation](https://laravel.com/docs/logging)
- [Debugging Telegram Bots](https://core.telegram.org/bots/faq#my-bot-is-not-working)

## Контрольный чеклист

Перед обращением в поддержку убедитесь, что вы проверили:

- [ ] `php artisan telegram:webhook:test` работает успешно
- [ ] `php artisan telegram:webhook:info` показывает правильный URL
- [ ] `pending_update_count` не растет
- [ ] `last_error_message` = "None"
- [ ] В `storage/logs/telegram.log` появляются записи
- [ ] HTTPS настроен правильно (для продакшн)
- [ ] Маршрут `/telegram/webhook` исключен из CSRF
- [ ] База данных подключена и работает
- [ ] Все миграции выполнены: `php artisan migrate:status`

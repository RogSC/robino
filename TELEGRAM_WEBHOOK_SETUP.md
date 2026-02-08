# Инструкция по регистрации вебхука Telegram бота

## Предварительные настройки

Убедитесь, что в файле `.env` настроены следующие переменные:

```env
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=optional_secret_token
```

## Доступные команды

### 1. Установка вебхука

Установить вебхук с URL из конфигурации:
```bash
cd example
php artisan telegram:webhook:set
```

Установить вебхук с пользовательским URL:
```bash
cd example
php artisan telegram:webhook:set --url=https://yourdomain.com/telegram/webhook
```

### 2. Проверка информации о вебхуке

Получить информацию о текущем вебхуке:
```bash
cd example
php artisan telegram:webhook:info
```

Эта команда покажет:
- Текущий URL вебхука
- Количество ожидающих обновлений
- Информацию о последних ошибках (если есть)
- IP адрес, с которого Telegram отправляет запросы
- Максимальное количество соединений

### 3. Удаление вебхука

Удалить вебхук (сохранив ожидающие обновления):
```bash
cd example
php artisan telegram:webhook:delete
```

Удалить вебхук и очистить все ожидающие обновления:
```bash
cd example
php artisan telegram:webhook:delete --drop-pending
```

## Пошаговая инструкция по первоначальной настройке

1. **Получите токен бота** от [@BotFather](https://t.me/BotFather) в Telegram

2. **Настройте переменные окружения** в файле `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
   TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
   ```

3. **Убедитесь, что ваш сервер доступен** через HTTPS (Telegram требует HTTPS для вебхуков)

4. **Установите вебхук**:
   ```bash
   cd example
   php artisan telegram:webhook:set
   ```

5. **Проверьте установку**:
   ```bash
   cd example
   php artisan telegram:webhook:info
   ```

6. **Протестируйте бота**, отправив команду `/start` в вашего бота в Telegram

## Требования

- HTTPS соединение (обязательно для продакшн)
- Публично доступный URL
- Порты: 443, 80, 88 или 8443
- SSL сертификат (можно использовать Let's Encrypt)

## Отладка

Если вебхук не работает, проверьте:

1. **Информацию о вебхуке**:
   ```bash
   php artisan telegram:webhook:info
   ```
   Проверьте наличие ошибок в выводе

2. **Логи Laravel**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Права доступа** к маршруту `/telegram/webhook` в файле `routes/web.php`

4. **Исключение CSRF** для вебхука в `app/Http/Middleware/VerifyCsrfToken.php`

## Дополнительные возможности

### Переключение между вебхуком и long polling

Для разработки можно использовать long polling вместо вебхука:
```bash
php artisan telegram:webhook:delete
```

Затем можно использовать метод `getUpdates` для получения обновлений в ручном режиме.

### Очистка ожидающих обновлений

Если накопилось много старых обновлений:
```bash
php artisan telegram:webhook:set
```
(команда автоматически очищает ожидающие обновления при установке)

Или явно:
```bash
php artisan telegram:webhook:delete --drop-pending
php artisan telegram:webhook:set
```

## Безопасность

Для повышения безопасности рекомендуется:

1. Использовать `TELEGRAM_WEBHOOK_SECRET` для проверки подлинности запросов
2. Проверять IP адрес отправителя (Telegram использует определенные диапазоны IP)
3. Использовать rate limiting для предотвращения злоупотреблений

## Полезные ссылки

- [Telegram Bot API - Webhooks](https://core.telegram.org/bots/api#setwebhook)
- [Telegram Bot API - getWebhookInfo](https://core.telegram.org/bots/api#getwebhookinfo)
- [Telegram Bot API - deleteWebhook](https://core.telegram.org/bots/api#deletewebhook)

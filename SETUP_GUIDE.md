# Luna Period Tracker - Quick Setup Guide

## 🚀 Quick Start

### 1. Configure Environment Variables

Copy `.env.example` to `.env` and update these values:

```bash
# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook

# Roocode AI Integration
ROOCODE_API_URL=http://localhost:3000/api/ai
ROOCODE_API_KEY=your_api_key_here
```

### 2. Run Migrations

```bash
php artisan migrate
```

This creates:
- `periods` table
- `partners` table
- Updates `telegram_users` table with new fields

### 3. Set Up Telegram Webhook

```bash
php artisan telegram:set-webhook
```

Verify webhook is set:
```bash
php artisan telegram:webhook-info
```

### 4. Test the Bot

Send `/start` to your bot in Telegram. You should receive a welcome message.

## 📋 Available Commands

### Period Tracking
- `/start_period` - Start tracking new period
- `/end_period` - End current period
- `/status` - View cycle status and predictions

### Partner Features
- `/invite_partner` - Generate invitation code
- `/connect_partner CODE` - Connect to partner
- `/partner_status` - View partner's cycle
- `/remove_partner` - Remove connection

### General
- `/help` - Show all commands
- Ask questions directly for AI assistance

## 🔧 Roocode Integration

Your roocode endpoint should accept POST requests with this format:

**Request:**
```json
{
  "query": "User's question",
  "context": {
    "user_name": "Alice",
    "gender": "female",
    "period_tracking": {
      "total_periods_tracked": 6,
      "average_cycle_length": 28,
      "is_in_period": false,
      // ... more context
    }
  },
  "user_id": 123456789
}
```

**Response:**
```json
{
  "response": "AI's answer"
}
```

## 🐛 Troubleshooting

### Webhook not receiving updates
```bash
# Check webhook info
php artisan telegram:webhook-info

# Delete and reset webhook
php artisan telegram:delete-webhook
php artisan telegram:set-webhook
```

### Database errors
```bash
# Fresh migration
php artisan migrate:fresh

# Or rollback and migrate
php artisan migrate:rollback
php artisan migrate
```

### Check logs
```bash
tail -f storage/logs/laravel.log
```

## 📚 Documentation

- [Full Documentation (English)](README_PERIOD_TRACKER.md)
- [Полная Документация (Русский)](README_PERIOD_TRACKER_RU.md)

## 🎯 Next Steps

1. Customize AI responses in [`TelegramBotService::buildUserContext()`](app/Services/TelegramBotService.php)
2. Add symptom tracking UI
3. Implement push notifications for period reminders
4. Add export functionality for cycle data
5. Create admin panel for monitoring

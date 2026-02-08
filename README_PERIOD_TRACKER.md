# Luna Period Tracker Bot

Luna is a Telegram bot for tracking menstrual cycles with partner sharing capabilities and AI-powered insights through roocode integration.

## Features

### 🩸 Period Tracking
- Track menstrual cycle start and end dates
- Automatic cycle length calculation
- Period duration tracking
- Flow intensity monitoring (1-5 scale)
- Symptom logging

### 📊 Predictions & Insights
- Predict next period start date
- Calculate fertility windows
- Track ovulation dates
- Cycle regularity analysis
- Historical statistics

### 👫 Partner Sharing
- Generate unique invitation codes
- Share cycle information with partners
- Partner can view period predictions
- Privacy-focused connection system

### 🤖 AI Integration
- Forward questions to roocode AI
- Context-aware responses based on cycle data
- Natural language interaction
- Personalized insights

## Database Structure

### Tables

#### `periods`
- Tracks individual period records
- Fields: start_date, end_date, duration, flow_intensity, symptoms, notes

#### `partners`
- Manages partner connections
- Fields: user_id, partner_id, invitation_code, status, accepted_at

#### `telegram_users` (updated)
- Added fields: partner_code, average_cycle_length, average_period_length, gender

## Bot Commands

### Period Tracking Commands
- `/start_period [date]` - Start tracking a new period (date optional, defaults to today)
- `/end_period [date]` - End current period (date optional, defaults to today)
- `/status` or `/period_status` - View current cycle status and predictions

### Partner Commands
- `/invite_partner` - Generate invitation code and link for partner
- `/connect_partner CODE` - Connect to a partner using their code
- `/partner_status` - View partner's cycle information
- `/remove_partner` - Remove partner connection

### General Commands
- `/start` - Welcome message and bot introduction
- `/help` - Display all available commands

### AI Queries
Simply type your question in natural language, and the bot will forward it to the AI:
- "When is my next period?"
- "Am I in my fertile window?"
- "What symptoms are normal during my period?"

## Setup Instructions

### 1. Environment Configuration

Add to your `.env` file:

```env
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook

# Roocode AI Integration
ROOCODE_API_URL=http://localhost:3000/api/ai
ROOCODE_API_KEY=your_roocode_api_key
```

### 2. Run Migrations

```bash
php artisan migrate
```

This will create the following tables:
- `periods`
- `partners`
- Update `telegram_users` table

### 3. Set Up Telegram Webhook

```bash
php artisan telegram:set-webhook
```

### 4. Configure Roocode Integration

The bot sends user queries to the roocode API endpoint with context including:
- Current cycle information
- Period tracking history
- Fertility window status
- Partner connection status

#### Roocode API Expected Format

**Request:**
```json
{
  "query": "When is my next period?",
  "context": {
    "user_name": "Alice",
    "gender": "female",
    "period_tracking": {
      "total_periods_tracked": 6,
      "average_cycle_length": 28,
      "average_period_length": 5,
      "cycle_regularity": "regular",
      "last_period_date": "2026-01-15",
      "predicted_next_period": "2026-02-12",
      "current_cycle_day": 25,
      "is_in_period": false,
      "fertility_window": {
        "is_fertile_now": false,
        "ovulation_date": "2026-01-29"
      }
    },
    "has_partner": true
  },
  "user_id": 123456789
}
```

**Response:**
```json
{
  "response": "Based on your tracking history, your next period is predicted to start on February 12, 2026. That's in about 3 days!"
}
```

## Models

### Period Model
- [`Period`](luna/app/Models/Period.php) - Period tracking record
- Methods: `isActive()`, `getCycleLengthAttribute()`

### Partner Model
- [`Partner`](luna/app/Models/Partner.php) - Partner connection
- Methods: `accept()`, `reject()`, `isActive()`, `isExpired()`
- Auto-generates unique invitation codes

### TelegramUser Model (Updated)
- New relationships: `periods()`, `partnershipsAsUser()`, `partnershipsAsPartner()`
- New methods: `getCurrentPeriod()`, `getLastPeriod()`, `hasActivePeriod()`, `generatePartnerCode()`

## Services

### PeriodService
Main service for period tracking logic:
- `startPeriod()` - Start new period
- `endPeriod()` - End active period
- `predictNextPeriod()` - Predict next period start
- `calculateFertilityWindow()` - Calculate fertile days
- `getCurrentCycleDay()` - Get current day in cycle
- `getDaysUntilNextPeriod()` - Days until next period
- `getPeriodStatistics()` - Get cycle statistics
- `getPeriodInsights()` - Get formatted insights text

### TelegramBotService (Updated)
Enhanced with period tracking methods:
- `startPeriod()` - Handle period start command
- `endPeriod()` - Handle period end command
- `getPeriodStatus()` - Get formatted status message
- `createPartnerInvitation()` - Generate partner invitation
- `connectPartner()` - Connect to partner via code
- `getPartnerStatus()` - Get partner's cycle status
- `removePartner()` - Remove partner connection
- `queryAI()` - Forward query to roocode AI
- `buildUserContext()` - Build context for AI queries

## Partner Invitation Flow

1. **User A generates invitation:**
   ```
   /invite_partner
   ```
   Bot responds with:
   - Unique code (e.g., `ABC123`)
   - Deep link: `https://t.me/your_bot?start=partner_ABC123`

2. **User B connects:**
   - Click the link OR
   - Use command: `/connect_partner ABC123`

3. **Connection established:**
   - Both users receive confirmation
   - User B can now view User A's cycle info with `/partner_status`

## AI Integration Flow

1. User sends a question (detected by question words or `?`)
2. Bot builds context from user's period tracking data
3. Request sent to roocode API with query + context
4. AI response returned to user via Telegram

## Privacy & Security

- Partner codes are unique and time-limited (7 days expiration)
- Only accepted partners can view cycle information
- Users can remove partner connections at any time
- All data stored securely in database
- Partner code must be explicitly shared

## Usage Examples

### Tracking a Period
```
User: /start_period
Bot: 🩸 Period started on Feb 8, 2026.
     I'll track this for you and predict your next cycle!

[5 days later]
User: /end_period
Bot: ✅ Period ended on Feb 13, 2026.
     Duration: 5 days
     
     📊 Current cycle day: 1
     📈 Your cycle is regular.
     ⏱️ Average cycle: 28 days, Average period: 5 days.
```

### Checking Status
```
User: /status
Bot: 🌸 Period Tracker
     
     📆 Your next period is expected in 12 days.
     📊 Current cycle day: 16
     🌸 Your fertile window starts in 2 day(s).
     📈 Your cycle is regular.
     ⏱️ Average cycle: 28 days, Average period: 5 days.
     
     Fertility Window
     Ovulation: Feb 22
     Fertile: Feb 17 - Feb 23
```

### Inviting Partner
```
User: /invite_partner
Bot: 👫 Partner Invitation
     
     Share this with your partner to connect:
     
     🔗 Link: https://t.me/luna_bot?start=partner_ABC123
     
     📋 Or share this code: ABC123
     
     Your partner can use /connect_partner ABC123 to connect.
```

### AI Query
```
User: When should I expect PMS symptoms?
Bot: Based on your cycle pattern, PMS symptoms typically occur 5-7 days before 
     your period. Your next period is predicted for February 26, so you might 
     start experiencing symptoms around February 19-21.
```

## Troubleshooting

### Period not starting
- Ensure previous period was ended with `/end_period`
- Check bot has proper database permissions

### Partner connection failed
- Verify the partner code is correct (case-sensitive)
- Check invitation hasn't expired (7 days)
- Ensure partner isn't connecting to themselves

### AI queries not working
- Verify `ROOCODE_API_URL` and `ROOCODE_API_KEY` are set
- Check roocode service is running
- Review logs for API errors

## Development

To extend functionality:

1. **Add new symptoms tracking:** Update `Period` model's `symptoms` field handling
2. **Custom notifications:** Implement reminder system for upcoming periods
3. **Export data:** Add command to export cycle history
4. **Charts:** Generate visual cycle charts

## License

This project is part of the Luna health tracking application.

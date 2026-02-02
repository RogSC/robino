<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Telegram bot settings including the bot token,
    | webhook URL, and other related configurations.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Optional secret to verify incoming webhook requests.
    |
    */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for the bot behavior.
    |
    */
    'default_timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different bot actions.
    |
    */
    'rate_limits' => [
        'messages_per_minute' => 20,
        'commands_per_minute' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for subscription-related features.
    |
    */
    'subscription' => [
        'default_grace_period_days' => 7,
        'free_plan_daily_meal_limit' => 5,
    ],
];
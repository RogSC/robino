<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook:set {--url= : Custom webhook URL (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Telegram bot webhook URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botToken = config('telegram.bot_token');
        $webhookUrl = $this->option('url') ?? config('telegram.webhook_url');

        if (empty($botToken)) {
            $this->error('Telegram bot token is not configured. Please set TELEGRAM_BOT_TOKEN in your .env file.');
            return 1;
        }

        if (empty($webhookUrl)) {
            $this->error('Webhook URL is not configured. Please set TELEGRAM_WEBHOOK_URL in your .env file or use --url option.');
            return 1;
        }

        $this->info("Setting webhook for bot...");
        $this->info("Webhook URL: {$webhookUrl}");

        try {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
            
            $params = [
                'url' => $webhookUrl,
                'drop_pending_updates' => true, // Очистить все ожидающие обновления
            ];

            // Добавляем секретный токен если он настроен
            if ($secret = config('telegram.webhook_secret')) {
                $params['secret_token'] = $secret;
            }

            $response = Http::post($apiUrl, $params);
            
            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $this->info('✓ Webhook successfully set!');
                $this->line('');
                $this->line('Description: ' . ($result['description'] ?? 'N/A'));
                return 0;
            } else {
                $this->error('✗ Failed to set webhook.');
                $this->line('Error: ' . ($result['description'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred: ' . $e->getMessage());
            return 1;
        }
    }
}

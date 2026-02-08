<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetTelegramWebhookInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get current Telegram bot webhook information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botToken = config('telegram.bot_token');

        if (empty($botToken)) {
            $this->error('Telegram bot token is not configured. Please set TELEGRAM_BOT_TOKEN in your .env file.');
            return 1;
        }

        $this->info("Getting webhook information...");

        try {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
            
            $response = Http::get($apiUrl);
            
            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $info = $result['result'] ?? [];
                
                $this->info('✓ Webhook information retrieved successfully!');
                $this->line('');
                
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['URL', $info['url'] ?? 'Not set'],
                        ['Has custom certificate', ($info['has_custom_certificate'] ?? false) ? 'Yes' : 'No'],
                        ['Pending update count', $info['pending_update_count'] ?? 0],
                        ['Last error date', isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None'],
                        ['Last error message', $info['last_error_message'] ?? 'None'],
                        ['Max connections', $info['max_connections'] ?? 40],
                        ['IP address', $info['ip_address'] ?? 'N/A'],
                    ]
                );
                
                if (isset($info['allowed_updates']) && !empty($info['allowed_updates'])) {
                    $this->line('');
                    $this->info('Allowed updates: ' . implode(', ', $info['allowed_updates']));
                }
                
                return 0;
            } else {
                $this->error('✗ Failed to get webhook information.');
                $this->line('Error: ' . ($result['description'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred: ' . $e->getMessage());
            return 1;
        }
    }
}

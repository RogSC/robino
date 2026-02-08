<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeleteTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook:delete {--drop-pending : Drop all pending updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Telegram bot webhook';

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

        $this->info("Deleting webhook...");

        try {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
            
            $params = [];
            
            if ($this->option('drop-pending')) {
                $params['drop_pending_updates'] = true;
                $this->warn('All pending updates will be dropped.');
            }
            
            $response = Http::post($apiUrl, $params);
            
            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $this->info('✓ Webhook successfully deleted!');
                $this->line('');
                $this->line('Description: ' . ($result['description'] ?? 'N/A'));
                
                if ($this->option('drop-pending')) {
                    $this->info('Pending updates have been dropped.');
                }
                
                return 0;
            } else {
                $this->error('✗ Failed to delete webhook.');
                $this->line('Error: ' . ($result['description'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred: ' . $e->getMessage());
            return 1;
        }
    }
}

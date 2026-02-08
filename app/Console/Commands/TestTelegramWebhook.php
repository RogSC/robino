<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook:test {--user-id=123456789 : Test user ID} {--text=/start : Test message text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Telegram webhook endpoint with a sample request';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $text = $this->option('text');
        
        $webhookUrl = config('app.url') . '/telegram/webhook';
        
        $this->info("Testing webhook at: {$webhookUrl}");
        $this->line('');

        // Создаем тестовый запрос как от Telegram
        $testPayload = [
            'update_id' => rand(100000000, 999999999),
            'message' => [
                'message_id' => rand(1000, 9999),
                'from' => [
                    'id' => (int)$userId,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'testuser',
                    'language_code' => 'en',
                ],
                'chat' => [
                    'id' => (int)$userId,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'testuser',
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
            ],
        ];

        $this->info('Sending test payload:');
        $this->line(json_encode($testPayload, JSON_PRETTY_PRINT));
        $this->line('');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'TelegramBot (like TwitterBot)',
                ])
                ->post($webhookUrl, $testPayload);

            $this->info('Response Status: ' . $response->status());
            $this->line('Response Body: ' . $response->body());
            $this->line('');

            if ($response->successful()) {
                $this->info('✓ Webhook test successful!');
                $this->line('');
                $this->info('Check the logs at:');
                $this->line('  storage/logs/telegram.log');
                $this->line('  storage/logs/laravel.log');
                return 0;
            } else {
                $this->error('✗ Webhook test failed!');
                $this->line('Response: ' . $response->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred: ' . $e->getMessage());
            $this->line('');
            $this->warn('Common issues:');
            $this->line('  1. Make sure your application is running (php artisan serve)');
            $this->line('  2. Check APP_URL in .env file');
            $this->line('  3. Ensure /telegram/webhook route is registered');
            $this->line('  4. Check CSRF exemption for webhook route');
            return 1;
        }
    }
}

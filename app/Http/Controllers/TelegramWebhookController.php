<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Services\TelegramBotService;
use App\Services\SubscriptionService;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected TelegramBotService $telegramBotService;
    protected SubscriptionService $subscriptionService;
    protected AccessControlService $accessControlService;

    public function __construct(
        TelegramBotService $telegramBotService,
        SubscriptionService $subscriptionService,
        AccessControlService $accessControlService
    ) {
        $this->telegramBotService = $telegramBotService;
        $this->subscriptionService = $subscriptionService;
        $this->accessControlService = $accessControlService;
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            // Логируем начало обработки
            Log::channel('telegram')->info('=== START: Processing Telegram webhook ===');
            
            $update = $request->all();
            
            Log::channel('telegram')->debug('Received update data', ['update' => $update]);

            if (!isset($update['message'])) {
                Log::channel('telegram')->warning('Received webhook without message', $update);
                return response()->json(['status' => 'ok']);
            }

            $message = $update['message'];
            $chatId = $message['chat']['id'] ?? null;
            $userId = $message['from']['id'] ?? null;
            $text = $message['text'] ?? '';

            if (!$chatId || !$userId) {
                Log::channel('telegram')->warning('Missing chat or user ID', $update);
                return response()->json(['status' => 'ok']);
            }

            Log::channel('telegram')->info('Processing message', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'text' => $text,
            ]);

            // Register or update the Telegram user
            $telegramUserData = [
                'id' => $userId,
                'first_name' => $message['from']['first_name'] ?? null,
                'last_name' => $message['from']['last_name'] ?? null,
                'username' => $message['from']['username'] ?? null,
            ];

            Log::channel('telegram')->debug('Registering/updating user', $telegramUserData);
            $telegramUser = $this->telegramBotService->registerOrUpdateUser($telegramUserData);
            Log::channel('telegram')->info('User registered/updated', ['telegram_user_id' => $telegramUser->id]);

            // Check access control
            Log::channel('telegram')->debug('Checking access control');
            $accessResult = $this->accessControlService->validateAccess($telegramUser, 'send_message');
            if (!$accessResult['allowed']) {
                Log::channel('telegram')->warning('Access denied', ['reason' => $accessResult['message']]);
                $this->telegramBotService->sendMessage($chatId, $accessResult['message']);
                return response()->json(['status' => 'ok']);
            }

            // Process the command
            Log::channel('telegram')->info('Processing command', ['text' => $text]);
            $this->processCommand($telegramUser, $chatId, $text);

            Log::channel('telegram')->info('=== END: Successfully processed webhook ===');
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Error processing Telegram webhook: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Process the command sent by the user
     */
    protected function processCommand(TelegramUser $telegramUser, int $chatId, string $text): void
    {
        // Handle deep link for partner connection
        if (preg_match('/^\/start partner_([A-Z0-9]+)$/', $text, $matches)) {
            $partnerCode = $matches[1];
            $result = $this->telegramBotService->connectPartner($telegramUser, $partnerCode);
            $this->telegramBotService->sendMessage($chatId, $result['message'], ['parse_mode' => 'Markdown']);
            return;
        }

        // Normalize the command
        $command = strtolower(trim($text));
        
        if (strpos($command, '/') === 0) {
            // It's a command
            $parts = explode(' ', $command, 2);
            $commandName = $parts[0];
            $commandArgs = $parts[1] ?? '';
            
            // Use new handleCommand method for period tracking commands
            $periodCommands = [
                '/start_period', '/end_period', '/status',
                '/period_status', '/invite_partner', '/connect_partner',
                '/partner_status', '/remove_partner'
            ];
            
            if (in_array($commandName, $periodCommands) || $commandName === '/start' || $commandName === '/help') {
                $result = $this->telegramBotService->handleCommand($telegramUser, $commandName, $commandArgs);
                $this->telegramBotService->sendMessage($chatId, $result['message'], ['parse_mode' => 'Markdown']);
                return;
            }
            
            switch ($commandName) {
                case '/add':
                    $this->handleAddCommand($telegramUser, $chatId, $commandArgs);
                    break;
                    
                case '/today':
                    $this->handleTodayCommand($telegramUser, $chatId);
                    break;
                    
                case '/week':
                    $this->handleWeekCommand($telegramUser, $chatId);
                    break;
                    
                case '/profile':
                    $this->handleProfileCommand($telegramUser, $chatId);
                    break;
                    
                case '/subscribe':
                case '/subscription':
                    $this->handleSubscribeCommand($telegramUser, $chatId);
                    break;
                    
                case '/support':
                    $this->handleSupportCommand($telegramUser, $chatId);
                    break;
                    
                default:
                    $this->telegramBotService->sendMessage($chatId, "Unknown command: {$commandName}\nUse /help to see available commands.");
                    break;
            }
        } else {
            // Check if it's a question for AI
            if ($this->looksLikeQuestion($text)) {
                $this->handleAIQuery($telegramUser, $chatId, $text);
            } else {
                // Assume it's a food entry if it doesn't start with /
                $this->handleFoodEntry($telegramUser, $chatId, $text);
            }
        }
    }

    /**
     * Check if text looks like a question
     */
    protected function looksLikeQuestion(string $text): bool
    {
        // Check for question marks or question words
        $questionWords = ['when', 'what', 'where', 'why', 'how', 'who', 'which', 'is', 'am', 'are', 'can', 'could', 'should', 'will'];
        $firstWord = strtolower(explode(' ', trim($text))[0] ?? '');
        
        return str_contains($text, '?') || in_array($firstWord, $questionWords);
    }

    /**
     * Handle AI query
     */
    protected function handleAIQuery(TelegramUser $telegramUser, int $chatId, string $query): void
    {
        Log::channel('telegram')->info('Processing AI query', ['query' => $query]);
        
        $result = $this->telegramBotService->queryAI($telegramUser, $query);
        $this->telegramBotService->sendMessage($chatId, $result['message'], ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /start command (delegated to TelegramBotService)
     */
    protected function handleStartCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $result = $this->telegramBotService->handleCommand($telegramUser, '/start', null);
        $this->telegramBotService->sendMessage($chatId, $result['message'], ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /help command (delegated to TelegramBotService)
     */
    protected function handleHelpCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $result = $this->telegramBotService->handleCommand($telegramUser, '/help', null);
        $this->telegramBotService->sendMessage($chatId, $result['message'], ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /add command
     */
    protected function handleAddCommand(TelegramUser $telegramUser, int $chatId, string $args): void
    {
        if (empty($args)) {
            $message = "Please specify what you ate with the weight.\nExample: /add Chicken breast 150g";
            $this->telegramBotService->sendMessage($chatId, $message);
            return;
        }

        $result = $this->telegramBotService->recordMeal($telegramUser, $args);

        if ($result['success']) {
            $this->telegramBotService->sendMessage($chatId, $result['message']);
        } else {
            $this->telegramBotService->sendMessage($chatId, $result['message']);
        }
    }

    /**
     * Handle food entry (when user sends text without a command)
     */
    protected function handleFoodEntry(TelegramUser $telegramUser, int $chatId, string $text): void
    {
        $result = $this->telegramBotService->recordMeal($telegramUser, $text);

        if ($result['success']) {
            $this->telegramBotService->sendMessage($chatId, $result['message']);
        } else {
            $this->telegramBotService->sendMessage($chatId, $result['message']);
        }
    }

    /**
     * Handle /today command
     */
    protected function handleTodayCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $summary = $this->telegramBotService->getTodaySummary($telegramUser);
        $this->telegramBotService->sendMessage($chatId, $summary, ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /week command
     */
    protected function handleWeekCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $summary = $this->telegramBotService->getWeekSummary($telegramUser);
        $this->telegramBotService->sendMessage($chatId, $summary, ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /profile command
     */
    protected function handleProfileCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($telegramUser);

        $profileMessage = "*Your Profile*\n";
        $profileMessage .= "Name: {$telegramUser->first_name} {$telegramUser->last_name}\n";
        $profileMessage .= "Username: @{$telegramUser->username}\n";
        $profileMessage .= "Current Plan: {$subscriptionStatus['plan_name']}\n";
        
        if ($subscriptionStatus['expires_at']) {
            $expiresAt = \Carbon\Carbon::parse($subscriptionStatus['expires_at']);
            $profileMessage .= "Expires: {$expiresAt->format('M j, Y')}";
            
            if ($subscriptionStatus['days_until_expiry'] !== null) {
                if ($subscriptionStatus['days_until_expiry'] > 0) {
                    $profileMessage .= " (in {$subscriptionStatus['days_until_expiry']} days)\n";
                } else {
                    $profileMessage .= " (expired)\n";
                }
            } else {
                $profileMessage .= "\n";
            }
        } else {
            $profileMessage .= "Expires: Never\n";
        }

        $keyboard = [
            [
                ['text' => '💳 Extend Subscription', 'callback_data' => 'extend_subscription'],
                ['text' => '🛠️ Support', 'callback_data' => 'contact_support']
            ]
        ];

        $this->telegramBotService->sendInlineKeyboard($chatId, $profileMessage, $keyboard);
    }

    /**
     * Handle /subscribe command
     */
    protected function handleSubscribeCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $availablePlans = $this->subscriptionService->getAvailablePlans();
        
        if ($availablePlans->isEmpty()) {
            $message = "Currently, there are no subscription plans available. Please check back later.";
            $this->telegramBotService->sendMessage($chatId, $message);
            return;
        }

        $message = "*Available Subscription Plans*\n\n";
        
        foreach ($availablePlans as $plan) {
            $message .= sprintf(
                "*%s* - %s %s/%s\n%s\nMax meals per day: %s\n\n",
                $plan->name,
                $plan->currency,
                $plan->price,
                $plan->duration_days == 30 ? 'month' : $plan->duration_days . ' days',
                $plan->description,
                $plan->max_daily_meals == 0 ? 'Unlimited' : $plan->max_daily_meals
            );
        }

        $this->telegramBotService->sendMessage($chatId, $message, ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle /support command
     */
    protected function handleSupportCommand(TelegramUser $telegramUser, int $chatId): void
    {
        $message = "You can contact our support team directly through this bot.\n\n" .
                  "To create a support ticket, please describe your issue in detail.";

        $this->telegramBotService->sendMessage($chatId, $message);
    }
}
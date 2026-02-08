<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Meal;
use App\Models\Food;
use App\Models\Subscription;
use App\Models\Period;
use App\Models\Partner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TelegramBotService
{
    protected string $botToken;
    protected string $apiUrl;
    protected PeriodService $periodService;

    public function __construct(PeriodService $periodService)
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
        $this->periodService = $periodService;
    }

    /**
     * Send a message to a Telegram user
     */
    public function sendMessage(int $chatId, string $text, array $options = []): array
    {
        $response = Http::post($this->apiUrl . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            ...$options
        ]);

        return $response->json();
    }

    /**
     * Send a message with inline keyboard
     */
    public function sendInlineKeyboard(int $chatId, string $text, array $keyboard): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);
    }

    /**
     * Get user info from Telegram
     */
    public function getUserInfo(int $userId): array
    {
        $response = Http::get($this->apiUrl . '/getChat', [
            'chat_id' => $userId
        ]);

        return $response->json();
    }

    /**
     * Register or update Telegram user in our database
     */
    public function registerOrUpdateUser(array $telegramUserData): TelegramUser
    {
        $telegramUser = TelegramUser::updateOrCreate(
            ['telegram_id' => $telegramUserData['id']],
            [
                'first_name' => $telegramUserData['first_name'] ?? null,
                'last_name' => $telegramUserData['last_name'] ?? null,
                'username' => $telegramUserData['username'] ?? null,
                'last_active_at' => now(),
            ]
        );

        // Create default subscription if none exists
        if (!$telegramUser->currentSubscription()) {
            $this->assignDefaultSubscription($telegramUser);
        }

        return $telegramUser;
    }

    /**
     * Assign a default subscription (free plan) to new users
     */
    protected function assignDefaultSubscription(TelegramUser $telegramUser): void
    {
        $freePlan = \App\Models\SubscriptionPlan::where('slug', 'free')
            ->orWhere('price', 0)
            ->first();

        if (!$freePlan) {
            // Create a default free plan if it doesn't exist
            $freePlan = \App\Models\SubscriptionPlan::create([
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Basic plan with limited features',
                'price' => 0,
                'duration_days' => 30,
                'max_daily_meals' => 5,
                'has_advanced_stats' => false,
                'has_support_priority' => false,
                'features' => [
                    'daily_meal_limit' => 5,
                    'basic_stats' => true,
                    'standard_support' => true,
                ],
                'is_active' => true,
            ]);
        }

        $telegramUser->subscriptions()->create([
            'subscription_plan_id' => $freePlan->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays($freePlan->duration_days),
        ]);
    }

    /**
     * Parse food entry from user input
     */
    public function parseFoodEntry(string $input): array
    {
        // Extract weight/portion from input (e.g., "180g", "180 g", "180 grams")
        $weightPattern = '/(\d+(?:\.\d+)?)\s*(?:g|grams|kg|kilograms|ml|l|oz|pounds?)\b/i';
        preg_match($weightPattern, $input, $weightMatches);
        
        $weight = 0;
        if (!empty($weightMatches)) {
            $weightValue = (float)$weightMatches[1];
            
            // Convert units to grams
            $unit = strtolower(trim($weightMatches[0], $weightValue));
            switch ($unit) {
                case 'kg':
                case 'kilograms':
                    $weight = $weightValue * 1000;
                    break;
                case 'g':
                case 'grams':
                    $weight = $weightValue;
                    break;
                case 'oz':
                    $weight = $weightValue * 28.35; // oz to grams
                    break;
                case 'pounds':
                    $weight = $weightValue * 453.592; // pounds to grams
                    break;
                case 'ml':
                case 'l':
                    // For liquids, we'll assume 1ml = 1g (approximation)
                    $weight = $unit === 'l' ? $weightValue * 1000 : $weightValue;
                    break;
                default:
                    $weight = $weightValue;
            }
        }

        // Remove weight info from the food name
        $foodName = trim(preg_replace($weightPattern, '', $input));

        return [
            'name' => $foodName,
            'weight_in_grams' => $weight,
        ];
    }

    /**
     * Find or create food item
     */
    public function findOrCreateFood(string $name): ?Food
    {
        // Try to find existing food (case-insensitive)
        $food = Food::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($food) {
            return $food;
        }

        // If not found, you might want to integrate with a nutrition API here
        // For now, return null to indicate manual entry needed
        return null;
    }

    /**
     * Record a meal
     */
    public function recordMeal(TelegramUser $telegramUser, string $foodInput): array
    {
        $parsed = $this->parseFoodEntry($foodInput);
        
        if (empty($parsed['name'])) {
            return [
                'success' => false,
                'message' => 'Please specify a food item. Example: "Chicken breast 150g"'
            ];
        }

        if ($parsed['weight_in_grams'] <= 0) {
            return [
                'success' => false,
                'message' => 'Please specify a valid weight. Example: "Chicken breast 150g"'
            ];
        }

        // Check subscription limits
        $currentSubscription = $telegramUser->currentSubscription();
        if (!$currentSubscription) {
            return [
                'success' => false,
                'message' => 'No active subscription found. Please subscribe to continue using the service.'
            ];
        }

        // Check daily meal limit
        $todayMealsCount = $telegramUser->meals()
            ->whereDate('consumed_at', today())
            ->count();

        if (!$currentSubscription->isWithinLimitForDailyMeals($todayMealsCount)) {
            return [
                'success' => false,
                'message' => "You've reached your daily meal limit of {$currentSubscription->subscriptionPlan->max_daily_meals}. Upgrade your subscription for unlimited entries."
            ];
        }

        // Try to find the food in our database
        $food = $this->findOrCreateFood($parsed['name']);

        if (!$food) {
            return [
                'success' => false,
                'message' => "Food '{$parsed['name']}' not found in our database. We'll add it soon, but for now you'll need to enter calories manually."
            ];
        }

        // Calculate nutrition based on weight
        $nutrition = $food->calculateNutrition($parsed['weight_in_grams']);

        // Create the meal record
        $meal = $telegramUser->meals()->create([
            'food_id' => $food->id,
            'name' => $food->name,
            'weight_in_grams' => $parsed['weight_in_grams'],
            'calories' => $nutrition['calories'],
            'protein' => $nutrition['protein'],
            'carbs' => $nutrition['carbs'],
            'fat' => $nutrition['fat'],
            'consumed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Recorded: {$parsed['name']} ({$parsed['weight_in_grams']}g)\nCalories: {$nutrition['calories']} kcal",
            'meal' => $meal
        ];
    }

    /**
     * Get today's meals summary
     */
    public function getTodaySummary(TelegramUser $telegramUser): string
    {
        $meals = $telegramUser->meals()
            ->whereDate('consumed_at', today())
            ->get();

        if ($meals->isEmpty()) {
            return "No meals recorded today. Use /add to log your meals!";
        }

        $totalCalories = $meals->sum('calories');
        $totalProtein = $meals->sum('protein');
        $totalCarbs = $meals->sum('carbs');
        $totalFat = $meals->sum('fat');

        $summary = "*Today's Nutrition Summary*\n";
        $summary .= "Total meals: {$meals->count()}\n";
        $summary .= "Calories: {$totalCalories} kcal\n";
        $summary .= "Protein: {$totalProtein}g\n";
        $summary .= "Carbs: {$totalCarbs}g\n";
        $summary .= "Fat: {$totalFat}g\n\n";

        foreach ($meals as $meal) {
            $summary .= "• {$meal->name} ({$meal->getFormattedWeight()}, {$meal->calories} kcal)\n";
        }

        return $summary;
    }

    /**
     * Get week's meals summary
     */
    public function getWeekSummary(TelegramUser $telegramUser): string
    {
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        $meals = $telegramUser->meals()
            ->whereBetween('consumed_at', [$startDate, $endDate])
            ->get();

        if ($meals->isEmpty()) {
            return "No meals recorded this week.";
        }

        $dailyTotals = [];
        foreach ($meals as $meal) {
            $dateKey = $meal->consumed_at->toDateString();
            if (!isset($dailyTotals[$dateKey])) {
                $dailyTotals[$dateKey] = [
                    'calories' => 0,
                    'protein' => 0,
                    'carbs' => 0,
                    'fat' => 0,
                    'meals' => []
                ];
            }
            
            $dailyTotals[$dateKey]['calories'] += $meal->calories;
            $dailyTotals[$dateKey]['protein'] += $meal->protein;
            $dailyTotals[$dateKey]['carbs'] += $meal->carbs;
            $dailyTotals[$dateKey]['fat'] += $meal->fat;
            $dailyTotals[$dateKey]['meals'][] = $meal;
        }

        $summary = "*Week's Nutrition Summary*\n";
        $summary .= "From: {$startDate->format('M j')} to {$endDate->format('M j')}\n\n";

        foreach ($dailyTotals as $date => $totals) {
            $dateObj = \Carbon\Carbon::parse($date);
            $summary .= "*{$dateObj->format('D, M j')}*\n";
            $summary .= "Meals: " . count($totals['meals']) . "\n";
            $summary .= "Calories: {$totals['calories']} kcal\n";
            $summary .= "Protein: {$totals['protein']}g\n";
            $summary .= "Carbs: {$totals['carbs']}g\n";
            $summary .= "Fat: {$totals['fat']}g\n\n";
        }

        return $summary;
    }

    /**
     * Start a new period for user
     */
    public function startPeriod(TelegramUser $telegramUser, ?string $dateInput = null): array
    {
        try {
            $startDate = $dateInput ? Carbon::parse($dateInput) : now();
            $period = $this->periodService->startPeriod($telegramUser, $startDate);

            $message = "🩸 Period started on {$startDate->format('M j, Y')}.\n\n";
            $message .= "I'll track this for you and predict your next cycle!";

            return [
                'success' => true,
                'message' => $message,
                'period' => $period
            ];
        } catch (\Exception $e) {
            Log::error('Error starting period: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to start period. Please try again.'
            ];
        }
    }

    /**
     * End current period for user
     */
    public function endPeriod(TelegramUser $telegramUser, ?string $dateInput = null): array
    {
        try {
            $activePeriod = $telegramUser->getCurrentPeriod();
            
            if (!$activePeriod) {
                return [
                    'success' => false,
                    'message' => 'No active period to end. Use /start_period to begin tracking.'
                ];
            }

            $endDate = $dateInput ? Carbon::parse($dateInput) : now();
            $period = $this->periodService->endPeriod($activePeriod, $endDate);

            $message = "✅ Period ended on {$endDate->format('M j, Y')}.\n";
            $message .= "Duration: {$period->duration} days\n\n";
            $message .= $this->periodService->getPeriodInsights($telegramUser);

            return [
                'success' => true,
                'message' => $message,
                'period' => $period
            ];
        } catch (\Exception $e) {
            Log::error('Error ending period: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to end period. Please try again.'
            ];
        }
    }

    /**
     * Get period status and predictions
     */
    public function getPeriodStatus(TelegramUser $telegramUser): string
    {
        $insights = $this->periodService->getPeriodInsights($telegramUser);
        $stats = $this->periodService->getPeriodStatistics($telegramUser);

        if ($stats['total_periods'] === 0) {
            return "👋 Welcome! I can help you track your menstrual cycle.\n\n" .
                   "Use /start_period to begin tracking your current period.\n" .
                   "I'll help predict your next cycle and provide insights!";
        }

        $status = "🌸 *Period Tracker*\n\n";
        $status .= $insights . "\n\n";
        
        $fertility = $this->periodService->calculateFertilityWindow($telegramUser);
        if ($fertility) {
            $status .= "\n*Fertility Window*\n";
            $status .= "Ovulation: {$fertility['ovulation_date']->format('M j')}\n";
            $status .= "Fertile: {$fertility['fertile_start']->format('M j')} - {$fertility['fertile_end']->format('M j')}\n";
        }

        return $status;
    }

    /**
     * Create partner invitation
     */
    public function createPartnerInvitation(TelegramUser $user): array
    {
        try {
            // Check if user already has a partner
            $existingPartner = Partner::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->first();

            if ($existingPartner) {
                return [
                    'success' => false,
                    'message' => 'You already have a connected partner. Use /remove_partner to disconnect first.'
                ];
            }

            // Generate or get partner code
            $partnerCode = $user->getOrCreatePartnerCode();

            // Create invitation link
            $botUsername = config('telegram.bot_username', 'your_bot');
            $inviteLink = "https://t.me/{$botUsername}?start=partner_{$partnerCode}";

            $message = "👫 *Partner Invitation*\n\n";
            $message .= "Share this with your partner to connect:\n\n";
            $message .= "🔗 Link: {$inviteLink}\n\n";
            $message .= "📋 Or share this code: `{$partnerCode}`\n\n";
            $message .= "Your partner can use /connect_partner {$partnerCode} to connect.";

            return [
                'success' => true,
                'message' => $message,
                'code' => $partnerCode,
                'link' => $inviteLink
            ];
        } catch (\Exception $e) {
            Log::error('Error creating partner invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create invitation. Please try again.'
            ];
        }
    }

    /**
     * Connect partner using invitation code
     */
    public function connectPartner(TelegramUser $partner, string $code): array
    {
        try {
            // Find user by partner code
            $user = TelegramUser::where('partner_code', $code)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid partner code. Please check and try again.'
                ];
            }

            if ($user->id === $partner->id) {
                return [
                    'success' => false,
                    'message' => 'You cannot connect to yourself!'
                ];
            }

            // Check if partnership already exists
            $existingPartnership = Partner::where(function ($query) use ($user, $partner) {
                $query->where('user_id', $user->id)
                      ->where('partner_id', $partner->id);
            })->orWhere(function ($query) use ($user, $partner) {
                $query->where('user_id', $partner->id)
                      ->where('partner_id', $user->id);
            })->first();

            if ($existingPartnership && $existingPartnership->status === 'accepted') {
                return [
                    'success' => false,
                    'message' => 'You are already connected to this partner.'
                ];
            }

            // Create new partnership
            $partnership = Partner::create([
                'user_id' => $user->id,
                'partner_id' => $partner->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Notify the user
            $this->sendMessage(
                $user->telegram_id,
                "👫 {$partner->first_name} has connected as your partner!"
            );

            return [
                'success' => true,
                'message' => "✅ Successfully connected to {$user->first_name}!\n\nYou can now view their period insights with /partner_status",
                'partnership' => $partnership
            ];
        } catch (\Exception $e) {
            Log::error('Error connecting partner: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to connect partner. Please try again.'
            ];
        }
    }

    /**
     * Get partner's period status
     */
    public function getPartnerStatus(TelegramUser $user): string
    {
        $partnerships = Partner::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
        })
        ->where('status', 'accepted')
        ->get();

        if ($partnerships->isEmpty()) {
            return "No connected partners. Use /invite_partner to connect with someone!";
        }

        $status = "👫 *Partner Status*\n\n";

        foreach ($partnerships as $partnership) {
            $partner = $partnership->user_id === $user->id
                ? $partnership->partner
                : $partnership->user;

            $status .= "*{$partner->first_name}*\n";
            $insights = $this->periodService->getPeriodInsights($partner);
            $status .= $insights . "\n\n";
        }

        return $status;
    }

    /**
     * Remove partner connection
     */
    public function removePartner(TelegramUser $user): array
    {
        try {
            $deleted = Partner::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('partner_id', $user->id);
            })
            ->where('status', 'accepted')
            ->delete();

            if ($deleted > 0) {
                return [
                    'success' => true,
                    'message' => '✅ Partner connection removed.'
                ];
            }

            return [
                'success' => false,
                'message' => 'No active partner connection found.'
            ];
        } catch (\Exception $e) {
            Log::error('Error removing partner: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to remove partner. Please try again.'
            ];
        }
    }

    /**
     * Forward user query to AI (roocode integration)
     */
    public function queryAI(TelegramUser $user, string $query): array
    {
        try {
            // Get user context for AI
            $context = $this->buildUserContext($user);

            // Send to roocode API endpoint
            $roocodeUrl = config('services.roocode.api_url', 'http://localhost:3000/api/ai');
            $roocodeKey = config('services.roocode.api_key');

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$roocodeKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($roocodeUrl, [
                'query' => $query,
                'context' => $context,
                'user_id' => $user->telegram_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => $data['response'] ?? 'No response from AI',
                    'ai_response' => $data
                ];
            }

            return [
                'success' => false,
                'message' => 'AI service is temporarily unavailable. Please try again later.'
            ];
        } catch (\Exception $e) {
            Log::error('Error querying AI: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to process your query. Please try again.'
            ];
        }
    }

    /**
     * Build user context for AI
     */
    protected function buildUserContext(TelegramUser $user): array
    {
        $context = [
            'user_name' => $user->first_name,
            'gender' => $user->gender,
        ];

        // Add period tracking context
        $stats = $this->periodService->getPeriodStatistics($user);
        if ($stats['total_periods'] > 0) {
            $context['period_tracking'] = [
                'total_periods_tracked' => $stats['total_periods'],
                'average_cycle_length' => $stats['average_cycle_length'],
                'average_period_length' => $stats['average_period_length'],
                'cycle_regularity' => $stats['cycle_regularity'],
                'last_period_date' => $stats['last_period_date'],
                'predicted_next_period' => $stats['predicted_next_period'],
                'current_cycle_day' => $this->periodService->getCurrentCycleDay($user),
                'is_in_period' => $this->periodService->isInPeriod($user),
            ];

            $fertility = $this->periodService->calculateFertilityWindow($user);
            if ($fertility) {
                $context['period_tracking']['fertility_window'] = [
                    'is_fertile_now' => $fertility['is_fertile_now'],
                    'ovulation_date' => $fertility['ovulation_date']->toDateString(),
                ];
            }
        }

        // Add partner context
        $partners = Partner::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
        })->where('status', 'accepted')->count();

        $context['has_partner'] = $partners > 0;

        return $context;
    }

    /**
     * Handle bot command
     */
    public function handleCommand(TelegramUser $user, string $command, ?string $params = null): array
    {
        return match ($command) {
            '/start' => [
                'success' => true,
                'message' => $this->getWelcomeMessage($user)
            ],
            '/start_period' => $this->startPeriod($user, $params),
            '/end_period' => $this->endPeriod($user, $params),
            '/status', '/period_status' => [
                'success' => true,
                'message' => $this->getPeriodStatus($user)
            ],
            '/invite_partner' => $this->createPartnerInvitation($user),
            '/connect_partner' => $params
                ? $this->connectPartner($user, trim($params))
                : ['success' => false, 'message' => 'Please provide partner code: /connect_partner CODE'],
            '/partner_status' => [
                'success' => true,
                'message' => $this->getPartnerStatus($user)
            ],
            '/remove_partner' => $this->removePartner($user),
            '/help' => [
                'success' => true,
                'message' => $this->getHelpMessage()
            ],
            default => [
                'success' => false,
                'message' => 'Unknown command. Use /help to see available commands.'
            ]
        };
    }

    /**
     * Get welcome message
     */
    protected function getWelcomeMessage(TelegramUser $user): string
    {
        $message = "👋 Welcome to Luna Period Tracker, {$user->first_name}!\n\n";
        $message .= "I can help you:\n";
        $message .= "🩸 Track your menstrual cycle\n";
        $message .= "📊 Predict your next period\n";
        $message .= "🌸 Monitor fertility windows\n";
        $message .= "👫 Share cycle info with your partner\n";
        $message .= "🤖 Ask questions to AI assistant\n\n";
        $message .= "Use /help to see all commands!";
        
        return $message;
    }

    /**
     * Get help message
     */
    protected function getHelpMessage(): string
    {
        $message = "📋 *Available Commands*\n\n";
        $message .= "*Period Tracking:*\n";
        $message .= "/start_period - Start tracking a period\n";
        $message .= "/end_period - End current period\n";
        $message .= "/status - View period status and predictions\n\n";
        $message .= "*Partner Features:*\n";
        $message .= "/invite_partner - Generate invitation for partner\n";
        $message .= "/connect_partner CODE - Connect using partner code\n";
        $message .= "/partner_status - View partner's cycle status\n";
        $message .= "/remove_partner - Disconnect partner\n\n";
        $message .= "*AI Assistant:*\n";
        $message .= "Just type your question and I'll ask AI for you!\n\n";
        $message .= "Example: 'When is my fertile window?'";
        
        return $message;
    }
}
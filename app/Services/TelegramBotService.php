<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Meal;
use App\Models\Food;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected string $botToken;
    protected string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
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
}
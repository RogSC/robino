<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Subscription;
use App\Services\SubscriptionService;

class AccessControlService
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Check if user has access to a specific feature
     */
    public function hasAccess(TelegramUser $telegramUser, string $feature): bool
    {
        return match($feature) {
            'add_meal' => $this->canAddMeal($telegramUser),
            'view_advanced_stats' => $this->canViewAdvancedStats($telegramUser),
            'access_priority_support' => $this->canAccessPrioritySupport($telegramUser),
            'unlimited_meals' => $this->hasUnlimitedMeals($telegramUser),
            default => $this->hasValidSubscription($telegramUser)
        };
    }

    /**
     * Check if user has a valid subscription
     */
    protected function hasValidSubscription(TelegramUser $telegramUser): bool
    {
        return $this->subscriptionService->hasActiveSubscription($telegramUser);
    }

    /**
     * Check if user can add a meal
     */
    protected function canAddMeal(TelegramUser $telegramUser): bool
    {
        return $this->subscriptionService->canPerformAction($telegramUser, 'add_meal');
    }

    /**
     * Check if user can view advanced stats
     */
    protected function canViewAdvancedStats(TelegramUser $telegramUser): bool
    {
        return $this->subscriptionService->canPerformAction($telegramUser, 'view_advanced_stats');
    }

    /**
     * Check if user can access priority support
     */
    protected function canAccessPrioritySupport(TelegramUser $telegramUser): bool
    {
        return $this->subscriptionService->canPerformAction($telegramUser, 'access_priority_support');
    }

    /**
     * Check if user has unlimited meals
     */
    protected function hasUnlimitedMeals(TelegramUser $telegramUser): bool
    {
        $subscription = $this->subscriptionService->getCurrentSubscription($telegramUser);
        
        if (!$subscription) {
            return false;
        }

        $maxMeals = $subscription->subscriptionPlan->max_daily_meals;
        return $maxMeals === 0; // 0 means unlimited
    }

    /**
     * Get access limitations message for a user
     */
    public function getAccessLimitationsMessage(TelegramUser $telegramUser): string
    {
        $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($telegramUser);

        if (!$subscriptionStatus['has_subscription']) {
            return "You don't have an active subscription. Please subscribe to unlock full features.";
        }

        if (!$subscriptionStatus['is_active']) {
            return "Your subscription has expired. Please renew to continue using the service.";
        }

        // Check specific limitations
        $limitations = [];

        if (!$subscriptionStatus['can_add_meals']) {
            $plan = $this->subscriptionService->getCurrentSubscription($telegramUser)?->subscriptionPlan;
            if ($plan) {
                $limitations[] = "Daily meal limit reached ({$plan->max_daily_meals} meals/day)";
            }
        }

        if (empty($limitations)) {
            return ""; // No limitations
        }

        return "Access limitations:\n" . implode("\n", array_map(fn($limit) => "• {$limit}", $limitations));
    }

    /**
     * Check rate limiting for specific actions
     */
    public function isRateLimited(TelegramUser $telegramUser, string $action, int $limit, string $timeFrame = '1 hour'): bool
    {
        $cacheKey = "rate_limit_{$telegramUser->telegram_id}_{$action}";
        $requests = cache()->get($cacheKey, []);

        // Clean old requests
        $timeThreshold = now()->sub($timeFrame)->timestamp;
        $requests = array_filter($requests, fn($timestamp) => $timestamp > $timeThreshold);

        // Check if limit is exceeded
        if (count($requests) >= $limit) {
            return true;
        }

        // Add current request
        $requests[] = now()->timestamp;
        cache()->put($cacheKey, $requests, now()->add($timeFrame)->diffInSeconds());

        return false;
    }

    /**
     * Check if user is blocked
     */
    public function isUserBlocked(TelegramUser $telegramUser): bool
    {
        // In a real implementation, you'd check if the user is blocked
        // This could be stored in a separate field or table
        return false;
    }

    /**
     * Validate user access before performing an action
     */
    public function validateAccess(TelegramUser $telegramUser, string $action): array
    {
        // Check if user is blocked
        if ($this->isUserBlocked($telegramUser)) {
            return [
                'allowed' => false,
                'message' => 'Your account has been suspended. Please contact support.',
                'code' => 'ACCOUNT_SUSPENDED'
            ];
        }

        // Check subscription validity
        if (!$this->hasValidSubscription($telegramUser)) {
            return [
                'allowed' => false,
                'message' => 'You need an active subscription to perform this action.',
                'code' => 'NO_SUBSCRIPTION'
            ];
        }

        // Check specific feature access
        if (!$this->hasAccess($telegramUser, $action)) {
            $limitations = $this->getAccessLimitationsMessage($telegramUser);
            return [
                'allowed' => false,
                'message' => $limitations ?: 'You do not have permission to perform this action.',
                'code' => 'ACCESS_DENIED'
            ];
        }

        // Check rate limiting
        if ($this->isRateLimited($telegramUser, $action, 10, '1 minute')) {
            return [
                'allowed' => false,
                'message' => 'Too many requests. Please try again later.',
                'code' => 'RATE_LIMITED'
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Access granted',
            'code' => 'OK'
        ];
    }
}
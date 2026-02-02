<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Check if user has an active subscription
     */
    public function hasActiveSubscription(TelegramUser $telegramUser): bool
    {
        $subscription = $telegramUser->currentSubscription();
        return $subscription && $subscription->isActive();
    }

    /**
     * Get user's current subscription
     */
    public function getCurrentSubscription(TelegramUser $telegramUser): ?Subscription
    {
        return $telegramUser->currentSubscription();
    }

    /**
     * Subscribe user to a plan
     */
    public function subscribeToPlan(TelegramUser $telegramUser, SubscriptionPlan $plan, string $paymentGateway = 'manual'): Subscription
    {
        // Cancel any existing active subscriptions
        $this->cancelActiveSubscription($telegramUser);

        // Create new subscription
        $subscription = $telegramUser->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays($plan->duration_days),
            'auto_renew' => false, // Default to no auto-renew
        ]);

        // Create payment record
        $subscription->payments()->create([
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => $paymentGateway,
            'transaction_id' => 'SUB_' . $subscription->id . '_' . time(),
            'status' => 'completed',
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'paid_at' => now(),
        ]);

        return $subscription;
    }

    /**
     * Cancel active subscription
     */
    public function cancelActiveSubscription(TelegramUser $telegramUser): bool
    {
        $activeSubscription = $this->getCurrentSubscription($telegramUser);
        
        if ($activeSubscription) {
            $activeSubscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Extend subscription by days
     */
    public function extendSubscription(TelegramUser $telegramUser, int $days): ?Subscription
    {
        $subscription = $this->getCurrentSubscription($telegramUser);
        
        if ($subscription) {
            $subscription->update([
                'expires_at' => $subscription->expires_at->addDays($days),
            ]);
            
            return $subscription;
        }
        
        return null;
    }

    /**
     * Process subscription expiration
     */
    public function processExpiration(): void
    {
        // Find subscriptions that expired today (without grace period)
        $expiredSubscriptions = Subscription::where('expires_at', '<=', now())
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('grace_period_ends_at')
                      ->orWhere('grace_period_ends_at', '<', now());
            })
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            
            // Optionally downgrade to free plan or apply other business logic
            $this->handleExpiredSubscription($subscription);
        }
    }

    /**
     * Handle expired subscription (downgrade to free, etc.)
     */
    protected function handleExpiredSubscription(Subscription $subscription): void
    {
        // In a real implementation, you might want to:
        // - Send notification to user
        // - Apply grace period
        // - Downgrade to free plan
        // - Restrict certain features
        
        // For now, just log the expiration
        \Log::info("Subscription expired for TelegramUser ID: {$subscription->telegramUser->telegram_id}");
    }

    /**
     * Check if user can perform an action based on their subscription
     */
    public function canPerformAction(TelegramUser $telegramUser, string $action): bool
    {
        $subscription = $this->getCurrentSubscription($telegramUser);
        
        if (!$subscription) {
            return false;
        }

        switch ($action) {
            case 'add_meal':
                $todayMealsCount = $telegramUser->meals()
                    ->whereDate('consumed_at', today())
                    ->count();
                
                return $subscription->isWithinLimitForDailyMeals($todayMealsCount);
                
            case 'view_advanced_stats':
                return $subscription->hasAdvancedStatsAccess();
                
            case 'access_priority_support':
                return $subscription->subscriptionPlan->has_support_priority;
                
            default:
                return $subscription->isActive();
        }
    }

    /**
     * Get subscription status information
     */
    public function getSubscriptionStatus(TelegramUser $telegramUser): array
    {
        $subscription = $this->getCurrentSubscription($telegramUser);
        
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'is_active' => false,
                'plan_name' => 'No Subscription',
                'expires_at' => null,
                'days_until_expiry' => null,
                'can_add_meals' => false,
                'can_view_advanced_stats' => false,
            ];
        }

        return [
            'has_subscription' => true,
            'is_active' => $subscription->isActive(),
            'plan_name' => $subscription->subscriptionPlan->name,
            'plan_slug' => $subscription->subscriptionPlan->slug,
            'expires_at' => $subscription->expires_at,
            'days_until_expiry' => $subscription->daysUntilExpiry(),
            'can_add_meals' => $this->canPerformAction($telegramUser, 'add_meal'),
            'can_view_advanced_stats' => $this->canPerformAction($telegramUser, 'view_advanced_stats'),
            'can_access_priority_support' => $this->canPerformAction($telegramUser, 'access_priority_support'),
        ];
    }

    /**
     * Apply grace period to subscription
     */
    public function applyGracePeriod(Subscription $subscription, int $days = 7): void
    {
        $subscription->update([
            'grace_period_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * Get all available subscription plans
     */
    public function getAvailablePlans(): \Illuminate\Support\Collection
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();
    }
}
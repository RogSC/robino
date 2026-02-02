<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display available subscription plans
     */
    public function index(): JsonResponse
    {
        $plans = $this->subscriptionService->getAvailablePlans();
        
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Subscribe user to a plan
     */
    public function subscribe(Request $request, int $planId): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        
        // In a real implementation, you would authenticate the user
        // For now, assuming we have a way to identify the Telegram user
        $telegramUserId = $request->input('telegram_user_id'); // This would come from auth in real scenario
        
        $telegramUser = TelegramUser::findOrFail($telegramUserId);
        
        $subscription = $this->subscriptionService->subscribeToPlan($telegramUser, $plan);
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to the plan',
            'data' => $subscription
        ]);
    }

    /**
     * Get user's subscription status
     */
    public function status(Request $request): JsonResponse
    {
        $telegramUserId = $request->input('telegram_user_id'); // This would come from auth in real scenario
        
        $telegramUser = TelegramUser::findOrFail($telegramUserId);
        
        $status = $this->subscriptionService->getSubscriptionStatus($telegramUser);
        
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Cancel user's subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $telegramUserId = $request->input('telegram_user_id'); // This would come from auth in real scenario
        
        $telegramUser = TelegramUser::findOrFail($telegramUserId);
        
        $result = $this->subscriptionService->cancelActiveSubscription($telegramUser);
        
        return response()->json([
            'success' => $result,
            'message' => $result ? 'Subscription cancelled successfully' : 'No active subscription to cancel'
        ]);
    }

    /**
     * Extend user's subscription
     */
    public function extend(Request $request): JsonResponse
    {
        $telegramUserId = $request->input('telegram_user_id'); // This would come from auth in real scenario
        $days = $request->input('days', 30); // Default to 30 days
        
        $telegramUser = TelegramUser::findOrFail($telegramUserId);
        
        $subscription = $this->subscriptionService->extendSubscription($telegramUser, $days);
        
        return response()->json([
            'success' => $subscription !== null,
            'message' => $subscription ? 'Subscription extended successfully' : 'No active subscription to extend',
            'data' => $subscription
        ]);
    }
}
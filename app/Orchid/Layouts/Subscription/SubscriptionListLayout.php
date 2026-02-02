<?php

namespace App\Orchid\Layouts\Subscription;

use App\Models\Subscription;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SubscriptionListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'subscriptions';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->filter(),

            TD::make('telegramUser.telegram_id', 'Telegram ID')
                ->sort()
                ->filter()
                ->render(function (Subscription $subscription) {
                    return Link::make($subscription->telegramUser->telegram_id)
                        ->route('platform.telegram-user.edit', $subscription->telegramUser->id);
                }),

            TD::make('telegramUser.first_name', 'User Name')
                ->sort()
                ->render(function (Subscription $subscription) {
                    return $subscription->telegramUser->first_name . ' ' . $subscription->telegramUser->last_name;
                }),

            TD::make('subscriptionPlan.name', 'Plan')
                ->sort()
                ->filter()
                ->render(function (Subscription $subscription) {
                    return $subscription->subscriptionPlan->name;
                }),

            TD::make('status', 'Status')
                ->sort()
                ->filter()
                ->render(function (Subscription $subscription) {
                    $badgeType = match($subscription->status) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'warning',
                        default => 'secondary'
                    };
                    
                    return "<span class='badge badge-{$badgeType}'>{$subscription->status}</span>";
                }),

            TD::make('started_at', 'Started')
                ->sort()
                ->render(function (Subscription $subscription) {
                    return $subscription->started_at ? $subscription->started_at->format('Y-m-d H:i') : 'N/A';
                }),

            TD::make('expires_at', 'Expires')
                ->sort()
                ->render(function (Subscription $subscription) {
                    return $subscription->expires_at ? $subscription->expires_at->format('Y-m-d H:i') : 'N/A';
                }),

            TD::make('auto_renew', 'Auto Renew')
                ->sort()
                ->render(function (Subscription $subscription) {
                    return $subscription->auto_renew ? 'Yes' : 'No';
                }),

            TD::make('actions', 'Actions')
                ->cantHide()
                ->render(function (Subscription $subscription) {
                    return Link::make('View')
                        ->route('platform.subscription.edit', $subscription->id)
                        ->icon('eye');
                }),
        ];
    }
}
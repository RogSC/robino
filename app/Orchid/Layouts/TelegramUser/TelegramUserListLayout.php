<?php

namespace App\Orchid\Layouts\TelegramUser;

use App\Models\TelegramUser;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TelegramUserListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'telegramUsers';

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

            TD::make('telegram_id', 'Telegram ID')
                ->sort()
                ->filter(),

            TD::make('first_name', 'First Name')
                ->sort()
                ->filter(),

            TD::make('last_name', 'Last Name')
                ->sort()
                ->filter(),

            TD::make('username', 'Username')
                ->sort()
                ->filter(),

            TD::make('timezone', 'Timezone')
                ->sort()
                ->filter(),

            TD::make('last_active_at', 'Last Active')
                ->sort()
                ->render(function (TelegramUser $telegramUser) {
                    return $telegramUser->last_active_at ? $telegramUser->last_active_at->diffForHumans() : 'Never';
                }),

            TD::make('subscriptions.subscriptionPlan.name', 'Current Plan')
                ->render(function (TelegramUser $telegramUser) {
                    $subscription = $telegramUser->currentSubscription();
                    return $subscription ? $subscription->subscriptionPlan->name : 'No Subscription';
                }),

            TD::make('actions', 'Actions')
                ->cantHide()
                ->render(function (TelegramUser $telegramUser) {
                    return Link::make('View')
                        ->route('platform.telegram-user.edit', $telegramUser->id)
                        ->icon('eye');
                }),
        ];
    }
}
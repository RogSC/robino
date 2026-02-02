<?php

namespace App\Orchid\Screens\Subscription;

use App\Models\Subscription;
use App\Orchid\Layouts\Subscription\SubscriptionListLayout;
use App\Orchid\Layouts\Subscription\SubscriptionFiltersLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SubscriptionListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'subscriptions' => Subscription::with(['telegramUser', 'subscriptionPlan'])
                ->filtersApply()
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Subscriptions';
    }

    /**
     * The description is displayed in the header.
     */
    public function description(): ?string
    {
        return 'Manage user subscriptions';
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            SubscriptionFiltersLayout::class,
            SubscriptionListLayout::class,
        ];
    }
}
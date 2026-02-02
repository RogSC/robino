<?php

namespace App\Orchid\Screens\SubscriptionPlan;

use App\Models\SubscriptionPlan;
use App\Orchid\Layouts\SubscriptionPlan\SubscriptionPlanListLayout;
use App\Orchid\Layouts\SubscriptionPlan\SubscriptionPlanFiltersLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SubscriptionPlanListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'subscriptionPlans' => SubscriptionPlan::filtersApply()
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Subscription Plans';
    }

    /**
     * The description is displayed in the header.
     */
    public function description(): ?string
    {
        return 'Manage subscription plans';
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Create Plan')
                ->icon('plus')
                ->route('platform.subscription-plan.create'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            SubscriptionPlanFiltersLayout::class,
            SubscriptionPlanListLayout::class,
        ];
    }
}